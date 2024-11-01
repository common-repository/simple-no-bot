=== Invisible Anti Spam for Contact Form 7 (Simple No-Bot) ===
Contributors: lilaeamedia
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=DE4W9KW7HQJNA
Tags: contact form 7, auto captcha, bot blocker, spam blocker, invisible recaptcha, honeypot, antispam, anti-spam, captcha, anti spam, form, forms, contactform7, contact form, cf7, recaptcha, no captcha
Requires at least: 5.2
Tested up to: 5.3
Stable tag: 2.2.5
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Simple, lightweight, no captcha, no configuration. Just works.

== Description ==

Simple No-Bot uses javascript to detect if Contact Form 7 is being submitted by a spam bot. 

We wrote this when clients were reporting hundreds of bogus contact forms were getting past Honeypot, but did not want to add a captcha that would impact conversions. 

This lightweight script has been extremely effective for eliminating spam messages from Contact Form 7 (and other forms) submissions. It does not pretend to be a complete anti spam solution.

== IMPORTANT ==

SNB REJECTS SUBMISSIONS UNLESS THE USER INTERACTS WITH THE FORM. In earlier versions of SNB, the submit button was disabled until this threshold was met. You can now set this option in wp-config.php (see below).

In most cases it will be enabled after the user starts typing in the first field. It has not broken your form.

Please report any feedback and false negatives/positives on our support form at http://www.lilaeamedia.com/contact/ before posting a crappy review. Thanks.

== New! Improved! ==

You can now hook Simple No Bot into any form. The filter below will return TRUE if bots are detected.

`
$is_spam = FALSE; // you can use whatever flag is being used by your plugin. 
$is_spam = apply_filters( 'snb_test_spam', $is_spam );
`

We have added additional analysis to detect pesky bots that can mimic browsers and run scripts.

SNB now keeps a list of IPs as they are flagged as spam and automatically fails them. The oldest IPs are pruned when it reaches 100 (or SNB_MAX_SPAM_IPS, see below). You can pass ?snb_flush=true as Admin to flush all spam IPs.

You can disable the submit button until the event threshold is reached by adding the following flag to wp-config.php:

`define( 'SNB_DISABLE_SUBMIT', TRUE );`

Other configurable options:

`
define( 'SNB_SPAM_THRESHOLD', 2 ); // maximum score before being considered spam
define( 'SNB_MIN_EVENTS', 2 ); // minimum number of events required to fetch token
define( 'SNB_BLOCK_SPAM_IPS', TRUE ); // use IP blocking on hard fails
define( 'SNB_SPAM_IP_LIFESPAN', 60 * 60 * 24 * 30 ); // time before spam ips expire - default 30 days
define( 'SNB_MAX_SPAM_IPS', 100 ); // max number of IPs to store before rotating
define( 'SNB_SESSION_LIFESPAN', 60 * 30 ); // time token is valid to send message - default 30 minutes`

== Installation ==

1. To install from the Plugins repository:
    * In the WordPress Admin, go to "Plugins > Add New."
    * Type "simple no-bot" in the "Search" box and click "Search Plugins."
    * Locate "Simple No-Bot Captcha Alternative for Contact Form 7" in the list and click "Install Now."

2. To install manually:
    * Download the IntelliWidget plugin from https://wordpress.org/plugins/simple-no-bot/
    * In the WordPress Admin, go to "Plugins > Add New."
    * Click the "Upload" link at the top of the page.
    * Browse for the zip file, select and click "Install."

3. In the WordPress Admin, go to "Plugins > Installed Plugins." Locate "Simple No-Bot Captcha Alternative for Contact Form 7" in the list and click "Activate."

== Frequently Asked Questions ==

= Why not just use Recaptcha 3? =

Google is great and all, but with every recaptcha, font, map or tag you use, you are passing each visitor's usage information to Google and strengthening their control over the web.

= How does it work? =

The browser automatically generates data from input events and passes it to the server via XHR. The server generates a unique token, 
stores a session in a transient record and returns token to the browser. The browser then injects a new input field to WPCF7 
form that contains token. When form is submitted, SNB rejects the form if no corresponding transient exists (among other things).

= Does it work without Javascript =

No. Contact forms will fail if Javascript is not enabled.

= Does it require cookies? =

Not currently. We may add more behavioral analysis if the latest generation of JS-empowered bots continues to proliferate.

== Screenshots ==

No screens to shoot.

== Changelog ==
2.2.4 More super secret Turing tweaks. 
2.2.0 Removed the hash comparison and added super secret Turing device.
2.1.5 Disabling the submit button before user interaction is now optional. Reduced minimum events to 2.
2.1.3 Added general plugin support. Strenghened hashing and XHR protocol. Added spam IP list. Added debug log.
1.0.5 Simplified validation
1.0.2 Change wp nonce functions to wpcf7 nonce functions
1.0 Initial release

== Upgrade Notice ==

See change log.

== Support ==

Please report any feedback and false negatives/positives on our support form at http://www.lilaeamedia.com/contact/

(c)2019 Lilaea Media
