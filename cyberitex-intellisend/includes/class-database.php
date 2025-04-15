<?php
/**
 * IntelliSend Database Operations
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Make sure WordPress functions are available
if ( ! function_exists( 'get_option' ) ) {
    require_once ABSPATH . 'wp-includes/option.php';
}

if ( ! function_exists( 'absint' ) ) {
    /**
     * Convert a value to non-negative integer.
     *
     * @param mixed $maybeint Data to convert to a non-negative integer.
     * @return int A non-negative integer.
     */
    function absint( $maybeint ) {
        return abs( intval( $maybeint ) );
    }
}

/**
 * IntelliSend Database Class
 * 
 * Handles all database operations for the IntelliSend plugin.
 */
class IntelliSend_Database {

    /**
     * Encrypt sensitive data
     * 
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    private static function encrypt_data($data) {
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
    public static function decrypt_data($encrypted_data) {
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
     * Create all required database tables on plugin activation.
     */
    public static function create_tables() {
        global $wpdb;
        
        try {
            $charset_collate = $wpdb->get_charset_collate();
            
            // Make sure dbDelta function is available
            if ( ! function_exists( 'dbDelta' ) ) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }
            
            // Create providers table
            $providers_table = $wpdb->prefix . 'intellisend_providers';
            $sql = "CREATE TABLE IF NOT EXISTS $providers_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                description text,
                helpLink varchar(255),
                server varchar(255) NOT NULL,
                port int(11) NOT NULL,
                encryption varchar(20) NOT NULL,
                authRequired tinyint(1) DEFAULT 1,
                sender varchar(255),
                username varchar(255),
                password varchar(255),
                configured tinyint(1) DEFAULT 0,
                PRIMARY KEY  (id),
                KEY name (name)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // Insert default providers if the table is empty
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $providers_table");
            if ($count == 0) {
                $default_providers = array(
                    array(
                        'name' => 'google',
                        'server' => 'smtp.gmail.com',
                        'port' => '587',
                        'encryption' => 'tls',
                        'username' => '',
                        'password' => '',
                        'description' => 'App Password is required',
                        'helpLink' => 'https://support.google.com/mail/answer/185833',
                        'authRequired' => 1,
                        'configured' => 0,
                    ),
                    array(
                        'name' => 'microsoft',
                        'server' => 'smtp.office365.com',
                        'port' => '587',
                        'encryption' => 'tls',
                        'username' => '',
                        'password' => '',
                        'description' => 'App Password is required',
                        'helpLink' => 'https://support.microsoft.com/en-us/account-billing/manage-app-passwords-for-two-step-verification-d6dc8c6d-4bf7-4851-ad95-6d07799387e9',
                        'authRequired' => 1,
                        'configured' => 0,
                    ),
                    array(
                        'name' => 'yahoo',
                        'server' => 'smtp.mail.yahoo.com',
                        'port' => '587',
                        'encryption' => 'tls',
                        'username' => '',
                        'password' => '',
                        'description' => 'App Password is required',
                        'helpLink' => 'https://help.yahoo.com/kb/SLN15241.html',
                        'authRequired' => 1,
                        'configured' => 0,
                    ),
                    array(
                        'name' => 'zoho',
                        'server' => 'smtp.zoho.com',
                        'port' => '587',
                        'encryption' => 'tls',
                        'username' => '',
                        'password' => '',
                        'description' => 'Use your Zoho Mail address and password.',
                        'helpLink' => 'https://www.zoho.com/mail/help/zoho-smtp.html',
                        'authRequired' => 1,
                        'configured' => 0,
                    ),
                    array(
                        'name' => 'mailchimp',
                        'server' => 'smtp.mandrillapp.com',
                        'port' => '587',
                        'encryption' => 'tls',
                        'username' => '',
                        'password' => '',
                        'description' => 'Use your API key as the password.',
                        'helpLink' => 'https://mailchimp.com/developer/transactional/docs/smtp-integration/',
                        'authRequired' => 1,
                        'configured' => 0,
                    ),
                    array(
                        'name' => 'sendgrid',
                        'server' => 'smtp.sendgrid.net',
                        'port' => '587',
                        'encryption' => 'tls',
                        'username' => '',
                        'password' => '',
                        'description' => 'Use "apikey" as username and your API key as password.',
                        'helpLink' => 'https://www.twilio.com/docs/sendgrid/for-developers/sending-email/integrating-with-the-smtp-api',
                        'authRequired' => 1,
                        'configured' => 0,
                    ),
                    array(
                        'name' => 'other',
                        'server' => '',
                        'port' => '587',
                        'encryption' => 'tls',
                        'username' => '',
                        'password' => '',
                        'description' => 'Enter your custom SMTP server details.',
                        'helpLink' => '',
                        'authRequired' => 1,
                        'configured' => 0,
                    )
                );
                
                foreach ($default_providers as $provider) {
                    $wpdb->insert($providers_table, $provider);
                }
            }
            
            // Create settings table
            $settings_table = $wpdb->prefix . 'intellisend_settings';
            $sql = "CREATE TABLE IF NOT EXISTS $settings_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                defaultProviderName varchar(100),
                antiSpamEndPoint varchar(255),
                antiSpamApiKey varchar(255),
                testRecipient varchar(255),
                spamTestMessage text,
                logsRetentionDays int(11) DEFAULT 365,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // Insert default settings if settings table is empty
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $settings_table");
            if ($count == 0) {
                $admin_email = function_exists('get_option') ? get_option('admin_email') : '';
                
                $wpdb->insert(
                    $settings_table,
                    array(
                        'defaultProviderName' => 'other',
                        'antiSpamEndPoint' => 'https://api.cyberitex.com/v1/tools/SpamCheck',
                        'antiSpamApiKey' => '',
                        'testRecipient' => $admin_email,
                        'spamTestMessage' => 'CONGRATULATIONS! You have been selected to receive a FREE $500 Gift Card! Click here to claim: http://claim-your-prize-now.example.com Limited time offer! Reply now or call +1-555-123-4567. This is a one-time message, to unsubscribe reply STOP.',
                        'logsRetentionDays' => 365,
                    )
                );
            }
            
            // Create routing table
            $routing_table = $wpdb->prefix . 'intellisend_routing';
            $sql = "CREATE TABLE IF NOT EXISTS $routing_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                subjectPatterns text NOT NULL,
                defaultProviderName varchar(100) NOT NULL,
                recipients text,
                antiSpamEnabled tinyint(1) DEFAULT 1,
                enabled tinyint(1) DEFAULT 1,
                priority int(11) DEFAULT 100,
                PRIMARY KEY  (id),
                KEY name (name),
                KEY enabled (enabled),
                KEY priority (priority)
            ) $charset_collate;";
            dbDelta( $sql );
            
            // Insert default routing rule if the table is empty
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $routing_table");
            if ($count == 0) {
                $wpdb->insert(
                    $routing_table,
                    array(
                        'name' => 'Default Rule',
                        'subjectPatterns' => '*',
                        'defaultProviderName' => 'other',
                        'recipients' => '',
                        'antiSpamEnabled' => 0,
                        'enabled' => 1,
                        'priority' => -1,
                    )
                );
            }
            
            // Create reports table
            $reports_table = $wpdb->prefix . 'intellisend_reports';
            $sql = "CREATE TABLE IF NOT EXISTS $reports_table (
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
            dbDelta( $sql );
        } catch (Exception $e) {
            // Log the error or store it for later display
            error_log('IntelliSend Database Error: ' . $e->getMessage());
            return false;
        }
        
        return true;
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
    public static function get_providers( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';
        
        // Start building the query
        $query = "SELECT * FROM $table WHERE 1=1";
        
        // Add filters if provided
        if ( is_array( $args ) ) {
            // Filter by configured status
            if ( isset( $args['configured'] ) ) {
                $query .= $wpdb->prepare( " AND configured = %d", absint( $args['configured'] ) );
            }
            
            // Filter by name
            if ( isset( $args['name'] ) ) {
                $query .= $wpdb->prepare( " AND name = %s", $args['name'] );
            }
            
            // Filter by auth required
            if ( isset( $args['authRequired'] ) ) {
                $query .= $wpdb->prepare( " AND authRequired = %d", absint( $args['authRequired'] ) );
            }
        }
        
        // Add ordering
        $query .= " ORDER BY name ASC";
        
        return $wpdb->get_results( $query );
    }
    
    /**
     * Get a single provider by ID
     * 
     * @param int $id Provider ID
     * @return object|null Provider object or null if not found
     */
    public static function get_provider($id) {
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
    public static function get_provider_by_name($name) {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE name = %s", $name));
    }
    
    /**
     * Add a new provider
     * 
     * @param array $data Provider data
     * @return int|bool Provider ID on success, false on failure
     */
    public static function add_provider($data) {
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
        
        // Determine if the provider is configured
        $configured = 0;
        if (!empty($data['server']) && !empty($data['port'])) {
            // For providers that require authentication, also check username and password
            if (isset($data['authRequired']) && $data['authRequired']) {
                if (!empty($data['username']) && !empty($data['password'])) {
                    $configured = 1;
                }
            } else {
                // For providers that don't require auth, just having server and port is enough
                $configured = 1;
            }
        }
        
        // Encrypt password if provided
        $password = !empty($data['password']) ? self::encrypt_data($data['password']) : '';
        
        // Insert the provider
        $result = $wpdb->insert(
            $table,
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => isset($data['description']) ? sanitize_text_field($data['description']) : '',
                'helpLink' => isset($data['helpLink']) ? sanitize_text_field($data['helpLink']) : '',
                'server' => sanitize_text_field($data['server']),
                'port' => sanitize_text_field($data['port']),
                'encryption' => isset($data['encryption']) ? sanitize_text_field($data['encryption']) : 'tls',
                'authRequired' => absint($data['authRequired']),
                'username' => sanitize_text_field($data['username']),
                'sender' => isset($data['sender']) && !empty($data['sender']) ? sanitize_text_field($data['sender']) : sanitize_text_field($data['username']),
                'password' => $password,
                'configured' => $configured,
            )
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update an existing provider
     * 
     * @param int $id Provider ID
     * @param array $data Provider data
     * @param bool $set_as_default Whether to set this provider as the default provider
     * @return bool True on success, false on failure
     */
    public static function update_provider($id, $data, $set_as_default = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_providers';
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
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
        
        // Set configured to true if server and port are provided
        if (!empty($data['server']) && !empty($data['port'])) {
            // Get current provider data to check authRequired if not in the update data
            $authRequired = isset($data['authRequired']) ? $data['authRequired'] : null;
            
            if ($authRequired === null) {
                $provider = self::get_provider($id);
                $authRequired = $provider ? $provider->authRequired : 0;
            }
            
            // For providers that require authentication, also check username and password
            if ($authRequired) {
                $username = isset($data['username']) ? $data['username'] : null;
                $password = isset($data['password']) ? $data['password'] : null;
                
                if ($username === null || $password === null) {
                    $provider = $provider ?? self::get_provider($id);
                    $username = $username ?? ($provider ? $provider->username : '');
                    $password = $password ?? ($provider ? $provider->password : '');
                }
                
                if (!empty($username) && !empty($password)) {
                    $update_data['configured'] = 1;
                } else {
                    $update_data['configured'] = 0;
                }
            } else {
                // For providers that don't require auth, just having server and port is enough
                $update_data['configured'] = 1;
            }
        } else {
            $update_data['configured'] = 0;
        }
        
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
    public static function delete_provider($id) {
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
    public static function get_settings() {
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
    public static function update_settings($data) {
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
                    'antiSpamApiKey' => isset($data['antiSpamApiKey']) ? self::encrypt_data(sanitize_text_field($data['antiSpamApiKey'])) : '',
                    'testRecipient' => isset($data['testRecipient']) ? sanitize_email($data['testRecipient']) : '',
                    'spamTestMessage' => isset($data['spamTestMessage']) ? sanitize_textarea_field($data['spamTestMessage']) : '',
                    'logsRetentionDays' => isset($data['logsRetentionDays']) ? absint($data['logsRetentionDays']) : 365,
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
            
            if (isset($data['antiSpamApiKey'])) {
                $update_data['antiSpamApiKey'] = self::encrypt_data(sanitize_text_field($data['antiSpamApiKey']));
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
     * Get all routing rules
     * 
     * @return array Array of routing rule objects
     */
    public static function get_routing_rules() {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_routing';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY priority DESC, name ASC");
    }
    
    /**
     * Get a single routing rule by ID
     * 
     * @param int $id Routing rule ID
     * @return object|null Routing rule object or null if not found
     */
    public static function get_routing_rule($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_routing';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Create a new routing rule
     * 
     * @param array $data Routing rule data
     * @return int|false The ID of the inserted routing rule or false on failure
     */
    public static function create_routing_rule($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_routing';
        
        $result = $wpdb->insert(
            $table,
            array(
                'name' => sanitize_text_field($data['name']),
                'subjectPatterns' => sanitize_text_field($data['subjectPatterns']),
                'defaultProviderName' => sanitize_text_field($data['defaultProviderName']),
                'recipients' => sanitize_textarea_field($data['recipients']),
                'antiSpamEnabled' => isset($data['antiSpamEnabled']) ? absint($data['antiSpamEnabled']) : 1,
                'enabled' => isset($data['enabled']) ? absint($data['enabled']) : 1,
                'priority' => absint($data['priority']),
            )
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update an existing routing rule
     * 
     * @param int $id Routing rule ID
     * @param array $data Routing rule data
     * @return bool True on success, false on failure
     */
    public static function update_routing_rule($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_routing';
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['subjectPatterns'])) {
            $update_data['subjectPatterns'] = sanitize_text_field($data['subjectPatterns']);
        }
        
        if (isset($data['defaultProviderName'])) {
            $update_data['defaultProviderName'] = sanitize_text_field($data['defaultProviderName']);
        }
        
        if (isset($data['recipients'])) {
            $update_data['recipients'] = sanitize_textarea_field($data['recipients']);
        }
        
        if (isset($data['antiSpamEnabled'])) {
            $update_data['antiSpamEnabled'] = absint($data['antiSpamEnabled']);
        }
        
        if (isset($data['enabled'])) {
            $update_data['enabled'] = absint($data['enabled']);
        }
        
        if (isset($data['priority'])) {
            $update_data['priority'] = absint($data['priority']);
        }
        
        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $id)
        ) !== false;
    }
    
    /**
     * Delete a routing rule
     * 
     * @param int $id Routing rule ID
     * @return bool True on success, false on failure
     */
    public static function delete_routing_rule($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_routing';
        return $wpdb->delete($table, array('id' => $id)) !== false;
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
    public static function get_reports($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_reports';
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => '',
            'is_spam' => null,
            'providerName' => '',
            'routingRuleId' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $limit = absint($args['per_page']);
        $offset = ($args['page'] - 1) * $limit;
        
        $where = array();
        $where_format = array();
        
        // Status filter
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $where_format[] = $args['status'];
        }
        
        // Is spam filter
        if ($args['is_spam'] !== null) {
            $where[] = 'isSpam = %d';
            $where_format[] = absint($args['is_spam']);
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
            $where[] = '(subject LIKE %s OR sender LIKE %s OR recipient LIKE %s)';
            $where_format[] = $search_term;
            $where_format[] = $search_term;
            $where_format[] = $search_term;
        }
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
            $where_clause = $wpdb->prepare($where_clause, $where_format);
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'date DESC';
        }
        
        $query = "SELECT * FROM $table $where_clause ORDER BY $orderby LIMIT %d OFFSET %d";
        $prepared_query = $wpdb->prepare($query, $limit, $offset);
        
        return $wpdb->get_results($prepared_query);
    }
    
    /**
     * Get the total count of reports with optional filtering
     * 
     * @param array $args Optional. Query arguments.
     * @return int Total count of reports
     */
    public static function get_reports_count($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_reports';
        
        // Default arguments
        $defaults = array(
            'status' => '',
            'isSpam' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Start building the query
        $query = "SELECT COUNT(*) FROM $table WHERE 1=1";
        
        // Add filters
        if (!empty($args['status'])) {
            $query .= $wpdb->prepare(" AND status = %s", $args['status']);
        }
        
        if ($args['isSpam'] !== '') {
            $query .= $wpdb->prepare(" AND isSpam = %d", absint($args['isSpam']));
        }
        
        if (!empty($args['date_from'])) {
            $query .= $wpdb->prepare(" AND date >= %s", $args['date_from'] . ' 00:00:00');
        }
        
        if (!empty($args['date_to'])) {
            $query .= $wpdb->prepare(" AND date <= %s", $args['date_to'] . ' 23:59:59');
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $query .= $wpdb->prepare(
                " AND (subject LIKE %s OR sender LIKE %s OR recipients LIKE %s OR message LIKE %s)",
                $search, $search, $search, $search
            );
        }
        
        // Execute the query
        return (int) $wpdb->get_var($query);
    }
    
    /**
     * Get a single report by ID
     * 
     * @param int $id Report ID
     * @return object|null Report object or null if not found
     */
    public static function get_report($id) {
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
    public static function create_report($data) {
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
    public static function update_report($id, $data) {
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
    public static function delete_report($id) {
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
    public static function delete_old_reports($days) {
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
    public static function count_reports($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_reports';
        
        // Default arguments
        $defaults = array(
            'status' => '',
            'isSpam' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Start building the query
        $query = "SELECT COUNT(*) FROM $table WHERE 1=1";
        
        // Add filters
        if (!empty($args['status'])) {
            $query .= $wpdb->prepare(" AND status = %s", $args['status']);
        }
        
        if ($args['isSpam'] !== '') {
            $query .= $wpdb->prepare(" AND isSpam = %d", absint($args['isSpam']));
        }
        
        if (!empty($args['date_from'])) {
            $query .= $wpdb->prepare(" AND date >= %s", $args['date_from'] . ' 00:00:00');
        }
        
        if (!empty($args['date_to'])) {
            $query .= $wpdb->prepare(" AND date <= %s", $args['date_to'] . ' 23:59:59');
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $query .= $wpdb->prepare(
                " AND (subject LIKE %s OR sender LIKE %s OR recipients LIKE %s OR message LIKE %s)",
                $search, $search, $search, $search
            );
        }
        
        // Execute the query
        return (int) $wpdb->get_var($query);
    }
}
