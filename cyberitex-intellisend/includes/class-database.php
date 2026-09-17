<?php

/**
 * includes\class-database.php
 * IntelliSend Database Operations
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if (! defined('ABSPATH')) {
    exit;
}

// Make sure WordPress functions are available
if (! function_exists('get_option')) {
    require_once ABSPATH . 'wp-includes/option.php';
}

if (! function_exists('absint')) {
    /**
     * Convert a value to non-negative integer.
     *
     * @param mixed $maybeint Data to convert to a non-negative integer.
     * @return int A non-negative integer.
     */
    function absint($maybeint)
    {
        return abs(intval($maybeint));
    }
}

/**
 * IntelliSend Database Class
 * 
 * Handles all database operations for the IntelliSend plugin.
 */
class IntelliSend_Database
{

    /**
     * Schema version. Bump this whenever the table structure changes so
     * maybe_upgrade() runs the migration on installs updated in place.
     */
    const DB_VERSION = '1.2.0';

    /**
     * Transport type constants for the providers table.
     */
    const TYPE_SMTP = 'smtp';
    const TYPE_API  = 'api';

    /**
     * Encrypt sensitive data
     * 
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    private static function encrypt_data($data)
    {
        if (empty($data)) {
            return '';
        }

        // Use WordPress salt for additional security
        $salt = defined('AUTH_SALT') ? AUTH_SALT : 'intellisend_default_salt';

        // Generate a random initialization vector
        $iv_size = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($iv_size);

        // Encrypt the data
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $salt,
            0,
            $iv
        );

        // Combine the IV and encrypted data
        $encrypted_data = base64_encode($iv . $encrypted);

        return $encrypted_data;
    }

    /**
     * Decrypt sensitive data
     * 
     * @param string $encrypted_data Encrypted data
     * @return string Decrypted data
     */
    public static function decrypt_data($encrypted_data)
    {
        if (empty($encrypted_data)) {
            return '';
        }

        try {
            // Use WordPress salt for additional security
            $salt = defined('AUTH_SALT') ? AUTH_SALT : 'intellisend_default_salt';

            // Decode the combined data
            $combined = base64_decode($encrypted_data);

            // Extract the IV and encrypted data
            $iv_size = openssl_cipher_iv_length('AES-256-CBC');
            $iv = substr($combined, 0, $iv_size);
            $encrypted = substr($combined, $iv_size);

            // Decrypt the data
            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $salt,
                0,
                $iv
            );

            return $decrypted;
        } catch (Exception $e) {
            // Return empty string on error
            return '';
        }
    }



    /**
     * Create routing table with updated structure
     */
    public static function create_routing_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $routing_table = $wpdb->prefix . 'intellisend_routing';
        $sql = "CREATE TABLE $routing_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        pattern_type enum('wildcard','starts_with','contains','ends_with','regex') DEFAULT 'wildcard',
        subject_patterns text NOT NULL,
        default_provider_name varchar(100) NOT NULL,
        recipients text,
        anti_spam_enabled tinyint(1) DEFAULT 0,
        enabled tinyint(1) DEFAULT 1,
        priority int(11) DEFAULT 100,
        is_default tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY name (name),
        KEY enabled (enabled),
        KEY priority (priority),
        KEY is_default (is_default)
    ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        dbDelta($sql);

        // Insert default routing rule if the table is empty
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $routing_table");
        if ($count == 0) {
            $admin_email = get_option('admin_email');
            $wpdb->insert(
                $routing_table,
                array(
                    'name' => 'Default',
                    'pattern_type' => 'wildcard',
                    'subject_patterns' => '*',
                    'default_provider_name' => 'other',
                    'recipients' => $admin_email,
                    'anti_spam_enabled' => 0,
                    'enabled' => 1,
                    'priority' => -1,
                    'is_default' => 1,
                )
            );
        }
    }


    /**
     * Create all required database tables on plugin activation.
     */
    public static function create_tables()
    {
        global $wpdb;

        try {
            $charset_collate = $wpdb->get_charset_collate();

            // Make sure dbDelta function is available
            if (! function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }

            // Create providers table
            $providers_table = $wpdb->prefix . 'intellisend_providers';
            $sql = "CREATE TABLE $providers_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                type varchar(20) NOT NULL DEFAULT 'smtp',
                description text,
                helpLink varchar(255),
                server varchar(255) NOT NULL,
                port int(11) NOT NULL,
                encryption varchar(20) NOT NULL,
                authRequired tinyint(1) DEFAULT 1,
                sender varchar(255),
                username varchar(255),
                password varchar(255),
                apiKey varchar(500),
                apiEndpoint varchar(255),
                configured tinyint(1) DEFAULT 0,
                PRIMARY KEY  (id),
                KEY name (name),
                KEY type (type)
            ) $charset_collate;";
            dbDelta($sql);

            // Create settings table
            $settings_table = $wpdb->prefix . 'intellisend_settings';
            $sql = "CREATE TABLE $settings_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                defaultProviderName varchar(100),
                antiSpamEndPoint varchar(255),
                antiSpamApiKey varchar(255),
                antiSpamSubjectPatterns text,
                testRecipient varchar(255),
                spamTestMessage text,
                logsRetentionDays int(11) DEFAULT 365,
                debug_enabled tinyint(1) DEFAULT 0,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            dbDelta($sql);

            // Insert default settings if settings table is empty
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $settings_table");
            if ($count == 0) {
                $admin_email = function_exists('get_option') ? get_option('admin_email') : '';

                $wpdb->insert(
                    $settings_table,
                    array(
                        'defaultProviderName' => 'other',
                        'antiSpamEndPoint' => 'https://api.cyberitex.com/v1/tools/spamCheck',
                        'antiSpamApiKey' => '',
                        'testRecipient' => $admin_email,
                        'spamTestMessage' => 'CONGRATULATIONS! You have been selected to receive a FREE $500 Gift Card! Click here to claim: http://claim-your-prize-now.example.com Limited time offer! Reply now or call +1-555-123-4567. This is a one-time message, to unsubscribe reply STOP.',
                        'logsRetentionDays' => 365,
                        'debug_enabled' => 0,
                    )
                );
            }

            // Create routing table
            self::create_routing_table();

            // Create reports table
            $reports_table = $wpdb->prefix . 'intellisend_reports';
            $sql = "CREATE TABLE $reports_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                date datetime NOT NULL,
                subject varchar(255) NOT NULL,
                sender varchar(255) NOT NULL,
                recipients text NOT NULL,
                message longtext NOT NULL,
                status varchar(50) NOT NULL,
                log longtext,
                antiSpamEnabled tinyint(1) DEFAULT 1,
                isSpam tinyint(1) DEFAULT 0,
                routingRuleId bigint(20),
                providerName varchar(100),
                PRIMARY KEY  (id),
                KEY date (date),
                KEY status (status),
                KEY isSpam (isSpam)
            ) $charset_collate;";
            dbDelta($sql);

            // New columns and new built-in providers, for fresh and upgraded installs alike.
            self::ensure_provider_columns();
            self::backfill_provider_types();
            self::seed_missing_providers();
        } catch (Exception $e) {
            // Log the error or store it for later display
            error_log('IntelliSend Database Error: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Run schema upgrades when the stored schema version is behind the code.
     *
     * create_tables() only runs on activation, so this keeps installs that were
     * updated in place (FTP, Git pull, auto-update) from missing new columns.
     */
    public static function maybe_upgrade()
    {
        if (defined('INTELLISEND_ACTIVATING')) {
            return;
        }

        if (get_option('intellisend_db_version') === self::DB_VERSION) {
            return;
        }

        // create_tables() creates anything missing and runs the explicit
        // column migrations for tables that already exist.
        self::create_tables();

        update_option('intellisend_db_version', self::DB_VERSION);
    }

    /**
     * Add the API transport columns to an existing providers table.
     *
     * dbDelta() handles this for the canonical "CREATE TABLE" form used above,
     * but it is finicky about statement formatting and silently emits nothing
     * when it cannot parse a definition. These columns hold credentials, so
     * they are added explicitly rather than left to that inference.
     */
    private static function ensure_provider_columns()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';

        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");

        if (empty($columns)) {
            return;
        }

        // Ordered: apiEndpoint is positioned after apiKey, so apiKey goes first.
        $additions = array(
            'type'        => "ALTER TABLE $table ADD COLUMN type varchar(20) NOT NULL DEFAULT 'smtp' AFTER name",
            'apiKey'      => "ALTER TABLE $table ADD COLUMN apiKey varchar(500) NULL AFTER password",
            'apiEndpoint' => "ALTER TABLE $table ADD COLUMN apiEndpoint varchar(255) NULL AFTER apiKey",
        );

        foreach ($additions as $column => $sql) {
            if (! in_array($column, $columns, true)) {
                $wpdb->query($sql);
            }
        }

        // Index the transport type so filtered provider lookups stay cheap.
        $has_type_index = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM $table WHERE Key_name = %s", 'type'));

        if (empty($has_type_index)) {
            $wpdb->query("ALTER TABLE $table ADD KEY type (type)");
        }
    }

    /**
     * Give every legacy provider row an explicit transport type.
     */
    private static function backfill_provider_types()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';

        $wpdb->query("UPDATE $table SET type = 'smtp' WHERE type IS NULL OR type = ''");
    }

    /**
     * The built-in providers shipped with the plugin.
     *
     * Keyed by provider name. SMTP entries are PHPMailer presets; entries with
     * type = 'api' are HTTP transports and must have a matching class in
     * IntelliSend_Api_Transport::registry().
     *
     * @return array
     */
    public static function get_default_providers()
    {
        return array(
            'google' => array(
                'name' => 'google',
                'type' => self::TYPE_SMTP,
                'server' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'description' => 'App Password is required',
                'helpLink' => 'https://support.google.com/mail/answer/185833',
                'authRequired' => 1,
                'configured' => 0,
            ),
            'microsoft' => array(
                'name' => 'microsoft',
                'type' => self::TYPE_SMTP,
                'server' => 'smtp.office365.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'description' => 'App Password is required',
                'helpLink' => 'https://support.microsoft.com/en-us/account-billing/manage-app-passwords-for-two-step-verification-d6dc8c6d-4bf7-4851-ad95-6d07799387e9',
                'authRequired' => 1,
                'configured' => 0,
            ),
            'yahoo' => array(
                'name' => 'yahoo',
                'type' => self::TYPE_SMTP,
                'server' => 'smtp.mail.yahoo.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'description' => 'App Password is required',
                'helpLink' => 'https://help.yahoo.com/kb/SLN15241.html',
                'authRequired' => 1,
                'configured' => 0,
            ),
            'zoho' => array(
                'name' => 'zoho',
                'type' => self::TYPE_SMTP,
                'server' => 'smtp.zoho.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'description' => 'Use your Zoho Mail address and password.',
                'helpLink' => 'https://www.zoho.com/mail/help/zoho-smtp.html',
                'authRequired' => 1,
                'configured' => 0,
            ),
            'mailchimp' => array(
                'name' => 'mailchimp',
                'type' => self::TYPE_SMTP,
                'server' => 'smtp.mandrillapp.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'description' => 'Use your API key as the password.',
                'helpLink' => 'https://mailchimp.com/developer/transactional/docs/smtp-integration/',
                'authRequired' => 1,
                'configured' => 0,
            ),
            'sendgrid' => array(
                'name' => 'sendgrid',
                'type' => self::TYPE_SMTP,
                'server' => 'smtp.sendgrid.net',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'description' => 'Use "apikey" as username and your API key as password.',
                'helpLink' => 'https://www.twilio.com/docs/sendgrid/for-developers/sending-email/integrating-with-the-smtp-api',
                'authRequired' => 1,
                'configured' => 0,
            ),
            'sendgrid-api' => array(
                'name' => 'sendgrid-api',
                'type' => self::TYPE_API,
                'server' => '',
                'port' => 443,
                'encryption' => '',
                'username' => '',
                'password' => '',
                'apiKey' => '',
                'apiEndpoint' => 'https://api.sendgrid.com',
                'description' => 'Sends over the SendGrid Web API v3 (HTTPS), so no SMTP ports are needed. Paste an API key with Mail Send permission.',
                'helpLink' => 'https://www.twilio.com/docs/sendgrid/api-reference/mail-send/mail-send',
                'authRequired' => 1,
                'configured' => 0,
            ),
            'brevo' => array(
                'name' => 'brevo',
                'type' => self::TYPE_SMTP,
                'server' => 'smtp-relay.brevo.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'description' => 'Use your Brevo SMTP login as username and an SMTP key as password (not your account password).',
                'helpLink' => 'https://help.brevo.com/hc/en-us/articles/209462765-Send-your-first-transactional-email-via-SMTP',
                'authRequired' => 1,
                'configured' => 0,
            ),
            'brevo-api' => array(
                'name' => 'brevo-api',
                'type' => self::TYPE_API,
                'server' => '',
                'port' => 443,
                'encryption' => '',
                'username' => '',
                'password' => '',
                'apiKey' => '',
                'apiEndpoint' => 'https://api.brevo.com',
                'description' => 'Sends over the Brevo transactional email API (HTTPS), so no SMTP ports are needed. Paste an API v3 key.',
                'helpLink' => 'https://developers.brevo.com/reference/sendtransacemail',
                'authRequired' => 1,
                'configured' => 0,
            ),
            'ses' => array(
                'name' => 'ses',
                'type' => self::TYPE_SMTP,
                'server' => 'email-smtp.us-east-1.amazonaws.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'description' => 'Use SES SMTP credentials, not your AWS keys. Edit the server host to match your SES region.',
                'helpLink' => 'https://docs.aws.amazon.com/ses/latest/dg/send-email-smtp.html',
                'authRequired' => 1,
                'configured' => 0,
            ),
            'ses-api' => array(
                'name' => 'ses-api',
                'type' => self::TYPE_API,
                'server' => '',
                'port' => 443,
                'encryption' => '',
                'username' => '',
                'password' => '',
                'apiKey' => '',
                'apiEndpoint' => 'https://email.us-east-1.amazonaws.com',
                'description' => 'Sends over the Amazon SES v2 API (HTTPS), signed with AWS SigV4. Needs an Access Key ID and Secret Access Key with ses:SendEmail.',
                'helpLink' => 'https://docs.aws.amazon.com/ses/latest/APIReference-V2/API_SendEmail.html',
                'authRequired' => 1,
                'configured' => 0,
            ),
            'other' => array(
                'name' => 'other',
                'type' => self::TYPE_SMTP,
                'server' => '',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'description' => 'Enter your custom SMTP server details.',
                'helpLink' => '',
                'authRequired' => 1,
                'configured' => 0,
            ),
        );
    }

    /**
     * SMTP presets whose host varies by region, so the admin must be able to
     * edit the server field even though the provider is not "other".
     *
     * @param object|string $provider Provider row or name.
     * @return bool
     */
    public static function has_editable_server($provider)
    {
        $name = is_object($provider) ? $provider->name : (string) $provider;

        return in_array($name, array('other', 'ses'), true);
    }

    /**
     * Insert any built-in provider that is not in the table yet.
     *
     * Runs on every schema upgrade, not just on a fresh install, so presets
     * added in later releases reach sites that already have a providers table.
     * Existing rows are never touched, so configured credentials stay put.
     */
    private static function seed_missing_providers()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';

        $existing = $wpdb->get_col("SELECT name FROM $table");
        $existing = is_array($existing) ? $existing : array();

        foreach (self::get_default_providers() as $name => $provider) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            $wpdb->insert($table, $provider);
        }
    }

    /**
     * Provider CRUD Operations
     */

    /**
     * Get all providers
     * 
     * @param array $args Optional. Query arguments.
     * @return array Array of provider objects
     */
    public static function get_providers($args = array())
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';

        // Start building the query
        $query = "SELECT * FROM $table WHERE 1=1";

        // Add filters if provided
        if (is_array($args)) {
            // Filter by configured status
            if (isset($args['configured'])) {
                $query .= $wpdb->prepare(" AND configured = %d", absint($args['configured']));
            }

            // Filter by name
            if (isset($args['name'])) {
                $query .= $wpdb->prepare(" AND name = %s", $args['name']);
            }

            // Filter by auth required
            if (isset($args['authRequired'])) {
                $query .= $wpdb->prepare(" AND authRequired = %d", absint($args['authRequired']));
            }

            // Filter by transport type ('smtp' or 'api')
            if (isset($args['type'])) {
                $query .= $wpdb->prepare(" AND type = %s", sanitize_text_field($args['type']));
            }
        }

        // Add ordering
        $query .= " ORDER BY name ASC";

        return $wpdb->get_results($query);
    }

    /**
     * Get a single provider by ID
     * 
     * @param int $id Provider ID
     * @return object|null Provider object or null if not found
     */
    public static function get_provider($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Get a provider by name
     * 
     * @param string $name Provider name
     * @return object|null Provider object or null if not found
     */
    public static function get_provider_by_name($name)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE name = %s", $name));
    }

    /**
     * Get the transport type for a provider row.
     *
     * @param object $provider Provider object.
     * @return string 'smtp' or 'api'
     */
    public static function get_provider_type($provider)
    {
        if (! $provider) {
            return self::TYPE_SMTP;
        }

        $type = isset($provider->type) ? strtolower(trim($provider->type)) : '';

        return self::TYPE_API === $type ? self::TYPE_API : self::TYPE_SMTP;
    }

    /**
     * Whether a provider sends over an HTTP API instead of SMTP.
     *
     * @param object $provider Provider object.
     * @return bool
     */
    public static function is_api_provider($provider)
    {
        return self::TYPE_API === self::get_provider_type($provider);
    }

    /**
     * Human readable label for a provider, used across the admin UI.
     *
     * @param object|string $provider Provider object or provider name.
     * @return string
     */
    public static function get_provider_label($provider)
    {
        $name = is_object($provider) ? $provider->name : (string) $provider;

        // API transports name themselves, so the label never drifts from the class.
        if (class_exists('IntelliSend_Api_Transport')) {
            $transport = IntelliSend_Api_Transport::for_provider($name);

            if ($transport) {
                return $transport::get_label();
            }
        }

        $labels = array(
            'sendgrid'  => 'SendGrid (SMTP)',
            'brevo'     => 'Brevo (SMTP)',
            'ses'       => 'Amazon SES (SMTP)',
            'mailchimp' => 'Mailchimp / Mandrill',
            'other'     => 'Other (Custom SMTP)',
        );

        if (isset($labels[$name])) {
            return $labels[$name];
        }

        return ucfirst($name);
    }

    /**
     * Get the decrypted API key for a provider.
     *
     * @param object $provider Provider object.
     * @return string
     */
    public static function get_provider_api_key($provider)
    {
        if (! $provider || empty($provider->apiKey)) {
            return '';
        }

        return self::decrypt_data($provider->apiKey);
    }

    /**
     * Decide whether a provider has everything it needs to send.
     *
     * @param array       $data     Incoming provider data.
     * @param object|null $existing Existing provider row, for partial updates.
     * @return int 1 when configured, 0 otherwise.
     */
    private static function evaluate_configured($data, $existing = null)
    {
        $value = function ($key) use ($data, $existing) {
            if (array_key_exists($key, $data) && null !== $data[$key]) {
                return $data[$key];
            }

            return ($existing && isset($existing->$key)) ? $existing->$key : '';
        };

        $type = isset($data['type']) ? strtolower(trim($data['type'])) : self::get_provider_type($existing);

        if (self::TYPE_API === $type) {
            // API transports need a secret and a verified sender address. The
            // secret may also come from the transport's constant or environment
            // variable, and some vendors need a second, non-secret credential.
            $has_key = ! empty($value('apiKey'));
            $has_identity = true;

            if (class_exists('IntelliSend_Api_Transport')) {
                $name = isset($data['name']) ? $data['name'] : ($existing ? $existing->name : '');
                $transport = IntelliSend_Api_Transport::for_provider($name);

                if ($transport) {
                    if (! $has_key && '' !== $transport::get_environment_key()) {
                        $has_key = true;
                    }

                    if ($transport::requires_identity()) {
                        // get_identity() already falls back to the constant and
                        // the environment variable.
                        $has_identity = ! empty($value('username')) || '' !== $transport::get_identity($existing);
                    }
                }
            }

            return ($has_key && $has_identity && ! empty($value('sender'))) ? 1 : 0;
        }

        if (empty($value('server')) || empty($value('port'))) {
            return 0;
        }

        $auth_required = $value('authRequired');

        if (! $auth_required) {
            return 1;
        }

        return (! empty($value('username')) && ! empty($value('password'))) ? 1 : 0;
    }

    /**
     * Add a new provider and handle first provider logic
     *
     * @param array $data Provider data
     * @return int|bool Provider ID on success, false on failure
     */
    public static function add_provider($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';

        // Check if a provider with the same name already exists
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE name = %s",
                $data['name']
            )
        );

        if ($existing) {
            return false;
        }

        // Check if there are any configured providers before adding this one
        $existing_configured = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE configured = 1"
        );

        // Normalize the transport type
        $type = isset($data['type']) && self::TYPE_API === strtolower(trim($data['type'])) ? self::TYPE_API : self::TYPE_SMTP;
        $data['type'] = $type;

        // Determine if the provider is configured
        $configured = self::evaluate_configured($data);

        // Encrypt secrets if provided
        $password = !empty($data['password']) ? self::encrypt_data($data['password']) : '';
        $api_key  = !empty($data['apiKey']) ? self::encrypt_data($data['apiKey']) : '';

        // Insert the provider
        $result = $wpdb->insert(
            $table,
            array(
                'name' => sanitize_text_field($data['name']),
                'type' => $type,
                'description' => isset($data['description']) ? sanitize_text_field($data['description']) : '',
                'helpLink' => isset($data['helpLink']) ? sanitize_text_field($data['helpLink']) : '',
                'server' => isset($data['server']) ? sanitize_text_field($data['server']) : '',
                'port' => isset($data['port']) ? sanitize_text_field($data['port']) : '',
                'encryption' => isset($data['encryption']) ? sanitize_text_field($data['encryption']) : 'tls',
                'authRequired' => isset($data['authRequired']) ? absint($data['authRequired']) : 1,
                'username' => isset($data['username']) ? sanitize_text_field($data['username']) : '',
                'sender' => isset($data['sender']) && !empty($data['sender']) ? sanitize_text_field($data['sender']) : (isset($data['username']) ? sanitize_text_field($data['username']) : ''),
                'password' => $password,
                'apiKey' => $api_key,
                'apiEndpoint' => isset($data['apiEndpoint']) ? esc_url_raw($data['apiEndpoint']) : '',
                'configured' => $configured,
            )
        );

        if ($result) {
            $provider_id = $wpdb->insert_id;

            // If this is the first configured provider, make it the default and update routing rule
            if ($configured && $existing_configured == 0) {
                $provider_name = sanitize_text_field($data['name']);

                // Update settings to make this the default provider
                $settings_table = $wpdb->prefix . 'intellisend_settings';
                $settings = self::get_settings();

                $settings_data = array('defaultProviderName' => $provider_name);

                if ($settings) {
                    $wpdb->update(
                        $settings_table,
                        $settings_data,
                        array('id' => $settings->id)
                    );
                } else {
                    $settings_data = array_merge($settings_data, array(
                        'antiSpamEndPoint' => 'https://api.cyberitex.com/v1/tools/spamCheck',
                        'antiSpamApiKey' => '',
                        'testRecipient' => get_option('admin_email'),
                        'spamTestMessage' => 'This is a test spam message from IntelliSend.',
                        'logsRetentionDays' => 365,
                    ));
                    $wpdb->insert($settings_table, $settings_data);
                }

                // Update the default routing rule to use this provider
                $routing_table = $wpdb->prefix . 'intellisend_routing';
                $wpdb->update(
                    $routing_table,
                    array('default_provider_name' => $provider_name),
                    array('priority' => -1) // Default rule has priority -1
                );
            }

            return $provider_id;
        }

        return false;
    }

    /**
     * Update provider and handle default routing rule updates
     * 
     * @param int $id Provider ID
     * @param array $data Provider data
     * @param bool $set_as_default Whether to set this provider as the default provider
     * @return bool True on success, false on failure
     */
    public static function update_provider($id, $data, $set_as_default = false)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';

        $existing = self::get_provider($id);

        $update_data = array();

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }

        if (isset($data['type'])) {
            $update_data['type'] = self::TYPE_API === strtolower(trim($data['type'])) ? self::TYPE_API : self::TYPE_SMTP;
        }

        if (isset($data['description'])) {
            $update_data['description'] = sanitize_text_field($data['description']);
        }

        if (isset($data['helpLink'])) {
            $update_data['helpLink'] = sanitize_text_field($data['helpLink']);
        }

        if (isset($data['server'])) {
            $update_data['server'] = sanitize_text_field($data['server']);
        }

        if (isset($data['port'])) {
            $update_data['port'] = sanitize_text_field($data['port']);
        }

        if (isset($data['encryption'])) {
            $update_data['encryption'] = sanitize_text_field($data['encryption']);
        }

        if (isset($data['authRequired'])) {
            $update_data['authRequired'] = absint($data['authRequired']);
        }

        if (isset($data['username'])) {
            $update_data['username'] = sanitize_text_field($data['username']);
        }

        if (isset($data['sender'])) {
            $update_data['sender'] = sanitize_text_field($data['sender']);
        } else if (isset($data['username'])) {
            // If sender is not provided but username is updated, use username as sender
            $update_data['sender'] = sanitize_text_field($data['username']);
        }

        if (isset($data['password'])) {
            $update_data['password'] = self::encrypt_data($data['password']);
        }

        if (isset($data['apiKey'])) {
            $update_data['apiKey'] = self::encrypt_data($data['apiKey']);
        }

        if (isset($data['apiEndpoint'])) {
            $update_data['apiEndpoint'] = esc_url_raw($data['apiEndpoint']);
        }

        // Recompute the configured flag from the merged (incoming + stored) values,
        // so a partial update never clears it. evaluate_configured() picks the SMTP
        // or API rules based on the resulting transport type.
        $update_data['configured'] = self::evaluate_configured($data, $existing);

        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $id)
        ) !== false;

        // If update was successful and set_as_default is true, set this provider as the default
        if ($result && $set_as_default) {
            // Get the provider name
            $provider = self::get_provider($id);
            if ($provider) {
                $settings_table = $wpdb->prefix . 'intellisend_settings';
                $settings = self::get_settings();

                if ($settings) {
                    // Update existing settings
                    $wpdb->update(
                        $settings_table,
                        array('defaultProviderName' => $provider->name),
                        array('id' => $settings->id)
                    );
                } else {
                    // Insert new settings if none exist
                    $wpdb->insert(
                        $settings_table,
                        array(
                            'defaultProviderName' => $provider->name,
                            'antiSpamEndPoint' => '',
                            'antiSpamApiKey' => '',
                            'testRecipient' => get_option('admin_email'),
                            'spamTestMessage' => 'This is a test spam message from IntelliSend.',
                            'logsRetentionDays' => 30,
                        )
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Delete a provider
     * 
     * @param int $id Provider ID
     * @return bool True on success, false on failure
     */
    public static function delete_provider($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';
        return $wpdb->delete($table, array('id' => $id)) !== false;
    }

    /**
     * Settings CRUD Operations
     */

    /**
     * Get all settings
     * 
     * @return object|null Settings object or null if not found
     */
    public static function get_settings()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_settings';

        $settings = $wpdb->get_row("SELECT * FROM $table LIMIT 1");

        // Decrypt API key if it exists
        if ($settings && !empty($settings->antiSpamApiKey)) {
            $settings->antiSpamApiKey = self::decrypt_data($settings->antiSpamApiKey);
        }

        return $settings;
    }

    /**
     * Update settings
     * 
     * @param array $data Settings data
     * @return bool True on success, false on failure
     */
    public static function update_settings($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_settings';

        $settings = self::get_settings();

        if (!$settings) {
            // Insert new settings if none exist
            $result = $wpdb->insert(
                $table,
                array(
                    'defaultProviderName' => isset($data['defaultProviderName']) ? sanitize_text_field($data['defaultProviderName']) : 'other',
                    'antiSpamEndPoint' => isset($data['antiSpamEndPoint']) ? sanitize_text_field($data['antiSpamEndPoint']) : '',
                    'antiSpamApiKey' => isset($data['antiSpamApiKey']) && is_string($data['antiSpamApiKey']) ? self::encrypt_data($data['antiSpamApiKey']) : '',
                    'testRecipient' => isset($data['testRecipient']) ? sanitize_email($data['testRecipient']) : '',
                    'spamTestMessage' => isset($data['spamTestMessage']) ? sanitize_textarea_field($data['spamTestMessage']) : '',
                    'logsRetentionDays' => isset($data['logsRetentionDays']) ? absint($data['logsRetentionDays']) : 365,
                    'debug_enabled' => isset($data['debug_enabled']) ? absint($data['debug_enabled']) : 0,
                )
            );

            return $result !== false;
        } else {
            // Update existing settings
            $update_data = array();

            if (isset($data['defaultProviderName'])) {
                $update_data['defaultProviderName'] = sanitize_text_field($data['defaultProviderName']);
            }

            if (isset($data['antiSpamEndPoint'])) {
                $update_data['antiSpamEndPoint'] = sanitize_text_field($data['antiSpamEndPoint']);
            }

            if (isset($data['antiSpamApiKey']) && is_string($data['antiSpamApiKey'])) {
                $update_data['antiSpamApiKey'] = self::encrypt_data($data['antiSpamApiKey']);
            }

            if (isset($data['testRecipient'])) {
                $update_data['testRecipient'] = sanitize_email($data['testRecipient']);
            }

            if (isset($data['spamTestMessage'])) {
                $update_data['spamTestMessage'] = sanitize_textarea_field($data['spamTestMessage']);
            }

            if (isset($data['logsRetentionDays'])) {
                $update_data['logsRetentionDays'] = absint($data['logsRetentionDays']);
            }

            if (isset($data['debug_enabled'])) {
                $update_data['debug_enabled'] = absint($data['debug_enabled']);
            }

            return $wpdb->update(
                $table,
                $update_data,
                array('id' => $settings->id)
            ) !== false;
        }
    }

    /**
     * Routing CRUD Operations
     */

    /**
     * Get routing rules with additional filtering options
     * 
     * @param array $args Optional. Query arguments.
     * @return array Array of routing rule objects
     */
    public static function get_routing_rules($args = array())
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_routing';

        // Start building the query
        $query = "SELECT * FROM $table WHERE 1=1";

        // Add filters if provided
        if (is_array($args)) {
            // Filter by enabled status
            if (isset($args['enabled'])) {
                $query .= $wpdb->prepare(" AND enabled = %d", absint($args['enabled']));
            }

            // Filter by name
            if (isset($args['name'])) {
                $query .= $wpdb->prepare(" AND name LIKE %s", '%' . $wpdb->esc_like($args['name']) . '%');
            }

            // Filter by provider
            if (isset($args['provider'])) {
                $query .= $wpdb->prepare(" AND default_provider_name = %s", $args['provider']);
            }

            // Filter by default rule (is_default = 1 or priority = -1)
            if (isset($args['is_default']) && $args['is_default']) {
                $query .= " AND (is_default = 1 OR priority = -1)";
            }
        }

        // Add ordering - ensure default rule (priority = -1) always comes first
        $query .= " ORDER BY CASE WHEN priority = -1 THEN 0 ELSE 1 END, priority ASC, name ASC";

        return $wpdb->get_results($query);
    }

    /**
     * Get a single routing rule by ID
     * 
     * @param int $id Routing rule ID
     * @return object|null Routing rule object or null if not found
     */
    public static function get_routing_rule($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_routing';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Create routing rule with validation
     */
    public static function create_routing_rule($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_routing';

        // Validate required fields
        if (empty($data->name) || empty($data->subject_patterns) || empty($data->default_provider_name)) {
            error_log('IntelliSend: Missing required fields for routing rule creation');
            return false;
        }

        // Validate pattern type
        $valid_pattern_types = array('wildcard', 'starts_with', 'contains', 'ends_with', 'regex');
        $pattern_type = isset($data->pattern_type) ? $data->pattern_type : 'wildcard';
        if (!in_array($pattern_type, $valid_pattern_types)) {
            $pattern_type = 'wildcard';
        }

        // Don't allow creating another default rule
        if (isset($data->is_default) && $data->is_default) {
            error_log('IntelliSend: Attempted to create another default rule');
            return false;
        }

        // Set default recipients to admin email if not specified
        $recipients = isset($data->recipients) ? $data->recipients : '';
        if (empty($recipients)) {
            $recipients = get_option('admin_email');
        }

        $insert_data = array(
            'name' => sanitize_text_field($data->name),
            'pattern_type' => $pattern_type,
            'subject_patterns' => sanitize_textarea_field($data->subject_patterns),
            'default_provider_name' => sanitize_text_field($data->default_provider_name),
            'recipients' => sanitize_textarea_field($recipients),
            'anti_spam_enabled' => isset($data->anti_spam_enabled) ? absint($data->anti_spam_enabled) : 0,
            'enabled' => isset($data->enabled) ? absint($data->enabled) : 1,
            'priority' => isset($data->priority) ? intval($data->priority) : 100,
            'is_default' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $result = $wpdb->insert($table, $insert_data);

        if ($result === false) {
            error_log('IntelliSend: Database error in create_routing_rule: ' . $wpdb->last_error);
            error_log('IntelliSend: Insert data: ' . print_r($insert_data, true));
            return false;
        }

        return $wpdb->insert_id;
    }


    /**
     * Update routing rule with validation
     */
    public static function update_routing_rule($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_routing';

        // Ensure we have the ID
        if (empty($data->id)) {
            error_log('IntelliSend: No ID provided for routing rule update');
            return false;
        }

        // Get existing rule
        $existing_rule = self::get_routing_rule($data->id);
        if (!$existing_rule) {
            error_log('IntelliSend: Routing rule not found with ID: ' . $data->id);
            return false;
        }

        // Don't allow changing default rule's essential properties
        $is_default_rule = $existing_rule->is_default == 1 || $existing_rule->priority == -1;

        $update_data = array();

        // Basic fields (always updatable)
        if (isset($data->name)) {
            $update_data['name'] = sanitize_text_field($data->name);
        }

        if (isset($data->default_provider_name)) {
            $update_data['default_provider_name'] = sanitize_text_field($data->default_provider_name);
        }

        if (isset($data->recipients)) {
            $recipients = sanitize_textarea_field($data->recipients);
            if (empty($recipients)) {
                $recipients = get_option('admin_email');
            }
            $update_data['recipients'] = $recipients;
        }

        if (isset($data->anti_spam_enabled)) {
            $update_data['anti_spam_enabled'] = absint($data->anti_spam_enabled);
        }

        if (isset($data->enabled)) {
            $update_data['enabled'] = absint($data->enabled);
        }

        // For default rule, maintain fixed properties
        if ($is_default_rule) {
            $update_data['priority'] = -1;
            $update_data['is_default'] = 1;
            $update_data['pattern_type'] = 'wildcard';
            $update_data['subject_patterns'] = '*';
        } else {
            // Non-default rules can have these updated
            if (isset($data->subject_patterns)) {
                $update_data['subject_patterns'] = sanitize_textarea_field($data->subject_patterns);
            }

            if (isset($data->pattern_type)) {
                $valid_types = array('wildcard', 'starts_with', 'contains', 'ends_with', 'regex');
                if (in_array($data->pattern_type, $valid_types)) {
                    $update_data['pattern_type'] = $data->pattern_type;
                }
            }

            if (isset($data->priority)) {
                $update_data['priority'] = intval($data->priority);
            }
        }

        $update_data['updated_at'] = current_time('mysql');

        // Perform the update
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $data->id),
            null, // format for update data - let WordPress handle it
            array('%d') // format for where clause
        );

        // Check for database errors
        if ($result === false) {
            error_log('IntelliSend: Database error in update_routing_rule: ' . $wpdb->last_error);
            error_log('IntelliSend: Update data: ' . print_r($update_data, true));
            return false;
        }

        return true;
    }


    /**
     * Delete routing rule (prevent deleting default rule)
     */
    public static function delete_routing_rule($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_routing';

        // Get rule to check if it's default
        $rule = self::get_routing_rule($id);
        if (!$rule) {
            error_log('IntelliSend: Rule not found for deletion: ' . $id);
            return false;
        }

        // Don't allow deleting the default rule
        if ($rule->is_default == 1 || $rule->priority == -1) {
            error_log('IntelliSend: Attempted to delete default rule');
            return false;
        }

        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        if ($result === false) {
            error_log('IntelliSend: Database error in delete_routing_rule: ' . $wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Reports CRUD Operations
     */

    /**
     * Get all reports with optional filtering
     * 
     * @param array $args Optional. Query arguments.
     * @return array Array of report objects
     */
    public static function get_reports($args = array())
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_reports';

        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);
        $limit = max(1, absint($args['per_page']));
        $offset = (max(1, (int) $args['page']) - 1) * $limit;
        list($where_clause, $values) = self::get_report_filter_sql($args);

        $columns = array('id', 'date', 'subject', 'sender', 'recipients', 'message', 'status', 'log', 'antiSpamEnabled', 'isSpam', 'routingRuleId', 'providerName');
        $orderby = in_array($args['orderby'], $columns, true) ? $args['orderby'] : 'date';
        $order = is_string($args['order']) && strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        // Mail can share a timestamp; the ID keeps pagination deterministic.
        $sort = "$orderby $order" . ($orderby === 'id' ? '' : ", id $order");
        $values[] = $limit;
        $values[] = $offset;
        $query = "SELECT * FROM $table $where_clause ORDER BY $sort LIMIT %d OFFSET %d";

        return $wpdb->get_results($wpdb->prepare($query, $values));
    }

    /**
     * Build one set of predicates for report rows and both count entry points.
     * Both historical isSpam and current is_spam arguments remain supported.
     *
     * @param array $args Report filters.
     * @return array SQL fragment and its parameter values.
     */
    private static function get_report_filter_sql($args)
    {
        global $wpdb;
        $args = wp_parse_args($args, array(
            'status' => '',
            'is_spam' => null,
            'isSpam' => '',
            'providerName' => '',
            'routingRuleId' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => '',
        ));
        $where = array();
        $where_format = array();

        // Status filter
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_format[] = $args['status'];
        }

        // Is spam filter
        $is_spam = $args['is_spam'] !== null ? $args['is_spam'] : $args['isSpam'];
        if ($is_spam !== '' && $is_spam !== null) {
            $where[] = 'isSpam = %d';
            $where_format[] = absint($is_spam);
        }

        // Provider filter
        if (!empty($args['providerName'])) {
            $where[] = 'providerName = %s';
            $where_format[] = $args['providerName'];
        }

        // Routing rule filter
        if (!empty($args['routingRuleId'])) {
            $where[] = 'routingRuleId = %d';
            $where_format[] = intval($args['routingRuleId']);
        }

        // Date range filters
        if (!empty($args['date_from'])) {
            $where[] = 'date >= %s';
            $where_format[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where[] = 'date <= %s';
            $where_format[] = $args['date_to'] . ' 23:59:59';
        }

        // Search filter
        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(subject LIKE %s OR sender LIKE %s OR recipients LIKE %s OR message LIKE %s)';
            $where_format[] = $search_term;
            $where_format[] = $search_term;
            $where_format[] = $search_term;
            $where_format[] = $search_term;
        }

        return array(empty($where) ? '' : 'WHERE ' . implode(' AND ', $where), $where_format);
    }

    /**
     * Get the total count of reports with optional filtering
     * 
     * @param array $args Optional. Query arguments.
     * @return int Total count of reports
     */
    public static function get_reports_count($args = array())
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_reports';
        list($where_clause, $values) = self::get_report_filter_sql($args);
        $query = "SELECT COUNT(*) FROM $table $where_clause";
        return (int) $wpdb->get_var(empty($values) ? $query : $wpdb->prepare($query, $values));
    }

    /**
     * Get a single report by ID
     * 
     * @param int $id Report ID
     * @return object|null Report object or null if not found
     */
    public static function get_report($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_reports';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Create a new report
     * 
     * @param array $data Report data
     * @return int|false The ID of the inserted report or false on failure
     */
    public static function create_report($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_reports';

        $result = $wpdb->insert(
            $table,
            array(
                'date' => isset($data['date']) ? $data['date'] : current_time('mysql'),
                'subject' => sanitize_text_field($data['subject']),
                'sender' => sanitize_email($data['sender']),
                'recipients' => sanitize_textarea_field($data['recipients']),
                'message' => wp_kses_post($data['message']),
                'status' => sanitize_text_field($data['status']),
                'log' => isset($data['log']) ? sanitize_textarea_field($data['log']) : null,
                'antiSpamEnabled' => isset($data['antiSpamEnabled']) ? absint($data['antiSpamEnabled']) : 0,
                'isSpam' => isset($data['isSpam']) ? absint($data['isSpam']) : 0,
                'routingRuleId' => isset($data['routingRuleId']) ? absint($data['routingRuleId']) : null,
                'providerName' => isset($data['providerName']) ? sanitize_text_field($data['providerName']) : '',
            )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update an existing report
     * 
     * @param int $id Report ID
     * @param array $data Report data
     * @return bool True on success, false on failure
     */
    public static function update_report($id, $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_reports';

        $update_data = array();

        if (isset($data['date'])) {
            $update_data['date'] = $data['date'];
        }

        if (isset($data['subject'])) {
            $update_data['subject'] = sanitize_text_field($data['subject']);
        }

        if (isset($data['sender'])) {
            $update_data['sender'] = sanitize_email($data['sender']);
        }

        if (isset($data['recipients'])) {
            $update_data['recipients'] = sanitize_textarea_field($data['recipients']);
        }

        if (isset($data['message'])) {
            $update_data['message'] = wp_kses_post($data['message']);
        }

        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }

        if (isset($data['log'])) {
            $update_data['log'] = sanitize_textarea_field($data['log']);
        }

        if (isset($data['antiSpamEnabled'])) {
            $update_data['antiSpamEnabled'] = absint($data['antiSpamEnabled']);
        }

        if (isset($data['isSpam'])) {
            $update_data['isSpam'] = absint($data['isSpam']);
        }

        if (isset($data['routingRuleId'])) {
            $update_data['routingRuleId'] = absint($data['routingRuleId']);
        }

        if (isset($data['providerName'])) {
            $update_data['providerName'] = sanitize_text_field($data['providerName']);
        }

        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $id)
        ) !== false;
    }

    /**
     * Delete a report
     * 
     * @param int $id Report ID
     * @return bool True on success, false on failure
     */
    public static function delete_report($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_reports';
        return $wpdb->delete($table, array('id' => $id)) !== false;
    }

    /**
     * Delete reports older than a specified number of days
     * 
     * @param int $days Number of days to keep reports for
     * @return int|false Number of rows deleted or false on error
     */
    public static function delete_old_reports($days)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_reports';

        $date = date('Y-m-d H:i:s', strtotime("-$days days"));

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE date < %s",
            $date
        ));
    }

    /**
     * Get the total count of reports with optional filtering
     * 
     * @param array $args Optional. Query arguments.
     * @return int Total count of reports
     */
    public static function count_reports($args = array())
    {
        return self::get_reports_count($args);
    }
}
