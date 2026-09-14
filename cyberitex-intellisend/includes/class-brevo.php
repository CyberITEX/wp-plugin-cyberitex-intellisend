<?php

/**
 * includes\class-brevo.php
 * Brevo (formerly Sendinblue) transactional email API transport.
 *
 * POST /v3/smtp/email, authenticated with an "api-key" header. A successful
 * send returns HTTP 201 with { "messageId": "<...>" }.
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

if (! class_exists('IntelliSend_Brevo')) :

    final class IntelliSend_Brevo extends IntelliSend_Api_Transport
    {
        /**
         * API base URL. Brevo serves one global endpoint.
         */
        const API_BASE = 'https://api.brevo.com';

        public static function get_label()
        {
            return 'Brevo (Web API)';
        }

        public static function get_default_base()
        {
            return self::API_BASE;
        }

        public static function get_send_path()
        {
            return '/v3/smtp/email';
        }

        public static function get_env_constant()
        {
            return 'BREVO_API_KEY';
        }

        public static function get_regions()
        {
            // Brevo has a single global API host, so there is nothing to choose.
            return array(self::API_BASE => 'Global (api.brevo.com)');
        }

        public static function get_key_placeholder()
        {
            return 'xkeysib-xxxxxxxxxxxxxxxxxxxxxx';
        }

        public static function get_sender_hint()
        {
            return 'Must be a validated sender or belong to a domain you have authenticated in Brevo.';
        }

        protected static function get_auth_headers($api_key)
        {
            // Brevo uses its own header rather than Authorization: Bearer.
            return array(
                'api-key' => $api_key,
                'accept'  => 'application/json',
            );
        }

        protected static function build_payload($provider, $message)
        {
            $payload = array(
                'sender'  => array('email' => $message['from_email'], 'name' => $message['from_name']),
                'to'      => self::to_address_objects($message['to']),
                'subject' => $message['subject'],
            );

            if (! empty($message['cc'])) {
                $payload['cc'] = self::to_address_objects($message['cc']);
            }

            if (! empty($message['bcc'])) {
                $payload['bcc'] = self::to_address_objects($message['bcc']);
            }

            if (! empty($message['reply_to'])) {
                $payload['replyTo'] = array('email' => $message['reply_to']);
            }

            // Brevo splits the body by type rather than taking a content array.
            if ($message['is_html']) {
                $payload['htmlContent'] = $message['body'];
            } else {
                $payload['textContent'] = $message['body'];
            }

            if (! empty($message['attachments'])) {
                $payload['attachment'] = array();

                foreach ($message['attachments'] as $attachment) {
                    $payload['attachment'][] = array(
                        'content' => $attachment['content'],
                        'name'    => $attachment['filename'],
                    );
                }
            }

            return $payload;
        }

        /**
         * Brevo returns the id in the response body.
         *
         * @param string $raw Response body.
         * @return string
         */
        protected static function get_message_id_from_body($raw)
        {
            $data = json_decode($raw, true);

            if (is_array($data) && ! empty($data['messageId'])) {
                return is_array($data['messageId']) ? implode(', ', $data['messageId']) : (string) $data['messageId'];
            }

            return '';
        }

        /**
         * Verify an API key against /v3/account.
         *
         * @param string $api_key  Key to test. Falls back to the configured key when empty.
         * @param object $provider Optional provider row.
         * @return array
         */
        public static function validate_api_key($api_key = '', $provider = null)
        {
            if (empty($api_key)) {
                $api_key = self::get_api_key($provider);
            }

            if (empty($api_key)) {
                return array('success' => false, 'message' => 'No Brevo API key provided.', 'code' => 0);
            }

            $response = wp_remote_get(
                self::get_api_base($provider) . '/v3/account',
                array(
                    'timeout' => 15,
                    'headers' => array_merge(
                        array('Content-Type' => 'application/json'),
                        self::get_auth_headers($api_key)
                    ),
                )
            );

            if (is_wp_error($response)) {
                return array('success' => false, 'message' => 'Connection error: ' . $response->get_error_message(), 'code' => 0);
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $raw  = wp_remote_retrieve_body($response);

            if (200 === $code) {
                $data    = json_decode($raw, true);
                $account = is_array($data) && ! empty($data['email']) ? ' (' . $data['email'] . ')' : '';

                return array('success' => true, 'message' => 'Brevo API key is valid.' . $account, 'code' => $code);
            }

            if (401 === $code || 403 === $code) {
                return array('success' => false, 'message' => 'Brevo rejected the API key (HTTP ' . $code . '). Check the key and try again.', 'code' => $code);
            }

            return array('success' => false, 'message' => self::extract_error($raw, $code), 'code' => $code);
        }

        /**
         * Brevo errors arrive as { "code": "...", "message": "..." }.
         *
         * @param string $raw  Response body.
         * @param int    $code HTTP status code.
         * @return string
         */
        protected static function extract_error($raw, $code)
        {
            $data = json_decode($raw, true);

            if (is_array($data) && ! empty($data['message'])) {
                $message = is_array($data['message']) ? implode('; ', $data['message']) : (string) $data['message'];

                if (! empty($data['code'])) {
                    $message .= ' (code: ' . $data['code'] . ')';
                }

                return 'Brevo API error (HTTP ' . $code . '): ' . $message;
            }

            return 'Brevo API request failed with HTTP ' . $code . '.';
        }
    }

endif;
