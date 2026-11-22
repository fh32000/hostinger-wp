<?php
/**
 * License & Lead Management for Ahli MPGS Gateway
 *
 * @package WC_Ahli_MPGS
 */

defined( 'ABSPATH' ) || exit;

class WC_Ahli_MPGS_License {

    const OPTION_NAME     = 'wc_ahli_mpgs_license_data';
    const TRANSIENT_CHECK = 'wc_ahli_mpgs_license_check_cache';

    /**
     * Get clean domain/host of the current WordPress site.
     */
    public static function get_site_domain(): string {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( empty( $host ) ) {
            $host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? 'localhost' ) );
        }
        // Remove port and www
        $host = preg_replace( '/^www\./i', '', $host );
        return strtolower( trim( explode( ':', $host )[0] ) );
    }

    /**
     * Get stored license data.
     */
    public static function get_license_data(): array {
        $defaults = [
            'license_key'     => '',
            'client_email'    => '',
            'client_phone'    => '',
            'status'          => 'unlicensed', // unlicensed, valid, invalid, expired, suspended
            'bound_domain'    => '',
            'message'         => '',
            'last_check'      => 0,
            'server_url'      => '',
        ];

        $data = get_option( self::OPTION_NAME, [] );
        return wp_parse_args( is_array( $data ) ? $data : [], $defaults );
    }

    /**
     * Is the plugin currently licensed and valid for this domain?
     */
    public static function is_active(): bool {
        $data = self::get_license_data();
        if ( empty( $data['license_key'] ) || $data['status'] !== 'valid' ) {
            return false;
        }

        // Domain verification
        $current_domain = self::get_site_domain();
        if ( ! empty( $data['bound_domain'] ) && strtolower( $data['bound_domain'] ) !== $current_domain ) {
            return false;
        }

        return true;
    }

    /**
     * Get API endpoint URL (Google Apps Script Web App).
     */
    public static function get_server_url(): string {
        $data = self::get_license_data();
        if ( ! empty( $data['server_url'] ) ) {
            return esc_url_raw( $data['server_url'] );
        }
        // Fallback option in gateway settings
        $gateway_settings = get_option( 'woocommerce_wc_ahli_mpgs_gateway_settings', [] );
        return esc_url_raw( $gateway_settings['license_api_url'] ?? '' );
    }

    /**
     * Activate or re-validate license with the Google Apps Script Web App.
     */
    public static function activate( string $key, string $email, string $phone, string $server_url = '' ): array {
        $key        = sanitize_text_field( trim( $key ) );
        $email      = sanitize_email( trim( $email ) );
        $phone      = sanitize_text_field( trim( $phone ) );
        $server_url = ! empty( $server_url ) ? esc_url_raw( trim( $server_url ) ) : self::get_server_url();

        if ( empty( $server_url ) ) {
            return [
                'success' => false,
                'message' => 'لم يتم إعداد رابط خادم الترخيص (License Server URL). يرجى مراجعة إعدادات البوابة.',
            ];
        }

        if ( empty( $phone ) || empty( $email ) ) {
            return [
                'success' => false,
                'message' => 'يرجى إدخال رقم الجوال والبريد الإلكتروني لإتمام العملية.',
            ];
        }

        $domain = self::get_site_domain();

        $payload = [
            'action'      => 'activate',
            'license_key' => $key,
            'domain'      => $domain,
            'email'       => $email,
            'phone'       => $phone,
            'site_url'    => home_url(),
            'site_name'   => get_bloginfo( 'name' ),
            'wp_version'  => get_bloginfo( 'version' ),
        ];

        $response = wp_remote_post( $server_url, [
            'method'      => 'POST',
            'timeout'     => 20,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers'     => [ 'Content-Type' => 'application/json' ],
            'body'        => wp_json_encode( $payload ),
            'sslverify'   => false,
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => 'تعذر الاتصال بخادم الترخيص: ' . $response->get_error_message(),
            ];
        }

        $body_raw = wp_remote_retrieve_body( $response );
        $res      = json_decode( $body_raw, true );

        if ( ! is_array( $res ) ) {
            return [
                'success' => false,
                'message' => 'استجابة غير صالحة من خادم الترخيص.',
            ];
        }

        $current_data                 = self::get_license_data();
        $current_data['license_key']  = $key;
        $current_data['client_email'] = $email;
        $current_data['client_phone'] = $phone;
        $current_data['last_check']   = time();
        if ( ! empty( $server_url ) ) {
            $current_data['server_url'] = $server_url;
        }

        if ( ! empty( $res['success'] ) && true === $res['success'] ) {
            $current_data['status']       = 'valid';
            $current_data['bound_domain'] = $res['domain'] ?? $domain;
            $current_data['message']      = $res['message'] ?? 'الترخيص مفعل وصالح لهذا الموقع.';
            update_option( self::OPTION_NAME, $current_data );
            set_transient( self::TRANSIENT_CHECK, true, 12 * HOUR_IN_SECONDS );

            return [
                'success' => true,
                'message' => $current_data['message'],
            ];
        } else {
            $current_data['status']  = 'invalid';
            $current_data['message'] = $res['message'] ?? 'فشل التحقق من الترخيص.';
            update_option( self::OPTION_NAME, $current_data );
            delete_transient( self::TRANSIENT_CHECK );

            return [
                'success' => false,
                'message' => $current_data['message'],
            ];
        }
    }

    /**
     * Send lead info when user submits phone/email or installs.
     */
    public static function capture_lead( string $email, string $phone, string $note = '' ): void {
        $server_url = self::get_server_url();
        if ( empty( $server_url ) ) {
            return;
        }

        $payload = [
            'action'    => 'lead',
            'domain'    => self::get_site_domain(),
            'email'     => sanitize_email( $email ),
            'phone'     => sanitize_text_field( $phone ),
            'site_url'  => home_url(),
            'site_name' => get_bloginfo( 'name' ),
            'note'      => sanitize_text_field( $note ),
        ];

        wp_remote_post( $server_url, [
            'method'      => 'POST',
            'timeout'     => 10,
            'redirection' => 5,
            'blocking'    => false, // asynchronous
            'headers'     => [ 'Content-Type' => 'application/json' ],
            'body'        => wp_json_encode( $payload ),
            'sslverify'   => false,
        ] );
    }

    /**
     * Deactivate license locally.
     */
    public static function deactivate(): void {
        $data           = self::get_license_data();
        $data['status'] = 'unlicensed';
        $data['message'] = 'تم إلغاء التفعيل.';
        update_option( self::OPTION_NAME, $data );
        delete_transient( self::TRANSIENT_CHECK );
    }
}

