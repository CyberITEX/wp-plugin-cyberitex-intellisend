"""Browser regressions for admin scripts, using local jQuery and synthetic data only.
Run: python tests/admin-browser.py --jquery <wordpress/wp-includes/js/jquery/jquery.min.js>
Requires Python Playwright and its Chromium browser. No email or API requests are sent.
"""
import argparse
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--jquery', type=Path, required=True)
args = parser.parse_args()
checks = 0

def check(condition, description):
    global checks
    assert condition, description
    checks += 1
    print('PASS', description)

with sync_playwright() as runtime:
    browser = runtime.chromium.launch(headless=True)
    page = browser.new_page()
    errors, requests = [], []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.route('**/*', lambda route: (requests.append(route.request.url), route.abort()))

    def fixture(body, script, name):
        page.set_content(body)
        page.add_script_tag(path=str(args.jquery.resolve()))
        page.add_style_tag(path=str(ROOT / 'admin/css/intellisend-admin.css'))
        source = (ROOT / 'admin/js' / script).read_text(encoding='utf-8')
        source = source.replace(name + '.init();', '')
        source = source.replace('})(jQuery);', 'window.subject = ' + name + '; })(jQuery);')
        page.add_script_tag(content=source)
        page.evaluate('jQuery.fx.off = true; window.ajaxurl = "/blocked-ajax"; window.intellisendData = {ajax_url: "/blocked-ajax", nonce: "synthetic"};')

    fixture('''<div class="wrap"><h1>Routing</h1><div id="row"><input class="rule-name"><select class="rule-provider"><option value="other">Other</option></select><input class="rule-recipients"><textarea class="rule-patterns"></textarea><select class="rule-pattern-type"><option value="contains">Contains</option></select><input class="rule-priority" value="2"><select class="rule-enabled"><option value="1">On</option></select><select class="rule-antispam"><option value="0">Off</option></select></div><div class="recipients-container"><div class="recipients-tags"></div><input class="rule-recipients"></div></div>''', 'routing-page.js', 'RoutingManager')
    encoded = page.evaluate('''() => { const row=jQuery('#row'); row.find('.rule-name').val('Order "priority" & support'); row.find('.rule-patterns').val('subject "quoted" & /\\\\d+/'); return Object.fromEntries(new URLSearchParams(subject.createSerializedFormData(row, 7))); }''')
    check(encoded['name'] == 'Order "priority" & support', 'Routing preserves quoted rule names and ampersands')
    check(encoded['subject_patterns'] == 'subject "quoted" & /\\d+/', 'Routing preserves quoted patterns and backslashes')
    check(encoded['id'] == '7' and encoded['anti_spam_enabled'] == '0', 'Routing retains backend field names and explicit disabled state')
    rejected = page.evaluate('''() => { const row=jQuery('#row'); row.append(jQuery('<input class="rule-recipients-input">').val('invalid-recipient')); return !subject.validateRule(row); }''')
    check(rejected, 'Routing rejects an invalid pending recipient instead of silently discarding it')
    page.evaluate('subject.showNotification("error", "<img src=x onerror=alert(1)>")')
    check(page.locator('.intellisend-notification img').count() == 0, 'Routing notification renders server text without HTML')
    page.evaluate('subject.addRecipientTag(jQuery(".recipients-container"), "person@example.test")')
    check(page.get_by_role('button', name='Remove person@example.test').count() == 1, 'Recipient removal uses a named button')

    fixture('''<div class="wrap intellisend-settings-wrap"><form id="intellisend-settings-form"><input id="api-key" value="synthetic-secret"><input id="anti-spam-endpoint" value="https://example.test/check"><input id="has-existing-api-key" value="0"><input id="intellisend_settings_nonce" value="synthetic"><button type="submit">Save</button></form><label for="logs-retention-days">Retention</label><input type="hidden" id="logs-retention-days" value="30"><section class="intellisend-settings-section"><h2 class="intellisend-settings-section-title">General settings</h2><div class="intellisend-settings-row"><input aria-label="Setting"></div></section></div>''', 'settings-page.js', 'IntelliSendSettings')
    page.evaluate('''() => { window.IntelliSendToast = {success(){}, error(){}}; jQuery.ajax = options => { options.success({success:true,data:{message:'Saved'}}); options.complete(); }; subject.saveAntiSpamSettings(jQuery('form'), jQuery('button[type=submit]'), 'Save'); subject.setupLogsRetentionDropdown(); subject.setupSectionToggle(); }''')
    check(page.locator('#has-existing-api-key').input_value() == '1' and page.locator('#api-key').input_value() == '', 'First secret save updates presence state and clears the secret field')
    check(page.get_by_label('Retention').get_attribute('id') == 'logs-retention-select', 'Retention label targets the visible select')
    collapse = page.get_by_role('button', name='General settings')
    collapse.focus(); page.keyboard.press('Enter')
    check(collapse.get_attribute('aria-expanded') == 'false', 'Settings sections collapse from keyboard and expose state')
    page.keyboard.press('Space')
    check(collapse.get_attribute('aria-expanded') == 'true', 'Settings sections reopen from keyboard')

    fixture('<body></body>', 'providers-page.js', 'IntelliSendProviders')
    page.evaluate('subject.showNotification("error", "<img src=x onerror=alert(1)>")')
    check(page.locator('.intellisend-notification img').count() == 0 and page.get_by_role('alert').count() == 1, 'Provider notification uses safe announced text')

    page.set_content('<body></body>')
    page.add_script_tag(path=str(args.jquery.resolve()))
    page.add_script_tag(path=str(ROOT / 'admin/js/intellisend-toast.js'))
    page.evaluate('IntelliSendToast.error("<img src=x onerror=alert(1)>")')
    check(page.locator('.toast-message img').count() == 0 and page.get_by_role('button', name='Dismiss notification').count() == 1, 'Shared toast renders safe text and keyboard dismissal')

    fields = ''.join('<div id="' + name + '"></div>' for name in ['report-date','report-status','report-provider','report-routing','report-from','report-to','report-subject','report-message','report-headers','report-spam-section','report-spam-score','report-error-section','report-error-message'])
    fixture('<div class="intellisend-admin"><h1>Reports</h1><button id="trigger" class="view-report" data-id="1">View report</button><table class="intellisend-table"><thead><tr><th class="sortable" data-sort="date">Date</th></tr></thead></table><div id="view-report-modal" class="intellisend-modal" role="dialog" aria-modal="true" aria-labelledby="report-modal-title" style="display:none"><div class="intellisend-modal-content"><h3 id="report-modal-title">Email Report Details</h3><button class="intellisend-modal-close">Close</button>' + fields + '</div></div></div>', 'reports-page.js', 'IntelliSendReports')
    page.evaluate('''() => { jQuery.ajax = options => options.success({success:true,data:{date:'2026-09-14 12:00:00', status:'failed', isSpam:'0', message:'<p>Hello <strong>world</strong></p><img src="https://remote.invalid/pixel"><a href="https://remote.invalid/link">Link</a><script>parent.injected=true</script>', log:'Synthetic delivery error'}}); subject.setupEventListeners(); subject.setupSortableColumns(); }''')
    page.locator('#trigger').focus(); page.locator('#trigger').click()
    check(page.locator('.intellisend-modal-close').evaluate('(el) => el === document.activeElement'), 'Report dialog moves keyboard focus inside')
    page.keyboard.press('Tab')
    check(page.locator('.report-message-preview').evaluate('(el) => el === document.activeElement'), 'Report preview is keyboard reachable within dialog')
    page.keyboard.press('Tab')
    check(page.locator('.intellisend-modal-close').evaluate('(el) => el === document.activeElement'), 'Report dialog keeps keyboard focus inside')
    check(page.locator('#report-error-message').text_content() == 'Synthetic delivery error', 'Failed report displays the stored error log')
    check(not page.locator('#report-spam-section').is_visible(), 'String zero spam flag is not treated as spam')
    frame = page.locator('.report-message-preview')
    check(frame.get_attribute('sandbox') == 'allow-same-origin' and "default-src 'none'" in frame.get_attribute('srcdoc'), 'Report HTML is isolated with sandbox and restrictive CSP')
    check('href=' not in frame.get_attribute('srcdoc') and 'https://remote.invalid' not in frame.get_attribute('srcdoc'), 'Report preview strips remote image and link destinations')
    check('<strong>world</strong>' in frame.get_attribute('srcdoc'), 'Report preview preserves email text formatting')
    page.frame_locator('.report-message-preview').locator('body').click()
    page.keyboard.press('Escape')
    check(page.locator('#trigger').evaluate('(el) => el === document.activeElement'), 'Escape inside report preview closes dialog and restores trigger focus')
    check(page.get_by_role('button', name='Date', exact=True).count() == 1, 'Sortable report column has a native keyboard control')
    page.wait_for_timeout(100)
    check(not requests, 'Synthetic browser checks made no external requests')
    check(not errors, 'Admin scripts produced no browser runtime errors: ' + str(errors))
    browser.close()
print(f'{checks} browser regression checks passed.')
