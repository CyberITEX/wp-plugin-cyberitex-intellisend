<?php
/** Run with php tests/reports-regression.php; isolated SQLite data, no WordPress or network. */
if (PHP_SAPI !== 'cli') {
    exit;
}
define('ABSPATH', __DIR__ . '/');
function get_option($name) { return false; }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, $args); }
function sanitize_sql_orderby($value) { return preg_match('/^[a-zA-Z_]+ (ASC|DESC)$/i', $value) ? $value : false; }

class IntelliSend_Report_Test_DB {
    public $prefix = 'wp_';
    public $pdo;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE wp_intellisend_reports (id INTEGER PRIMARY KEY, date TEXT, subject TEXT, sender TEXT, recipients TEXT, message TEXT, status TEXT, log TEXT, antiSpamEnabled INTEGER, isSpam INTEGER, routingRuleId INTEGER, providerName TEXT)');
        $insert = $this->pdo->prepare('INSERT INTO wp_intellisend_reports VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        for ($i = 1; $i <= 65; $i++) {
            $insert->execute(array($i, '2026-09-' . ($i <= 40 ? '01' : '14') . ' 12:00:00', 'Order ' . $i, 'sender@example.test', $i === 7 ? 'needle@example.test' : 'recipient@example.test', $i === 9 ? 'A unique body phrase' : 'Message', $i % 3 === 0 ? 'blocked' : ($i % 3 === 1 ? 'sent' : 'failed'), '', 1, $i % 3 === 0 ? 1 : 0, $i % 2 + 1, $i % 2 === 0 ? 'other' : 'sendgrid-api'));
        }
    }
    public function prepare($query, ...$args) {
        if (isset($args[0]) && is_array($args[0])) { $args = $args[0]; }
        return preg_replace_callback('/%[sd]/', function ($match) use (&$args) {
            $value = array_shift($args);
            return $match[0] === '%d' ? (string) (int) $value : $this->pdo->quote((string) $value);
        }, $query);
    }
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function get_results($query) { return $this->pdo->query($query)->fetchAll(PDO::FETCH_OBJ); }
    public function get_var($query) { return $this->pdo->query($query)->fetchColumn(); }
}
$wpdb = new IntelliSend_Report_Test_DB();
require dirname(__DIR__) . '/includes/class-database.php';
$failures = 0;
$checks = 0;
function report_check($label, $callback) {
    global $failures, $checks;
    $checks++;
    try {
        if (!$callback()) { throw new RuntimeException('Unexpected result'); }
        echo "PASS: $label\n";
    } catch (Throwable $error) {
        $failures++;
        echo "FAIL: $label: " . $error->getMessage() . "\n";
    }
}
report_check('Default page has 20 reports', function () { return count(IntelliSend_Database::get_reports()) === 20; });
report_check('Recipient search uses stored recipients column', function () { $rows = IntelliSend_Database::get_reports(array('search' => 'needle@example.test')); return count($rows) === 1 && (int) $rows[0]->id === 7; });
report_check('Message search matches row and count', function () { return count(IntelliSend_Database::get_reports(array('search' => 'unique body phrase'))) === 1 && IntelliSend_Database::count_reports(array('search' => 'unique body phrase')) === 1; });
$cases = array(
    'provider' => array('providerName' => 'sendgrid-api'),
    'routing rule' => array('routingRuleId' => 1),
    'spam flag' => array('is_spam' => 1),
    'legacy spam flag' => array('isSpam' => 0),
    'status' => array('status' => 'failed'),
    'date window' => array('date_from' => '2026-09-14', 'date_to' => '2026-09-14'),
    'combined filters' => array('providerName' => 'sendgrid-api', 'routingRuleId' => 2, 'status' => 'sent', 'date_from' => '2026-09-14'),
);
foreach ($cases as $label => $filters) {
    report_check($label . ' uses identical list and count filters', function () use ($filters) {
        $rows = IntelliSend_Database::get_reports(array_merge($filters, array('per_page' => 100)));
        return count($rows) > 0 && count($rows) === IntelliSend_Database::count_reports($filters) && count($rows) === IntelliSend_Database::get_reports_count($filters);
    });
}
report_check('Filtered last page agrees with total', function () { return count(IntelliSend_Database::get_reports(array('providerName' => 'sendgrid-api', 'page' => 2))) === 13 && IntelliSend_Database::count_reports(array('providerName' => 'sendgrid-api')) === 33; });
report_check('Equal timestamps have stable pagination', function () {
    $first = IntelliSend_Database::get_reports(array('page' => 1));
    $second = IntelliSend_Database::get_reports(array('page' => 2));
    return (int) $first[0]->id === 65 && (int) $second[0]->id === 45;
});
report_check('Unknown sort column falls back safely', function () { return count(IntelliSend_Database::get_reports(array('orderby' => 'does_not_exist'))) === 20; });
report_check('SQL-looking sort text cannot modify tables', function () { return count(IntelliSend_Database::get_reports(array('orderby' => 'date; DROP TABLE wp_intellisend_reports; --'))) === 20 && IntelliSend_Database::count_reports() === 65; });
report_check('Zero page size still returns a bounded page', function () { return count(IntelliSend_Database::get_reports(array('per_page' => 0))) === 1; });
echo "$checks checks, $failures failures\n";
exit($failures ? 1 : 0);
