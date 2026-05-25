<?php
/**
 * Sync Queue Implementation.
 *
 * Manages a persistent queue of failed ERP sync operations,
 * processing them in FIFO order with exponential backoff retries.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class SyncQueue
 *
 * Handles enqueueing, processing, and retrying failed sync operations.
 * Uses a custom database table for persistence across requests.
 */
class SyncQueue {

    /**
     * Queue item status: pending processing.
     */
    private const STATUS_PENDING = 'pending';

    /**
     * Queue item status: currently being processed.
     */
    private const STATUS_PROCESSING = 'processing';

    /**
     * Queue item status: permanently failed after max retries.
     */
    private const STATUS_FAILED = 'failed';

    /**
     * Queue item status: successfully completed.
     */
    private const STATUS_COMPLETED = 'completed';

    /**
     * Default maximum retry attempts.
     */
    private const DEFAULT_MAX_RETRIES = 3;

    /**
     * Default batch size for queue processing.
     */
    private const DEFAULT_BATCH_SIZE = 50;

    /**
     * Database table name (without prefix).
     */
    private const TABLE_NAME = 'erp_sync_queue';

    /**
     * Backoff multiplier base in seconds for exponential backoff.
     * Retry intervals: 30s, 120s, 600s (matching design spec).
     *
     * @var int[]
     */
    private array $backoff_intervals = [ 30, 120, 600 ];

    /**
     * Constructor.
     *
     * Loads backoff configuration from options.
     */
    public function __construct() {
        $configured_backoff = get_option( 'erp_retry_backoff_seconds', [] );
        if ( ! empty( $configured_backoff ) && is_array( $configured_backoff ) ) {
            $this->backoff_intervals = array_map( 'intval', $configured_backoff );
        }
    }

    /**
     * Enqueue a failed operation for later retry.
     *
     * Preserves the full operation payload so it can be replayed.
     *
     * @param string $operation_type Type of operation (e.g., 'push_order', 'sync_stock').
     * @param array  $payload        Full operation payload to preserve.
     * @param string $error_message  Error message from the failed attempt.
     * @return int|false Inserted queue item ID or false on failure.
     */
    public function enqueue( string $operation_type, array $payload, string $error_message = '' ) {
        global $wpdb;

        $table_name  = $wpdb->prefix . self::TABLE_NAME;
        $max_retries = (int) get_option( 'erp_retry_max_attempts', self::DEFAULT_MAX_RETRIES );

        // Calculate next retry time using first backoff interval.
        $next_retry_at = gmdate( 'Y-m-d H:i:s', time() + $this->backoff_intervals[0] );

        $result = $wpdb->insert(
            $table_name,
            [
                'operation_type' => sanitize_text_field( $operation_type ),
                'payload'        => wp_json_encode( $payload ),
                'status'         => self::STATUS_PENDING,
                'attempts'       => 1, // First attempt already failed.
                'max_retries'    => $max_retries,
                'next_retry_at'  => $next_retry_at,
                'error_message'  => sanitize_text_field( $error_message ),
                'created_at'     => current_time( 'mysql', true ),
                'updated_at'     => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $result ) {
            $this->log( 'error', sprintf( 'Failed to enqueue operation: %s', $operation_type ) );
            return false;
        }

        $this->log(
            'info',
            sprintf( 'Enqueued operation %s (ID: %d). Next retry at %s.', $operation_type, $wpdb->insert_id, $next_retry_at )
        );

        return $wpdb->insert_id;
    }

    /**
     * Process the queue in FIFO order.
     *
     * Fetches pending items whose retry time has passed and attempts
     * to re-execute them. Configurable batch size limits throughput.
     *
     * @param int $batch_size Number of items to process per run.
     * @return array{processed: int, succeeded: int, failed: int, remaining: int} Processing results.
     */
    public function process_queue( int $batch_size = 0 ): array {
        global $wpdb;

        if ( $batch_size <= 0 ) {
            $batch_size = (int) get_option( 'erp_batch_size', self::DEFAULT_BATCH_SIZE );
        }

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $now        = current_time( 'mysql', true );

        // Fetch pending items ready for retry, ordered by creation (FIFO).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name}
                WHERE status = %s
                AND (next_retry_at IS NULL OR next_retry_at <= %s)
                ORDER BY created_at ASC
                LIMIT %d",
                self::STATUS_PENDING,
                $now,
                $batch_size
            )
        );

        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed'    => 0,
            'remaining' => 0,
        ];

        if ( empty( $items ) ) {
            return $results;
        }

        $erp_client = Plugin::get_instance()->get_erp_client();

        if ( ! $erp_client ) {
            $this->log( 'error', 'Cannot process queue: ERP client not initialized.' );
            return $results;
        }

        foreach ( $items as $item ) {
            $results['processed']++;

            // Mark as processing.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table_name,
                [ 'status' => self::STATUS_PROCESSING, 'updated_at' => current_time( 'mysql', true ) ],
                [ 'id' => $item->id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );

            $payload = json_decode( $item->payload, true );
            $success = $this->execute_operation( $erp_client, $item->operation_type, $payload );

            if ( $success ) {
                $this->mark_completed( $item->id );
                $results['succeeded']++;
            } else {
                $this->handle_retry_or_fail( $item );
                if ( (int) $item->attempts >= (int) $item->max_retries ) {
                    $results['failed']++;
                }
            }
        }

        // Count remaining pending items.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results['remaining'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
                self::STATUS_PENDING
            )
        );

        $this->log(
            'info',
            sprintf(
                'Queue processed: %d items, %d succeeded, %d failed, %d remaining.',
                $results['processed'],
                $results['succeeded'],
                $results['failed'],
                $results['remaining']
            )
        );

        return $results;
    }

    /**
     * Get all permanently failed operations.
     *
     * @return array List of failed queue items.
     */
    public function get_failed_operations(): array {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE status = %s ORDER BY updated_at DESC",
                self::STATUS_FAILED
            )
        );

        return $items ?: [];
    }

    /**
     * Retry all permanently failed operations.
     *
     * Resets their status to pending and resets attempt counters.
     *
     * @param int $max_retries New max retries value.
     * @return array{reset: int} Number of items reset for retry.
     */
    public function retry_failed( int $max_retries = self::DEFAULT_MAX_RETRIES ): array {
        global $wpdb;

        $table_name    = $wpdb->prefix . self::TABLE_NAME;
        $next_retry_at = gmdate( 'Y-m-d H:i:s', time() + $this->backoff_intervals[0] );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name}
                SET status = %s, attempts = 0, max_retries = %d, next_retry_at = %s, updated_at = %s
                WHERE status = %s",
                self::STATUS_PENDING,
                $max_retries,
                $next_retry_at,
                current_time( 'mysql', true ),
                self::STATUS_FAILED
            )
        );

        $this->log( 'info', sprintf( 'Reset %d failed operations for retry.', $updated ) );

        return [ 'reset' => (int) $updated ];
    }

    /**
     * Get queue statistics.
     *
     * @return array{pending: int, processing: int, failed: int, completed: int, total: int}
     */
    public function get_stats(): array {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status"
        );

        $stats = [
            'pending'    => 0,
            'processing' => 0,
            'failed'     => 0,
            'completed'  => 0,
            'total'      => 0,
        ];

        foreach ( $results as $row ) {
            if ( isset( $stats[ $row->status ] ) ) {
                $stats[ $row->status ] = (int) $row->count;
            }
            $stats['total'] += (int) $row->count;
        }

        return $stats;
    }

    /**
     * Execute a queued operation against the ERP.
     *
     * @param ERPClient $erp_client    ERP client instance.
     * @param string    $operation_type Operation type identifier.
     * @param array     $payload        Operation payload.
     * @return bool True on success, false on failure.
     */
    private function execute_operation( ERPClient $erp_client, string $operation_type, array $payload ): bool {
        try {
            switch ( $operation_type ) {
                case 'push_order':
                    $erp_client->create_order( $payload );
                    break;

                case 'sync_customer':
                    $erp_client->sync_customer( $payload );
                    break;

                case 'confirm_payment':
                    $erp_client->confirm_payment(
                        $payload['erp_order_id'] ?? '',
                        $payload['payment_data'] ?? []
                    );
                    break;

                case 'update_order_status':
                    $erp_client->update_order_status(
                        $payload['erp_order_id'] ?? '',
                        $payload['status'] ?? ''
                    );
                    break;

                default:
                    $this->log( 'warning', sprintf( 'Unknown operation type: %s', $operation_type ) );
                    return false;
            }

            return true;
        } catch ( \Exception $e ) {
            $this->log(
                'error',
                sprintf( 'Queue operation %s failed: %s', $operation_type, $e->getMessage() )
            );
            return false;
        }
    }

    /**
     * Handle retry logic or mark as permanently failed.
     *
     * Increments attempt counter and calculates next retry time
     * using exponential backoff. If max retries exceeded, marks
     * as failed and notifies admin.
     *
     * @param object $item Queue item database row.
     */
    private function handle_retry_or_fail( object $item ): void {
        global $wpdb;

        $table_name   = $wpdb->prefix . self::TABLE_NAME;
        $new_attempts = (int) $item->attempts + 1;

        if ( $new_attempts >= (int) $item->max_retries ) {
            // Max retries exceeded - mark as permanently failed.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table_name,
                [
                    'status'     => self::STATUS_FAILED,
                    'attempts'   => $new_attempts,
                    'updated_at' => current_time( 'mysql', true ),
                ],
                [ 'id' => $item->id ],
                [ '%s', '%d', '%s' ],
                [ '%d' ]
            );

            $this->notify_admin_failure( $item );
            $this->log(
                'error',
                sprintf( 'Operation %s (ID: %d) permanently failed after %d attempts.', $item->operation_type, $item->id, $new_attempts )
            );
        } else {
            // Calculate next retry with exponential backoff.
            $backoff_index = min( $new_attempts - 1, count( $this->backoff_intervals ) - 1 );
            $wait_seconds  = $this->backoff_intervals[ $backoff_index ];
            $next_retry_at = gmdate( 'Y-m-d H:i:s', time() + $wait_seconds );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table_name,
                [
                    'status'        => self::STATUS_PENDING,
                    'attempts'      => $new_attempts,
                    'next_retry_at' => $next_retry_at,
                    'updated_at'    => current_time( 'mysql', true ),
                ],
                [ 'id' => $item->id ],
                [ '%s', '%d', '%s', '%s' ],
                [ '%d' ]
            );

            $this->log(
                'info',
                sprintf(
                    'Operation %s (ID: %d) scheduled for retry %d at %s.',
                    $item->operation_type,
                    $item->id,
                    $new_attempts,
                    $next_retry_at
                )
            );
        }
    }

    /**
     * Mark a queue item as completed.
     *
     * @param int $item_id Queue item ID.
     */
    private function mark_completed( int $item_id ): void {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table_name,
            [
                'status'     => self::STATUS_COMPLETED,
                'updated_at' => current_time( 'mysql', true ),
            ],
            [ 'id' => $item_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Notify admin about a permanently failed operation.
     *
     * Sends an email to the site admin with failure details.
     *
     * @param object $item Failed queue item.
     */
    private function notify_admin_failure( object $item ): void {
        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );

        $subject = sprintf(
            /* translators: 1: site name, 2: operation type */
            __( '[%1$s] ERP Sync Failed: %2$s', 'wc-erp-integration' ),
            $site_name,
            $item->operation_type
        );

        $message = sprintf(
            /* translators: 1: operation type, 2: item ID, 3: attempts, 4: error message, 5: admin URL */
            __(
                "A sync operation has permanently failed after maximum retries.\n\n" .
                "Operation: %1\$s\n" .
                "Queue ID: %2\$d\n" .
                "Attempts: %3\$d\n" .
                "Last Error: %4\$s\n\n" .
                "Review failed operations at: %5\$s",
                'wc-erp-integration'
            ),
            $item->operation_type,
            $item->id,
            $item->attempts,
            $item->error_message ?? 'Unknown',
            admin_url( 'admin.php?page=erp-integration&tab=queue' )
        );

        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     */
    private function log( string $level, string $message ): void {
        $configured_level = get_option( 'erp_log_level', 'info' );
        $levels           = [ 'debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3 ];

        $configured_priority = $levels[ $configured_level ] ?? 1;
        $message_priority    = $levels[ $level ] ?? 1;

        if ( $message_priority < $configured_priority ) {
            return;
        }

        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->log( $level, '[SyncQueue] ' . $message, [ 'source' => 'erp-integration' ] );
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf( '[ERP SyncQueue][%s] %s', strtoupper( $level ), $message ) );
        }
    }
}
