# Changelog

## [1.2.1] - 2026-09-14

### Fixed
- Require genuine WordPress session nonces for report access and deletion; reject malformed AJAX inputs and inconsistent provider identities.
- Preserve credential bytes through WordPress request unslashing and storage, respect SMTP encryption/authentication settings, and restore temporary test-mail hooks and mailer state.
- Correct wildcard and regular-expression routing, API mail content filters, SMTP encryption reset between messages, and SES attachment-message subject encoding.
- Test the entered anti-spam endpoint without saving it; validate HTTP responses and boolean spam results.
- Keep report search, filters, counts and pagination consistent, including recipient/body searches and stable ordering.
- Preserve quoted routing field values, update saved-key state, and render notifications as text.

### Improved
- Keyboard controls, dialog focus handling, form labels, responsive layouts and visible focus indicators.
- Isolated formatted report previews with remote images and links disabled.
- Add isolated PHP and browser regression checks and a scoped audit report in `AUDIT.md`.

No database schema, encrypted credential format, retention policy or uninstall changes are included.

All notable changes to the IntelliSend Spam Interceptor plugin will be documented in this file.

## [1.2.0] - 2026-08-27

### Added
- Brevo support, both transports: a `brevo` SMTP preset (`smtp-relay.brevo.com`) and a `brevo-api` Web API transport (`POST /v3/smtp/email`)
- Amazon SES support, both transports: a `ses` SMTP preset (`email-smtp.<region>.amazonaws.com`) and a `ses-api` transport using the SES v2 API (`POST /v2/email/outbound-emails`)
- AWS Signature Version 4 signing, implemented directly against the documented algorithm so no AWS SDK is needed, verified against the signing-key and full-request test vectors in the AWS documentation
- SES region selector covering the 22 regions that serve the v2 email API, with the region derived from the endpoint host for the credential scope
- SES attachments are delivered as a raw MIME message (`Content.Raw`), which every SES version accepts; Bcc stays out of the MIME headers and travels in `Destination` so the list never leaks
- `BREVO_API_KEY`, `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` constant and environment variable support, matching the SendGrid behaviour
- Transports can now declare a second, non-secret credential (an AWS Access Key ID), stored in the `username` column and surfaced as its own admin field
- "Send Test Email" on the SMTP Providers page, which sends a real message through the provider being edited without first making it the site default. Works for every provider, SMTP preset or API transport
- `IntelliSend_Api_Transport`, a shared base class holding all message normalization (header parsing, address dedup, attachment encoding) so each vendor class only describes its endpoint, auth header, request body and error format
- A transport registry, so provider labels, key placeholders, region choices, sender hints and env-var names all come from the transport class instead of being hardcoded in the view

### Changed
- The providers page API fields are now driven by the selected transport: field labels ("API Key" vs "Secret Access Key", "Data Residency" vs "AWS Region"), the identity row, the key placeholder and the hints all come from the transport class
- The region row hides itself when a vendor has only one region, and the key hint names that vendor's own constant
- The SMTP host field is now editable for presets whose host is region specific (Amazon SES), not only for "Other"
- Built-in providers are seeded by name on every schema upgrade, not only when the providers table is empty, so presets added in later releases reach existing sites
- The API endpoint submitted when saving a provider is validated against the transport's own region list, so a tampered POST cannot redirect mail to an arbitrary host

### Fixed
- A test send against an unconfigured provider now returns a readable message instead of an SMTP timeout or a raw vendor credential error
- **`dbDelta()` never migrated any table.** All four `CREATE TABLE` statements used `IF NOT EXISTS`, which defeats dbDelta's table-name regex (`|CREATE TABLE ([^ ]*)|` matches the literal `IF`), so it described a table named "IF", concluded nothing existed, and emitted no `ALTER TABLE`. The statements now use the canonical form, and the routing table's `PRIMARY KEY (id)` was given the second space dbDelta requires.
- Result messages no longer read "the Brevo (Web API) API", since the transport label already names the interface

## [1.1.0] - 2026-08-27

### Added
- SendGrid Web API v3 transport (`sendgrid-api` provider), so mail can be delivered over HTTPS on hosts that block SMTP ports 25/465/587
- `SENDGRID_API_KEY` support: define the constant in `wp-config.php` or set the environment variable and it takes precedence over the key stored in the database
- EU data residency option (`api.eu.sendgrid.com`) for EU-pinned SendGrid subusers
- "Test API Key" button on the SMTP Providers page, validated against SendGrid's `/v3/scopes` endpoint (also checks for the `mail.send` scope)
- `type`, `apiKey` and `apiEndpoint` columns on the providers table, plus an in-place schema upgrade for existing installs (worked around the dbDelta defect; root-fixed in 1.2.0)
- Friendly provider labels across the admin UI, distinguishing "SendGrid (SMTP)" from "SendGrid (Web API)"

### Changed
- Routing, spam filtering, BCC recipients and report logging now work identically for SMTP and API transports
- The `configured` flag is recomputed from merged stored plus submitted values, so partial provider saves no longer clear it
- Provider saves no longer require SMTP host/port fields for API transports
- Minimum WordPress version is now 5.7 (the `pre_wp_mail` filter the API transport hooks)

### Fixed
- Saving a provider without retyping the password no longer emits a PHP notice for the missing `provider_password` field
- The provider list AJAX response no longer includes stored password ciphertext

## [1.0.0] - 2025-04-13

### Added
- Initial release
- SMTP configuration with multiple provider support
- Spam detection using CyberITEX AntiSpamCheck API
- Email routing based on patterns
- Comprehensive reporting system
- Database-driven settings management
- Admin interface for all plugin features
- API key validation
- Test email and spam test functionality

### Changed
- Transitioned from WordPress options to custom database tables
- Enhanced security with proper data sanitization
- Improved error handling and logging

### Fixed
- Email delivery reliability issues
- Settings persistence problems
