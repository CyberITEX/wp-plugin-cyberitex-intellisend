readme.txt
=== IntelliSend Form ===
Contributors: cyberitex
Tags: spam, contact form, security, smtp, email, spam checker
Requires at least: 5.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

IntelliSend Form configures SMTP, intercepts outgoing emails from form plugins to check for spam via AntiSpamCheck API, and discards spam silently so your visitors see success without confusion.

== Description ==

**IntelliSend Form** integrates seamlessly with the native WordPress `wp_mail()` function. It:

1. **Configures SMTP** for reliable email sending.  
2. **Intercepts all outgoing emails** (including those from popular contact form plugins like Contact Form 7, Gravity Forms, WPForms, etc.).  
3. **Checks spam** via the AntiSpamCheck API.  
4. **Silently discards spam** so your visitors won't see error messages (the form appears successful).  
5. **Allows advanced setup** with a configurable "Recipient Email," an API Key validation feature, and a custom Spam Test.  
6. **Provides a "Report" page** to view all sent/blocked/failed messages in an interactive table (sortable columns, pagination, expand/collapse message details).  

### Key Features
- **Custom SMTP**: Use your own SMTP credentials to ensure deliverability.
- **SpamCheck API**: Leverage robust spam detection.
- **Silent Discard**: Even if flagged as spam, your form plugin will show a normal success message to visitors.
- **Advanced Logging**: A Report page lists all messages, spam or otherwise, with statuses (Sent, Blocked, Failed) and details.
- **Clear All Logs**: An option to clear the entire message log at once.
- **Test Email + Spam Test**: Quickly verify your SMTP config and see how spammy messages get handled.
- **API Key Check**: A dedicated button verifies if your API Key is valid before relying on it in production.

== Installation ==

1. **Upload** the `intellisend-form` folder to `/wp-content/plugins/`.  
2. **Activate** the plugin through the **Plugins** menu in WordPress.  
3. Navigate to **"IntelliSend Form"** in your WP Admin menu (the plugin's settings page).  
4. **Configure** your SMTP details, **enter your AntiSpamCheck API key**, set **Recipient Email** for intercepted messages, and **Save**.  
5. Use the **Check API Key** button to confirm your key is valid.  
6. Optionally **send a Test Email** to verify SMTP and a **Spam Test** to confirm spam is discarded properly.  
7. Go to the **"Email Report"** submenu to see a detailed dashboard of all recent emails, including spam attempts, failures, and successful deliveries.

== Frequently Asked Questions ==

= Does this work with Contact Form 7, Gravity Forms, WPForms, etc.? =  
Yes. It hooks into `wp_mail()`, which most form plugins use by default.

= Will visitors see an error if they submit a spammy message? =  
No. By design, spam is quietly intercepted and discarded. The form plugin shows a normal success message.

= Why is my "to" address not matching the form plugin's settings? =  
By default, the plugin **overrides** the "to" address with the configured **Recipient Email**. You can change this behavior in the plugin code if needed.

= How can I confirm the API Key is valid? =  
There's a **"Check API Key"** button on the plugin settings page. If invalid, you'll see an error notice.

= Where do spammy emails go? =  
They are effectively dropped/blackholed. This prevents clutter in your real inbox.

= What if I want to see spammy messages anyway? =  
View them under the **"Email Report"** page, where they'll be marked as **Blocked** and show up in the logs.

== Changelog ==

= 1.0.3 =
* Renamed plugin to "IntelliSend Form"
* Fixed issue with unexpected output during activation
* Improved settings saving functionality

= 1.0.2 =
* Added a detailed "Report" page with sortable columns, pagination, expand/collapse message details.
* Enabled logging for all sent/blocked/failed emails, plus a "Clear All Logs" option.
* Improved UI for better user experience, including a "Settings" link in the plugin list.

= 1.0.1 =
* Added API Key validation feature on the settings page.
* Improved "spam sabotage" logic to silently discard spam while showing form success.
* Enhanced debug logging for troubleshooting.
* Minor UI and documentation updates.

= 1.0.0 =
* Initial release.

== License ==
This plugin is distributed under the GPL v2 or later license.
