<?php
/**
 * IntelliSend SpamCheck Class
 *
 * Performs spam detection by calling the AntiSpamCheck API.
 *
 * @package   IntelliSend_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access.
}

if ( ! class_exists( 'IntelliSend_SpamCheck' ) ) :

final class IntelliSend_SpamCheck {

    /**
     * Check if a message is spam
     * 
     * @param string $message Message to check for spam
     * @param string $api_key_override Optional. If provided, this key will be used instead of the one from the database.
     * @return array Array with spam check results
     */
    public function check($message, $api_key_override = '') {
        // Get settings
        $settings = IntelliSend_Database::get_settings();
        
        // Use provided API key or get from settings
        $api_key = $api_key_override;
        if (empty($api_key)) {
            $api_key = $settings->antiSpamApiKey;
        }
        
        if ( empty( $api_key ) ) {
            return array(
                'success' => false,
                'message' => 'No API key provided',
                'isSpam'  => false,
            );
        }
        
        // Prepare the API request
        $api_url = !empty($settings->antiSpamEndPoint) ? $settings->antiSpamEndPoint : 'https://api.cyberitex.com/v1/tools/SpamCheck';
        
        $args = array(
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key'    => sanitize_text_field( $api_key ),
            ),
            'body'    => json_encode( array(
                'message' => $message,
            ) ),
        );
        
        // Make the API request
        $response = wp_remote_post( $api_url, $args );
        
        // Check for errors
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'isSpam'  => false,
            );
        }
        
        // Check for HTTP status code
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code === 401 ) {
            return array(
                'success' => false,
                'message' => 'Invalid API key. Please check your API key and try again.',
                'isSpam'  => false,
            );
        }
        
        if ( $status_code !== 200 ) {
            return array(
                'success' => false,
                'message' => 'API request failed with status code: ' . $status_code,
                'isSpam'  => false,
            );
        }
        
        // Parse the response
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // Check if the response is in the expected format
        if ( ! is_array( $data ) || ! isset( $data['isSpam'] ) ) {
            return array(
                'success' => false,
                'message' => 'Invalid response from spam check API',
                'isSpam'  => false,
            );
        }
        
        // Return the spam check results
        return array(
            'success' => true,
            'message' => 'Spam check completed successfully',
            'isSpam'  => (bool) $data['isSpam'],
            'score'   => isset( $data['score'] ) ? floatval( $data['score'] ) : 0,
        );
    }
    
    /**
     * Validate the API key
     * 
     * @param string $api_key_override Optional. If provided, this key will be used instead of the one from the database.
     * @return array Array with validation results
     */
    public function validate_api_key($api_key_override = '') {
        // Get settings
        $settings = IntelliSend_Database::get_settings();
        
        // Use provided API key or get from settings
        $api_key = '';
        
        if (!empty($api_key_override)) {
            $api_key = $api_key_override;
        } else {
            $api_key = $settings->antiSpamApiKey;
        }
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => 'No API key provided',
            );
        }
        
        // Prepare the API request
        $api_url = !empty($settings->antiSpamEndPoint) ? $settings->antiSpamEndPoint : 'https://api.cyberitex.com/v1/tools/SpamCheck';
        
        $args = array(
            'method'  => 'POST',
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key'    => sanitize_text_field( $api_key ),
            ),
            'body'    => json_encode( array(
                'validate' => true,
            ) ),
        );
        
        // Make the API request
        $response = wp_remote_post( $api_url, $args );
        
        // Check for errors
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }
        
        // Parse the response
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! is_array( $data ) ) {
            return array(
                'success' => false,
                'message' => 'Invalid response from API',
            );
        }
        
        // Return the validation results
        return array(
            'success' => isset($data['valid']) ? $data['valid'] : false,
            'message' => isset($data['message']) ? $data['message'] : 'API key validation failed',
        );
    }
}

endif;
