<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CRM API client: builds JSON per Falnic CRM lead documentation and sends via cURL.
 */
class Falnic_CRM_API {

    private $core;

    public function __construct( $core ) {
        $this->core = $core;
    }

    public function core() {
        return $this->core;
    }

    /**
     * Send data to the CRM lead endpoint.
     *
     * @param string $endpoint Target URL.
     * @param array  $data     Mapped lead data.
     * @param string $api_key  Decrypted API key.
     * @param string $headers  Raw "Name: Value" lines.
     * @return array ['success'=>bool, 'message'=>string, 'http_code'=>int|null, 'response'=>string]
     */
    public function send( $endpoint, array $data, $api_key = '', $headers = '' ) {
        $endpoint = esc_url_raw( $endpoint );
        if ( $endpoint === '' ) {
            return array( 'success' => false, 'message' => esc_html__( 'آدرس endpoint خالی است.', 'falnic-crm' ), 'http_code' => null, 'response' => '' );
        }

        // Allow developers to reshape the payload before sending (extensibility).
        $data = apply_filters( 'falnic_crm_payload', $data, $endpoint );

        $json_body = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

        $request_headers = array(
            'Content-Type: application/json',
            'X-API-KEY: ' . $api_key,
        );

        // Parse custom headers: one per line "Name: Value".
        foreach ( array_filter( explode( "\n", $headers ) ) as $line ) {
            $line = trim( $line );
            if ( $line === '' || strpos( $line, ':' ) === false ) {
                continue;
            }
            list( $name, $value ) = explode( ':', $line, 2 );
            $request_headers[] = trim( $name ) . ': ' . trim( $value );
        }

        // Filter for extra headers / driver customization.
        $request_headers = apply_filters( 'falnic_crm_headers', $request_headers, $endpoint, $api_key );

        $ch = curl_init();
        curl_setopt_array( $ch, array(
            CURLOPT_URL            => $endpoint,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $request_headers,
            CURLOPT_POSTFIELDS     => $json_body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, // Compatibility; can be forced true via filter if needed.
            CURLOPT_HEADER         => false,
        ) );

        // Async-friendly: a filter can short-circuit for custom drivers.
        $short = apply_filters( 'falnic_crm_send_shortcircuit', null, $endpoint, $json_body, $request_headers );
        if ( $short !== null ) {
            return $short;
        }

        $response = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curl_error = curl_error( $ch );
        curl_close( $ch );

        if ( false === $response ) {
            return array(
                'success'   => false,
                'message'   => esc_html__( 'خطا در ارتباط cURL:', 'falnic-crm' ) . ' ' . esc_html( $curl_error ),
                'http_code' => $http_code,
                'response'  => '',
            );
        }

        $success = ( $http_code >= 200 && $http_code < 300 );

        return array(
            'success'   => $success,
            'message'   => $success ? esc_html__( 'با موفقیت ارسال شد.', 'falnic-crm' ) : esc_html__( 'خطا در پاسخ CRM.', 'falnic-crm' ),
            'http_code' => $http_code,
            'response'  => is_string( $response ) ? $response : '',
        );
    }
}
