# CLAUDE.md

Guidance for working in this repository.

## What this is

**IntelliSend** (`cyberitex-intellisend`) is a WordPress plugin by CyberITEX that intercepts every
`wp_mail()` call, routes it through a configured email provider (SMTP or HTTP API), optionally
screens it for spam, and logs the result. It works with any form plugin that goes through
`wp_mail()` (Contact Form 7, Gravity Forms, WPForms, Ninja Forms).

The plugin lives in the `cyberitex-intellisend/` subdirectory; that folder is what ships. The
repo root also holds a stale `cyberitex-intellisend.zip` build artifact.

There is no build step, no package manager, no committed test suite, and no Composer dependency.
Files are edited in place and loaded directly by WordPress.

## Layout

```
cyberitex-intellisend/
├── cyberitex-intellisend.php   Plugin header, constants, activation hooks, bootstrap
├── includes/
│   ├── class-intellisend.php   Loads dependencies, registers admin + public hooks
│   ├── class-activator.php     Activation / deactivation
│   ├── class-database.php      All schema and CRUD (the largest and most central file)
│   ├── class-form.php          wp_mail interception, routing, sending, logging
│   ├── class-api-transport.php Abstract base + registry for HTTP mail transports
│   ├── class-sendgrid.php      SendGrid Web API v3
│   ├── class-brevo.php         Brevo transactional email API
│   ├── class-ses.php           Amazon SES v2 API, including AWS SigV4 signing
│   └── class-spamcheck.php     CyberITEX AntiSpamCheck API client
└── admin/
    ├── class-admin.php         Menus, asset enqueuing (per-page, keyed off $hook)
    ├── class-ajax.php          Every AJAX handler
    ├── ajax/routing-handlers.php
    ├── views/                  One render function per page
    ├── js/                     One file per page, plus a shared toast library
    └── css/                    One file per page
```

Every directory contains an `index.php` silence stub. Keep adding them to new directories.

## Architecture notes

### Everything is in custom tables, not options

Four tables, all prefixed `{$wpdb->prefix}intellisend_`:

- **providers** - one row per email provider (`google`, `microsoft`, `sendgrid`, `sendgrid-api`,
  `brevo`, `brevo-api`, `ses`, `ses-api`, `other`, ...). `type` is `smtp` or `api`. `configured`
  gates whether the provider can be selected or used. Rows are defined in
  `get_default_providers()` and inserted by name on every schema upgrade, so presets added in a
  later release reach existing sites; existing rows are never overwritten.
- **settings** - a single row. `defaultProviderName`, anti-spam endpoint and key, test recipient,
  log retention, `debug_enabled`.
- **routing** - rules matched against the email subject. `priority` ascending, `-1` marks the
  catch-all default rule. `pattern_type` is one of wildcard / starts_with / contains / ends_with /
  regex.
- **reports** - the email log. Statuses used: `sent`, `blocked` (spam), `failed`.

The only WordPress option used is `intellisend_db_version`.

### The send pipeline

Hooks registered in `IntelliSend_Form::setup_email_hooks()`, in the order WordPress fires them
inside `wp_mail()`:

1. `wp_mail` -> `intercept_email()` resolves the routing rule, resolves the provider, runs the
   spam check, and stashes all of it in class statics (`$current_email`, `$matched_rule`,
   `$current_provider`).
2. `pre_wp_mail` -> `maybe_send_via_api()`. If the resolved provider has `type = 'api'`, it sends
   over HTTP, writes the report row, and returns a bool, which short-circuits `wp_mail()` so
   PHPMailer is never involved. Otherwise it returns the incoming value and the SMTP path
   continues.
3. `phpmailer_init` -> `configure_phpmailer()` applies SMTP host/port/encryption/credentials,
   the sender, and the rule's recipients as BCC.
4. `wp_mail_succeeded` / `wp_mail_failed` -> report logging (SMTP path only; the API path logs
   inline and re-fires these actions itself with its own handlers detached, so third-party
   listeners still see the outcome without double logging).

The reliance on class statics carried between hooks is the fragile part of this design. Anything
that changes hook order or resets state mid-send will break routing silently.

Spam verdict handling: a rule with `anti_spam_enabled` sends the body to the CyberITEX
AntiSpamCheck API. A positive verdict does **not** cancel the send; recipients are replaced with
`blackhole@cyberitex.com` so the visitor still sees a success message. That address is hardcoded
in `class-form.php`.

Test emails set `$GLOBALS['intellisend_test_email']`, which every hook checks and bails on. The
AJAX test handler also detaches and reattaches the plugin's own hooks around the send. The API
branch of `handle_test_email()` needs neither, since it calls the transport directly instead of
going through `wp_mail()`.

`handle_test_email()` resolves the provider by **name** from the `provider_id` POST field, so any
provider can be tested without being the site default. Two entry points reach it: the Settings
page (via `ajax_handler`, `intellisend_settings` nonce, tests the current default) and the
Providers page (`wp_ajax_intellisend_send_test_email`, `intellisend_providers` nonce, tests the
provider being edited). It reads credentials from the database, so unsaved form edits are not
covered by a test.

### Transports

`type = 'smtp'` goes through PHPMailer. `type = 'api'` goes through a subclass of
`IntelliSend_Api_Transport`, resolved by provider name from
`IntelliSend_Api_Transport::registry()`. API transports use the WordPress HTTP API, never Composer
packages.

| Provider | Class | Endpoint | Success |
| --- | --- | --- | --- |
| `sendgrid-api` | `IntelliSend_SendGrid` | `POST /v3/mail/send` | 202, empty body |
| `brevo-api` | `IntelliSend_Brevo` | `POST /v3/smtp/email` | 201, `{"messageId":...}` |
| `ses-api` | `IntelliSend_SES` | `POST /v2/email/outbound-emails` | 200, `{"MessageId":...}` |

The base class owns everything vendor-neutral: wp_mail header parsing, address normalization and
dedup (Cc against To, Bcc against both), attachment reading and base64 encoding, credential
resolution, log lines, and the send/response loop. A subclass only describes its vendor:

- Identity and copy: `get_label()`, `get_key_label()`, `get_region_label()`, `get_regions()`,
  `get_key_placeholder()`, `get_sender_hint()`
- Wiring: `get_default_base()`, `get_send_path()`, `get_env_constant()`
- Request: `build_payload()`, `get_auth_headers()`, and `build_headers()` when the signature has
  to cover the whole request (SES overrides this for SigV4)
- Response: `extract_error()`, `validate_api_key()`, optionally `is_success_code()`,
  `get_message_id_header()`, `get_message_id_from_body()`

To add a vendor: write the subclass, add it to `registry()`, and add a provider row to
`get_default_providers()` with `type = 'api'`. Nothing in `class-form.php`, `class-ajax.php`, the
view, or the JS needs to change; they all read from the registry and `describe_all()`.

**Credentials.** Resolution order is always: the vendor's constant, then the same name as an
environment variable, then the database column. The UI disables the field and says which source is
in use. Most vendors have one secret (`apiKey`, encrypted). Vendors that need a second, non-secret
credential set `requires_identity()` and get `get_identity()` / `get_identity_constant()`; that
value lives in the **`username`** column, not `apiKey`, because it is not a secret. SES uses this
for the AWS Access Key ID.

**SES specifics.** Signing is AWS Signature Version 4, implemented directly in
`IntelliSend_SES::sign_request()`; it is verified against the signing-key and full-request test
vectors published in the AWS documentation. The region comes from the endpoint host
(`email.<region>.amazonaws.com`) and is part of the credential scope, so endpoint and region can
never disagree. Ordinary mail uses SES `Content.Simple`; because Simple content does not carry
attachments, a message with attachments is rebuilt as a raw MIME multipart and sent as
`Content.Raw`. Bcc is deliberately omitted from the MIME headers and carried in `Destination`
instead, so the list cannot leak to recipients.

### Secrets

`IntelliSend_Database::encrypt_data()` / `decrypt_data()` use AES-256-CBC with `AUTH_SALT` as the
key. Applied to provider `password`, provider `apiKey`, and `settings.antiSpamApiKey`. Note that
`get_settings()` returns `antiSpamApiKey` already **decrypted**, while provider rows are returned
with secrets still **encrypted** - an easy asymmetry to trip over. Never echo either into a form
value or an AJAX response; the UI convention is an empty password field where blank means "keep
the stored value".

### Schema migrations

`maybe_upgrade()` runs on `admin_init`, compares `IntelliSend_Database::DB_VERSION` against the
`intellisend_db_version` option, and calls `create_tables()` when they differ. That is what lets
installs updated in place (FTP, Git pull, auto-update) pick up schema changes without
reactivation.

**Keep the `CREATE TABLE` statements in the canonical dbDelta form.** They previously used
`CREATE TABLE IF NOT EXISTS`, which silently broke every migration: dbDelta's table-name regex
(`|CREATE TABLE ([^ ]*)|`) matches the literal `IF`, so it described a table named "IF",
concluded nothing existed, and emitted no `ALTER TABLE`. dbDelta is also strict about formatting -
`PRIMARY KEY  (id)` needs two spaces, or it tries to add a duplicate key. Reintroducing either
mistake will not raise an error; it will just quietly stop migrating.

Because dbDelta fails silently when it cannot parse a definition, columns that hold credentials
are added explicitly as well, in `ensure_provider_columns()` (`SHOW COLUMNS` plus `ALTER TABLE`).
Prefer that belt-and-braces approach for anything whose absence would break sending.

To ship a schema change: update the `CREATE TABLE` statement, add an explicit column migration if
it matters, bump `DB_VERSION`.

### AJAX

Handlers live in `IntelliSend_Ajax`. Two styles coexist:

- `wp_ajax_intellisend_ajax_handler` with a `sub_action` POST field (settings page), nonce
  `intellisend_settings`.
- Dedicated `wp_ajax_intellisend_*` actions (providers, routing, reports), each with its own
  nonce: `intellisend_providers`, `intellisend_routing_nonce`, `intellisend_ajax_nonce`.

Several handlers accept more than one nonce for backward compatibility. Every handler must check
both a nonce and `current_user_can('manage_options')`.

## Conventions

- Never define anything before the `if (!defined('WPINC')) die;` (or `ABSPATH`) guard.
- Escape on output: `esc_html`, `esc_attr`, `esc_url`, `esc_html__`. Sanitize on input:
  `sanitize_text_field`, `sanitize_email`, `esc_url_raw`, `absint`.
- Text domain is `intellisend` in most strings, though `intellisend-form` and
  `cyberitex-spam-interceptor` appear in older code. Prefer `intellisend`.
- Provider display names come from `IntelliSend_Database::get_provider_label()`, not `ucfirst()`.
  For API providers it delegates to the transport's `get_label()`, so the label never drifts.
- Admin copy for API providers (field labels, placeholders, hints, region lists) comes from the
  transport via `describe_all()`, localized as `intellisendProviders`. Do not hardcode a vendor's
  wording in the view or the JS.
- Diagnostic logging goes through `debug_log()`, gated on `settings.debug_enabled`. Real errors
  use `error_log()` unconditionally.
- Admin JS is jQuery in an IIFE with a single object literal per page, initialized on document
  ready. No bundler, no modules.
- Bump the version in three places together: the plugin header, `INTELLISEND_VERSION`, and
  `CHANGELOG.md`.
- No em dashes in code comments, commit messages, or documentation.

## Verifying changes

There is no test suite in the repo. PHP syntax can be checked with the local binary:

```
& "C:\Program64\php8.4\php.exe" -l <file>
```

The API transports are the one part that can be tested without WordPress: they only touch
`wp_remote_post`, `wp_remote_get`, `is_email`, `get_option`, `get_bloginfo`, `wp_json_encode`,
`wp_check_filetype` and `untrailingslashit`. Stub those plus an `IntelliSend_Database` with
`decrypt_data()`, require the transport files, and assert on the captured request. That is how the
payload shapes, credential precedence, and the SigV4 vectors were checked; it catches far more
than `php -l`. Worth rebuilding in the scratchpad whenever you touch a transport.

Everything else has to be exercised in a real WordPress install: **Settings** (default provider,
test email, spam test, debug toggle), **SMTP Providers** (save, test API key), **Email Routing**
(inline rule editing), **Reports** (log rows and expanded detail).

## Known rough edges

- `class-form.php` writes a `spamScore` key into the report data, but the reports table has no
  such column; `create_report()` silently drops it.
- `admin/js/intellisend-admin.js` contains a provider-details fetch targeting `#defaultProviderName`
  and `#server`, IDs that no current view renders. It is dead code.
- `uninstall.php` still deletes `cyberitex_*` options and drops `cyberitex_si_logs` from the
  plugin's earlier incarnation. It does not touch any `intellisend_*` table or the
  `intellisend_db_version` option.
- `README.md` ends with a block of raw design notes and superseded schema sketches. Treat the code
  as authoritative.
- `IntelliSend_Admin::init()` is hooked to `plugins_loaded`, while `IntelliSend_Form::init()` runs
  both from the constructor and from an `init` action at the bottom of `class-form.php`. Watch for
  double registration when touching bootstrap.
