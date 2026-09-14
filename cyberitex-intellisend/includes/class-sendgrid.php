<?php

/**
 * includes\class-sendgrid.php
 * SendGrid Web API v3 transport.
 *
 * POST /v3/mail/send, authenticated with a bearer API key. A successful send
 * returns HTTP 202 with an empty body.
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

if (! class_exists('IntelliSend_SendGrid')) :

    final class IntelliSend_SendGrid extends IntelliSend_Api_Transport
    {
        /**
         * Default API base URL (global region).
         */
        const API_BASE = 'https://api.sendgrid.com';

        /**
         * API base URL for EU data residency (EU-pinned subusers).
         */
        const API_BASE_EU = 'https://api.eu.sendgrid.com';

        public static function get_label()
        {
            return 'SendGrid (Web API)';
        }

        public static function get_default_base()
        {
            return self::API_BASE;
        }

        public static function get_send_path()
        {
            return '/v3/mail/send';
        }

        public static function get_env_constant()
        {
            return 'SENDGRID_API_KEY';
        }

        public static function get_regions()
        {
            return array(
                self::API_BASE    => 'Global (api.sendgrid.com)',
                self::API_BASE_EU => 'EU (api.eu.sendgrid.com)',
            );
        }

        public static function get_key_placeholder()
        {
            return 'SG.xxxxxxxxxxxxxxxxxxxxxx';
        }

        public static function get_sender_hint()
        {
            return 'Must be a verified Single Sender or a domain you have authenticated in SendGrid.';
        }

        /**
         * SendGrid returns the message id in a response header.
         */
        protected static function get_message_id_header()
        {
            return 'x-message-id';
        }

        protected static function get_auth_headers($api_key)
        {
            return array('Authorization' => 'Bearer ' . $api_key);
        }

        /**
         * Build the v3 mail/send body.
         *
         * @param object $provider Provider row.
         * @param array  $message  Normalized message.
         * @return array
         */
        protected static function build_payload($provider, $message)
        {
            $personalization = array('to' => self::to_address_objects($message['to']));

            if (! empty($message['cc'])) {
                $personalization['cc'] = self::to_address_objects($message['cc']);
            }

            if (! empty($message['bcc'])) {
                $personalization['bcc'] = self::to_address_objects($message['bcc']);
            }

            $payload = array(
                'personalizations' => array($personalization),
                'from'             => array('email' => $message['from_email'], 'name' => $message['from_name']),
                'subject'          => $message['subject'],
                'content'          => array(
                    array(
                        'type'  => $message['content_type'],
                        'value' => $message['body'],
                    ),
                ),
            );

            if (! empty($message['reply_to'])) {
                $payload['reply_to'] = array('email' => $message['reply_to']);
            }

            if (! empty($message['attachments'])) {
                $payload['attachments'] = array();

                foreach ($message['attachments'] as $attachment) {
                    $payload['attachments'][] = array(
                        'content'     => $attachment['content'],
                        'filename'    => $attachment['filename'],
                        'type'        => $attachment['type'],
                        'disposition' => 'attachment',
                    );
                }
            }

            return $payload;
        }

        /**
         * Verify an API key against /v3/scopes, which also reveals its permissions.
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
                return array('success' => false, 'message' => 'No SendGrid API key provided.', 'code' => 0);
            }

            $response = wp_remote_get(
                self::get_api_base($provider) . '/v3/scopes',
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
                $data   = json_decode($raw, true);
                $scopes = is_array($data) && isset($data['scopes']) ? (array) $data['scopes'] : array();

                if (! empty($scopes) && ! in_array('mail.send', $scopes, true)) {
                    return array(
                        'success' => false,
                        'message' => 'The API key is valid but lacks the "Mail Send" permission. Create a key with Mail Send access.',
                        'code'    => $code,
                    );
                }

                return array('success' => true, 'message' => 'SendGrid API key is valid.', 'code' => $code);
            }

            if (401 === $code || 403 === $code) {
                return array('success' => false, 'message' => 'SendGrid rejected the API key (HTTP ' . $code . '). Check the key and try again.', 'code' => $code);
            }

            return array('success' => false, 'message' => self::extract_error($raw, $code), 'code' => $code);
        }

        /**
         * SendGrid errors arrive as { "errors": [ { message, field } ] }.
         *
         * @param string $raw  Response body.
         * @param int    $code HTTP status code.
         * @return string
         */
        protected static function extract_error($raw, $code)
        {
            $data = json_decode($raw, true);

            if (is_array($data) && ! empty($data['errors']) && is_array($data['errors'])) {
                $messages = array();

                foreach ($data['errors'] as $error) {
                    if (! is_array($error)) {
                        continue;
                    }

                    $message = isset($error['message']) ? $error['message'] : 'Unknown error';
                    if (! empty($error['field'])) {
                        $message .= ' (field: ' . $error['field'] . ')';
                    }
                    $messages[] = $message;
                }

                if (! empty($messages)) {
                    return 'SendGrid API error (HTTP ' . $code . '): ' . implode('; ', $messages);
                }
            }

            return 'SendGrid API request failed with HTTP ' . $code . '.';
        }
    }

endif;
