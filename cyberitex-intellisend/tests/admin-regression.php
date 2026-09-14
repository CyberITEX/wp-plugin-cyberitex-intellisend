<?php
/** Standalone admin boundary regressions. No WordPress, network or mail delivery. */
if (PHP_SAPI !== 'cli') {
    exit;
}
define('WPINC', 'wp-includes');
define('ABSPATH', __DIR__ . '/');
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return true;
});

final class AjaxResponse extends RuntimeException {
    public $success;
    public $data;
    public function __construct($success, $data) { $this->success = $success; $this->data = $data; }
}
function wp_send_json_error($data) { throw new AjaxResponse(false, $data); }
function wp_send_json_success($data) { throw new AjaxResponse(true, $data); }
function current_user_can($capability) { return $GLOBALS['admin_allowed']; }
function wp_verify_nonce($nonce, $action) { return is_string($nonce) && $nonce === 'session:' . $action; }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return sanitize_text_field($value); }
function sanitize_email($value) { return filter_var($value, FILTER_SANITIZE_EMAIL); }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function esc_url_raw($value) { return trim($value); }
function esc_html($value) { return $value; }
function esc_html__($value, $domain = '') { return $value; }
function __($value, $domain = '') { return $value; }
function absint($value) { return abs((int) $value); }
function current_time($type) { return '2026-01-01 12:00:00'; }
function get_option($key) { return $key === 'admin_email' ? 'admin@example.test' : ''; }
function get_bloginfo($key) { return 'Regression fixture'; }
function is_admin() { return true; }

$GLOBALS['hooks'] = array();
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['hooks'][$hook][$priority][] = $callback;
}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { add_filter($hook, $callback, $priority, $accepted_args); }
function remove_filter($hook, $callback, $priority = 10) {
    foreach ($GLOBALS['hooks'][$hook][$priority] ?? array() as $key => $existing) {
        if ($existing === $callback) { unset($GLOBALS['hooks'][$hook][$priority][$key]); }
    }
    if (empty($GLOBALS['hooks'][$hook][$priority])) { unset($GLOBALS['hooks'][$hook][$priority]); }
    if (empty($GLOBALS['hooks'][$hook])) { unset($GLOBALS['hooks'][$hook]); }
}
function remove_action($hook, $callback, $priority = 10) { remove_filter($hook, $callback, $priority); }
function apply_filters($hook, $value) {
    $callbacks = $GLOBALS['hooks'][$hook] ?? array();
    ksort($callbacks);
    foreach ($callbacks as $group) { foreach ($group as $callback) { $value = $callback($value); } }
    return $value;
}
function do_action($hook, $value) {
    $callbacks = $GLOBALS['hooks'][$hook] ?? array();
    ksort($callbacks);
    foreach ($callbacks as $group) { foreach ($group as $callback) { $callback($value); } }
}
#[AllowDynamicProperties]
final class FixtureMailer { public function isSMTP() {} }
function wp_mail($to, $subject, $message, $headers) {
    ++$GLOBALS['mail_calls'];
    $GLOBALS['phpmailer'] = new FixtureMailer();
    do_action('phpmailer_init', $GLOBALS['phpmailer']);
    $GLOBALS['observed_mailer'] = $GLOBALS['phpmailer'];
    $GLOBALS['observed_from'] = apply_filters('wp_mail_from', 'original@example.test');
    if ($GLOBALS['throw_mail']) { throw new RuntimeException('Sensitive fixture exception'); }
    return true;
}

final class FixtureWpdb {
    public $prefix = 'wp_';
    public $last_error = '';
    public $queries = array();
    public $inserted = array();
    public function get_var($query) { return !empty($GLOBALS['fixture_debug']) ? 'wp_intellisend_settings' : null; }
    public function get_row($query) { return null; }
    public function prepare($query, ...$args) { return $query; }
    public function query($query) { $this->queries[] = $query; return 1; }
    public function insert($table, $data) { $this->inserted[] = $data; return 1; }
}
final class IntelliSend_Database {
    const TYPE_API = 'api';
    const TYPE_SMTP = 'smtp';
    public static $providers;
    public static $updates = array();
    public static $settings_updates = array();
    public static $report_reads = 0;
    public static function get_settings() { return (object) array('defaultProviderName' => 'other', 'testRecipient' => 'recipient@example.test', 'antiSpamApiKey' => 'stored-key', 'antiSpamEndPoint' => 'https://saved.example.test/check', 'debug_enabled' => !empty($GLOBALS['fixture_debug'])); }
    public static function get_provider($id) { return self::$providers[$id] ?? null; }
    public static function get_provider_by_name($name) { foreach (self::$providers as $provider) { if ($provider->name === $name) { return $provider; } } return null; }
    public static function get_provider_type($provider) { return $provider ? $provider->type : 'smtp'; }
    public static function get_providers($args = array()) { return array_values(self::$providers); }
    public static function get_provider_label($provider) { return is_object($provider) ? $provider->name : $provider; }
    public static function is_api_provider($provider) { return $provider->type === 'api'; }
    public static function update_provider($id, $data, $default = false) { self::$updates[] = array($id, $data, $default); return true; }
    public static function update_settings($data) { self::$settings_updates[] = $data; return true; }
    public static function get_routing_rules($args = array()) { return array(); }
    public static function get_routing_rule($id) { return (object) array('id' => $id, 'name' => 'Rule', 'priority' => 10, 'is_default' => 0, 'recipients' => '', 'pattern_type' => 'wildcard'); }
    public static function create_routing_rule($data) { self::$updates[] = $data; return true; }
    public static function update_routing_rule($data) { self::$updates[] = $data; return true; }
    public static function get_report($id) { ++self::$report_reads; return null; }
    public static function decrypt_data($data) { return $data; }
}
final class FixtureTransport {
    public static function get_environment_key() { return ''; }
    public static function requires_identity() { return false; }
    public static function get_regions() { return array('https://api.example.test' => 'Fixture'); }
    public static function get_default_base() { return 'https://api.example.test'; }
}
final class IntelliSend_Api_Transport {
    public static function for_provider($name) { return $name === 'sendgrid-api' ? new FixtureTransport() : null; }
}
final class IntelliSend_SpamCheck {
    public static $captured;
    public function check($message, $key = '', $endpoint = '') { self::$captured = array($message, $key, $endpoint); return array('success' => true); }
}

require dirname(__DIR__) . '/includes/class-form.php';
require dirname(__DIR__) . '/admin/class-ajax.php';
$checks = 0;
function check($condition, $message) {
    ++$GLOBALS['checks'];
    if (!$condition) { throw new RuntimeException($message); }
}
function request($handler, $post, $allowed = true) {
    $GLOBALS['admin_allowed'] = $allowed;
    $_POST = $post;
    try { IntelliSend_Ajax::$handler(); } catch (AjaxResponse $response) { return $response; }
    throw new RuntimeException('Handler did not return JSON: ' . $handler);
}
function reset_fixture() {
    $GLOBALS['wpdb'] = new FixtureWpdb();
    $GLOBALS['mail_calls'] = 0;
    $GLOBALS['throw_mail'] = false;
    IntelliSend_Database::$updates = array();
    IntelliSend_Database::$settings_updates = array();
    IntelliSend_Database::$report_reads = 0;
    IntelliSend_Database::$providers = array(
        2 => (object) array('id' => 2, 'name' => 'other', 'type' => 'smtp', 'server' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'authRequired' => 1, 'username' => 'fixture', 'password' => 'stored-password', 'sender' => 'sender@example.test', 'configured' => 1),
        3 => (object) array('id' => 3, 'name' => 'sendgrid-api', 'type' => 'api', 'apiKey' => 'stored-key', 'configured' => 1),
    );
}
reset_fixture();
foreach (array('handle_get_report', 'handle_delete_reports', 'handle_delete_all_reports') as $handler) {
    foreach (array(null, '', 'invalid', 'ee86b922eb', array('nonce')) as $nonce) {
        $post = array('id' => '1', 'ids' => array('1'));
        if ($nonce !== null) { $post['nonce'] = $nonce; }
        check(!request($handler, $post)->success, $handler . ' accepted invalid nonce');
        check(empty($GLOBALS['wpdb']->queries) && IntelliSend_Database::$report_reads === 0, 'Invalid nonce reached report data');
    }
    check(!request($handler, array('nonce' => 'session:intellisend_ajax_nonce'), false)->success, 'Unauthorised report request accepted');
}
check(request('handle_delete_reports', array('nonce' => 'session:intellisend_ajax_nonce', 'ids' => array('1')))->success, 'Valid report deletion rejected');
check(request('handle_delete_all_reports', array('nonce' => 'session:intellisend_ajax_nonce'))->success, 'Valid bulk deletion rejected');
foreach (array(array(array('1')), array('0'), array('1bad'), array('-1')) as $ids) {
    reset_fixture();
    check(!request('handle_delete_reports', array('nonce' => 'session:intellisend_ajax_nonce', 'ids' => $ids))->success, 'Malformed report IDs accepted');
    check(empty($GLOBALS['wpdb']->queries), 'Malformed report IDs caused deletion');
}

$provider_post = array('nonce' => 'session:intellisend_providers', 'provider_id' => '2', 'provider_name' => 'other', 'provider_type' => 'smtp', 'provider_server' => 'smtp.example.test', 'provider_port' => '465', 'provider_username' => 'fixture', 'provider_sender' => 'sender@example.test');
$secret = "  pass\\word'with\"quotes  ";
$post = $provider_post + array('provider_password' => addslashes($secret));
check(request('handle_save_provider', $post)->success, 'SMTP save failed');
$saved = IntelliSend_Database::$updates[0][1];
check($saved['password'] === $secret && $saved['encryption'] === 'ssl', 'Credential bytes or SSL465 changed');
reset_fixture();
IntelliSend_Database::$providers[2]->authRequired = 0;
IntelliSend_Database::$providers[2]->encryption = '';
$post = $provider_post;
$post['provider_port'] = '587';
$post['provider_password'] = '';
check(request('handle_save_provider', $post)->success, 'Existing SMTP save failed');
$saved = IntelliSend_Database::$updates[0][1];
check(!isset($saved['password']) && $saved['encryption'] === '' && $saved['authRequired'] === 0, 'Saved transport settings were overwritten');
foreach (array(array('provider_id' => '999'), array('provider_id' => '3'), array('provider_type' => 'api'), array('provider_id' => array('2')), array('provider_port' => '70000'), array('provider_port' => '465bad'), array('provider_sender' => 'invalid')) as $changes) {
    reset_fixture();
    check(!request('handle_save_provider', array_replace($provider_post, $changes))->success, 'Invalid provider save accepted');
    check(empty(IntelliSend_Database::$updates), 'Invalid provider save wrote data');
}
$api_post = array('nonce' => 'session:intellisend_providers', 'provider_id' => '3', 'provider_name' => 'sendgrid-api', 'provider_type' => 'api', 'provider_sender' => 'sender@example.test', 'provider_api_key' => addslashes($secret));
check(request('handle_save_provider', $api_post)->success, 'API save failed');
check(IntelliSend_Database::$updates[0][1]['apiKey'] === $secret, 'API secret bytes changed');

reset_fixture();
$settings_post = array('action' => 'intellisend_ajax_handler', 'nonce' => 'session:intellisend_settings', 'sub_action' => 'settings_saved', 'antiSpamApiKey' => addslashes($secret));
check(request('ajax_handler', $settings_post)->success, 'Settings save failed');
check(IntelliSend_Database::$settings_updates[0]['antiSpamApiKey'] === $secret, 'Spam secret bytes changed');
$spam_post = array('action' => 'intellisend_ajax_handler', 'nonce' => 'session:intellisend_settings', 'sub_action' => 'spam_test_sent', 'api_key' => '', 'use_existing_key' => '1', 'message' => 'Fixture', 'endpoint' => 'https://edited.example.test/check');
check(request('ajax_handler', $spam_post)->success, 'Spam test failed');
check(IntelliSend_SpamCheck::$captured === array('Fixture', 'stored-key', 'https://edited.example.test/check'), 'Spam test ignored stored key or endpoint override');

foreach (array(false, true) as $throw) {
    foreach (array(false, true) as $had_globals) {
        reset_fixture();
        unset($GLOBALS['intellisend_test_email'], $GLOBALS['phpmailer']);
        $old_mailer = new stdClass();
        if ($had_globals) { $GLOBALS['intellisend_test_email'] = 'prior-value'; $GLOBALS['phpmailer'] = $old_mailer; }
        $baseline = $GLOBALS['hooks'];
        $GLOBALS['throw_mail'] = $throw;
        $response = request('handle_send_test_email', array('nonce' => 'session:intellisend_providers', 'provider_id' => 'other', 'test_email' => 'recipient@example.test'));
        check($response->success === !$throw && $GLOBALS['mail_calls'] === 1, 'SMTP test result incorrect');
        check($GLOBALS['hooks'] === $baseline, 'SMTP test leaked or removed hooks');
        check($had_globals ? $GLOBALS['phpmailer'] === $old_mailer : !array_key_exists('phpmailer', $GLOBALS), 'SMTP test leaked global mailer');
        check($had_globals ? $GLOBALS['intellisend_test_email'] === 'prior-value' : !array_key_exists('intellisend_test_email', $GLOBALS), 'SMTP test failed to restore bypass state');
        check($GLOBALS['observed_from'] === 'sender@example.test' && $GLOBALS['observed_mailer']->Host === 'smtp.example.test', 'SMTP test ignored selected provider');
        check(strpos(json_encode($response->data), 'Sensitive fixture exception') === false, 'Exception contents leaked');
    }
}
reset_fixture();
check(!request('handle_send_test_email', array('nonce' => array('invalid')))->success && $GLOBALS['mail_calls'] === 0, 'Malformed nonce sent email');
check(!request('handle_send_test_email', array('nonce' => 'session:intellisend_providers', 'test_email' => 'invalid'))->success && $GLOBALS['mail_calls'] === 0, 'Invalid test recipient sent email');
check(request('handle_send_test_email', array('nonce' => 'session:intellisend_settings', 'test_email' => 'recipient@example.test'))->success, 'Genuine legacy settings nonce rejected');

$rule = array('name' => 'Fixture rule', 'default_provider_name' => 'other', 'subject_patterns' => '^Order [0-9]{1,3}$', 'pattern_type' => 'regex', 'priority' => '10', 'recipients' => 'copy@example.test');
foreach (array(array('default_provider_name' => 'missing'), array('recipients' => 'invalid'), array('priority' => '-1'), array('subject_patterns' => '['), array('name' => array('malformed'))) as $changes) {
    reset_fixture();
    $encoded = addslashes(http_build_query(array_replace($rule, $changes)));
    check(!request('handle_add_routing_rule', array('nonce' => 'session:intellisend_routing_nonce', 'formData' => $encoded))->success, 'Invalid routing rule accepted');
    check(empty(IntelliSend_Database::$updates), 'Invalid routing rule wrote data');
}
check(request('handle_add_routing_rule', array('nonce' => 'session:intellisend_routing_nonce', 'formData' => addslashes(http_build_query($rule))))->success, 'Valid regex quantifier rejected');
$update = $rule + array('id' => '1');
check(request('handle_update_routing_rule', array('nonce' => 'session:intellisend_ajax_nonce', 'formData' => addslashes(http_build_query($update))))->success, 'Genuine routing nonce alias rejected');
$last_rule = end(IntelliSend_Database::$updates);
check($last_rule->priority === 10, 'Ordinary rule id 1 was promoted to default');
$log_path = tempnam(sys_get_temp_dir(), 'intellisend-admin-');
$previous_log = ini_get('error_log');
try {
    ini_set('error_log', $log_path);
    $GLOBALS['fixture_debug'] = true;
    $secret_probe = 'synthetic-secret-must-not-be-logged';
    $probe = array('nonce' => 'invalid', 'api_key' => $secret_probe, 'provider_password' => $secret_probe);
    check(!request('handle_add_routing_rule', $probe)->success, 'Invalid debug request accepted');
    $probe['nonce'] = 'session:intellisend_routing_nonce';
    $probe['formData'] = http_build_query($rule + array('api_key' => $secret_probe));
    check(request('handle_add_routing_rule', $probe)->success, 'Debug routing save failed');
    $log = file_get_contents($log_path);
    check(strpos($log, 'INTELLISEND ADD ROUTING RULE') !== false && strpos($log, $secret_probe) === false, 'Debug log disclosed posted credentials');
} finally {
    $GLOBALS['fixture_debug'] = false;
    ini_set('error_log', $previous_log);
    unlink($log_path);
}
echo 'PASS: ' . $checks . " admin boundary regression checks; no network or mail delivery.\n";
