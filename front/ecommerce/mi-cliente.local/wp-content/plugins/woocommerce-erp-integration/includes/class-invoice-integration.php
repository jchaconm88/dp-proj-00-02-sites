<?php
/**
 * Invoice Integration (ERP → SUNAT).
 *
 * Handles electronic invoicing flow between WooCommerce, ERP, and SUNAT.
 * Supports boleta and factura based on customer document type.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Class InvoiceIntegration
 *
 * Manages invoice generation requests to ERP, webhook handling for
 * generated invoices, SUNAT error handling, and admin invoice registry.
 */
class InvoiceIntegration {

    /**
     * Invoice type: Boleta (for DNI holders).
     */
    private const TYPE_BOLETA = 'boleta';

    /**
     * Invoice type: Factura (for RUC holders).
     */
    private const TYPE_FACTURA = 'factura';

    /**
     * DNI document length.
     */
    private const DNI_LENGTH = 8;

    /**
     * RUC document length.
     */
    private const RUC_LENGTH = 11;

    /**
     * Constructor.
     *
     * Registers invoice-related hooks.
     */
    public function __construct() {
        // Request invoice on payment confirmation.
        add_action( 'woocommerce_payment_complete', [ $this, 'request_invoice_on_payment' ], 20, 1 );

        // Admin hooks.
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'wp_ajax_erp_retry_invoice', [ $this, 'ajax_retry_invoice' ] );
    }

    /**
     * Request invoice generation from ERP on payment confirmation.
     *
     * Determines invoice type (boleta/factura) based on customer document type.
     * Sends POST /orders/{id}/invoice to ERP.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public function request_invoice_on_payment( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Skip if invoice already requested.
        if ( $order->get_meta( '_erp_invoice_requested' ) ) {
            return;
        }

        $erp_order_id = $order->get_meta( '_erp_order_id' );
        if ( ! $erp_order_id ) {
            $this->log( 'warning', sprintf(
                'Cannot request invoice for order #%d: no ERP order ID.',
                $order_id
            ) );
            return;
        }

        // Determine invoice type based on document.
        $invoice_type = $this->determine_invoice_type( $order );

        $plugin = Plugin::get_instance();
        $client = $plugin->get_erp_client();

        if ( ! $client ) {
            $this->queue_invoice_request( $order_id, $erp_order_id, $invoice_type );
            return;
        }

        try {
            $response = $client->request_invoice( $erp_order_id, $invoice_type );

            $order->update_meta_data( '_erp_invoice_requested', 'yes' );
            $order->update_meta_data( '_erp_invoice_requested_at', current_time( 'mysql' ) );
            $order->update_meta_data( '_erp_invoice_type', $invoice_type );
            $order->update_meta_data( '_erp_invoice_status', 'pending' );

            if ( ! empty( $response['invoice_id'] ) ) {
                $order->update_meta_data( '_erp_invoice_id', $response['invoice_id'] );
            }

            $order->save();

            $this->log( 'info', sprintf(
                'Invoice requested for order #%d (type: %s, ERP order: %s)',
                $order_id,
                $invoice_type,
                $erp_order_id
            ) );
        } catch ( \RuntimeException $e ) {
            $this->log( 'error', sprintf(
                'Failed to request invoice for order #%d: %s',
                $order_id,
                $e->getMessage()
            ) );

            $order->update_meta_data( '_erp_invoice_status', 'error' );
            $order->update_meta_data( '_erp_invoice_error', $e->getMessage() );
            $order->save();

            // Queue for retry.
            $this->queue_invoice_request( $order_id, $erp_order_id, $invoice_type );
        }
    }

    /**
     * Determine invoice type based on customer document.
     *
     * - DNI (8 digits) → Boleta
     * - RUC (11 digits) → Factura
     *
     * @param \WC_Order $order WooCommerce order.
     * @return string Invoice type constant.
     */
    private function determine_invoice_type( \WC_Order $order ): string {
        $document_type   = $order->get_meta( '_billing_document_type' ) ?: '';
        $document_number = $order->get_meta( '_billing_document_number' ) ?: '';

        // Explicit document type.
        if ( 'ruc' === strtolower( $document_type ) ) {
            return self::TYPE_FACTURA;
        }

        if ( 'dni' === strtolower( $document_type ) ) {
            return self::TYPE_BOLETA;
        }

        // Infer from document number length.
        $doc_length = strlen( preg_replace( '/\D/', '', $document_number ) );

        if ( self::RUC_LENGTH === $doc_length ) {
            return self::TYPE_FACTURA;
        }

        // Default to boleta.
        return self::TYPE_BOLETA;
    }

    /**
     * Handle invoice_generated webhook from ERP.
     *
     * Fetches PDF/XML from ERP, stores with order, and emails to customer.
     *
     * @param array $payload Webhook payload.
     */
    public static function handle_invoice_generated( array $payload ): void {
        $order_id   = $payload['wc_order_id'] ?? 0;
        $invoice_id = $payload['invoice_id'] ?? '';
        $pdf_url    = $payload['pdf_url'] ?? '';
        $xml_url    = $payload['xml_url'] ?? '';
        $series     = $payload['series'] ?? '';
        $number     = $payload['number'] ?? '';

        if ( ! $order_id || ! $invoice_id ) {
            self::log_static( 'error', 'invoice_generated webhook missing required fields.' );
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            self::log_static( 'error', sprintf( 'Order #%d not found for invoice webhook.', $order_id ) );
            return;
        }

        // Download and store PDF.
        $pdf_path = '';
        if ( $pdf_url ) {
            $pdf_path = self::download_and_store_file( $pdf_url, $order_id, 'pdf' );
        }

        // Download and store XML.
        $xml_path = '';
        if ( $xml_url ) {
            $xml_path = self::download_and_store_file( $xml_url, $order_id, 'xml' );
        }

        // Update order meta.
        $order->update_meta_data( '_erp_invoice_id', $invoice_id );
        $order->update_meta_data( '_erp_invoice_status', 'generated' );
        $order->update_meta_data( '_erp_invoice_series', $series );
        $order->update_meta_data( '_erp_invoice_number', $number );
        $order->update_meta_data( '_erp_invoice_generated_at', current_time( 'mysql' ) );

        if ( $pdf_path ) {
            $order->update_meta_data( '_erp_invoice_pdf_path', $pdf_path );
        }
        if ( $xml_path ) {
            $order->update_meta_data( '_erp_invoice_xml_path', $xml_path );
        }

        $order->save();

        // Add order note.
        $order->add_order_note( sprintf(
            /* translators: 1: series, 2: number */
            __( 'Comprobante electrónico generado: %1$s-%2$s', 'wc-erp-integration' ),
            $series,
            $number
        ) );

        // Email invoice to customer.
        self::email_invoice_to_customer( $order, $pdf_path, $series, $number );

        self::log_static( 'info', sprintf(
            'Invoice %s-%s generated for order #%d',
            $series,
            $number,
            $order_id
        ) );
    }

    /**
     * Handle SUNAT rejection.
     *
     * Logs the rejection, marks invoice as pending review, and notifies admin.
     *
     * @param array $payload Webhook payload with rejection details.
     */
    public static function handle_invoice_rejected( array $payload ): void {
        $order_id     = $payload['wc_order_id'] ?? 0;
        $invoice_id   = $payload['invoice_id'] ?? '';
        $reject_code  = $payload['sunat_code'] ?? '';
        $reject_msg   = $payload['sunat_message'] ?? '';

        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Update order meta.
        $order->update_meta_data( '_erp_invoice_status', 'rejected' );
        $order->update_meta_data( '_erp_invoice_sunat_code', $reject_code );
        $order->update_meta_data( '_erp_invoice_sunat_message', $reject_msg );
        $order->update_meta_data( '_erp_invoice_rejected_at', current_time( 'mysql' ) );
        $order->save();

        // Add order note.
        $order->add_order_note( sprintf(
            /* translators: 1: SUNAT code, 2: SUNAT message */
            __( 'Comprobante rechazado por SUNAT. Código: %1$s - %2$s', 'wc-erp-integration' ),
            $reject_code,
            $reject_msg
        ) );

        // Log the rejection.
        self::log_static( 'error', sprintf(
            'SUNAT rejected invoice for order #%d. Code: %s, Message: %s',
            $order_id,
            $reject_code,
            $reject_msg
        ) );

        // Notify admin.
        self::notify_admin_invoice_error( $order, $reject_code, $reject_msg );
    }

    /**
     * Download a file from URL and store it in the uploads directory.
     *
     * @param string $url      File URL.
     * @param int    $order_id Order ID.
     * @param string $type     File type (pdf, xml).
     * @return string Local file path or empty string on failure.
     */
    private static function download_and_store_file( string $url, int $order_id, string $type ): string {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/erp-invoices/' . gmdate( 'Y/m' );

        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
        }

        $filename = sprintf( 'invoice-%d-%s.%s', $order_id, wp_generate_password( 8, false ), $type );
        $filepath = $target_dir . '/' . $filename;

        $response = wp_remote_get( $url, [ 'timeout' => 30 ] );

        if ( is_wp_error( $response ) ) {
            self::log_static( 'error', sprintf( 'Failed to download %s for order #%d: %s', $type, $order_id, $response->get_error_message() ) );
            return '';
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return '';
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if ( file_put_contents( $filepath, $body ) === false ) {
            return '';
        }

        return $filepath;
    }

    /**
     * Email invoice PDF to customer.
     *
     * @param \WC_Order $order    Order object.
     * @param string    $pdf_path Path to PDF file.
     * @param string    $series   Invoice series.
     * @param string    $number   Invoice number.
     */
    private static function email_invoice_to_customer( \WC_Order $order, string $pdf_path, string $series, string $number ): void {
        $customer_email = $order->get_billing_email();
        if ( ! $customer_email ) {
            return;
        }

        $subject = sprintf(
            /* translators: 1: series-number, 2: order number */
            __( 'Comprobante electrónico %1$s - Pedido #%2$s', 'wc-erp-integration' ),
            $series . '-' . $number,
            $order->get_order_number()
        );

        $message = sprintf(
            /* translators: 1: customer name, 2: series-number */
            __( "Hola %1\$s,\n\nAdjuntamos tu comprobante electrónico %2\$s.\n\nGracias por tu compra.", 'wc-erp-integration' ),
            $order->get_billing_first_name(),
            $series . '-' . $number
        );

        $attachments = [];
        if ( $pdf_path && file_exists( $pdf_path ) ) {
            $attachments[] = $pdf_path;
        }

        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        wp_mail( $customer_email, $subject, $message, $headers, $attachments );
    }

    /**
     * Notify admin about invoice error.
     *
     * @param \WC_Order $order       Order object.
     * @param string    $error_code  SUNAT error code.
     * @param string    $error_msg   SUNAT error message.
     */
    private static function notify_admin_invoice_error( \WC_Order $order, string $error_code, string $error_msg ): void {
        $admin_email = get_option( 'admin_email' );

        $subject = sprintf(
            /* translators: %d: order ID */
            __( '[URGENTE] Error de facturación - Pedido #%d', 'wc-erp-integration' ),
            $order->get_id()
        );

        $message = sprintf(
            __( "Se ha producido un error al generar el comprobante electrónico.\n\nPedido: #%1\$d\nCliente: %2\$s\nCódigo SUNAT: %3\$s\nMensaje: %4\$s\n\nPor favor revise el pedido y reintente la emisión.", 'wc-erp-integration' ),
            $order->get_id(),
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            $error_code,
            $error_msg
        );

        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Queue invoice request for retry.
     *
     * @param int    $order_id     WooCommerce order ID.
     * @param string $erp_order_id ERP order ID.
     * @param string $invoice_type Invoice type.
     */
    private function queue_invoice_request( int $order_id, string $erp_order_id, string $invoice_type ): void {
        $plugin     = Plugin::get_instance();
        $sync_queue = $plugin->get_sync_queue();

        if ( $sync_queue ) {
            $sync_queue->enqueue( 'invoice_request', [
                'order_id'     => $order_id,
                'erp_order_id' => $erp_order_id,
                'invoice_type' => $invoice_type,
            ] );
        }
    }

    /**
     * Register admin menu for invoice registry.
     */
    public function register_admin_menu(): void {
        add_submenu_page(
            'woocommerce',
            __( 'Registro de Comprobantes', 'wc-erp-integration' ),
            __( 'Comprobantes', 'wc-erp-integration' ),
            'manage_woocommerce',
            'erp-invoices',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Render admin invoice registry page with filters.
     */
    public function render_admin_page(): void {
        // Get filter parameters.
        $status_filter = sanitize_text_field( $_GET['invoice_status'] ?? '' );
        $type_filter   = sanitize_text_field( $_GET['invoice_type'] ?? '' );
        $date_from     = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to       = sanitize_text_field( $_GET['date_to'] ?? '' );

        // Query orders with invoice data.
        $args = [
            'limit'      => 50,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'meta_query' => [
                [
                    'key'     => '_erp_invoice_requested',
                    'value'   => 'yes',
                    'compare' => '=',
                ],
            ],
        ];

        if ( $status_filter ) {
            $args['meta_query'][] = [
                'key'   => '_erp_invoice_status',
                'value' => $status_filter,
            ];
        }

        if ( $type_filter ) {
            $args['meta_query'][] = [
                'key'   => '_erp_invoice_type',
                'value' => $type_filter,
            ];
        }

        if ( $date_from ) {
            $args['date_created'] = '>=' . $date_from;
        }

        $orders = wc_get_orders( $args );

        // Render the page.
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Registro de Comprobantes Electrónicos', 'wc-erp-integration' ); ?></h1>

            <!-- Filters -->
            <form method="get" class="erp-invoice-filters">
                <input type="hidden" name="page" value="erp-invoices">

                <select name="invoice_status">
                    <option value=""><?php esc_html_e( 'Todos los estados', 'wc-erp-integration' ); ?></option>
                    <option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pendiente', 'wc-erp-integration' ); ?></option>
                    <option value="generated" <?php selected( $status_filter, 'generated' ); ?>><?php esc_html_e( 'Generado', 'wc-erp-integration' ); ?></option>
                    <option value="rejected" <?php selected( $status_filter, 'rejected' ); ?>><?php esc_html_e( 'Rechazado', 'wc-erp-integration' ); ?></option>
                    <option value="error" <?php selected( $status_filter, 'error' ); ?>><?php esc_html_e( 'Error', 'wc-erp-integration' ); ?></option>
                </select>

                <select name="invoice_type">
                    <option value=""><?php esc_html_e( 'Todos los tipos', 'wc-erp-integration' ); ?></option>
                    <option value="boleta" <?php selected( $type_filter, 'boleta' ); ?>><?php esc_html_e( 'Boleta', 'wc-erp-integration' ); ?></option>
                    <option value="factura" <?php selected( $type_filter, 'factura' ); ?>><?php esc_html_e( 'Factura', 'wc-erp-integration' ); ?></option>
                </select>

                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'Desde', 'wc-erp-integration' ); ?>">
                <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'Hasta', 'wc-erp-integration' ); ?>">

                <button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'wc-erp-integration' ); ?></button>
            </form>

            <!-- Invoice Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Pedido', 'wc-erp-integration' ); ?></th>
                        <th><?php esc_html_e( 'Tipo', 'wc-erp-integration' ); ?></th>
                        <th><?php esc_html_e( 'Serie-Número', 'wc-erp-integration' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'wc-erp-integration' ); ?></th>
                        <th><?php esc_html_e( 'Fecha', 'wc-erp-integration' ); ?></th>
                        <th><?php esc_html_e( 'Acciones', 'wc-erp-integration' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $orders as $order ) : ?>
                        <?php
                        $inv_type   = $order->get_meta( '_erp_invoice_type' );
                        $inv_status = $order->get_meta( '_erp_invoice_status' );
                        $inv_series = $order->get_meta( '_erp_invoice_series' );
                        $inv_number = $order->get_meta( '_erp_invoice_number' );
                        $inv_date   = $order->get_meta( '_erp_invoice_generated_at' ) ?: $order->get_meta( '_erp_invoice_requested_at' );
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_id() ); ?></a></td>
                            <td><?php echo esc_html( ucfirst( $inv_type ) ); ?></td>
                            <td><?php echo $inv_series ? esc_html( $inv_series . '-' . $inv_number ) : '—'; ?></td>
                            <td>
                                <span class="erp-invoice-status erp-invoice-status--<?php echo esc_attr( $inv_status ); ?>">
                                    <?php echo esc_html( ucfirst( $inv_status ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $inv_date ?: '—' ); ?></td>
                            <td>
                                <?php if ( in_array( $inv_status, [ 'error', 'rejected' ], true ) ) : ?>
                                    <button class="button button-small erp-retry-invoice" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                                        <?php esc_html_e( 'Reintentar', 'wc-erp-integration' ); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ( $order->get_meta( '_erp_invoice_pdf_path' ) ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=erp_download_invoice&order_id=' . $order->get_id() ), 'download_invoice' ) ); ?>" class="button button-small">
                                        <?php esc_html_e( 'PDF', 'wc-erp-integration' ); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * AJAX handler to retry invoice generation.
     */
    public function ajax_retry_invoice(): void {
        check_ajax_referer( 'erp_retry_invoice', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permisos insuficientes.', 'wc-erp-integration' ) ] );
        }

        $order_id = absint( $_POST['order_id'] ?? 0 );
        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de pedido inválido.', 'wc-erp-integration' ) ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( [ 'message' => __( 'Pedido no encontrado.', 'wc-erp-integration' ) ] );
        }

        // Reset invoice status and re-request.
        $order->update_meta_data( '_erp_invoice_requested', '' );
        $order->update_meta_data( '_erp_invoice_status', '' );
        $order->save();

        $this->request_invoice_on_payment( $order_id );

        wp_send_json_success( [ 'message' => __( 'Solicitud de comprobante reenviada.', 'wc-erp-integration' ) ] );
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     */
    private function log( string $level, string $message ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, [ 'source' => 'erp-invoicing' ] );
        }
    }

    /**
     * Static logging helper.
     *
     * @param string $level   Log level.
     * @param string $message Log message.
     */
    private static function log_static( string $level, string $message ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, [ 'source' => 'erp-invoicing' ] );
        }
    }
}
