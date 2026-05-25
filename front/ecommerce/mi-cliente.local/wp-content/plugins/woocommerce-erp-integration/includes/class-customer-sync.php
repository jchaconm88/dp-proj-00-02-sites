<?php
/**
 * Customer Sync Service.
 *
 * Pushes WooCommerce customer data to the ERP system
 * on customer creation and profile updates.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class CustomerSync
 *
 * Hooks into WooCommerce customer lifecycle events to push
 * customer data to the ERP in real-time.
 */
class CustomerSync {

    /**
     * Meta key for storing the ERP customer ID.
     */
    private const META_ERP_CUSTOMER_ID = '_erp_customer_id';

    /**
     * Meta key for tracking customer sync status.
     */
    private const META_SYNC_STATUS = '_erp_customer_sync_status';

    /**
     * Meta key for last sync timestamp.
     */
    private const META_LAST_SYNC = '_erp_customer_last_sync';

    /**
     * ERP client instance.
     *
     * @var ERPClient
     */
    private ERPClient $erp_client;

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
     * Initialize WooCommerce hooks for customer sync.
     */
    public function init(): void {
        add_action( 'woocommerce_created_customer', [ $this, 'on_customer_created' ], 10, 3 );
        add_action( 'woocommerce_update_customer', [ $this, 'on_customer_updated' ], 10, 1 );

        // Also hook into profile updates from wp-admin.
        add_action( 'profile_update', [ $this, 'on_profile_updated' ], 10, 2 );
    }

    /**
     * Handle new customer creation.
     *
     * @param int   $customer_id  New customer (user) ID.
     * @param array $new_customer_data Customer data from registration.
     * @param bool  $password_generated Whether password was auto-generated.
     */
    public function on_customer_created( int $customer_id, array $new_customer_data = [], bool $password_generated = false ): void {
        $this->sync_customer_to_erp( $customer_id );
    }

    /**
     * Handle customer profile update via WooCommerce.
     *
     * @param int $customer_id Customer (user) ID.
     */
    public function on_customer_updated( int $customer_id ): void {
        $this->sync_customer_to_erp( $customer_id );
    }

    /**
     * Handle profile update from WordPress admin.
     *
     * Only syncs if the user is a WooCommerce customer.
     *
     * @param int      $user_id       User ID.
     * @param \WP_User $old_user_data Previous user data.
     */
    public function on_profile_updated( int $user_id, $old_user_data = null ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        // Only sync customers, not admins or other roles.
        if ( ! in_array( 'customer', $user->roles, true ) ) {
            return;
        }

        $this->sync_customer_to_erp( $user_id );
    }

    /**
     * Sync customer data to the ERP.
     *
     * @param int $customer_id WooCommerce customer (user) ID.
     */
    private function sync_customer_to_erp( int $customer_id ): void {
        $customer_data = $this->build_customer_payload( $customer_id );

        if ( empty( $customer_data ) ) {
            return;
        }

        try {
            $response = $this->erp_client->sync_customer( $customer_data );

            // Store ERP customer ID.
            $erp_customer_id = $response['erp_customer_id'] ?? $response['id'] ?? '';
            if ( $erp_customer_id ) {
                update_user_meta( $customer_id, self::META_ERP_CUSTOMER_ID, $erp_customer_id );
            }

            update_user_meta( $customer_id, self::META_SYNC_STATUS, 'synced' );
            update_user_meta( $customer_id, self::META_LAST_SYNC, gmdate( 'c' ) );

            $this->log(
                'info',
                sprintf( 'Customer %d synced to ERP (ERP ID: %s).', $customer_id, $erp_customer_id )
            );
        } catch ( \RuntimeException $e ) {
            update_user_meta( $customer_id, self::META_SYNC_STATUS, 'failed' );
            update_user_meta( $customer_id, self::META_LAST_SYNC, gmdate( 'c' ) );

            // Enqueue for retry.
            $this->sync_queue->enqueue( 'sync_customer', $customer_data, $e->getMessage() );

            $this->log(
                'error',
                sprintf(
                    'Failed to sync customer %d to ERP: %s. Enqueued for retry.',
                    $customer_id,
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Build the customer payload for the ERP.
     *
     * @param int $customer_id WooCommerce customer (user) ID.
     * @return array ERP-compatible customer payload.
     */
    private function build_customer_payload( int $customer_id ): array {
        $user = get_userdata( $customer_id );
        if ( ! $user ) {
            return [];
        }

        $payload = [
            'external_id' => (string) $customer_id,
            'email'       => $user->user_email,
            'first_name'  => get_user_meta( $customer_id, 'billing_first_name', true ) ?: $user->first_name,
            'last_name'   => get_user_meta( $customer_id, 'billing_last_name', true ) ?: $user->last_name,
            'phone'       => get_user_meta( $customer_id, 'billing_phone', true ),
            'company'     => get_user_meta( $customer_id, 'billing_company', true ),
            'billing'     => [
                'address_1' => get_user_meta( $customer_id, 'billing_address_1', true ),
                'address_2' => get_user_meta( $customer_id, 'billing_address_2', true ),
                'city'      => get_user_meta( $customer_id, 'billing_city', true ),
                'state'     => get_user_meta( $customer_id, 'billing_state', true ),
                'postcode'  => get_user_meta( $customer_id, 'billing_postcode', true ),
                'country'   => get_user_meta( $customer_id, 'billing_country', true ),
            ],
            'shipping'    => [
                'first_name' => get_user_meta( $customer_id, 'shipping_first_name', true ),
                'last_name'  => get_user_meta( $customer_id, 'shipping_last_name', true ),
                'company'    => get_user_meta( $customer_id, 'shipping_company', true ),
                'address_1'  => get_user_meta( $customer_id, 'shipping_address_1', true ),
                'address_2'  => get_user_meta( $customer_id, 'shipping_address_2', true ),
                'city'       => get_user_meta( $customer_id, 'shipping_city', true ),
                'state'      => get_user_meta( $customer_id, 'shipping_state', true ),
                'postcode'   => get_user_meta( $customer_id, 'shipping_postcode', true ),
                'country'    => get_user_meta( $customer_id, 'shipping_country', true ),
            ],
            'registered_at' => $user->user_registered,
        ];

        // Include existing ERP customer ID if available (for updates).
        $erp_customer_id = get_user_meta( $customer_id, self::META_ERP_CUSTOMER_ID, true );
        if ( $erp_customer_id ) {
            $payload['erp_customer_id'] = $erp_customer_id;
        }

        /**
         * Filter the ERP customer payload before sending.
         *
         * @param array $payload     ERP customer payload.
         * @param int   $customer_id WooCommerce customer ID.
         */
        return apply_filters( 'erp_customer_payload', $payload, $customer_id );
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
            $logger->log( $level, '[CustomerSync] ' . $message, [ 'source' => 'erp-integration' ] );
        } else {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf( '[ERP CustomerSync][%s] %s', strtoupper( $level ), $message ) );
        }
    }
}
