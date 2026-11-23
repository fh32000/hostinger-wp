<?php
/**
 * MPGS API Handler
 * Handles all communication with the Mastercard Payment Gateway Services (MPGS) REST API.
 *
 * @package WC_Ahli_MPGS
 */

defined( 'ABSPATH' ) || exit;

class WC_Ahli_MPGS_API {

    /** @var string */
    private $merchant_id;

    /** @var string */
    private $api_password;

    /** @var string */
    private $base_url;

    /** @var int */
    private $api_version;

    /**
     * Constructor.
     *
     * @param string $merchant_id   Merchant ID from MPGS portal.
     * @param string $api_password  API password (Password 1).
     * @param bool   $test_mode     Whether to use test/sandbox environment.
     * @param int    $api_version   MPGS API version number (default 72).
     */
    public function __construct( string $merchant_id, string $api_password, bool $test_mode = false, int $api_version = 61 ) {
        $this->merchant_id  = $merchant_id;
        $this->api_password = $api_password;
        $this->api_version  = $api_version;

        // The Ahli Bank gateway uses the same hostname for both live and test.
        // Test mode is typically differentiated by a test Merchant ID prefixed with "TEST".
        // Update this URL if the bank provides a separate sandbox endpoint.
        $this->base_url = $test_mode
            ? 'https://alahligatway.gateway.mastercard.com/api/rest'   // No separate test URL currently documented
            : 'https://alahligatway.gateway.mastercard.com/api/rest';
    }

    // ─── Public Methods ───────────────────────────────────────────────────────

    /**
     * Create a Hosted Checkout Session on MPGS.
     *
     * @param array $payload  Order / interaction data.
     * @return array{success: bool, data: array, message: string}
     */
    public function create_checkout_session( array $payload ): array {
        $endpoint = $this->build_endpoint( "merchant/{$this->merchant_id}/session" );
        return $this->post( $endpoint, $payload );
    }

    /**
     * Retrieve payment/transaction details for a session.
     *
     * @param string $session_id  The session.id returned by create_checkout_session.
     * @return array{success: bool, data: array, message: string}
     */
    public function retrieve_session( string $session_id ): array {
        $endpoint = $this->build_endpoint( "merchant/{$this->merchant_id}/session/{$session_id}" );
        return $this->get( $endpoint );
    }

    /**
     * Retrieve order details from MPGS by order ID.
     *
     * @param string $order_id
     * @return array{success: bool, data: array, message: string}
     */
    public function retrieve_order( string $order_id ): array {
        $endpoint = $this->build_endpoint( "merchant/{$this->merchant_id}/order/{$order_id}" );
        return $this->get( $endpoint );
    }

    /**
     * Request a refund for an existing transaction.
     *
     * @param string $order_id        MPGS order ID.
     * @param string $transaction_id  MPGS transaction ID to refund.
     * @param float  $amount          Amount to refund.
     * @param string $currency        3-letter ISO currency code (e.g. SAR).
     * @param string $refund_password Excessive Refunds API password.
     * @return array{success: bool, data: array, message: string}
     */
    public function refund( string $order_id, string $transaction_id, float $amount, string $currency, string $refund_password ): array {
        // Refunds use a different transaction ID (new unique ID).
        $new_transaction_id = 'REFUND-' . time();
        $endpoint = $this->build_endpoint(
            "merchant/{$this->merchant_id}/order/{$order_id}/transaction/{$new_transaction_id}"
        );

        $payload = [
            'apiOperation'  => 'REFUND',
            'transaction'   => [
                'amount'   => number_format( $amount, 2, '.', '' ),
                'currency' => $currency,
            ],
            'order' => [
                'id' => $order_id,
            ],
        ];

        // For refunds, temporarily swap the password to the excessive-refunds password.
        $original_password  = $this->api_password;
        $this->api_password = $refund_password;
        $result             = $this->put( $endpoint, $payload );
        $this->api_password = $original_password;

        return $result;
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Build a fully-qualified MPGS API endpoint URL.
     */
    private function build_endpoint( string $path ): string {
        return sprintf(
            '%s/version/%d/%s',
            rtrim( $this->base_url, '/' ),
            $this->api_version,
            ltrim( $path, '/' )
        );
    }

    /**
     * Basic Auth header value.
     */
    private function auth_header(): string {
        return 'Basic ' . base64_encode( "merchant.{$this->merchant_id}:{$this->api_password}" );
    }

    /**
     * Common HTTP headers.
     */
    private function headers(): array {
        return [
            'Authorization' => $this->auth_header(),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    /**
     * POST request.
     */
    private function post( string $url, array $body ): array {
        $response = wp_remote_post( $url, [
            'headers' => $this->headers(),
            'body'    => wp_json_encode( $body ),
            'timeout' => 45,
            'sslverify' => true,
        ] );

        return $this->parse_response( $response );
    }

    /**
     * GET request.
     */
    private function get( string $url ): array {
        $response = wp_remote_get( $url, [
            'headers' => $this->headers(),
            'timeout' => 45,
            'sslverify' => true,
        ] );

        return $this->parse_response( $response );
    }

    /**
     * PUT request (used for refunds / transaction updates).
     */
    private function put( string $url, array $body ): array {
        $response = wp_remote_request( $url, [
            'method'  => 'PUT',
            'headers' => $this->headers(),
            'body'    => wp_json_encode( $body ),
            'timeout' => 45,
            'sslverify' => true,
        ] );

        return $this->parse_response( $response );
    }

    /**
     * Parse WP HTTP API response into a standard array.
     *
     * @param WP_Error|array $response
     * @return array{success: bool, data: array, http_code: int, message: string}
     */
    private function parse_response( $response ): array {
        if ( is_wp_error( $response ) ) {
            $this->log( 'WP_Error: ' . $response->get_error_message(), 'error' );
            return [
                'success'   => false,
                'data'      => [],
                'http_code' => 0,
                'message'   => $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body_raw  = wp_remote_retrieve_body( $response );
        $data      = json_decode( $body_raw, true ) ?? [];

        $this->log( "HTTP {$http_code} ← " . $body_raw );

        $success = ( $http_code >= 200 && $http_code < 300 )
                   && ( ! isset( $data['result'] ) || $data['result'] !== 'ERROR' );

        return [
            'success'   => $success,
            'data'      => $data,
            'http_code' => $http_code,
            'message'   => $data['error']['explanation'] ?? $data['result'] ?? '',
        ];
    }

    /**
     * Log to WooCommerce logger.
     */
    private function log( string $message, string $level = 'debug' ): void {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->log( $level, $message, [ 'source' => 'wc-ahli-mpgs' ] );
        }
    }
}

