# IntelliSend audit and implementation report

Date: 14 September 2026, updated 17 September 2026. Result: compatible fixes and responsive light/dark modes implemented through version 1.2.2; decisions and validation limits remain below.

## Scope and starting state

The review covered every production PHP file, all admin views, JavaScript and CSS, bootstrap, activation/deactivation/uninstall, documentation, data access and the SMTP/API delivery paths. The parent `CLAUDE.md` was read first. The working tree already contained extensive uncommitted API-provider work. A separate temporary copy captured that starting state; changes were reviewed against it as well as Git. Existing work was preserved. No release was published and the repository's existing ZIP was not rebuilt.

IntelliSend serves WordPress administrators who need central email routing and diagnostics, including mail from form plugins using `wp_mail()`. Its main workflows are configuring/testing a provider, selecting settings and anti-spam credentials, editing routing rules, and filtering/inspecting/deleting reports. The existing menus, cards, branding and inline editing remain.

The plugin stores providers, settings, routing and reports in four site-prefixed custom tables. `intellisend_db_version` tracks the schema. Provider passwords/API keys and the anti-spam key use the existing AES-256-CBC storage format. Provider credentials may also come from the existing constant/environment overrides.

`wp_mail`, `pre_wp_mail`, `phpmailer_init`, `wp_mail_succeeded` and `wp_mail_failed` connect routing, spam checks, SMTP/API delivery and logging. Authenticated `wp_ajax_intellisend_*` actions handle administration; no REST or unauthenticated AJAX routes were found. Active handlers require `manage_options`. `admin/ajax/routing-handlers.php` has no include/reference from the active implementation; its issues are not evidence of a reachable endpoint.

SMTP uses WordPress PHPMailer. SendGrid, Brevo and SES use the WordPress HTTP API; SES signs requests with SigV4. Anti-spam requests use the configured checker endpoint. There is no Composer/npm dependency manifest, committed initial test suite, build process or static-analysis configuration. Initial checks passed for 24 PHP and seven JavaScript files on PHP 8.4.8 / Node 24.14.1.

## Plan recorded before implementation

The preimplementation findings and plan were presented in the conversation before any repository edit. Work proceeded in this order: security/data integrity, delivery and report correctness, accessibility/feedback, then small visual and maintainability refinements. No schema, credential-format, retention, uninstall or default-provider workflow change was authorised or performed. Additional confirmed defects found during validation were addressed within that compatible scope.

| Confirmed finding and reproduction/evidence | Severity | Impact and implemented solution | Files |
| --- | --- | --- | --- |
| Report read/delete handlers accepted literal `ee86b922eb` after WordPress nonce verification failed. | High | Removed the bypass, require genuine session nonces, reject malformed IDs. Real admin/invalid-nonce/subscriber paths were checked. | `admin/class-ajax.php` |
| Notification builders concatenated response text into HTML. | Medium | Response messages now render as text; status announcements and named dismiss controls were added. This was an unsafe rendering sink, not a demonstrated anonymous exploit. | `admin/js/*.js` |
| Submitted provider ID/name/type could disagree; WordPress request slashing corrupted credentials containing quotes/backslashes. | Medium | Bind saves to the stored provider identity, reject malformed IDs, unslash scalar secrets once, preserve bytes during encryption, keep blank-field stored-secret behaviour. | `admin/class-ajax.php`, `includes/class-database.php` |
| `Order *` did not match `Order 123`; `Code ?` did not match `Code A`; lowercasing turned regex `\D` into `\d`; comma splitting broke `{1,3}`. | Medium | Correct escaped wildcard conversion, preserve regex syntax/case, parse top-level comma-separated expressions, reject invalid expressions when saving. Literal braces and character-class edge cases have regression coverage. | `includes/class-form.php`, `admin/class-ajax.php` |
| Reusing PHPMailer after SSL left encryption on for a subsequent provider configured without it; provider saves forced TLS, including port 465. | Medium | Reset per-send SMTP state; persist SSL for 465 and preserve existing authentication/configuration where appropriate. | `includes/class-form.php`, `admin/class-ajax.php` |
| SMTP tests installed anonymous hooks without reliable restoration; exceptions left test state active. | Medium | Scope callbacks, temporary mailer and globals with `finally`, preserving their previous presence/value; prevent duplicate test reporting. | `admin/class-ajax.php`, `includes/class-form.php` |
| API mail ignored normal content-type/name/charset filters; SES attachment MIME added literal quotes to `Re: Ticket`. | Medium | Apply applicable core mail filters, convert non-UTF-8 text before JSON encoding, fail locally on invalid encoding, separate subject encoding from address-phrase quoting. Forced provider sender precedence remains. | `includes/class-api-transport.php`, `includes/class-ses.php` |
| Spam Test reloaded the saved endpoint instead of testing the submitted one; string `false` became a true spam verdict; key validation could accept a success body on HTTP 401. | Medium | Optional temporary endpoint override, explicit response/status/boolean validation, missing-settings handling and CR/LF rejection for header credentials. Stored settings remain unchanged by tests. | `includes/class-spamcheck.php`, `admin/class-ajax.php` |
| Report search queried nonexistent `recipient`; counts omitted provider/rule filters and disagreed on spam/body search; equal timestamps paginated unstably. | Medium | Shared prepared predicates for rows and both count methods; use `recipients`, search body consistently, allowlist sort columns and add ID tie-breaking. Clamp negative/stale page links to the filtered result set. | `includes/class-database.php`, reports view |
| Report filters used `spam`/`error` while current writes use `blocked`/`failed`; modal expected a nonexistent error field and treated string `0` as spam. | Medium | Use stored statuses and diagnostic log, handle numeric spam flags, preserve WordPress wall-clock dates with a site-time label. | Reports view/JavaScript |
| Quoted routing values were interpolated into hidden-input HTML before serialization; invalid pending recipients were silently dropped. | Medium | Serialize data directly; keep quotes/backslashes/ampersands intact and block saving invalid recipients with field feedback. Validate configured providers, recipients and priorities at the server boundary too. | Routing JavaScript, AJAX handlers |
| Report HTML was rendered inside the admin document and could load remote tracking resources. | Medium | Preserve formatted content in an isolated iframe with restrictive CSP, scripts/forms disabled, and remote images/links removed. Existing `wp_kses_post` meant this was not a demonstrated script-XSS exploit. | Reports JavaScript |
| First secret save left the browser's key-presence flag false. | Low | Update presence state and clear secret inputs after successful saves; add a configuration link in the empty settings state. | Settings/provider views and JavaScript |
| Sorting, section collapse and recipient removal lacked keyboard controls; report dialog lacked managed focus and labels. | Low–medium | Native buttons/labels, keyboard toggles, dialog focus trapping and restoration including its preview, visible focus, reduced-motion support and narrow-screen refinements. | Admin views/JS, shared admin CSS |
| Real WordPress loading raised `Identifier 'IntelliSendToast' has already been declared`. | Low | The settings view and asset loader now use the same toast handle, so WordPress loads it once. | `admin/views/settings-page.php` |

## Validation performed

All reported tests use synthetic data. No live credentials, mail delivery, provider configuration or production database was used.

| Check | Result and limits |
| --- | --- |
| PHP syntax | All 28 PHP files pass `php -l` under PHP 8.4.8, including the new tests and index stub. |
| JavaScript syntax | All seven production scripts pass `node --check`. |
| Older PHP grammar | All 24 production PHP files parse with `php-parser` configured for PHP 7.0. This is syntax evidence, not an older-PHP runtime matrix. |
| Database regressions | 15 pass using actual in-memory SQLite queries. The starting implementation failed 11, including unknown-column errors and mismatched counts. |
| Admin boundaries | 110 pass: permissions/nonces, malformed IDs, provider identity/configuration, exact secrets, SMTP cleanup on success/exception, routing validation and secret-safe debug output. |
| Delivery | 62 assertions pass: pattern matching/parser compatibility, repeated SMTP state, core mail filters/charset, all three captured vendor payloads and failure responses, SES subject/Bcc handling, spam result and endpoint validation. |
| Browser fixtures | 29 pass using real scripts, local WordPress jQuery and Playwright Chromium, with external requests blocked. Covers serialization, text rendering, key state, labels, keyboard/focus, sandbox preview, light/dark surfaces and controls, out-of-wrapper dialogs and no runtime errors. |
| Actual WordPress integration | Activation succeeded on disposable WordPress 6.7.2 with the WordPress SQLite integration plugin and PHP 8.4.8. The original 39 browser/AJAX checks pass: four screens at 1440px and 390px, no document overflow, search/status filters, real-nonce report dialog, keyboard closure/focus return, invalid/fixed/missing nonce rejection, authorised selected fixture deletion, subscriber denial and no browser runtime errors. Two targeted checks verify negative and stale page links render the correct first/last page. Version 1.2.2 adds 58 live theme checks across both colour modes and widths, including page and control colours, theme asset loading, full page canvas, modal shell and isolated preview. Screenshots were inspected. |
| Final review | Whitespace/diff review against Git and the saved starting tree; independent delivery review identified two parser edge cases which were fixed and tested. |

The local WordPress lab blocked outbound HTTP and mail. WordPress update checks consequently produced expected connectivity warnings. A temporary fixture-seeding script also needed a `$wpdb` scope correction; this was lab code, not a plugin runtime defect. No MySQL/MariaDB installation, full supported WordPress/PHP matrix, network multisite, production SMTP/API send, two-site migration or third-party form-plugin end-to-end validation was performed. There was no existing build/static-analysis task to run.

Re-run committed regression checks from the plugin directory:

```powershell
php tests/reports-regression.php
php tests/admin-regression.php
php tests/delivery-regression.php
python tests/admin-browser.py --jquery <local-wordpress-path>/wp-includes/js/jquery/jquery.min.js
```

The report test needs PDO SQLite; the browser test needs Python Playwright and its Chromium browser. Test entry points are CLI-only or Python. No new runtime dependency was added to the plugin.

## Before and after workflows

- Provider saves retain exact credentials, identify the correct stored provider and respect SMTP configuration. Test sends restore the prior request state. The existing automatic default promotion on save is explicitly unchanged.
- Routing still uses the same inline editor and priority model. Quoted input is preserved; invalid recipients/providers/patterns produce feedback before saving.
- Report searches and totals agree; blocked/failed filters work. Details preserve email formatting while preventing remote resource loads. Keyboard users can sort, open, navigate and close the dialog.
- Saving the first anti-spam key no longer causes a false missing-key error on the next operation. Sections, labels, notifications and focus states now support keyboard/screen-reader use.

## Remaining findings and decisions

These are not fixed claims. They are separated from completed scope.

| Finding | Evidence, consequence and next step |
| --- | --- |
| **High: external spam forwarding** | `class-form.php` forwards flagged messages to `blackhole@cyberitex.com`; SMTP retains attachments while the API spam branch removes them. Previous documentation called this a discard. Current descriptions now state the actual behaviour. Replacing forwarding with local successful discard, changing the address or changing attachment handling needs an explicit delivery/privacy decision. |
| **Medium: default-provider changes are implicit** | `providers-page.php` retains hidden `is_default=1`; saving a provider can update site/default-rule routing. An explicit default choice changes the established workflow and was deferred as requested. Settings/default-rule consistency should be covered when that workflow is revised. |
| **Medium: schema failure tracking** | `create_tables()` can return success after failures and activation/upgrade record the schema version without proving completion. Remediation needs migration design, failure injection and real MySQL upgrade fixtures; no schema/version migration was introduced here. |
| **Medium: retention and uninstall policy** | Retention has no scheduled purge. `uninstall.php` still targets legacy `cyberitex_*` storage and leaves current IntelliSend tables/version behind. Automatically deleting historical mail or changing uninstall cleanup needs explicit approval and recovery requirements. |
| **Compatibility: older WordPress logging** | The declared WordPress 5.7 minimum predates `wp_mail_succeeded` (5.9); SMTP success reporting on 5.7–5.8 remains a limitation. A minimum-version change or compatible fallback needs separate work. The readme minimums now match the existing plugin header; they are not a new full-runtime support claim. |
| **Compatibility: specialised API mail** | Arbitrary custom mail headers and newer embedded-image features are not fully represented by the current API normalisation. Validate the actual form/mail plugins before production use. |
| **Security risk: configurable checker destination** | The checker still accepts administrator-configured endpoints. Credential confidentiality depends on the chosen endpoint/transport and redirect behaviour. Tightening destination/HTTPS policy can break custom integrations; assess and agree the allowed endpoints first. No remote SSRF exploit was attempted. |
| **Security/operations: credential storage** | Existing CBC encryption, salt coupling and fallback behaviour remain. Authenticated encryption, salt rotation/recovery and missing-OpenSSL behaviour need a separately approved backward-compatible storage plan. |
| **Logging/i18n/scale** | Spam scores still lack a storage column; historical text domains and English JavaScript strings are inconsistent. Full message/log retention and unbounded routing/provider lists need workload-specific review. Schema indexes, score persistence, translation consolidation and caching were not added speculatively. |
| **Multisite and release proof** | Tables are site-prefixed, but network activation/new-site provisioning and site-by-site upgrade/uninstall require a separate disposable multisite/MySQL run. Live deliverability, account ACLs and production readiness are unverified. |

## Files changed in this audit

This list is relative to the uncommitted starting tree, not the last Git commit.

- Backend: `admin/class-ajax.php`; `includes/class-database.php`, `class-form.php`, `class-api-transport.php`, `class-ses.php`, `class-spamcheck.php`.
- Views: `admin/views/settings-page.php`, `providers-page.php`, `routing-page.php`, `reports-page.php`.
- Scripts: `admin/js/intellisend-admin.js`, `intellisend-toast.js`, `providers-page.js`, `reports-page.js`, `routing-page.js`, `settings-page.js`.
- Styling: `admin/css/intellisend-admin.css`.
- Version/documentation: `cyberitex-intellisend.php`, `README.md`, `readme.txt`, `CHANGELOG.md`, this report.
- New checks: `tests/reports-regression.php`, `admin-regression.php`, `delivery-regression.php`, `admin-browser.py`, `index.php`.

No existing data was migrated or deleted by this implementation. Valid report-deletion tests touched only the disposable local fixture. Existing table names, columns, encrypted values, provider IDs, genuine nonce action aliases, AJAX action names and transport choices were preserved.
