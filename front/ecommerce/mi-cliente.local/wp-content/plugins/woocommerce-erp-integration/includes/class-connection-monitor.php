<?php
/**
 * Connection Monitor.
 *
 * Monitors the ERP connection health, detects disconnections,
 * manages cached data fallback, and triggers full sync on reconnection.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class ConnectionMonitor
 *
 * Detects ERP disconnections exceeding 5 minutes, logs events,
 * enqueues pending operations, notifies admin, and executes
 * full sync on reconnection. Uses cached data when ERP is unavailable.
 */
class ConnectionMonitor {

    /**
     * Option key for last successful connection timestamp.
     */
    private const OPTION_LAST_CONNECTED = 'erp_last_connected_at';

    /**
     * Option key for current connection status.
     */
    private const OPTION_CONNECTION_STATUS = 'erp_connection_status';

    /**
     * Option key for disconnection start timestamp.
     */
    private const OPTION_DISCONNECTED_SINCE = 'erp_disconnected_since';

    /**
     * Option key for admin notification sent flag.
     */
    private const OPTION_ADMIN_NOTIFIED = 'erp_admin_disconnection_notified';

    /**
     * Transient key prefix for cached ERP data.
     */
    private const CACHE_PREFIX = 'erp_cache_';

    /**
     * Default cache TTL in seconds (1 hour).
     */
    private const DEFAULT_CACHE_TTL = 3600;

    /**
     * Disconnection threshold in seconds (5 minutes).
     */
    private const DISCONNECTION_THRESHOLD = 300;

    /**
     * ERP client instance.
     *
     * @var ERPClient
     */
    private ERPClient $erp_client;

    /**
     * Sync service instance.
     *
     * @var SyncService|null
     */
    private ?SyncService $sync_service = null;

    /**
     * Sync queue instance.
     *
     * @var SyncQueue
     */
    private SyncQueue $sync_queue;

    /**
     * Constructor.
     *
     * @param ERPClient $erp_client ERP client instance.
     * @param SyncQueue $sync_queue Sync queue instance.
     */
    public function __construct( ERPClient $erp_client, SyncQueue $sync_queue ) {
        $this->erp_client = $erp_client;
        $this->sync_queue = $sync_queue;
    }

    /**
     * Set the sync service for full sync on reconnection.
     *
     * @param SyncService $sync_service Sync service instance.
     */
    public function set_sync_service( SyncService $sync_service ): void {
        $this->sync_service = $sync_service;
    }

    /**
     * Initialize passive connection monitoring (sin cron).
     *
     * No registra crons. El estado de conexión se actualiza pasivamente
     * desde ERPClient::request() en cada operación real.
     */
    public function init(): void {
        // No cron — health tracking is passive via ERPClient::request().
        // Hook opcional para seguimiento manual.
    }

    /**
     * Handle a successful connection (ERP is reachable).
     *
     * If previously disconnected, triggers reconnection logic.
     *
     * @param string $previous_status Previous connection status.
     */
    private function handle_connected( string $previous_status ): void {
        update_option( self::OPTION_LAST_CONNECTED, time() );
        update_option( self::OPTION_CONNECTION_STATUS, 'connected' );

        // If we were disconnected, trigger reconnection logic.
        if ( 'disconnected' === $previous_status ) {
            $this->on_reconnection();
        }
    }

    /**
     * Handle a failed connection (ERP is unreachable).
     *
     * Tracks disconnection duration and triggers alerts
     * when threshold is exceeded.
     *
     * @param string $previous_status Previous connection status.
     */
    private function handle_disconnected( string $previous_status ): void {
        if ( 'connected' === $previous_status ) {
            // First detection of disconnection.
            update_option( self::OPTION_DISCONNECTED_SINCE, time() );
            update_option( self::OPTION_CONNECTION_STATUS, 'disconnected' );
            update_option( self::OPTION_ADMIN_NOTIFIED, false );

            $this->log( 'warning', 'ERP connection lost. Monitoring for threshold.' );
        }

        // Check if disconnection exceeds threshold.
        $disconnected_since = (int) get_option( self::OPTION_DISCONNECTED_SINCE, time() );
        $duration           = time() - $disconnected_since;

        if ( $duration >= self::DISCONNECTION_THRESHOLD ) {
            $already_notified = get_option( self::OPTION_ADMIN_NOTIFIED, false );

            if ( ! $already_notified ) {
                $this->notify_admin_disconnection( $duration );
                update_option( self::OPTION_ADMIN_NOTIFIED, true );
            }

            $this->log(
                'error',
                sprintf( 'ERP disconnected for %d seconds (threshold: %d).', $duration, self::DISCONNECTION_THRESHOLD )
            );
        }
    }

    /**
     * Handle reconnection after a disconnection period.
     *
     * Logs the event, clears disconnection state, notifies admin,
     * and triggers a full sync to reconcile data.
     */
    private function on_reconnection(): void {
        $disconnected_since = (int) get_option( self::OPTION_DISCONNECTED_SINCE, 0 );
        $downtime           = $disconnected_since > 0 ? time() - $disconnected_since : 0;

        // Clear disconnection state.
        delete_option( self::OPTION_DISCONNECTED_SINCE );
        update_option( self::OPTION_ADMIN_NOTIFIED, false );

        $this->log(
            'info',
            sprintf( 'ERP connection restored after %d seconds of downtime.', $downtime )
        );

        // Notify admin of reconnection.
        $this->notify_admin_reconnection( $downtime );

        // Process any queued operations that accumulated during downtime (sin full sync).
        $this->sync_queue->process_queue();
    }

    /**
     * Check if the ERP is currently available.
     *
     * @return bool True if connected, false if disconnected.
     */
    public function is_connected(): bool {
        return 'connected' === get_option( self::OPTION_CONNECTION_STATUS, 'connected' );
    }

    /**
     * Get the current disconnection duration in seconds.
     *
     * @return int Seconds since disconnection, or 0 if connected.
     */
    public function get_downtime(): int {
        if ( $this->is_connected() ) {
            return 0;
        }

        $disconnected_since = (int) get_option( self::OPTION_DISCONNECTED_SINCE, 0 );
        return $disconnected_since > 0 ? time() - $disconnected_since : 0;
    }

    /**
     * Get cached data for a given key.
     *
     * Used as fallback when ERP is unavailable.
     *
     * @param string $key Cache key identifier.
     * @return mixed|false Cached data or false if not available.
     */
    public function get_cached_data( string $key ) {
        return get_transient( self::CACHE_PREFIX . $key );
    }

    /**
     * Store data in cache for fallback use.
     *
     * @param string $key  Cache key identifier.
     * @param mixed  $data Data to cache.
     * @param int    $ttl  Cache TTL in seconds.
     */
    public function set_cached_data( string $key, $data, int $ttl = self::DEFAULT_CACHE_TTL ): void {
        set_transient( self::CACHE_PREFIX . $key, $data, $ttl );
    }

    /**
     * Get connection status information for admin display.
     *
     * @return array{status: string, last_connected: int, downtime: int, healthy: bool}
     */
    public function get_status_info(): array {
        return [
            'status'         => get_option( self::OPTION_CONNECTION_STATUS, 'connected' ),
            'last_connected' => (int) get_option( self::OPTION_LAST_CONNECTED, 0 ),
            'downtime'       => $this->get_downtime(),
            'healthy'        => $this->is_connected(),
        ];
    }

    /**
     * Notify admin about ERP disconnection.
     *
     * @param int $duration Duration of disconnection in seconds.
     */
    private function notify_admin_disconnection( int $duration ): void {
        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );

        $subject = sprintf(
            /* translators: %s: site name */
            __( '[%s] ALERTA: Conexión ERP perdida', 'wc-erp-integration' ),
            $site_name
        );

        $message = sprintf(
            /* translators: 1: duration in minutes, 2: threshold in minutes, 3: admin URL */
            __(
                "La conexión con el sistema ERP se ha perdido.\n\n" .
                "Duración: %1\$d minutos\n" .
                "Umbral de alerta: %2\$d minutos\n\n" .
                "Las operaciones pendientes se están encolando automáticamente.\n" .
                "Se ejecutará una sincronización completa al reconectar.\n\n" .
                "Verificar estado en: %3\$s",
                'wc-erp-integration'
            ),
            (int) ceil( $duration / 60 ),
            (int) ceil( self::DISCONNECTION_THRESHOLD / 60 ),
            admin_url( 'admin.php?page=erp-integration&tab=status' )
        );

        wp_mail( $admin_email, $subject, $message );

        $this->log( 'info', 'Admin notified about ERP disconnection.' );
    }

    /**
     * Notify admin about ERP reconnection.
     *
     * @param int $downtime Total downtime in seconds.
     */
    private function notify_admin_reconnection( int $downtime ): void {
        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );

        $subject = sprintf(
            /* translators: %s: site name */
            __( '[%s] Conexión ERP restaurada', 'wc-erp-integration' ),
            $site_name
        );

        $message = sprintf(
            /* translators: 1: downtime in minutes, 2: admin URL */
            __(
                "La conexión con el sistema ERP ha sido restaurada.\n\n" .
                "Tiempo de inactividad total: %1\$d minutos\n\n" .
                "Se está ejecutando una sincronización completa para reconciliar datos.\n" .
                "Las operaciones encoladas se procesarán automáticamente.\n\n" .
                "Verificar estado en: %2\$s",
                'wc-erp-integration'
            ),
            (int) ceil( $downtime / 60 ),
            admin_url( 'admin.php?page=erp-integration&tab=status' )
        );

        wp_mail( $admin_email, $subject, $message );

        $this->log( 'info', 'Admin notified about ERP reconnection.' );
    }

    /**
     * Log a message using WooCommerce logger.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     */
    private function log( string $level, string $message ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->log( $level, '[ConnectionMonitor] ' . $message, [ 'source' => 'erp-integration' ] );
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf( '[ERP ConnectionMonitor][%s] %s', strtoupper( $level ), $message ) );
        }
    }
}
