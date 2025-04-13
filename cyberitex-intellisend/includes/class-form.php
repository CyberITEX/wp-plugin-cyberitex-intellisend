<?php
/**
 * Handles email interception and processing
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
 * Handles email interception and processing.
 *
 * @since      1.0.0
 * @package    IntelliSend
 */
class IntelliSend_Form {

    /**
     * Initialize the plugin
     */
    public static function init() {
        // Hook into WordPress mail
        add_filter( 'wp_mail', array( __CLASS__, 'intercept_mail' ) );
    }

    /**
     * Intercept mail and process it
     *
     * @param array $args Mail arguments.
     * @return array Modified mail arguments.
     */
    public static function intercept_mail( $args ) {
        // Get settings
        $settings = IntelliSend_Database::get_settings();
        
        // Extract mail arguments
        $to = $args['to'];
        $subject = $args['subject'];
        $message = $args['message'];
        $headers = $args['headers'];
        $attachments = $args['attachments'];
        
        // Parse headers to get From email and name
        $from_email = '';
        $from_name = '';
        
        if ( is_array( $headers ) ) {
            foreach ( $headers as $header ) {
                if ( strpos( $header, 'From:' ) === 0 ) {
                    preg_match( '/From: (.*) <(.*)>/', $header, $matches );
                    if ( isset( $matches[1] ) && isset( $matches[2] ) ) {
                        $from_name = $matches[1];
                        $from_email = $matches[2];
                    }
                }
            }
        } else if ( is_string( $headers ) && ! empty( $headers ) ) {
            $header_lines = explode( "\n", $headers );
            foreach ( $header_lines as $header ) {
                if ( strpos( $header, 'From:' ) === 0 ) {
                    preg_match( '/From: (.*) <(.*)>/', $header, $matches );
                    if ( isset( $matches[1] ) && isset( $matches[2] ) ) {
                        $from_name = $matches[1];
                        $from_email = $matches[2];
                    }
                }
            }
        }
        
        // Use default from settings if not provided
        if ( empty( $from_email ) && $settings ) {
            $from_email = $settings->defaultProviderName;
            $from_name = get_bloginfo('name');
        }
        
        // Determine the appropriate SMTP provider based on routing rules
        $rule = self::find_routing_rule( $to, $subject );
        $provider = null;
        
        if ( $rule ) {
            $provider = IntelliSend_Database::get_provider_by_name( $rule->defaultProviderName );
        } else {
            // Use default provider if no rule matches
            $provider = IntelliSend_Database::get_provider_by_name( $settings->defaultProviderName );
        }
        
        // Check for spam if enabled
        $is_spam = false;
        $message_content = $message;
        
        if ( $settings && isset($rule->antiSpamEnabled) && $rule->antiSpamEnabled && ! empty( $settings->antiSpamApiKey ) ) {
            // Prepare message content for spam check
            if ( is_array( $message ) ) {
                $message_content = implode( ' ', $message );
            }
            
            // Use the SpamCheck class to check for spam
            $spam_checker = new IntelliSend_SpamCheck();
            $spam_result = $spam_checker->check($message_content, $settings->antiSpamApiKey);
            $is_spam = isset($spam_result['success']) && $spam_result['success'] && isset($spam_result['score']) && $spam_result['score'] >= 7;
        }
        
        // Log the email if logging is enabled
        if ( $settings && $settings->logsRetentionDays > 0 ) {
            IntelliSend_Database::create_report( array(
                'date' => current_time( 'mysql' ),
                'subject' => $subject,
                'sender' => $from_email,
                'recipients' => is_array( $to ) ? implode( ', ', $to ) : $to,
                'message' => $message_content,
                'status' => $is_spam ? 'blocked' : 'sent',
                'antiSpamEnabled' => isset($rule->antiSpamEnabled) ? $rule->antiSpamEnabled : 0,
                'isSpam' => $is_spam ? 1 : 0,
                'routingRuleId' => $rule ? $rule->id : null,
            ) );
        }
        
        // If it's spam, block it
        if ( $is_spam ) {
            return false;
        }
        
        // Send the email using the selected provider
        if ( $provider ) {
            // Implementation of sending via the provider would go here
            // For now, just return true to let WordPress handle it
            return true;
        }
        
        // Default to letting WordPress handle it
        return true;
    }

    /**
     * Find the appropriate routing rule for an email
     *
     * @param string $to_email Recipient email.
     * @param string $subject Email subject.
     * @return object|null Routing rule or null if none found.
     */
    private static function find_routing_rule( $to_email, $subject ) {
        // Get all active routing rules
        $rules = IntelliSend_Database::get_routing_rules( array( 'enabled' => 1 ) );
        
        if ( empty( $rules ) ) {
            return null;
        }
        
        // If to_email is an array, use the first one
        if ( is_array( $to_email ) ) {
            $to_email = reset( $to_email );
        }
        
        // First, look for exact matches
        $exact_matches = array();
        $default_rule = null;
        
        foreach ( $rules as $rule ) {
            // Keep track of the default rule
            if ( $rule->priority == -1 ) {
                $default_rule = $rule;
                continue;
            }
            
            // Check if the pattern matches the email or subject
            if ( self::pattern_matches( $rule->subjectPatterns, $to_email ) || self::pattern_matches( $rule->subjectPatterns, $subject ) ) {
                $exact_matches[] = $rule;
            }
        }
        
        // If we found exact matches, return the one with highest priority (lowest number)
        if ( ! empty( $exact_matches ) ) {
            usort( $exact_matches, function( $a, $b ) {
                return $a->priority - $b->priority;
            } );
            
            return $exact_matches[0];
        }
        
        // If no exact matches, return the default rule
        return $default_rule;
    }

    /**
     * Check if a pattern matches a string
     *
     * @param string $pattern Pattern with wildcards.
     * @param string $string String to check.
     * @return bool True if pattern matches, false otherwise.
     */
    private static function pattern_matches( $pattern, $string ) {
        // Convert wildcard pattern to regex
        $regex = str_replace( 
            array( '*', '?' ), 
            array( '.*', '.' ), 
            preg_quote( $pattern, '/' )
        );
        
        return preg_match( '/^' . $regex . '$/i', $string );
    }
}
