<?php
/** Standalone synthetic delivery regressions. Run: php tests/delivery-regression.php */
if (PHP_SAPI !== 'cli') {
    exit;
}

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});
define('WPINC', 'wp-includes');
define('ABSPATH', __DIR__);
// Never resolve host environment credentials, even though all HTTP is stubbed.
define('SENDGRID_API_KEY', 'synthetic-sendgrid-key');
define('BREVO_API_KEY', 'synthetic-brevo-key');
define('AWS_ACCESS_KEY_ID', 'SYNTHETICACCESSKEY');
define('AWS_SECRET_ACCESS_KEY', 'synthetic-secret-key');

$GLOBALS['delivery_filters'] = array();
$GLOBALS['delivery_filter_calls'] = array();
$GLOBALS['delivery_requests'] = array();
$GLOBALS['delivery_response'] = array('response' => array('code' => 202), 'body' => '', 'headers' => array());
function add_action() {}
function add_filter($tag, $callback) { $GLOBALS['delivery_filters'][$tag][] = $callback; }
function apply_filters($tag, $value) {
    $GLOBALS['delivery_filter_calls'][] = $tag;
    foreach (isset($GLOBALS['delivery_filters'][$tag]) ? $GLOBALS['delivery_filters'][$tag] : array() as $callback) {
        $value = $callback($value);
    }
    return $value;
}
function get_option($name) { return 'admin_email' === $name ? 'admin@example.test' : false; }
function get_bloginfo($name) { return 'charset' === $name ? 'UTF-8' : 'Fixture Site'; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function untrailingslashit($value) { return rtrim($value, '/\\'); }
function wp_json_encode($value) { return json_encode($value); }
function wp_check_filetype($name) { return array('type' => 'text/plain'); }
function wp_remote_post($url, $arguments) {
    $GLOBALS['delivery_requests'][] = array('url' => $url, 'arguments' => $arguments);
    return $GLOBALS['delivery_response'];
}
function wp_remote_get($url, $arguments) { return wp_remote_post($url, $arguments); }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_remote_retrieve_header($response, $name) { return isset($response['headers'][$name]) ? $response['headers'][$name] : ''; }
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error {
    public function get_error_message() { return 'Synthetic connection failure'; }
}
class IntelliSend_Database {
    public static $settings;
    public static $reports = 0;
    public static function decrypt_data($value) { return $value; }
    public static function get_settings() { return self::$settings; }
    public static function create_report($data) { ++self::$reports; return self::$reports; }
}
$GLOBALS['wpdb'] = new class {
    public $prefix = 'fixture_';
    public function get_var($query) { return null; }
};

require dirname(__DIR__) . '/includes/class-form.php';
require dirname(__DIR__) . '/includes/class-api-transport.php';
require dirname(__DIR__) . '/includes/class-sendgrid.php';
require dirname(__DIR__) . '/includes/class-brevo.php';
require dirname(__DIR__) . '/includes/class-ses.php';
require dirname(__DIR__) . '/includes/class-spamcheck.php';

$assertions = 0;
function delivery_expect($expected, $actual, $label) {
    ++$GLOBALS['assertions'];
    if ($expected !== $actual) {
        throw new RuntimeException('FAILED: ' . $label);
    }
}
function delivery_invoke($class, $method) {
    $arguments = array_slice(func_get_args(), 2);
    $reflection = new ReflectionMethod($class, $method);
    if (PHP_VERSION_ID < 80100) {
        $reflection->setAccessible(true);
    }
    return $reflection->invokeArgs(null, $arguments);
}

foreach (array(
    array('Order *', 'Order 123', 'wildcard', true),
    array('Order *', 'Refund 123', 'wildcard', false),
    array('Code ?', 'Code A', 'wildcard', true),
    array('Code ?', 'Code AB', 'wildcard', false),
    array('Total [GBP]*', 'Total [GBP] 25', 'wildcard', true),
    array('^\\D+$', 'ABC', 'regex', true),
    array('^\\D+$', '123', 'regex', false),
    array('[', 'anything', 'regex', false),
    array('ORDER', 'Order 123', 'starts_with', true),
    array('123', 'Order 123', 'ends_with', true),
) as $case) {
    delivery_expect($case[3], (bool) delivery_invoke('IntelliSend_Form', 'pattern_matches', $case[0], $case[1], $case[2]), 'routing ' . $case[0] . ' / ' . $case[1]);
}
$patterns = '^Order [0-9]{1,3}$,^Hello (Alice,Bob)$,^Values [a,b]+$,^Comma\\,here$';
delivery_expect(4, count(IntelliSend_Form::parse_subject_patterns($patterns, 'regex')), 'regex commas remain inside quantifiers, groups, classes and escapes');
$rule = (object) array('subject_patterns' => $patterns, 'pattern_type' => 'regex');
foreach (array('Order 123', 'Hello Alice,Bob', 'Values a,b', 'Comma,here') as $subject) {
    delivery_expect(true, (bool) delivery_invoke('IntelliSend_Form', 'rule_matches_email', $rule, $subject, array()), 'regex subject ' . $subject);
}
delivery_expect(array('Order *', 'Refund *'), IntelliSend_Form::parse_subject_patterns('Order *, Refund *'), 'legacy comma-separated wildcards');
delivery_expect(array('Order {', 'Payment'), IntelliSend_Form::parse_subject_patterns('Order {,Payment', 'regex'), 'literal unmatched brace preserves comma-separated patterns');
delivery_expect(array('[]a,b]', 'Payment'), IntelliSend_Form::parse_subject_patterns('[]a,b],Payment', 'regex'), 'initial closing bracket remains literal inside class');
delivery_expect(array('[^]a,b]', 'Payment'), IntelliSend_Form::parse_subject_patterns('[^]a,b],Payment', 'regex'), 'negated class accepts initial literal closing bracket');
delivery_expect(array('[\\]]', 'Payment'), IntelliSend_Form::parse_subject_patterns('[\\]],Payment', 'regex'), 'escaped initial class character allows following closing bracket');
delivery_expect(array('[[:alpha:],]', 'Payment'), IntelliSend_Form::parse_subject_patterns('[[:alpha:],],Payment', 'regex'), 'POSIX character class does not expose its internal comma');

$mailer = new class {
    public $Host, $Port, $SMTPSecure, $SMTPAuth, $Username, $Password, $Debugoutput;
    public $SMTPDebug = 2;
    public function isSMTP() {}
};
$provider = (object) array('name' => 'other', 'server' => 'smtp.example.test', 'port' => 465, 'encryption' => 'ssl', 'authRequired' => true, 'username' => 'fixture-user', 'password' => 'fixture-password', 'sender' => 'sender@example.test');
delivery_invoke('IntelliSend_Form', 'configure_smtp_settings', $mailer, $provider);
delivery_expect('ssl', $mailer->SMTPSecure, 'first SMTP provider encryption');
$provider->encryption = 'none';
$provider->authRequired = false;
delivery_invoke('IntelliSend_Form', 'configure_smtp_settings', $mailer, $provider);
delivery_expect('', $mailer->SMTPSecure, 'next SMTP provider clears encryption');
delivery_expect(false, $mailer->SMTPAuth, 'next SMTP provider clears authentication');
delivery_expect('', $mailer->Username, 'next SMTP provider clears username');
delivery_expect('', $mailer->Password, 'next SMTP provider clears password');
delivery_invoke('IntelliSend_Form', 'configure_smtp_debugging', $mailer);
delivery_expect(0, $mailer->SMTPDebug, 'disabled debug does not inherit earlier mailer state');
$GLOBALS['intellisend_test_email'] = true;
IntelliSend_Form::log_email_success(array());
IntelliSend_Form::log_email_failure(new WP_Error());
unset($GLOBALS['intellisend_test_email']);
delivery_expect(0, IntelliSend_Database::$reports, 'isolated test mail is not logged by retained hooks');

add_filter('wp_mail_content_type', function () { return 'text/html'; });
add_filter('wp_mail_from', function () { return 'filtered@example.test'; });
add_filter('wp_mail_from_name', function () { return 'Filtered Name'; });
$mail = array('to' => 'Person <to@example.test>', 'subject' => 'Re: Ticket', 'message' => '<h1>Hello</h1>', 'headers' => array('Cc: to@example.test, cc@example.test', 'Bcc: cc@example.test, hidden@example.test'));
$message = delivery_invoke('IntelliSend_SendGrid', 'normalize_message', $provider, $mail);
delivery_expect('text/html', $message['content_type'], 'API honours core HTML content filter');
delivery_expect(true, $message['is_html'], 'API HTML payload flag follows filtered type');
delivery_expect('sender@example.test', $message['from_email'], 'configured sender retains precedence');
delivery_expect('Filtered Name', $message['from_name'], 'API honours sender name filter');
delivery_expect(array('wp_mail_from', 'wp_mail_from_name', 'wp_mail_content_type', 'wp_mail_charset'), $GLOBALS['delivery_filter_calls'], 'core mail filters run once');
delivery_expect(1, count($message['cc']), 'Cc excludes To duplicates');
delivery_expect(1, count($message['bcc']), 'Bcc excludes To and Cc duplicates');
$provider->sender = '';
$message = delivery_invoke('IntelliSend_SendGrid', 'normalize_message', $provider, $mail);
delivery_expect('filtered@example.test', $message['from_email'], 'sender filter applies without provider override');
$provider->sender = 'sender@example.test';
$GLOBALS['delivery_filters'] = array();
$latin_mail = $mail;
$latin_mail['message'] = "Caf\xe9";
$latin_mail['headers'] = array('Content-Type: text/plain; charset=ISO-8859-1');
$latin_message = delivery_invoke('IntelliSend_SendGrid', 'normalize_message', $provider, $latin_mail);
if (function_exists('iconv') || function_exists('mb_convert_encoding')) {
    delivery_expect("Caf\xc3\xa9", $latin_message['body'], 'declared non-UTF8 content converts for JSON');
    add_filter('wp_mail_charset', function () { return 'ISO-8859-1'; });
    $latin_mail['headers'] = array();
    $latin_message = delivery_invoke('IntelliSend_SendGrid', 'normalize_message', $provider, $latin_mail);
    delivery_expect("Caf\xc3\xa9", $latin_message['body'], 'charset filter controls conversion');
} else {
    delivery_expect(true, is_string($latin_message), 'unsupported charset conversion fails before HTTP');
}
$GLOBALS['delivery_filters'] = array();
$message = delivery_invoke('IntelliSend_SES', 'normalize_message', $provider, $mail);
$message['attachments'] = array(array('filename' => 'fixture.txt', 'type' => 'text/plain', 'content' => base64_encode('fixture contents')));
$payload = delivery_invoke('IntelliSend_SES', 'build_payload', $provider, $message);
$mime = base64_decode($payload['Content']['Raw']['Data']);
delivery_expect(true, false !== strpos($mime, "Subject: Re: Ticket\r\n"), 'SES attachment subject preserves punctuation without quotes');
delivery_expect(false, false !== stripos($mime, 'Bcc:'), 'SES raw MIME never exposes Bcc header');
delivery_expect(array('hidden@example.test'), $payload['Destination']['BccAddresses'], 'SES keeps Bcc in delivery envelope');
delivery_expect(false, false !== strpos($mime, 'hidden@example.test'), 'SES raw MIME excludes hidden recipient address');
$message['subject'] = "Caf\xc3\xa9";
$payload = delivery_invoke('IntelliSend_SES', 'build_payload', $provider, $message);
delivery_expect(true, false !== strpos(base64_decode($payload['Content']['Raw']['Data']), 'Subject: =?UTF-8?B?'), 'SES non-ASCII subject remains MIME encoded');

foreach (array(array('IntelliSend_SendGrid', 202), array('IntelliSend_Brevo', 201), array('IntelliSend_SES', 200)) as $transport) {
    $GLOBALS['delivery_response'] = array('response' => array('code' => $transport[1]), 'body' => '{}', 'headers' => array());
    $result = $transport[0]::send($provider, $mail);
    delivery_expect(true, $result['success'], $transport[0] . ' accepted response');
    $request = end($GLOBALS['delivery_requests']);
    delivery_expect(true, is_array(json_decode($request['arguments']['body'], true)), $transport[0] . ' sends valid JSON');
}
$GLOBALS['delivery_response'] = new WP_Error();
delivery_expect(false, IntelliSend_SendGrid::send($provider, $mail)['success'], 'HTTP error is returned as failed delivery');
$bad_mail = $mail;
$bad_mail['message'] = "invalid \xff UTF-8";
$requests_before = count($GLOBALS['delivery_requests']);
delivery_expect(false, IntelliSend_SendGrid::send($provider, $bad_mail)['success'], 'unencodable content fails locally');
delivery_expect($requests_before, count($GLOBALS['delivery_requests']), 'unencodable content does not make an HTTP request');

IntelliSend_Database::$settings = (object) array('antiSpamApiKey' => 'synthetic%2F<key>', 'antiSpamEndPoint' => 'https://saved.example.test/check');
$checker = new IntelliSend_SpamCheck();
$GLOBALS['delivery_response'] = array('response' => array('code' => 200), 'body' => '{"isSpam":"false","score":0}', 'headers' => array());
$verdict = $checker->check('fixture message', '', 'https://submitted.example.test/check');
delivery_expect(false, $verdict['isSpam'], 'string false never becomes a spam verdict');
$request = end($GLOBALS['delivery_requests']);
delivery_expect('https://submitted.example.test/check', $request['url'], 'spam test uses submitted endpoint without persisting');
delivery_expect('https://saved.example.test/check', IntelliSend_Database::$settings->antiSpamEndPoint, 'saved spam endpoint remains unchanged');
delivery_expect('synthetic%2F<key>', $request['arguments']['headers']['X-API-Key'], 'API key bytes are preserved');
$GLOBALS['delivery_response']['body'] = '{"isSpam":{}}';
delivery_expect(false, $checker->check('fixture')['success'], 'malformed spam verdict is rejected');
$GLOBALS['delivery_response']['body'] = '{"valid":true}';
$GLOBALS['delivery_response']['response']['code'] = 401;
delivery_expect(false, $checker->validate_api_key()['success'], 'HTTP failure cannot validate key');
$GLOBALS['delivery_response']['body'] = '{"valid":"false"}';
$GLOBALS['delivery_response']['response']['code'] = 200;
delivery_expect(false, $checker->validate_api_key()['success'], 'string false cannot validate key');
$requests_before = count($GLOBALS['delivery_requests']);
delivery_expect(false, $checker->check('fixture', "bad\r\nkey")['success'], 'header-breaking spam key rejected');
delivery_expect($requests_before, count($GLOBALS['delivery_requests']), 'invalid spam key never creates HTTP request');
IntelliSend_Database::$settings = false;
delivery_expect(false, $checker->check('fixture')['success'], 'missing settings return an error without warnings');

echo 'Delivery regressions passed: ' . $assertions . " assertions; synthetic HTTP only.\n";
