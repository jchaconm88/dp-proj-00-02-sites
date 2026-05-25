<?php
/**
 * ERP Client Interface.
 *
 * Defines the contract for all ERP API communication.
 *
 * @package AgenciaERP
 */

namespace AgenciaERP;

defined( 'ABSPATH' ) || exit;

/**
 * Interface ERPClientInterface
 *
 * Contract for ERP API client implementations.
 * All methods may throw ERPException on communication failures.
 */
interface ERPClientInterface {

    /**
     * Authenticate with the ERP API and obtain an access token.
     *
     * @return array{token: string, expires_at: int} Authentication token data.
     * @throws ERPException On authentication failure.
     */
    public function authenticate(): array;

    /**
     * Check ERP API health status.
     *
     * @return array{status: string, latency_ms: int} Health check result.
     * @throws ERPException On connection failure.
     */
    public function health_check(): array;

    /**
     * Retrieve products from the ERP.
     *
     * @param array       $filters Optional filters (category, status, etc.).
     * @param string|null $since   ISO 8601 date to fetch only updated products.
     * @return array List of product data arrays.
     */
    public function get_products( array $filters = [], ?string $since = null ): array;

    /**
     * Retrieve a single product by SKU.
     *
     * @param string $sku Product SKU.
     * @return array|null Product data or null if not found.
     */
    public function get_product_by_sku( string $sku ): ?array;

    /**
     * Retrieve stock levels for given SKUs.
     *
     * @param array $skus List of SKUs. Empty array returns all.
     * @return array Associative array of SKU => stock level.
     */
    public function get_stock_levels( array $skus = [] ): array;

    /**
     * Retrieve prices for given SKUs.
     *
     * @param array $skus List of SKUs. Empty array returns all.
     * @return array Associative array of SKU => price data.
     */
    public function get_prices( array $skus = [] ): array;

    /**
     * Create an order in the ERP.
     *
     * @param array $order_data Order payload.
     * @return array ERP order response with erp_order_id.
     */
    public function create_order( array $order_data ): array;

    /**
     * Update order status in the ERP.
     *
     * @param string $erp_order_id ERP order identifier.
     * @param string $status       New status.
     * @return bool True on success.
     */
    public function update_order_status( string $erp_order_id, string $status ): bool;

    /**
     * Confirm payment for an order in the ERP.
     *
     * @param string $erp_order_id ERP order identifier.
     * @param array  $payment_data Payment confirmation data.
     * @return bool True on success.
     */
    public function confirm_payment( string $erp_order_id, array $payment_data ): bool;

    /**
     * Get shipment status from the ERP.
     *
     * @param string $erp_order_id ERP order identifier.
     * @return array Shipment status data.
     */
    public function get_shipment_status( string $erp_order_id ): array;

    /**
     * Get shipping rates from the ERP.
     *
     * @param array $request Shipping quote request data.
     * @return array List of shipping rate options.
     */
    public function get_shipping_rates( array $request ): array;

    /**
     * Request invoice generation in the ERP.
     *
     * @param string $erp_order_id ERP order identifier.
     * @param string $invoice_type Invoice type (boleta/factura).
     * @return array Invoice request response.
     */
    public function request_invoice( string $erp_order_id, string $invoice_type ): array;

    /**
     * Sync customer data to the ERP.
     *
     * @param array $customer_data Customer payload.
     * @return array ERP customer response.
     */
    public function sync_customer( array $customer_data ): array;
}
