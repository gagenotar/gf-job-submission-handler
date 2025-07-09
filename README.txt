=== GF Job Submission Handler ===
Contributors: ucfwebcom
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv3 or later
License URI: http://www.gnu.org/copyleft/gpl-3.0.html

Automatically creates pending Portal Job or Affiliate Job posts from Gravity Forms submissions using conditional logic.

== Description ==

This plugin extends Gravity Forms to create and manage job postings on the CREOL website. When a form is submitted, the plugin evaluates whether the submitter has identified as a CREOL Industrial Affiliate. Based on this selection, the job post is routed to either the `portal_jobs` or `jobs` custom post type. All posts are created with a `pending` status and include custom metadata such as company name, location, job type, contact info, and duration.

In addition, the plugin registers a daily cron event to automatically remove expired job posts based on the selected `job_duration` value.

== Documentation ==

Head over to the [GF Job Submission Handler wiki](https://github.com/UCF/gf-job-submission-handler/wiki) for detailed information about this plugin, installation instructions, and more.

== Changelog ==

= 1.0.0 =
* Initial release
* Adds Gravity Forms submission routing to CPTs
* Adds automated cron cleanup for expired job listings

== Upgrade Notice ==

No breaking changes.

== Development ==

Note that this plugin does not include compiled CSS or JS files.

[Enabling debug mode](https://codex.wordpress.org/Debugging_in_WordPress) in your `wp-config.php` file is recommended during development to help catch warnings and bugs.

= Requirements =
* WordPress 5.8+
* PHP 7.4+
* Gravity Forms plugin (with access to the form ID used for job submissions)

= Instructions =
1. Clone the `gf-job-submission-handler` repo into your local development environment, within your WordPress installation's `plugins/` directory:  
   `git clone https://github.com/UCF/gf-job-submission-handler.git`
2. Activate this plugin in WordPress under **Plugins > Installed Plugins**.
3. Ensure that your Gravity Form's field IDs align with those referenced in the plugin. Adjust the plugin logic if needed.
4. Submit a test form to verify that the proper post type (`portal_jobs` or `jobs`) is created with a `pending` status.
5. To test the cron-based cleanup, use WP Crontrol or wait for the next daily event. Posts older than the `job_duration` field (in days) will be automatically deleted.

= Other Notes =
* This plugin relies on standard Gravity Forms hooks and uses `wp_insert_post()` for custom post creation.
* To monitor cron event execution, install a plugin like [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/).

== Contributing ==

Want to submit a bug report or feature request?  
Check out our [contributing guidelines](https://github.com/UCF/gf-job-submission-handler/blob/master/CONTRIBUTING.md) for more information. We'd love to hear from you!
