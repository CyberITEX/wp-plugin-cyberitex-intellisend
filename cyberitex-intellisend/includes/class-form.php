<?php
/**
 * Handles email interception and processing with enhanced spam detection
 *
 * @since      1.0.0
 * @package    IntelliSend
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * IntelliSend Form Class
 * 
 * Handles email interception and processing with intelligent spam detection
 * based on subject patterns and routing rules.
 *
 * @since      1.0.0
 * @package    IntelliSend
 */
class IntelliSend_Form {

    /**
     * Cache for settings to avoid multiple database calls
     * 
     * @var object
     */
    private static $settings_cache = null;
    
    /**
     * Cache for providers to avoid multiple database calls
     * 
     * @var array
     */
    private static $providers_cache = array();

    /**
     * Initialize the plugin
     */
    public static function init() {
        // Hook into WordPress mail with high priority to ensure we catch all emails
        add_filter( 'wp_mail', array( __CLASS__, 'intercept_mail' ), 10, 1 );
        
        // Hook into PHPMailer initialization for SMTP configuration
        add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ), 10, 1 );
        
        // Clear caches when settings are updated
        add_action( 'updated_option', array( __CLASS__, 'clear_caches' ) );
    }

    /**
     * Intercept mail and process it through the IntelliSend system
     *
     * @param array $args Mail arguments from wp_mail.
     * @return array|false Modified mail arguments or false to block email.
     */
    public static function intercept_mail( $args ) {
        // Skip processing for test emails to prevent infinite loops
        if ( isset( $GLOBALS['intellisend_test_email'] ) && $GLOBALS['intellisend_test_email'] === true ) {
            return $args;
        }
        
        try {
            // Get cached settings
            $settings = self::get_cached_settings();
            
            // Extract and validate mail arguments
            $mail_data = self::extract_mail_data( $args );
            if ( ! $mail_data ) {
                // Log error and allow email to proceed with WordPress defaults
                error_log( 'IntelliSend: Failed to extract mail data, proceeding with default wp_mail' );
                return $args;
            }
            
            // Find the appropriate routing rule
            $routing_rule = self::find_routing_rule( $mail_data['to'], $mail_data['subject'] );
            
            // Check if we should perform spam detection
            $spam_check_result = self::perform_spam_check( $mail_data, $settings, $routing_rule );
            
            // Log the email attempt
            self::log_email_attempt( $mail_data, $spam_check_result, $routing_rule, $settings );
            
            // Block email if it's spam
            if ( $spam_check_result['is_spam'] ) {
                // Return false to block the email but make it appear successful to the user
                add_filter( 'wp_mail_succeeded', '__return_true', 999 );
                return false;
            }
            
            // Configure SMTP provider for this email
            self::configure_smtp_provider( $routing_rule, $settings );
            
            // Modify mail arguments if needed
            $modified_args = self::modify_mail_args( $args, $mail_data, $routing_rule, $settings );
            
            return $modified_args;
            
        } catch ( Exception $e ) {
            // Log the error and allow email to proceed
            error_log( 'IntelliSend Error in intercept_mail: ' . $e->getMessage() );
            return $args;
        }
    }

    /**
     * Extract and validate mail data from wp_mail arguments
     *
     * @param array $args Mail arguments from wp_mail.
     * @return array|false Extracted mail data or false on failure.
     */
    private static function extract_mail_data( $args ) {
        if ( ! is_array( $args ) ) {
            return false;
        }
        
        // Extract basic mail components
        $to = isset( $args['to'] ) ? $args['to'] : '';
        $subject = isset( $args['subject'] ) ? $args['subject'] : '';
        $message = isset( $args['message'] ) ? $args['message'] : '';
        $headers = isset( $args['headers'] ) ? $args['headers'] : array();
        $attachments = isset( $args['attachments'] ) ? $args['attachments'] : array();
        
        // Validate required fields
        if ( empty( $to ) || empty( $subject ) ) {
            return false;
        }
        
        // Extract sender information from headers
        $sender_info = self::extract_sender_from_headers( $headers );
        
        // Normalize recipient(s)
        $recipients = self::normalize_recipients( $to );
        
        // Prepare message content for processing
        $message_content = is_array( $message ) ? implode( ' ', $message ) : $message;
        
        return array(
            'to' => $recipients,
            'subject' => $subject,
            'message' => $message_content,
            'headers' => $headers,
            'attachments' => $attachments,
            'sender_email' => $sender_info['email'],
            'sender_name' => $sender_info['name'],
            'recipients_string' => is_array( $recipients ) ? implode( ', ', $recipients ) : $recipients,
        );
    }

    /**
     * Extract sender information from email headers
     *
     * @param mixed $headers Email headers (string or array).
     * @return array Sender information with 'email' and 'name' keys.
     */
    private static function extract_sender_from_headers( $headers ) {
        $sender_email = '';
        $sender_name = '';
        
        // Convert headers to array if it's a string
        if ( is_string( $headers ) ) {
            $headers = explode( "\n", $headers );
        }
        
        if ( is_array( $headers ) ) {
            foreach ( $headers as $header ) {
                if ( strpos( $header, 'From:' ) === 0 ) {
                    // Parse "From: Name <email@domain.com>" format
                    if ( preg_match( '/From:\s*(.+?)\s*<(.+?)>/', $header, $matches ) ) {
                        $sender_name = trim( $matches[1], ' "' );
                        $sender_email = trim( $matches[2] );
                    } elseif ( preg_match( '/From:\s*(.+)/', $header, $matches ) ) {
                        // Simple "From: email@domain.com" format
                        $sender_email = trim( $matches[1] );
                    }
                    break;
                }
            }
        }
        
        // Use WordPress defaults if not found in headers
        $settings = self::get_cached_settings();
        if ( empty( $sender_email ) && $settings ) {
            $default_provider = self::get_cached_provider( $settings->defaultProviderName );
            if ( $default_provider && ! empty( $default_provider->sender ) ) {
                $sender_email = $default_provider->sender;
            } else {
                $sender_email = get_option( 'admin_email' );
            }
        }
        
        if ( empty( $sender_name ) ) {
            $sender_name = get_bloginfo( 'name' );
        }
        
        return array(
            'email' => $sender_email,
            'name' => $sender_name,
        );
    }

    /**
     * Normalize recipients to consistent format
     *
     * @param mixed $to Recipients (string or array).
     * @return array|string Normalized recipients.
     */
    private static function normalize_recipients( $to ) {
        if ( is_array( $to ) ) {
            return $to;
        }
        
        // Handle comma-separated recipients
        if ( strpos( $to, ',' ) !== false ) {
            return array_map( 'trim', explode( ',', $to ) );
        }
        
        return $to;
    }

    /**
     * Perform spam detection based on subject patterns and routing rules
     *
     * @param array $mail_data Extracted mail data.
     * @param object $settings Plugin settings.
     * @param object $routing_rule Matched routing rule.
     * @return array Spam check results.
     */
    private static function perform_spam_check( $mail_data, $settings, $routing_rule ) {
        // Default result
        $result = array(
            'should_check' => false,
            'is_spam' => false,
            'score' => 0,
            'reason' => 'No spam check performed',
            'api_response' => null,
        );
        
        // Check if we have the necessary components for spam checking
        if ( ! $settings || empty( $settings->antiSpamApiKey ) ) {
            $result['reason'] = 'No API key configured';
            return $result;
        }
        
        // Determine if we should check for spam
        $should_check = self::should_check_for_spam( $mail_data['subject'], $settings, $routing_rule );
        $result['should_check'] = $should_check;
        
        if ( ! $should_check ) {
            $result['reason'] = 'Subject does not match spam check patterns';
            return $result;
        }
        
        // Perform the actual spam check
        try {
            $spam_checker = new IntelliSend_SpamCheck();
            $api_result = $spam_checker->check( $mail_data['message'], $settings->antiSpamApiKey );
            
            $result['api_response'] = $api_result;
            
            if ( isset( $api_result['success'] ) && $api_result['success'] ) {
                $score = isset( $api_result['score'] ) ? floatval( $api_result['score'] ) : 0;
                $result['score'] = $score;
                $result['is_spam'] = $score >= 7; // Configurable threshold
                $result['reason'] = $result['is_spam'] ? 
                    "Detected as spam (score: {$score})" : 
                    "Not spam (score: {$score})";
            } else {
                $result['reason'] = 'API check failed: ' . ( $api_result['message'] ?? 'Unknown error' );
            }
            
        } catch ( Exception $e ) {
            $result['reason'] = 'Spam check exception: ' . $e->getMessage();
            error_log( 'IntelliSend Spam Check Error: ' . $e->getMessage() );
        }
        
        return $result;
    }

    /**
     * Determine if we should check for spam based on subject patterns
     *
     * @param string $subject Email subject.
     * @param object $settings Plugin settings.
     * @param object $routing_rule Matched routing rule.
     * @return bool True if spam check should be performed.
     */
    private static function should_check_for_spam( $subject, $settings, $routing_rule ) {
        // Priority 1: Check global spam subject patterns
        if ( ! empty( $settings->antiSpamSubjectPatterns ) ) {
            $patterns = self::parse_subject_patterns( $settings->antiSpamSubjectPatterns );
            
            foreach ( $patterns as $pattern ) {
                if ( self::pattern_matches( $pattern, $subject ) ) {
                    return true;
                }
            }
            
            // If patterns are defined but none match, don't check for spam
            return false;
        }
        
        // Priority 2: Fall back to routing rule configuration
        if ( $routing_rule && isset( $routing_rule->antiSpamEnabled ) ) {
            return (bool) $routing_rule->antiSpamEnabled;
        }
        
        // Priority 3: Default behavior (no spam check)
        return false;
    }

    /**
     * Parse subject patterns from settings
     *
     * @param string $patterns_string Raw patterns string from settings.
     * @return array Array of individual patterns.
     */
    private static function parse_subject_patterns( $patterns_string ) {
        if ( empty( $patterns_string ) ) {
            return array();
        }
        
        // Split by newlines and clean up
        $patterns = explode( "\n", $patterns_string );
        $clean_patterns = array();
        
        foreach ( $patterns as $pattern ) {
            $pattern = trim( $pattern );
            if ( ! empty( $pattern ) ) {
                $clean_patterns[] = $pattern;
            }
        }
        
        return $clean_patterns;
    }

    /**
     * Find the appropriate routing rule for an email
     *
     * @param mixed $to_email Recipient email(s).
     * @param string $subject Email subject.
     * @return object|null Routing rule or null if none found.
     */
    private static function find_routing_rule( $to_email, $subject ) {
        // Get all active routing rules
        $rules = IntelliSend_Database::get_routing_rules( array( 'enabled' => 1 ) );
        
        if ( empty( $rules ) ) {
            return null;
        }
        
        // Normalize recipient for pattern matching
        $primary_recipient = is_array( $to_email ) ? reset( $to_email ) : $to_email;
        
        // Separate rules by priority
        $priority_rules = array();
        $default_rule = null;
        
        foreach ( $rules as $rule ) {
            // Identify the default rule (priority = -1)
            if ( $rule->priority == -1 ) {
                $default_rule = $rule;
                continue;
            }
            
            $priority_rules[] = $rule;
        }
        
        // Sort priority rules by priority (lower number = higher priority)
        usort( $priority_rules, function( $a, $b ) {
            return $a->priority - $b->priority;
        } );
        
        // Check priority rules first
        foreach ( $priority_rules as $rule ) {
            if ( self::rule_matches_email( $rule, $primary_recipient, $subject ) ) {
                return $rule;
            }
        }
        
        // Return default rule if no priority rules match
        return $default_rule;
    }

    /**
     * Check if a routing rule matches the email
     *
     * @param object $rule Routing rule.
     * @param string $recipient Primary recipient email.
     * @param string $subject Email subject.
     * @return bool True if rule matches.
     */
    private static function rule_matches_email( $rule, $recipient, $subject ) {
        if ( empty( $rule->subjectPatterns ) ) {
            return false;
        }
        
        // Parse rule patterns
        $patterns = self::parse_subject_patterns( $rule->subjectPatterns );
        
        foreach ( $patterns as $pattern ) {
            // Check if pattern matches subject or recipient
            if ( self::pattern_matches( $pattern, $subject ) || 
                 self::pattern_matches( $pattern, $recipient ) ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if a pattern matches a string using wildcards
     *
     * @param string $pattern Pattern with wildcards (* and ?).
     * @param string $string String to check.
     * @return bool True if pattern matches.
     */
    private static function pattern_matches( $pattern, $string ) {
        if ( empty( $pattern ) || empty( $string ) ) {
            return false;
        }
        
        // Escape special regex characters except * and ?
        $pattern = preg_quote( $pattern, '/' );
        
        // Convert wildcards to regex
        $pattern = str_replace( 
            array( '\*', '\?' ), 
            array( '.*', '.' ), 
            $pattern 
        );
        
        // Perform case-insensitive match
        return preg_match( '/^' . $pattern . '$/i', $string );
    }

    /**
     * Configure SMTP provider for the current email
     *
     * @param object $routing_rule Matched routing rule.
     * @param object $settings Plugin settings.
     */
    private static function configure_smtp_provider( $routing_rule, $settings ) {
        // Determine which provider to use
        $provider_name = $settings->defaultProviderName; // Default fallback
        
        if ( $routing_rule && ! empty( $routing_rule->defaultProviderName ) ) {
            $provider_name = $routing_rule->defaultProviderName;
        }
        
        // Cache the provider for use in phpmailer_init
        $GLOBALS['intellisend_current_provider'] = self::get_cached_provider( $provider_name );
    }

    /**
     * Configure PHPMailer with the selected SMTP provider
     *
     * @param PHPMailer $phpmailer PHPMailer instance.
     */
    public static function configure_phpmailer( $phpmailer ) {
        // Skip if this is a test email or no provider is set
        if ( isset( $GLOBALS['intellisend_test_email'] ) && $GLOBALS['intellisend_test_email'] === true ) {
            return;
        }
        
        $provider = isset( $GLOBALS['intellisend_current_provider'] ) ? 
                   $GLOBALS['intellisend_current_provider'] : null;
        
        if ( ! $provider || empty( $provider->server ) ) {
            return;
        }
        
        try {
            // Configure SMTP
            $phpmailer->isSMTP();
            $phpmailer->Host = $provider->server;
            $phpmailer->Port = intval( $provider->port );
            
            // Set encryption
            if ( ! empty( $provider->encryption ) ) {
                if ( $provider->encryption === 'ssl' ) {
                    $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ( $provider->encryption === 'tls' ) {
                    $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
            }
            
            // Set authentication
            if ( $provider->authRequired && ! empty( $provider->username ) ) {
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $provider->username;
                
                // Decrypt password
                if ( ! empty( $provider->password ) ) {
                    $phpmailer->Password = IntelliSend_Database::decrypt_data( $provider->password );
                }
            }
            
            // Set sender information
            if ( ! empty( $provider->sender ) ) {
                $phpmailer->setFrom( $provider->sender, get_bloginfo( 'name' ) );
            }
            
        } catch ( Exception $e ) {
            error_log( 'IntelliSend PHPMailer Configuration Error: ' . $e->getMessage() );
        }
    }

    /**
     * Modify mail arguments based on routing rules and settings
     *
     * @param array $args Original mail arguments.
     * @param array $mail_data Extracted mail data.
     * @param object $routing_rule Matched routing rule.
     * @param object $settings Plugin settings.
     * @return array Modified mail arguments.
     */
    private static function modify_mail_args( $args, $mail_data, $routing_rule, $settings ) {
        // Override recipients if specified in routing rule
        if ( $routing_rule && ! empty( $routing_rule->recipients ) ) {
            $override_recipients = self::parse_recipients( $routing_rule->recipients );
            if ( ! empty( $override_recipients ) ) {
                $args['to'] = $override_recipients;
            }
        }
        
        return $args;
    }

    /**
     * Parse recipients string into array
     *
     * @param string $recipients_string Comma-separated recipients.
     * @return array Array of email addresses.
     */
    private static function parse_recipients( $recipients_string ) {
        if ( empty( $recipients_string ) ) {
            return array();
        }
        
        $recipients = explode( ',', $recipients_string );
        $clean_recipients = array();
        
        foreach ( $recipients as $recipient ) {
            $recipient = trim( $recipient );
            if ( ! empty( $recipient ) && is_email( $recipient ) ) {
                $clean_recipients[] = $recipient;
            }
        }
        
        return $clean_recipients;
    }

    /**
     * Log email attempt to database
     *
     * @param array $mail_data Extracted mail data.
     * @param array $spam_result Spam check results.
     * @param object $routing_rule Matched routing rule.
     * @param object $settings Plugin settings.
     */
    private static function log_email_attempt( $mail_data, $spam_result, $routing_rule, $settings ) {
        // Only log if retention is enabled
        if ( ! $settings || $settings->logsRetentionDays <= 0 ) {
            return;
        }
        
        try {
            // Determine status
            $status = 'sent';
            if ( $spam_result['is_spam'] ) {
                $status = 'blocked';
            }
            
            // Prepare log data
            $log_data = array(
                'date' => current_time( 'mysql' ),
                'subject' => $mail_data['subject'],
                'sender' => $mail_data['sender_email'],
                'recipients' => $mail_data['recipients_string'],
                'message' => self::truncate_message( $mail_data['message'] ),
                'status' => $status,
                'log' => self::format_log_details( $spam_result, $routing_rule ),
                'antiSpamEnabled' => $spam_result['should_check'] ? 1 : 0,
                'isSpam' => $spam_result['is_spam'] ? 1 : 0,
                'routingRuleId' => $routing_rule ? $routing_rule->id : null,
                'providerName' => $routing_rule ? $routing_rule->defaultProviderName : $settings->defaultProviderName,
            );
            
            // Save to database
            IntelliSend_Database::create_report( $log_data );
            
        } catch ( Exception $e ) {
            error_log( 'IntelliSend Logging Error: ' . $e->getMessage() );
        }
    }

    /**
     * Truncate message for logging (to prevent extremely large log entries)
     *
     * @param string $message Original message.
     * @return string Truncated message.
     */
    private static function truncate_message( $message ) {
        $max_length = 5000; // 5KB limit for message logging
        
        if ( strlen( $message ) > $max_length ) {
            return substr( $message, 0, $max_length ) . '... [Message truncated for logging]';
        }
        
        return $message;
    }

    /**
     * Format log details for database storage
     *
     * @param array $spam_result Spam check results.
     * @param object $routing_rule Matched routing rule.
     * @return string Formatted log details.
     */
    private static function format_log_details( $spam_result, $routing_rule ) {
        $details = array();
        
        // Add routing rule info
        if ( $routing_rule ) {
            $details[] = "Routing Rule: {$routing_rule->name} (ID: {$routing_rule->id})";
            $details[] = "Rule Priority: {$routing_rule->priority}";
            $details[] = "Provider: {$routing_rule->defaultProviderName}";
        }
        
        // Add spam check info
        $details[] = "Spam Check: " . ( $spam_result['should_check'] ? 'Yes' : 'No' );
        if ( $spam_result['should_check'] ) {
            $details[] = "Spam Result: {$spam_result['reason']}";
            if ( $spam_result['score'] > 0 ) {
                $details[] = "Spam Score: {$spam_result['score']}/10";
            }
        }
        
        return implode( "\n", $details );
    }

    /**
     * Get cached settings to avoid multiple database calls
     *
     * @return object|null Plugin settings.
     */
    private static function get_cached_settings() {
        if ( self::$settings_cache === null ) {
            self::$settings_cache = IntelliSend_Database::get_settings();
        }
        
        return self::$settings_cache;
    }

    /**
     * Get cached provider to avoid multiple database calls
     *
     * @param string $provider_name Provider name.
     * @return object|null Provider data.
     */
    private static function get_cached_provider( $provider_name ) {
        if ( ! isset( self::$providers_cache[ $provider_name ] ) ) {
            self::$providers_cache[ $provider_name ] = IntelliSend_Database::get_provider_by_name( $provider_name );
        }
        
        return self::$providers_cache[ $provider_name ];
    }

    /**
     * Clear internal caches when settings are updated
     */
    public static function clear_caches() {
        self::$settings_cache = null;
        self::$providers_cache = array();
    }

    /**
     * Get plugin statistics for dashboard
     *
     * @return array Plugin statistics.
     */
    public static function get_statistics() {
        global $wpdb;
        
        $reports_table = $wpdb->prefix . 'intellisend_reports';
        $stats = array();
        
        try {
            // Total emails processed
            $stats['total_emails'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$reports_table}" );
            
            // Emails sent successfully
            $stats['emails_sent'] = $wpdb->get_var( 
                $wpdb->prepare( "SELECT COUNT(*) FROM {$reports_table} WHERE status = %s", 'sent' )
            );
            
            // Spam emails blocked
            $stats['spam_blocked'] = $wpdb->get_var( 
                $wpdb->prepare( "SELECT COUNT(*) FROM {$reports_table} WHERE status = %s", 'blocked' )
            );
            
            // Failed emails
            $stats['emails_failed'] = $wpdb->get_var( 
                $wpdb->prepare( "SELECT COUNT(*) FROM {$reports_table} WHERE status = %s", 'failed' )
            );
            
            // Recent activity (last 24 hours)
            $stats['recent_activity'] = $wpdb->get_var( 
                $wpdb->prepare( 
                    "SELECT COUNT(*) FROM {$reports_table} WHERE date >= %s", 
                    date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
                )
            );
            
        } catch ( Exception $e ) {
            error_log( 'IntelliSend Statistics Error: ' . $e->getMessage() );
            
            // Return default stats on error
            $stats = array(
                'total_emails' => 0,
                'emails_sent' => 0,
                'spam_blocked' => 0,
                'emails_failed' => 0,
                'recent_activity' => 0,
            );
        }
        
        return $stats;
    }
}