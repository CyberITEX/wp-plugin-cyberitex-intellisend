<?php

/**
 * includes\class-api-transport.php
 * Shared base for IntelliSend HTTP API mail transports.
 *
 * Providers with type = 'api' deliver over HTTPS instead of SMTP, so hosts that
 * block outbound SMTP ports (25/465/587) can still send mail. Everything here
 * uses the WordPress HTTP API, so no Composer dependency is required.
 *
 * A subclass describes one vendor: its endpoint, auth header, request body and
 * error format. All message normalization (header parsing, address dedup,
 * attachment encoding) lives in this class so the vendors stay small.
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

if (! class_exists('IntelliSend_Api_Transport')) :

    abstract class IntelliSend_Api_Transport
    {
        // ===========================================
        // REGISTRY
        // ===========================================

        /**
         * Map of provider name => transport class.
         *
         * The keys must match the provider rows seeded by
         * IntelliSend_Database::get_default_providers().
         *
         * @return array
         */
        public static function registry()
        {
            return array(
                'sendgrid-api' => 'IntelliSend_SendGrid',
                'brevo-api'    => 'IntelliSend_Brevo',
                'ses-api'      => 'IntelliSend_SES',
            );
        }

        /**
         * Resolve the transport class for a provider.
         *
         * @param object|string $provider Provider row or provider name.
         * @return string|null Class name, or null when the provider has no transport.
         */
        public static function for_provider($provider)
        {
            $name = is_object($provider) ? $provider->name : (string) $provider;
            $registry = self::registry();

            if (! isset($registry[$name])) {
                return null;
            }

            $class = $registry[$name];

            return class_exists($class) ? $class : null;
        }

        /**
         * Describe every registered transport, for the admin UI.
         *
         * @return array Keyed by provider name.
         */
        public static function describe_all()
        {
            $described = array();

            foreach (self::registry() as $name => $class) {
                if (! class_exists($class)) {
                    continue;
                }

                $described[$name] = array(
                    'label'              => $class::get_label(),
                    'regions'            => $class::get_regions(),
                    'regionLabel'        => $class::get_region_label(),
                    'envConstant'        => $class::get_env_constant(),
                    'keySource'          => $class::get_key_source(),
                    'keyLabel'           => $class::get_key_label(),
                    'keyPlaceholder'     => $class::get_key_placeholder(),
                    'senderHint'         => $class::get_sender_hint(),
                    'defaultBase'        => $class::get_default_base(),
                    'requiresIdentity'   => $class::requires_identity(),
                    'identityLabel'      => $class::get_identity_label(),
                    'identityConstant'   => $class::get_identity_constant(),
                    'identityPlaceholder' => $class::get_identity_placeholder(),
                    'identitySource'     => $class::get_identity_source(),
                );
            }

            return $described;
        }

        // ===========================================
        // SUBCLASS CONTRACT
        // ===========================================

        /** Human readable name shown in the admin UI. */
        abstract public static function get_label();

        /** Default API base URL, no trailing slash and no path. */
        abstract public static function get_default_base();

        /** Path appended to the base URL to send a message. */
        abstract public static function get_send_path();

        /** Name of the constant / environment variable holding the API key. */
        abstract public static function get_env_constant();

        /** Selectable API base URLs as value => label (regions or data residency). */
        abstract public static function get_regions();

        /** Placeholder shown in the API key field. */
        abstract public static function get_key_placeholder();

        /** One line telling the admin what the sender address must satisfy. */
        abstract public static function get_sender_hint();

        /**
         * Build the vendor's JSON request body.
         *
         * @param object $provider Provider row.
         * @param array  $message  Normalized message (see normalize_message()).
         * @return array
         */
        abstract protected static function build_payload($provider, $message);

        /**
         * Authentication headers for an API request.
         *
         * @param string $api_key Decrypted API key.
         * @return array
         */
        abstract protected static function get_auth_headers($api_key);

        /**
         * Turn an error response body into a single readable message.
         *
         * @param string $raw  Response body.
         * @param int    $code HTTP status code.
         * @return string
         */
        abstract protected static function extract_error($raw, $code);

        /**
         * Verify an API key against the vendor.
         *
         * @param string $api_key  Key to test. Falls back to the configured key when empty.
         * @param object $provider Optional provider row, for the region endpoint and stored key.
         * @return array { @type bool $success, @type string $message, @type int $code }
         */
        abstract public static function validate_api_key($api_key = '', $provider = null);

        // ===========================================
        // OVERRIDABLE DEFAULTS
        // ===========================================

        /**
         * HTTP status codes that mean the vendor accepted the message.
         *
         * @param int $code HTTP status code.
         * @return bool
         */
        protected static function is_success_code($code)
        {
            return 200 === $code || 201 === $code || 202 === $code;
        }

        /**
         * Response header carrying the vendor's message id, if any.
         *
         * @return string Empty string when the vendor does not send one.
         */
        protected static function get_message_id_header()
        {
            return '';
        }

        /**
         * Label for the secret field. Most vendors call it an API key.
         *
         * @return string
         */
        public static function get_key_label()
        {
            return 'API Key';
        }

        /**
         * Label for the endpoint selector.
         *
         * @return string
         */
        public static function get_region_label()
        {
            return 'Data Residency';
        }

        /**
         * Whether the vendor needs a second, non-secret credential alongside
         * the key (an AWS Access Key ID, for instance).
         *
         * @return bool
         */
        public static function requires_identity()
        {
            return false;
        }

        /** Label for the identity field. */
        public static function get_identity_label()
        {
            return '';
        }

        /** Constant / environment variable holding the identity. */
        public static function get_identity_constant()
        {
            return '';
        }

        /** Placeholder for the identity field. */
        public static function get_identity_placeholder()
        {
            return '';
        }

        /**
         * Where the identity in use came from, for display in the admin UI.
         *
         * @param object $provider Provider row.
         * @return string 'constant', 'environment', 'database', or ''.
         */
        public static function get_identity_source($provider = null)
        {
            $name = static::get_identity_constant();

            if ('' !== $name) {
                if (defined($name) && '' !== trim((string) constant($name))) {
                    return 'constant';
                }

                $env = getenv($name);
                if (is_string($env) && '' !== trim($env)) {
                    return 'environment';
                }
            }

            // The identity is not a secret, so it lives in the username column.
            if ($provider && ! empty($provider->username)) {
                return 'database';
            }

            return '';
        }

        /**
         * Resolve the identity for a provider: constant, then environment
         * variable, then the stored username column.
         *
         * @param object $provider Provider row.
         * @return string
         */
        public static function get_identity($provider = null)
        {
            $name = static::get_identity_constant();

            if ('' !== $name) {
                if (defined($name) && '' !== trim((string) constant($name))) {
                    return trim((string) constant($name));
                }

                $env = getenv($name);
                if (is_string($env) && '' !== trim($env)) {
                    return trim($env);
                }
            }

            return $provider && ! empty($provider->username) ? $provider->username : '';
        }

        /**
         * Compose the request headers.
         *
         * The default is a JSON content type plus the vendor's auth header.
         * Vendors whose signature covers the request itself (AWS SigV4) override
         * this, since they need the endpoint and body to sign.
         *
         * @param object $provider Provider row.
         * @param string $endpoint Full request URL.
         * @param string $body     Serialized request body.
         * @param string $api_key  Decrypted API key.
         * @return array
         */
        protected static function build_headers($provider, $endpoint, $body, $api_key)
        {
            return array_merge(
                array('Content-Type' => 'application/json'),
                static::get_auth_headers($api_key)
            );
        }

        /**
         * Pull a message id out of a successful response body.
         *
         * @param string $raw Response body.
         * @return string
         */
        protected static function get_message_id_from_body($raw)
        {
            return '';
        }

        // ===========================================
        // ENDPOINT AND KEY RESOLUTION
        // ===========================================

        /**
         * Resolve the API base URL (no path) for a provider record.
         *
         * @param object $provider Provider row.
         * @return string
         */
        public static function get_api_base($provider = null)
        {
            $base = static::get_default_base();

            if ($provider && ! empty($provider->apiEndpoint)) {
                $base = untrailingslashit($provider->apiEndpoint);

                // Tolerate a stored full send URL as well as a bare base.
                $send_path = static::get_send_path();
                if ('' !== $send_path && substr($base, -strlen($send_path)) === $send_path) {
                    $base = untrailingslashit(substr($base, 0, -strlen($send_path)));
                }
            }

            return '' !== $base ? $base : static::get_default_base();
        }

        /**
         * Resolve the full send endpoint for a provider record.
         *
         * @param object $provider Provider row.
         * @return string
         */
        public static function get_endpoint($provider = null)
        {
            return static::get_api_base($provider) . static::get_send_path();
        }

        /**
         * Read an API key supplied outside the database.
         *
         * Checks the vendor's constant (define it in wp-config.php) first, then
         * the same name as an environment variable. A key supplied this way
         * takes precedence over the one stored in the provider record, so
         * deployments can keep the secret out of the database entirely.
         *
         * @return string Empty string when no key is defined.
         */
        public static function get_environment_key()
        {
            $name = static::get_env_constant();

            if ('' === $name) {
                return '';
            }

            if (defined($name)) {
                $value = trim((string) constant($name));
                if ('' !== $value) {
                    return $value;
                }
            }

            $env = getenv($name);
            if (is_string($env) && '' !== trim($env)) {
                return trim($env);
            }

            return '';
        }

        /**
         * Where the key in use came from, for display in the admin UI.
         *
         * @param object $provider Provider row.
         * @return string 'constant', 'environment', 'database', or '' when no key is available.
         */
        public static function get_key_source($provider = null)
        {
            $name = static::get_env_constant();

            if ('' !== $name) {
                if (defined($name) && '' !== trim((string) constant($name))) {
                    return 'constant';
                }

                $env = getenv($name);
                if (is_string($env) && '' !== trim($env)) {
                    return 'environment';
                }
            }

            if ($provider && ! empty($provider->apiKey)) {
                return 'database';
            }

            return '';
        }

        /**
         * Get the decrypted API key for a provider.
         *
         * @param object $provider Provider row.
         * @return string
         */
        protected static function get_api_key($provider)
        {
            // A key defined in wp-config.php or the environment always wins.
            $environment_key = static::get_environment_key();
            if ('' !== $environment_key) {
                return $environment_key;
            }

            if (! $provider) {
                return '';
            }

            // Already decrypted by the caller.
            if (! empty($provider->apiKeyPlain)) {
                return $provider->apiKeyPlain;
            }

            if (! empty($provider->apiKey)) {
                return IntelliSend_Database::decrypt_data($provider->apiKey);
            }

            // Fall back to the password column for records saved as SMTP first.
            if (! empty($provider->password)) {
                return IntelliSend_Database::decrypt_data($provider->password);
            }

            return '';
        }

        // ===========================================
        // SENDING
        // ===========================================

        /**
         * Send an email through the vendor's API.
         *
         * @param object $provider  Provider row (type = 'api').
         * @param array  $mail_args {
         *     @type string|array $to          Recipient(s).
         *     @type string       $subject     Subject line.
         *     @type string       $message     Body.
         *     @type string|array $headers     Optional wp_mail style headers.
         *     @type array        $attachments Optional file paths.
         *     @type array        $cc          Optional extra CC addresses.
         *     @type array        $bcc         Optional extra BCC addresses.
         * }
         * @return array {
         *     @type bool   $success Whether the vendor accepted the message.
         *     @type string $message Human readable result.
         *     @type int    $code    HTTP status code (0 on transport error).
         *     @type string $log     Multi-line log detail for the reports table.
         * }
         */
        public static function send($provider, $mail_args)
        {
            $api_key = static::get_api_key($provider);

            if (empty($api_key)) {
                return self::result(
                    false,
                    sprintf(
                        '%s not configured for %s. Save one on the SMTP Providers page, or define %s in wp-config.php.',
                        static::get_key_label(),
                        static::get_label(),
                        static::get_env_constant()
                    ),
                    0,
                    ''
                );
            }

            if (static::requires_identity() && '' === static::get_identity($provider)) {
                return self::result(
                    false,
                    sprintf(
                        '%s not configured for %s. Save one on the SMTP Providers page, or define %s in wp-config.php.',
                        static::get_identity_label(),
                        static::get_label(),
                        static::get_identity_constant()
                    ),
                    0,
                    ''
                );
            }

            $message = static::normalize_message($provider, $mail_args);

            if (is_string($message)) {
                // normalize_message() returns an error string when it cannot proceed.
                return self::result(false, $message, 0, '');
            }

            $endpoint = static::get_endpoint($provider);
            $log = static::build_log($endpoint, $message);
            $body = wp_json_encode(static::build_payload($provider, $message));

            if (false === $body) {
                return self::result(false, 'Unable to encode the email as UTF-8 JSON.', 0, $log);
            }

            $response = wp_remote_post(
                $endpoint,
                array(
                    'method'  => 'POST',
                    'timeout' => 30,
                    'headers' => static::build_headers($provider, $endpoint, $body, $api_key),
                    'body'    => $body,
                )
            );

            if (is_wp_error($response)) {
                return self::result(
                    false,
                    sprintf('%s connection error: %s', static::get_label(), $response->get_error_message()),
                    0,
                    $log . "\nError: " . $response->get_error_message()
                );
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $raw  = wp_remote_retrieve_body($response);

            if (static::is_success_code($code)) {
                $message_id = static::resolve_message_id($response, $raw);
                if ('' !== $message_id) {
                    $log .= "\nMessage-ID: " . $message_id;
                }

                return self::result(true, sprintf('Message accepted by %s.', static::get_label()), $code, $log . "\nHTTP Status: " . $code);
            }

            $error = static::extract_error($raw, $code);

            return self::result(false, $error, $code, $log . "\nHTTP Status: " . $code . "\nError: " . $error);
        }

        /**
         * Turn wp_mail arguments into the normalized structure subclasses build from.
         *
         * @param object $provider  Provider row.
         * @param array  $mail_args wp_mail arguments.
         * @return array|string Normalized message, or an error string.
         */
        protected static function normalize_message($provider, $mail_args)
        {
            $parsed = static::parse_headers(isset($mail_args['headers']) ? $mail_args['headers'] : array());
            $to     = static::normalize_addresses(isset($mail_args['to']) ? $mail_args['to'] : array());
            $cc     = array_merge($parsed['cc'], static::normalize_addresses(isset($mail_args['cc']) ? $mail_args['cc'] : array()));
            $bcc    = array_merge($parsed['bcc'], static::normalize_addresses(isset($mail_args['bcc']) ? $mail_args['bcc'] : array()));

            if (empty($to)) {
                return 'No valid recipient address for the API send.';
            }

            // Sender: provider record wins, then a From: header, then the site admin.
            $from_email = $parsed['from_email'];
            if (empty($from_email)) {
                $from_email = get_option('admin_email');
            }

            $from_email = apply_filters('wp_mail_from', $from_email);
            if (!empty($provider->sender)) {
                $from_email = $provider->sender;
            }
            if (! is_email($from_email)) {
                return 'Invalid sender address for the API send: ' . $from_email;
            }

            $cc  = static::dedupe_against($cc, $to);
            $bcc = static::dedupe_against($bcc, array_merge($to, $cc));

            $subject = isset($mail_args['subject']) ? (string) $mail_args['subject'] : '';
            $body    = isset($mail_args['message']) ? (string) $mail_args['message'] : '';
            $from_name = apply_filters('wp_mail_from_name', !empty($parsed['from_name']) ? $parsed['from_name'] : get_bloginfo('name'));
            $content_type = apply_filters('wp_mail_content_type', !empty($parsed['content_type']) ? $parsed['content_type'] : 'text/plain');
            $charset = apply_filters('wp_mail_charset', !empty($parsed['charset']) ? $parsed['charset'] : (get_bloginfo('charset') ?: 'UTF-8'));

            $message = array(
                'from_email'   => $from_email,
                'from_name'    => $from_name,
                'reply_to'     => $parsed['reply_to'],
                'to'           => $to,
                'cc'           => $cc,
                'bcc'          => $bcc,
                'subject'      => '' !== $subject ? $subject : '(no subject)',
                'body'         => '' !== $body ? $body : ' ',
                'content_type' => $content_type,
                'is_html'      => 'text/html' === $content_type,
            );

            // Vendor JSON APIs require UTF-8, including names and subject text.
            foreach (array('subject', 'body', 'from_name') as $field) {
                $message[$field] = static::convert_text_to_utf8($message[$field], $charset);
                if (false === $message[$field]) {
                    return 'Unable to convert the configured email character set to UTF-8.';
                }
            }
            foreach (array('to', 'cc', 'bcc') as $field) {
                foreach ($message[$field] as $index => $address) {
                    $message[$field][$index]['name'] = static::convert_text_to_utf8($address['name'], $charset);
                    if (false === $message[$field][$index]['name']) {
                        return 'Unable to convert the configured email character set to UTF-8.';
                    }
                }
            }
            $message['attachments'] = static::build_attachments(isset($mail_args['attachments']) ? $mail_args['attachments'] : array());
            return $message;
        }

        /**
         * Convert text without requiring an additional extension for UTF-8 sites.
         *
         * @return mixed|false False when a non-UTF-8 value cannot be converted.
         */
        protected static function convert_text_to_utf8($value, $charset)
        {
            if ('UTF8' === strtoupper(str_replace(array('-', '_'), '', (string) $charset))) {
                return $value;
            }
            if (!is_string($value) || '' === $value) {
                return $value;
            }
            try {
                if (function_exists('iconv')) {
                    return @iconv($charset, 'UTF-8', $value);
                }
                if (function_exists('mb_convert_encoding')) {
                    return mb_convert_encoding($value, 'UTF-8', $charset);
                }
            } catch (Throwable $error) {
                return false;
            }
            return false;
        }

        /**
         * Read the vendor's message id from the response header or body.
         *
         * @param array|WP_Error $response wp_remote_post response.
         * @param string         $raw      Response body.
         * @return string
         */
        protected static function resolve_message_id($response, $raw)
        {
            $header = static::get_message_id_header();

            if ('' !== $header) {
                $value = wp_remote_retrieve_header($response, $header);

                if (is_array($value)) {
                    $value = reset($value);
                }

                if (! empty($value)) {
                    return (string) $value;
                }
            }

            return (string) static::get_message_id_from_body($raw);
        }

        // ===========================================
        // SHARED HELPERS
        // ===========================================

        /**
         * Build a uniform result array.
         */
        protected static function result($success, $message, $code, $log)
        {
            return array(
                'success' => (bool) $success,
                'message' => $message,
                'code'    => (int) $code,
                'log'     => $log,
            );
        }

        /**
         * Parse wp_mail style headers into the pieces an API send needs.
         *
         * @param string|array $headers Raw headers.
         * @return array
         */
        protected static function parse_headers($headers)
        {
            $parsed = array(
                'from_email'   => '',
                'from_name'    => '',
                'reply_to'     => '',
                'cc'           => array(),
                'bcc'          => array(),
                'content_type' => '',
                'charset'      => '',
            );

            if (empty($headers)) {
                return $parsed;
            }

            if (! is_array($headers)) {
                $headers = explode("\n", str_replace("\r\n", "\n", $headers));
            }

            foreach ($headers as $key => $header) {
                // Support the associative form: array( 'Content-Type' => 'text/html' ).
                if (! is_numeric($key)) {
                    $name    = $key;
                    $content = $header;
                } else {
                    if (! is_string($header) || strpos($header, ':') === false) {
                        continue;
                    }
                    list($name, $content) = explode(':', trim($header), 2);
                }

                $name    = strtolower(trim($name));
                $content = trim($content);

                switch ($name) {
                    case 'from':
                        $address = static::split_address($content);
                        if (is_email($address['email'])) {
                            $parsed['from_email'] = $address['email'];
                            $parsed['from_name']  = $address['name'];
                        }
                        break;

                    case 'reply-to':
                        $address = static::split_address($content);
                        if (is_email($address['email'])) {
                            $parsed['reply_to'] = $address['email'];
                        }
                        break;

                    case 'cc':
                        $parsed['cc'] = array_merge($parsed['cc'], static::normalize_addresses($content));
                        break;

                    case 'bcc':
                        $parsed['bcc'] = array_merge($parsed['bcc'], static::normalize_addresses($content));
                        break;

                    case 'content-type':
                        if (preg_match('/;\s*charset\s*=\s*["\']?([^;"\'\s]+)/i', $content, $charset_match)) {
                            $parsed['charset'] = $charset_match[1];
                        }
                        if (strpos($content, ';') !== false) {
                            list($type) = explode(';', $content, 2);
                        } else {
                            $type = $content;
                        }
                        $type = strtolower(trim($type));
                        if (in_array($type, array('text/plain', 'text/html'), true)) {
                            $parsed['content_type'] = $type;
                        }
                        break;
                }
            }

            return $parsed;
        }

        /**
         * Split "Name <email@example.com>" into its parts.
         *
         * @param string $value Raw address.
         * @return array { @type string $email, @type string $name }
         */
        protected static function split_address($value)
        {
            $value = trim((string) $value);

            if (preg_match('/^(.*?)<([^>]+)>$/', $value, $matches)) {
                return array(
                    'email' => trim($matches[2]),
                    'name'  => trim($matches[1], " \t\"'"),
                );
            }

            return array('email' => $value, 'name' => '');
        }

        /**
         * Normalize a string or array of addresses into a list of unique
         * array( 'email' => .., 'name' => .. ) entries.
         *
         * @param string|array $addresses Addresses.
         * @return array
         */
        protected static function normalize_addresses($addresses)
        {
            if (empty($addresses)) {
                return array();
            }

            if (! is_array($addresses)) {
                $addresses = explode(',', $addresses);
            }

            $normalized = array();
            $seen       = array();

            foreach ($addresses as $address) {
                if (! is_string($address)) {
                    continue;
                }

                $parts = static::split_address($address);

                if (! is_email($parts['email'])) {
                    continue;
                }

                $key = strtolower($parts['email']);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key]   = true;
                $normalized[] = $parts;
            }

            return $normalized;
        }

        /**
         * Remove addresses that already appear in another list.
         *
         * @param array $addresses Candidate addresses.
         * @param array $existing  Addresses already used.
         * @return array
         */
        protected static function dedupe_against($addresses, $existing)
        {
            if (empty($addresses)) {
                return array();
            }

            $used = array();
            foreach ($existing as $address) {
                $used[strtolower($address['email'])] = true;
            }

            $filtered = array();
            foreach ($addresses as $address) {
                $key = strtolower($address['email']);
                if (isset($used[$key])) {
                    continue;
                }
                $used[$key] = true;
                $filtered[] = $address;
            }

            return $filtered;
        }

        /**
         * Convert normalized addresses into vendor address objects.
         *
         * @param array  $addresses  Normalized addresses.
         * @param string $email_key  Key holding the address.
         * @param string $name_key   Key holding the display name.
         * @return array
         */
        protected static function to_address_objects($addresses, $email_key = 'email', $name_key = 'name')
        {
            $out = array();

            foreach ($addresses as $address) {
                $entry = array($email_key => $address['email']);
                if (! empty($address['name'])) {
                    $entry[$name_key] = $address['name'];
                }
                $out[] = $entry;
            }

            return $out;
        }

        /**
         * Read and base64 encode wp_mail attachments.
         *
         * @param array $attachments wp_mail attachment paths.
         * @return array Each entry: filename, type, content (base64).
         */
        protected static function build_attachments($attachments)
        {
            if (empty($attachments)) {
                return array();
            }

            if (! is_array($attachments)) {
                $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
            }

            $built = array();

            foreach ($attachments as $name => $path) {
                $path = trim((string) $path);

                if ('' === $path || is_dir($path) || ! is_readable($path)) {
                    continue;
                }

                $contents = file_get_contents($path);
                if (false === $contents) {
                    continue;
                }

                $filename = is_string($name) && '' !== $name ? $name : basename($path);
                $filetype = wp_check_filetype($filename);

                $built[] = array(
                    'filename' => $filename,
                    'type'     => ! empty($filetype['type']) ? $filetype['type'] : 'application/octet-stream',
                    'content'  => base64_encode($contents),
                );
            }

            return $built;
        }

        /**
         * Build the log detail stored on the report row.
         *
         * @param string $endpoint Send endpoint.
         * @param array  $message  Normalized message.
         * @return string
         */
        protected static function build_log($endpoint, $message)
        {
            $flatten = function ($addresses) {
                return implode(', ', array_map(
                    function ($address) {
                        return $address['email'];
                    },
                    $addresses
                ));
            };

            $lines = array(
                'Transport: ' . static::get_label(),
                'Endpoint: ' . $endpoint,
                'From: ' . $message['from_email'],
                'To: ' . $flatten($message['to']),
            );

            if (! empty($message['cc'])) {
                $lines[] = 'CC: ' . $flatten($message['cc']);
            }

            if (! empty($message['bcc'])) {
                $lines[] = 'BCC: ' . $flatten($message['bcc']);
            }

            if (! empty($message['attachments'])) {
                $lines[] = 'Attachments: ' . count($message['attachments']);
            }

            return implode("\n", $lines);
        }
    }

endif;
