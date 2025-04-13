<!-- README.md -->
# CyberITEX Spam Interceptor

A **WordPress plugin** that configures SMTP and intercepts outgoing emails from **any** contact form plugin (e.g., Contact Form 7, Gravity Forms, WPForms, Ninja Forms) to check for spam using the [CyberITEX AntiSpamCheck API](https://api.cyberitex.com/v1/tools/SpamCheck). If spam is detected, the email is silently **discarded** so the visitor sees a normal success message, preventing confusion. Otherwise, it’s delivered to the configured recipient.

---

## Features

- **SMTP Configuration**  
  Enter your mail server, port, username, and password in a dedicated settings page for reliable email sending.

- **SpamCheck API Integration**  
  Leverage the CyberITEX AntiSpamCheck endpoint to detect and discard spammy messages.

- **API Key Validation**  
  Verify your CyberITEX API key with a built-in checker before relying on it in production.

- **Recipient Override**  
  Force all emails to go to a specific address, regardless of what each contact form plugin sets.

- **Silent Discard**  
  Spammy emails are never delivered, but users see a normal “success” message (no confusion or error).

- **Advanced Reporting**  
  View **all messages** (Sent, Blocked, Failed) in a **Report** page with:
  - **Sortable columns** (date, recipient, subject, status)
  - **Pagination** (choose 10, 100, or All)
  - **Expand/Collapse** detail rows
  - **Clear All Logs** option

- **Test Email & Spam Test**  
  - **Test Email**: Confirm your SMTP settings by sending a sample email.  
  - **Spam Test**: Confirm the plugin intercepts suspicious messages without alerting the user.

---

## Installation

1. **Upload** the `cyberitex-spam-interceptor` folder to `wp-content/plugins/`.  
2. **Activate** the plugin through the WordPress “Plugins” menu.  
3. **Configure** your SMTP details and CyberITEX AntiSpam API Key under **“CyberITEX AntiSpam”** in your WP Admin.  
4. (Optional) Go to the **Report** submenu to see logs of all recent emails, spam or otherwise.

---

## Usage

- **Automatic Interception**  
  Any contact form plugin that uses `wp_mail()` is automatically supported—no further setup needed.
- **Email Checks**  
  Each message’s content is sent to the CyberITEX SpamCheck API. If flagged, the email is diverted to a blackhole (undeliverable address) so your legitimate inbox stays clean, yet the user sees a “success” message.
- **Reporting & Logs**  
  - **View** all emails in a sortable table (Sent, Blocked if spam, or Failed).  
  - Click a row to **expand/collapse** full message details and any error info.  
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
id, name, description, helpLink, server, port, encryption, authRequired, username, password


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
│   ├── class-activator.php    (Activation/deactivation logic)
│   ├── class-database.php     (Database operations)
│   ├── class-form.php         (Email interception)
│   ├── class-intellisend.php  (Main plugin class)
│   └── class-spamcheck.php    (Spam detection)
└── cyberitex-intellisend.php  (Main plugin file)