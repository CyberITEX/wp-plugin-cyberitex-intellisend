<!-- README.md -->
# IntelliSend

A **WordPress plugin** that routes `wp_mail()` through SMTP or email-provider APIs, optionally checks messages using the [CyberITEX AntiSpamCheck API](https://api.cyberitex.com/v1/tools/SpamCheck), and records delivery reports. It works with form plugins that use WordPress mail, including Contact Form 7, Gravity Forms, WPForms and Ninja Forms.

Current spam handling forwards flagged messages to the hard-coded external address `blackhole@cyberitex.com`, while preserving the form success response. It does not discard messages locally. This behaviour is unchanged by the 1.2.1 audit; see [AUDIT.md](AUDIT.md) for privacy implications and decisions still required.

---

## Features

- **Responsive light and dark modes**
  All IntelliSend admin screens adapt to narrow displays and automatically follow the operating-system colour preference. Form controls, tables, dialogs, notices and message previews share the same accessible palette.

- **SMTP Configuration**
  Enter your mail server, port, username, and password in a dedicated settings page for reliable email sending.

- **Web API Transports (SendGrid, Brevo, Amazon SES)**
  Alongside the SMTP presets, two providers deliver over HTTPS instead of SMTP, which is what you want when the host blocks outbound ports 25/465/587. No Composer dependency: both use the WordPress HTTP API.

  | Provider | Transport | Endpoint | Credential constants |
  | --- | --- | --- | --- |
  | SendGrid (SMTP) | PHPMailer | `smtp.sendgrid.net:587` | - |
  | SendGrid (Web API) | HTTPS | `POST /v3/mail/send` | `SENDGRID_API_KEY` |
  | Brevo (SMTP) | PHPMailer | `smtp-relay.brevo.com:587` | - |
  | Brevo (Web API) | HTTPS | `POST /v3/smtp/email` | `BREVO_API_KEY` |
  | Amazon SES (SMTP) | PHPMailer | `email-smtp.<region>.amazonaws.com:587` | - |
  | Amazon SES (Web API) | HTTPS + SigV4 | `POST /v2/email/outbound-emails` | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` |

  Configure one under **IntelliSend > SMTP Providers**:
  1. Create credentials at the provider. SendGrid keys need the **Mail Send** permission; Brevo uses an API v3 key; SES needs an IAM access key pair allowed to call `ses:SendEmail` and `ses:GetAccount`.
  2. Select the provider, enter the credentials, and set a **Sender Email** the provider has verified (a SendGrid Single Sender or authenticated domain; a validated Brevo sender; an SES-verified identity in the same region).
  3. For SES, pick the **AWS Region** that holds your verified identity. The region is part of the request signature, so it has to match.
  4. Press **Test API Key**, then **Save Provider**. Use **Send Test** on the Settings page to send a real message.

  Instead of storing the key in the database, you can define it in `wp-config.php`, where it takes precedence over the stored value:

  ```php
  define( 'SENDGRID_API_KEY', 'SG.xxxxxxxxxxxxxxxxxxxxxx' );
  define( 'BREVO_API_KEY', 'xkeysib-xxxxxxxxxxxxxxxxxxxxxx' );
  define( 'AWS_ACCESS_KEY_ID', 'AKIAIOSFODNN7EXAMPLE' );
  define( 'AWS_SECRET_ACCESS_KEY', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY' );
  ```

  Environment variables of the same names work too. When either is present the matching admin field is disabled and says where the credential came from.

  SendGrid also offers EU data residency: switch **Data Residency** to `api.eu.sendgrid.com` for an EU-pinned subuser. Brevo has a single global host, so that row is hidden for it.

  SES notes: signing is AWS Signature Version 4, implemented directly with no AWS SDK. Attachments go out as a raw MIME message, since SES Simple content does not carry them. A new SES account is in the sandbox and can only send to verified addresses; **Test API Key** reports that if it applies.

  Routing rules, spam filtering, BCC recipients, and reports behave exactly the same as on the SMTP path.

  To add another vendor, subclass `IntelliSend_Api_Transport` (it already handles header parsing, address dedup and attachments), register it in `IntelliSend_Api_Transport::registry()`, and add a provider row to `IntelliSend_Database::get_default_providers()`.

- **SpamCheck API Integration**
  Use the CyberITEX AntiSpamCheck endpoint to classify messages when enabled for a routing rule.

- **API Key Validation**
  Verify your CyberITEX API key with a built-in checker before relying on it in production.

- **Recipient Override**
  Force all emails to go to a specific address, regardless of what each contact form plugin sets.

- **Spam Handling**
  Flagged messages use the existing external forwarding destination while form users see a normal success response.

- **Advanced Reporting**
  View **all messages** (Sent, Blocked, Failed) in a **Report** page with:
  - **Sortable columns** (date, recipient, subject, status)
    - **Pagination** (20 reports per page)
    - **View Details** dialog with an isolated formatted preview; remote images and links are disabled
  - **Clear All Logs** option

- **Test Email & Spam Test**
  - **Test Email**: Confirm a provider works by sending a sample message. Available in two places: **SMTP Providers** tests the provider you are editing, leaving the site default alone; **Settings** tests the current default. Both cover SMTP presets and API transports.
  - **Test API Key**: On **SMTP Providers**, validates an API transport's credentials against the vendor without sending anything.
  - **Spam Test**: Confirm the plugin intercepts suspicious messages without alerting the user.

---

## Installation

1. **Upload** the `cyberitex-intellisend` folder to `wp-content/plugins/`.
2. **Activate** the plugin through the WordPress “Plugins” menu.
3. **Configure** a provider under **IntelliSend > SMTP Providers**, then review **Settings** and **Email Routing**.
4. Open **Reports** to inspect messages and delivery diagnostics.

---

## Usage

- **Automatic Interception**
  Any contact form plugin that uses `wp_mail()` is automatically supported—no further setup needed.
- **Email Checks**: Rules with spam checks enabled send message content to the configured checker. Flagged messages use the external forwarding destination described above.
- **Reporting & Logs**
  - **View** all emails in a sortable table (Sent, Blocked if spam, or Failed).
  - Select **View Details** to read the message and diagnostics. Dates use the WordPress site time.
  - **Clear** logs as needed to keep your database tidy.
- **API Key Validation**
  Confirm your key’s validity from the plugin settings page to avoid misconfiguration issues.

---

## License

Distributed under the [GPL-2.0 License](LICENSE.md).

---

## Support

For questions or issues, please contact [support@cyberitex.com](mailto:support@cyberitex.com).


wp_intellisend_providers
id, name, type, description, helpLink, server, port, encryption, authRequired, username, password, apiKey, apiEndpoint, configured

type = 'smtp' (PHPMailer) or 'api' (HTTP transport, e.g. SendGrid Web API)
apiKey = encrypted; overridden by the transport's key constant or environment variable
apiEndpoint = API base URL, e.g. https://api.sendgrid.com, https://api.brevo.com or https://email.<region>.amazonaws.com
username    = for API transports, the non-secret half of a credential pair (AWS Access Key ID); unused by SendGrid and Brevo


wp_intellisend_settings
id, defaultProviderName, antiSpamEndPoint, antiSpamApiKey, testRecipient, spamTestMessage, logsRetentionDays


wp_intellisend_routing
id, name, subjectPatterns, defaultProviderName, recipients, antiSpamEnabled, enabled, priority (optional)

wp_intellisend_reports
id, date, subject, sender, recipients, message, status, log, antiSpamEnabled, isSpam, routingRuleId





password = encrypted




antiSpamApiKey = encrypted
testRecipient = default wp admin but can be updated when used
spamTestMessage = placeholder "URGENT: Your account has been compromised! Click here to verify: http://suspicious-link.com - Claim your $500 prize now! Limited time offer for Viagra and other medications at 90% discount. Reply with your credit card details." but it can be overwrite.. no need to save in db

No need to send the spam message test email, just check the result




wp_intellisend_routing
id, name, subject, recipients, antiSpamEnabled, enabled, priority (optional)


wp_intellisend_reports
id, date, subject, sender, recipients, message, status, log, antiSpamEnabled, isSpam, routingRuleName


---

cyberitex-intellisend/
├── admin/               (Admin-related files)
│   ├── class-admin.php  (Admin class)
│   ├── class-ajax.php   (AJAX handler)
│   ├── css/             (Admin styles)
│   ├── js/              (Admin scripts)
│   └── views/           (Admin page templates)
├── includes/            (Core functionality)
│   ├── class-activator.php     (Activation/deactivation logic)
│   ├── class-api-transport.php (Shared base for HTTP API transports)
│   ├── class-brevo.php         (Brevo transactional email API transport)
│   ├── class-ses.php           (Amazon SES v2 API transport, AWS SigV4)
│   ├── class-database.php      (Database operations)
│   ├── class-form.php          (Email interception and routing)
│   ├── class-intellisend.php   (Main plugin class)
│   ├── class-sendgrid.php      (SendGrid Web API v3 transport)
│   └── class-spamcheck.php     (Spam detection)
└── cyberitex-intellisend.php  (Main plugin file)
