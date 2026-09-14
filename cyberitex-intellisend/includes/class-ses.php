<?php

/**
 * includes\class-ses.php
 * Amazon SES v2 API transport.
 *
 * POST /v2/email/outbound-emails, authenticated with AWS Signature Version 4.
 * A successful send returns HTTP 200 with { "MessageId": "..." }.
 *
 * Unlike the other transports this one needs two credentials: an Access Key ID
 * (the identity, stored in the username column) and a Secret Access Key (the
 * secret, stored encrypted in apiKey). The AWS region comes from the selected
 * endpoint host.
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

if (! class_exists('IntelliSend_SES')) :

    final class IntelliSend_SES extends IntelliSend_Api_Transport
    {
        /**
         * Default API base URL.
         */
        const API_BASE = 'https://email.us-east-1.amazonaws.com';

        /**
         * AWS service name used in the credential scope.
         */
        const SERVICE = 'ses';

        /**
         * SigV4 algorithm identifier.
         */
        const ALGORITHM = 'AWS4-HMAC-SHA256';

        public static function get_label()
        {
            return 'Amazon SES (Web API)';
        }

        public static function get_default_base()
        {
            return self::API_BASE;
        }

        public static function get_send_path()
        {
            return '/v2/email/outbound-emails';
        }

        public static function get_env_constant()
        {
            return 'AWS_SECRET_ACCESS_KEY';
        }

        public static function get_key_label()
        {
            return 'Secret Access Key';
        }

        public static function get_key_placeholder()
        {
            return 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
        }

        public static function get_region_label()
        {
            return 'AWS Region';
        }

        public static function requires_identity()
        {
            return true;
        }

        public static function get_identity_label()
        {
            return 'Access Key ID';
        }

        public static function get_identity_constant()
        {
            return 'AWS_ACCESS_KEY_ID';
        }

        public static function get_identity_placeholder()
        {
            return 'AKIAIOSFODNN7EXAMPLE';
        }

        public static function get_sender_hint()
        {
            return 'Must be an email address or domain you have verified in SES, in the same region.';
        }

        /**
         * The SES regions that support the v2 email API.
         *
         * @return array
         */
        public static function get_regions()
        {
            $regions = array(
                'us-east-1'      => 'US East (N. Virginia)',
                'us-east-2'      => 'US East (Ohio)',
                'us-west-1'      => 'US West (N. California)',
                'us-west-2'      => 'US West (Oregon)',
                'ca-central-1'   => 'Canada (Central)',
                'eu-west-1'      => 'Europe (Ireland)',
                'eu-west-2'      => 'Europe (London)',
                'eu-west-3'      => 'Europe (Paris)',
                'eu-central-1'   => 'Europe (Frankfurt)',
                'eu-north-1'     => 'Europe (Stockholm)',
                'eu-south-1'     => 'Europe (Milan)',
                'ap-south-1'     => 'Asia Pacific (Mumbai)',
                'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                'ap-northeast-2' => 'Asia Pacific (Seoul)',
                'ap-northeast-3' => 'Asia Pacific (Osaka)',
                'ap-southeast-1' => 'Asia Pacific (Singapore)',
                'ap-southeast-2' => 'Asia Pacific (Sydney)',
                'ap-southeast-3' => 'Asia Pacific (Jakarta)',
                'sa-east-1'      => 'South America (Sao Paulo)',
                'me-south-1'     => 'Middle East (Bahrain)',
                'af-south-1'     => 'Africa (Cape Town)',
                'il-central-1'   => 'Israel (Tel Aviv)',
            );

            $options = array();

            foreach ($regions as $region => $label) {
                $options['https://email.' . $region . '.amazonaws.com'] = $label . ' - ' . $region;
            }

            return $options;
        }

        /**
         * Extract the AWS region from the configured endpoint host.
         *
         * @param object $provider Provider row.
         * @return string
         */
        public static function get_region($provider = null)
        {
            $host = parse_url(self::get_api_base($provider), PHP_URL_HOST);

            if (is_string($host) && preg_match('/^email(?:-fips)?\.([a-z0-9-]+)\.amazonaws\.com$/i', $host, $matches)) {
                return strtolower($matches[1]);
            }

            return 'us-east-1';
        }

        /**
         * SigV4 covers the request itself, so signing happens in build_headers().
         */
        protected static function get_auth_headers($api_key)
        {
            return array();
        }

        /**
         * Sign the outgoing request with AWS Signature Version 4.
         *
         * @param object $provider Provider row.
         * @param string $endpoint Full request URL.
         * @param string $body     Serialized request body.
         * @param string $api_key  Secret access key.
         * @return array
         */
        protected static function build_headers($provider, $endpoint, $body, $api_key)
        {
            return self::sign_request(
                'POST',
                $endpoint,
                $body,
                self::get_identity($provider),
                $api_key,
                self::get_region($provider),
                self::SERVICE,
                array('content-type' => 'application/json')
            );
        }

        /**
         * Build the AWS Signature Version 4 headers for a request.
         *
         * Implements the documented algorithm directly, so no AWS SDK is needed:
         * canonical request, string to sign, derived signing key, signature.
         *
         * @param string $method       HTTP method.
         * @param string $url          Full request URL, query string included.
         * @param string $body         Request body ('' for GET).
         * @param string $access_key   AWS Access Key ID.
         * @param string $secret_key   AWS Secret Access Key.
         * @param string $region       AWS region.
         * @param string $service      AWS service name.
         * @param array  $extra        Additional headers to sign, lowercase keys.
         * @param string $amzdate      Optional fixed timestamp (YYYYMMDD'T'HHMMSS'Z'), for tests.
         * @return array Headers to send, including Authorization.
         */
        public static function sign_request($method, $url, $body, $access_key, $secret_key, $region, $service, $extra = array(), $amzdate = '')
        {
            $method = strtoupper($method);
            $host   = (string) parse_url($url, PHP_URL_HOST);
            $path   = (string) parse_url($url, PHP_URL_PATH);
            $query  = (string) parse_url($url, PHP_URL_QUERY);

            if ('' === $path) {
                $path = '/';
            }

            if ('' === $amzdate) {
                $amzdate = gmdate('Ymd\THis\Z');
            }

            $datestamp    = substr($amzdate, 0, 8);
            $payload_hash = hash('sha256', $body);

            // Canonical headers must be sorted by lowercase name.
            $headers = array_merge($extra, array('host' => $host, 'x-amz-date' => $amzdate));
            ksort($headers);

            $canonical_headers = '';
            foreach ($headers as $name => $value) {
                $canonical_headers .= $name . ':' . trim(preg_replace('/\s+/', ' ', $value)) . "\n";
            }

            $signed_headers = implode(';', array_keys($headers));

            $canonical_request = implode("\n", array(
                $method,
                self::canonical_path($path),
                self::canonical_query($query),
                $canonical_headers,
                $signed_headers,
                $payload_hash,
            ));

            $credential_scope = $datestamp . '/' . $region . '/' . $service . '/aws4_request';

            $string_to_sign = implode("\n", array(
                self::ALGORITHM,
                $amzdate,
                $credential_scope,
                hash('sha256', $canonical_request),
            ));

            $signature = hash_hmac('sha256', $string_to_sign, self::signing_key($secret_key, $datestamp, $region, $service));

            $authorization = self::ALGORITHM
                . ' Credential=' . $access_key . '/' . $credential_scope
                . ', SignedHeaders=' . $signed_headers
                . ', Signature=' . $signature;

            $out = array('X-Amz-Date' => $amzdate, 'Authorization' => $authorization);

            // Send back the signed extras with their original casing intent.
            foreach ($extra as $name => $value) {
                if ('content-type' === $name) {
                    $out['Content-Type'] = $value;
                } else {
                    $out[$name] = $value;
                }
            }

            return $out;
        }

        /**
         * Derive the SigV4 signing key.
         *
         * @return string Raw binary key.
         */
        private static function signing_key($secret_key, $datestamp, $region, $service)
        {
            $date    = hash_hmac('sha256', $datestamp, 'AWS4' . $secret_key, true);
            $region  = hash_hmac('sha256', $region, $date, true);
            $service = hash_hmac('sha256', $service, $region, true);

            return hash_hmac('sha256', 'aws4_request', $service, true);
        }

        /**
         * URI-encode each path segment, leaving the separators alone.
         *
         * @param string $path Request path.
         * @return string
         */
        private static function canonical_path($path)
        {
            $segments = explode('/', $path);

            foreach ($segments as $index => $segment) {
                // rawurlencode leaves the unreserved set alone, which is what SigV4 wants.
                $segments[$index] = str_replace('%7E', '~', rawurlencode(rawurldecode($segment)));
            }

            return implode('/', $segments);
        }

        /**
         * Sort and re-encode the query string as SigV4 requires.
         *
         * @param string $query Raw query string.
         * @return string
         */
        private static function canonical_query($query)
        {
            if ('' === $query) {
                return '';
            }

            $pairs = array();

            foreach (explode('&', $query) as $pair) {
                if ('' === $pair) {
                    continue;
                }

                if (strpos($pair, '=') === false) {
                    $pairs[] = array(rawurlencode(rawurldecode($pair)), '');
                    continue;
                }

                list($key, $value) = explode('=', $pair, 2);
                $pairs[] = array(rawurlencode(rawurldecode($key)), rawurlencode(rawurldecode($value)));
            }

            usort($pairs, function ($a, $b) {
                return $a[0] === $b[0] ? strcmp($a[1], $b[1]) : strcmp($a[0], $b[0]);
            });

            $encoded = array();
            foreach ($pairs as $pair) {
                $encoded[] = $pair[0] . '=' . $pair[1];
            }

            return implode('&', $encoded);
        }

        /**
         * Build the SES v2 SendEmail body.
         *
         * Simple content is used for ordinary mail. Attachments go out as a raw
         * MIME message instead, which every SES version accepts.
         *
         * @param object $provider Provider row.
         * @param array  $message  Normalized message.
         * @return array
         */
        protected static function build_payload($provider, $message)
        {
            $emails = function ($addresses) {
                return array_map(
                    function ($address) {
                        return $address['email'];
                    },
                    $addresses
                );
            };

            $destination = array('ToAddresses' => $emails($message['to']));

            if (! empty($message['cc'])) {
                $destination['CcAddresses'] = $emails($message['cc']);
            }

            if (! empty($message['bcc'])) {
                $destination['BccAddresses'] = $emails($message['bcc']);
            }

            $payload = array(
                // SES accepts "Name <email>" here, which preserves the display name.
                'FromEmailAddress' => self::format_address($message['from_email'], $message['from_name']),
                'Destination'      => $destination,
            );

            if (! empty($message['reply_to'])) {
                $payload['ReplyToAddresses'] = array($message['reply_to']);
            }

            if (! empty($message['attachments'])) {
                $payload['Content'] = array(
                    'Raw' => array('Data' => base64_encode(self::build_mime($message))),
                );

                return $payload;
            }

            $body_key = $message['is_html'] ? 'Html' : 'Text';

            $payload['Content'] = array(
                'Simple' => array(
                    'Subject' => array('Data' => $message['subject'], 'Charset' => 'UTF-8'),
                    'Body'    => array(
                        $body_key => array('Data' => $message['body'], 'Charset' => 'UTF-8'),
                    ),
                ),
            );

            return $payload;
        }

        /**
         * Build a raw MIME message for sends that carry attachments.
         *
         * Bcc is deliberately left out of the headers: SES takes the real
         * envelope from Destination, so writing it here would leak the list.
         *
         * @param array $message Normalized message.
         * @return string
         */
        private static function build_mime($message)
        {
            $boundary = 'intellisend-' . md5(uniqid('intellisend', true));

            $to = array_map(
                function ($address) {
                    return self::format_address($address['email'], $address['name']);
                },
                $message['to']
            );

            $lines = array(
                'From: ' . self::format_address($message['from_email'], $message['from_name']),
                'To: ' . implode(', ', $to),
            );

            if (! empty($message['cc'])) {
                $cc = array_map(
                    function ($address) {
                        return self::format_address($address['email'], $address['name']);
                    },
                    $message['cc']
                );
                $lines[] = 'Cc: ' . implode(', ', $cc);
            }

            if (! empty($message['reply_to'])) {
                $lines[] = 'Reply-To: ' . $message['reply_to'];
            }

            // A subject is unstructured text; address-phrase quoting changes it.
            $subject = $message['subject'];
            $lines[] = 'Subject: ' . (preg_match('/^[\x20-\x7E]*$/', $subject)
                ? $subject
                : '=?UTF-8?B?' . base64_encode($subject) . '?=');
            $lines[] = 'MIME-Version: 1.0';
            $lines[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            $lines[] = '';

            // Body part
            $lines[] = '--' . $boundary;
            $lines[] = 'Content-Type: ' . $message['content_type'] . '; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: base64';
            $lines[] = '';
            $lines[] = trim(chunk_split(base64_encode($message['body']), 76, "\r\n"));

            foreach ($message['attachments'] as $attachment) {
                $lines[] = '--' . $boundary;
                $lines[] = 'Content-Type: ' . $attachment['type'] . '; name="' . $attachment['filename'] . '"';
                $lines[] = 'Content-Disposition: attachment; filename="' . $attachment['filename'] . '"';
                $lines[] = 'Content-Transfer-Encoding: base64';
                $lines[] = '';
                // build_attachments() already base64 encoded the file.
                $lines[] = trim(chunk_split($attachment['content'], 76, "\r\n"));
            }

            $lines[] = '--' . $boundary . '--';
            $lines[] = '';

            return implode("\r\n", $lines);
        }

        /**
         * Render "Name <email>", or the bare address when there is no name.
         *
         * @param string $email Address.
         * @param string $name  Display name.
         * @return string
         */
        private static function format_address($email, $name)
        {
            if (empty($name)) {
                return $email;
            }

            return self::encode_header($name) . ' <' . $email . '>';
        }

        /**
         * RFC 2047 encode a header value when it is not plain ASCII.
         *
         * @param string $value Header value.
         * @return string
         */
        private static function encode_header($value)
        {
            if (preg_match('/^[\x20-\x7E]*$/', $value)) {
                // Quote anything that would otherwise break the header grammar.
                return preg_match('/[,;:<>@"]/', $value) ? '"' . str_replace('"', '\\"', $value) . '"' : $value;
            }

            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        /**
         * SES returns the id in the response body.
         *
         * @param string $raw Response body.
         * @return string
         */
        protected static function get_message_id_from_body($raw)
        {
            $data = json_decode($raw, true);

            return is_array($data) && ! empty($data['MessageId']) ? (string) $data['MessageId'] : '';
        }

        /**
         * Verify the credentials with a signed GetAccount call.
         *
         * @param string $api_key  Secret access key. Falls back to the configured one when empty.
         * @param object $provider Optional provider row.
         * @return array
         */
        public static function validate_api_key($api_key = '', $provider = null)
        {
            if (empty($api_key)) {
                $api_key = self::get_api_key($provider);
            }

            $access_key = self::get_identity($provider);

            if (empty($api_key)) {
                return array('success' => false, 'message' => 'No AWS Secret Access Key provided.', 'code' => 0);
            }

            if (empty($access_key)) {
                return array('success' => false, 'message' => 'No AWS Access Key ID provided. Save one, or define AWS_ACCESS_KEY_ID in wp-config.php.', 'code' => 0);
            }

            $region = self::get_region($provider);
            $url = self::get_api_base($provider) . '/v2/email/account';

            $response = wp_remote_get(
                $url,
                array(
                    'timeout' => 15,
                    'headers' => self::sign_request('GET', $url, '', $access_key, $api_key, $region, self::SERVICE),
                )
            );

            if (is_wp_error($response)) {
                return array('success' => false, 'message' => 'Connection error: ' . $response->get_error_message(), 'code' => 0);
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $raw  = wp_remote_retrieve_body($response);

            if (200 === $code) {
                $data = json_decode($raw, true);
                $detail = '';

                if (is_array($data)) {
                    if (isset($data['ProductionAccessEnabled']) && ! $data['ProductionAccessEnabled']) {
                        $detail = ' This account is still in the SES sandbox, so it can only send to verified addresses.';
                    }

                    if (isset($data['SendingEnabled']) && ! $data['SendingEnabled']) {
                        $detail .= ' Sending is currently disabled for this account.';
                    }
                }

                return array('success' => true, 'message' => 'Amazon SES credentials are valid for ' . $region . '.' . $detail, 'code' => $code);
            }

            if (401 === $code || 403 === $code) {
                return array(
                    'success' => false,
                    'message' => 'AWS rejected the credentials (HTTP ' . $code . '). Check the Access Key ID, Secret Access Key, region, and that the IAM policy allows ses:SendEmail and ses:GetAccount. ' . self::extract_error($raw, $code),
                    'code'    => $code,
                );
            }

            return array('success' => false, 'message' => self::extract_error($raw, $code), 'code' => $code);
        }

        /**
         * SES errors arrive as { "message": "..." } or { "Message": "..." },
         * sometimes with an __type discriminator.
         *
         * @param string $raw  Response body.
         * @param int    $code HTTP status code.
         * @return string
         */
        protected static function extract_error($raw, $code)
        {
            $data = json_decode($raw, true);

            if (is_array($data)) {
                $message = '';

                foreach (array('message', 'Message', 'errorMessage') as $key) {
                    if (! empty($data[$key]) && is_string($data[$key])) {
                        $message = $data[$key];
                        break;
                    }
                }

                if ('' !== $message) {
                    if (! empty($data['__type']) && is_string($data['__type'])) {
                        // The type looks like "com.amazonaws...#MessageRejected".
                        $parts = explode('#', $data['__type']);
                        $message .= ' (' . end($parts) . ')';
                    }

                    return 'Amazon SES API error (HTTP ' . $code . '): ' . $message;
                }
            }

            return 'Amazon SES API request failed with HTTP ' . $code . '.';
        }
    }

endif;
