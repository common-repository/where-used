=== WhereUsed ===
Contributors: stevenayers63, jdorner
Tags: broken links, broken images, seo, redirect, where used
Requires at least: 5.3
Requires PHP: 7.4.0
Tested up to: 6.2.2
Stable tag: 1.4.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

Where used? This plugin helps you find usage of attachments, posts, links, blocks and more in all post types, taxonomy terms, post meta, user meta, and menus. This plugin is multisite compatible!

= Items That Will Be Detected =
- images, attachments
- links
- Gutenberg custom blocks or reusable blocks
- iframes
- Shortcodes (coming soon)

= Areas Where It Searches For The Items =
- posts, post meta (even custom post types)
- taxonomy terms, term meta
- users, user meta
- WordPress menus
- Redirection rules (if Redirection plugin is installed)

= Discover Problems =
- Broken links ( 404 errors hurt SEO ranking)
- Redirects referenced (301, 302 etc.)
- Unused reusable blocks

= Prevent You From Causing Problems =
- Know where something is referenced before you delete it and create broken links or broken functionality on your site
- Find where a reusable block is used before you delete the reusable block
- Find out where blocks are used before you uninstall a plugin that has custom blocks
- Know when the slug of your page has been accidentally redirected due to regex matching via Redirection plugin

== IMPORTANT: This Plugin DOES NOT ==
- Does not search theme's code or any plugin's code for hardcoded references
- Does not detect references or backlinks located on websites beyond the scope of your WordPress install.
- Does not search custom database tables
- WARNING: Does not find every existence of usage due to mentioned lines above and possibly some unforeseen scenarios. Please always be cautious when deleting posts and attachments. This plugin does it's best to help you be more confident in deleting unused content and maintaining existing content.

== Compatible With ==
- Multisite Installations
- [Bedrock](https://roots.io/bedrock/)
- [Fix Alt Text](https://wordpress.org/plugins/fix-alt-text/)
- [Redirection](https://wordpress.org/plugins/redirection/)
- [Advanced Custom Fields - ACF](https://wordpress.org/plugins/advanced-custom-fields/)
- [Network Media Library](https://github.com/humanmade/network-media-library)

== Recommended Plugins To Install ==
- [Redirection](https://wordpress.org/plugins/redirection/) - WhereUsed is more powerful as a tool with the Redirection plugin. Certain features of WhereUsed are not available unless Redirection is installed.
- [Fix Alt Text](https://wordpress.org/plugins/fix-alt-text/) - Like WhereUsed? You'll love our other plugin, Fix Alt Text, which will help you manage your image alt text easier for better website SEO and accessibility.

== Installation ==

1. Navigate to the Plugin management area:
 * Single Site: WP Admin > Plugins
 * Multisite: WP Admin > Network Admin > Plugins

2. Click "Add New" button at the top of the Plugins page. A new page will load displaying an Upload button at the top and an area to search for plugins.
3. You can either manually upload a zip file of WhereUsed, or you can search for the plugin in the WordPress repository by searching for "WhereUsed".
4. Once you have installed the plugin, continue to the Quick Setup Guides below:

== Quick Setup Guides ==

= Single Site Setup =
1. Install the WhereUsed plugin per instructions above and then activate it.
2. Modify settings as need to ensure your entire site gets scanned properly. Adjust settings here: Admin > Tools > WhereUsed > Settings
3. Do an initial full scan on the bottom right of the Dashboard here: Admin > Tools > WhereUsed
4. Once the scan is complete, you can review the Dashboard to discover detected broken links and redirects.

= Multisite Setup =
1. Install the WhereUsed plugin per the instructions above and activate the plugin for the entire network or go to each site individually in the network and activate the plugin.
2. All sites will default to using the network settings (for convenience). This can be disabled on a per site basis in the network settings area. WP Admin > Network Settings > Settings > WhereUsed
3. Each site will need to be scanned so that all references are detected
4. Multiple scans (full scan on each site) are prevented from running simultaneously to protect the server from getting too overwhelmed at one time.
5. Once all scans have been run, you will be able to see all references on each site and all references between each site.

== Changelog ==

= Versions Key (Major.Minor.Patch) =
* Major - 1.x.x increase involves major changes to the visual or functional aspects of the plugin, or removing functionality that has been previously deprecated. (higher risk of breaking changes)
* Minor - x.1.x increase introduces new features, improvements to existing features, or introduces deprecations. (low risk of breaking changes)
* Patch - x.x.1 increase is a bug fix, security fix, or minor improvement and does not introduce new features. (non-breaking changes)

= Upcoming Features =
* Detect shortcode usage
* Notify user of deprecated block usage
* Inline Link Editing
* Inline Anchor Text Editing

= Version 1.4.0 =
*Release Date - 28 Jul 2023*

* Bug fix: custom db tables were not being removed when the plugin was deleted from the site.
* Bug Fix: custom db tables were not being created in various scenarios
* Bug Fix: Admin meta boxes displaying references were being shown on post types that were not getting scanned.
* Bug Fix: Cache entries in the options table were not being removed on deactivate or uninstallation of the plugin.
* Bug Fix: The status code updates on saving a post were running twice.
* Improvement: Updated the saving post process to separate the discovery scan and the status code update scan. Status update scan for the post is now queued and runs in the background to remove the saving delay.
* Improvement: Converted Helpers Library to be under the plugin's namespace to prevent conflicts with other plugins using the same dependency

= Version 1.3.4 =
*Release Date - 13 Jul 2023*

* Minor Improvement: Removed some code that was migrated into the Helpers Library
* Minor Improvement: Updated Helpers Library 1.5.0

= Version 1.3.3 =
*Release Date - 31 May 2023*

* Bug Fix: When a redirect is updated, the reference is removed from the references table and not added back
* Bug Fix: When a post is saved, duplicate redirect rule references were getting created.
* Bug Fix: When you save a user or a term, the status codes cache is not removed from the database when the process is finished
* Bug Fix: The status code caching was using inconsistent making conventions which could lead to orphaned data in the options table
* Minor Improvement: Tested up to: 6.2.2
* Minor Improvement: Updated Helpers Library 1.4.1

= Version 1.3.2 =
*Release Date - 17 May 2023*

* Bug Fix: After sending a post to the trash, references associated with the post still appear in References table
* Bug Fix: Attachment references were not getting removed from references table when deleted.
* Bug Fix: Blocks within blocks were not being detected accurately
* Minor Improvement: Added additional core blocks to ignore recording as references during a scan

= Version 1.3.1 =
*Release Date - 14 Apr 2023*

* Bug Fix: When sanitizing URLs with spaces as %20 were being removed causing the URL to be incorrect.
* Security: Improved sanitation of data
* Minor Improvement: Updated Helpers Library 1.4.0

= Version 1.3.0 =
*Release Date - 05 Apr 2023*

* Deprecation: Remove weekly cron frequency option in settings. All existing settings using weekly frequency will contiue to work as before.
* Bug Fix: Deprecated PHP notice - PHP 8.2 deprecated sending null to trim()
* Bug Fix: False positive: warning message appears requiring full scan needed when a status check scan pushes the latest full scan out of the history log due to a scan retention limitations.
* Bug Fix: PHP Fatal error occurred if mbstring non-default extension was not installed. Fallback implemented.
* Minor Improvement: Changed the default status check cron frequency value from weekly to monthly.
* Minor Improvement: Updated Helpers Library 1.3.7
* Minor Improvement: Tested up to: 6.2.0

= Version 1.2.6 =
*Release Date - 10 Mar 2023*

* Bug Fix: The plugin directory constant values were incorrect if site_url() and admin_url() do not have the same HTTP protocol.
* Bug Fix: Prevent update of plugin if it is under local git control
* Minor Improvement: Updated Helpers Library 1.3.6

= Version 1.2.5 =
*Release Date - 9 Mar 2023*

* Bug Fix: Fixed fatal error related to multisite installations. Issue occurs when a previous blog has been deleted and a new blog is created. The new blog's IDs increments and no longer matches the assumed corresponding index within the full array of blogs.
* Bug Fix: The database tables were not getting created on plugin activation for a multisite.
* Minor Improvement: Updated Helpers Library 1.3.5

= Version 1.2.4 =
*Release Date - 29 Nov 2022*

* Bug Fix: Scan duration details should not display 0 days, 0 hours, etc.
* Bug Fix: Scan stats "Total Posts Scanned" should say "Total Unique URLs Checked" for a check status scan
* Bug Fix: Scan is showing a message that a scan is needed when it is actually not
* Bug Fix: When cancelling a status-check, the action was marking a full scan as needed
* Bug Fix: Relative anchor URLs starting with a # were getting assigned the incorrect base URL
* Minor Improvement: Tested up to: 6.1.1

= Version 1.2.3 =
*Release Date - 09 Nov 2022*

* Bug Fix: Migration script wasn't single site compatible
* Bug Fix: Content that required authentication could not be scanned on single site installs. Worked on multisite.
* Bug Fix: When scan menus option changed in settings, the user was not being notified that a new scan was needed.
* Bug Fix: Some changes to network settings in a multisite were not notifying sites that they needed a new scan.
* Bug Fix: Headers sent prematurely when displaying admin notice if updating plugin while another plugin is using an older version of Helpers Library
* Bug Fix: Scans involving thousands of users, posts, or terms would run out of memory due to $wpdb caching
* Bug Fix: There was a rare possibility that a post gets queued and then the post is deleted before the background scan processes it, which could cause the progress bar to stall on the dashboard.
* Bug Fix: When a post was deleted, posts referencing that now deleted post were not getting rescanned.
* Bug Fix: Checking if the Redirection plugin was active and caching it was resulting in an incorrect value when switching blogs in multisite setup
* Bug Fix: Non URLs were not being removed from queue during status check cron, which would hang the scan
* Bug Fix: User could access unused attachment data when a full scan had not completed, giving incorrect information
* Bug Fix: User and WP Cron could start a status check even though a full scan had not completed
* Minor Improvement: Added warning message at the top of the Unused Attachments Table
* Minor Improvement: Updated Helpers Library dependency
* Minor Improvement: removed unused notify network sites setting and logic associated with it. In a multisite setup, all sites with the plugin active will get notified
* Minor Improvement: Tested up to: 6.1.0

= Version 1.2.1 =
*Release Date - 27 Oct 2022*

* Bug Fix: Scans could not access pages that required user to be logged in to view
* Security: Removed usernames from the scan users queue file

= Version 1.2.0 =
*Release Date - 26 Oct 2022*

* New Feature: Filter "whereused_excluded_post_types" has been added to allow modification of post types excluded from scans
* New Feature: Filter "whereused_excluded_taxonomies" has been added to allow modification of post types excluded from scans
* Improvement: Debug logging is more efficient
* Improvement: Scan was revised to now handle very large databases and use less resources
* Improvement: Updated recommended taxonomies and post types to scan. Please review your settings.
* Bug Fix: The plugin was initializing on the init hook, but activation hooks do not work on the init hook and were causing loading issues when plugin was activated.
* Bug Fix: Uninstall script fixed
* Bug Fix: Scan would run out of memory when trying to queue 400K posts
* Bug Fix: Post was not getting rescanned after it has been updated
* Security: Minor XSS Escaping within a user protected area

= Version 1.1.7 =
*Release Date - 20 Oct 2022*

* Minor Improvement: Load debug logging sooner
* Bug Fix: Migration was not running to update the DB for better performance.
* Bug Fix: Referencing some incorrect constant variables

= Version 1.1.6 =
*Release Date - 18 Oct 2022*

* Bug Fix: Could not start a new scan if a previous scan was interrupted by deactivating the plugin or some other unexpected action.

= Version 1.1.5 =
*Release Date - 18 Oct 2022*

* Bug Fix: Uninstalling WhereUsed caused an error.
* Bug Fix: Migration script failed.
* Bug Fix: Fix error due to calling undefined class Admin
* Bug Fix: Fix error related to scope when calling Menu class

= Version 1.1.0 =
*Release Date - 18 Oct 2022*

* New Feature: Debug area for troubleshooting scan issues. Debug mode must be turned on in settings before it will appear.
* New Feature: Added hook whereused_scan_meta to allow 3rd-party code to associate a custom field that contain a post ID to a post
* New Feature: Added hook whereused_scan_block to allow 3rd-party code to handle the scanning of a custom block
* New Feature: Filter "whereused_ignored_blocks" has been added to replace "WhereUsed/ignored_blocks"
* Improvement: DB tuned for better performance at scale
* Improvement: Updated Helpers_Library 1.1.0 dependency
* Improvement: Refactored admin template header and footer display logic
* Improvement: Updated logic in how we store scan history
* Improvement: Updated the scan progress bar to show what is being scanned and simplified status
* Minor Improvement: Added links to documentation on the dashboard
* Minor Improvement: Added links to documentation throughout the code
* Minor Improvement: Tested up to: 6.0.3
* Bug Fix: Admin notices were not displayed on an AJAX request.
* Bug Fix: Status Check was failing to fully run.
* Bug Fix: The Network Settings tab was not visible on the Settings page when accessed via AJAX and the tab was always visible if the settings page was access directly first
* Bug Fix: wp-admin references were hardcoded throughout the plugin making it incompatible with [Bedrock](https://roots.io/bedrock/)
* Bug Fix: Redirection column was not visible when the plugin Redirection was not installed even though 301s were detected and needed to show destination.
* Deprecated: Filter "WhereUsed/detected_block"
* Deprecated: Filter "WhereUsed/ignored_blocks"
* Note: Increased Minimum WP Version to 5.3

= Version 1.0.6 =
*Release Date - 02 Sept 2022*

* Minor Improvement: Added Notification to the user if a scan halted due to running out of memory
* Bug Fix: Status scan was running out of memory
* Bug Fix: Any notifications beyond 621 count cannot be marked as read

= Version 1.0.5 =
*Release Date - 1 Sept 2022*

* Minor Improvement: Updated link to GPL license
* Bug Fix: Status check cron is running multiple times instead of once when scheduled due to caching
* Bug Fix: Status code scan scheduled cron shows all options in setting by default when saved as "No"

= Version 1.0.4 =
*Release Date - 1 Sept 2022*

* Bug Fix: Removed double slashes in logo src as well as JS src
* Bug Fix: The taxonomy terms were not being rescanned after a term was updated
* Bug Fix: The taxonomy terms references were not getting removed from the scan index db table when the term was deleted
* Bug Fix: The user was not rescanned after the user profile was updated
* Bug Fix: The user references were not getting removed from the scan index db table when the user was deleted

= Version 1.0.3 =
*Release Date - 15 Jul 2022*

* Bug Fix: In a multisite setup using Network Media Library plugin, attachments references detected as a featured image are missing title and additional information.
* Bug Fix: Turned off SSL verify when getting status codes for links so that it works on local environment URLs when snakeoil wildcard self-signed SSLs are used
* Bug Fix: Network settings were getting created in a single site environment
* Bug Fix: User could change scan settings during a running scan and cause the scan to lock up.
* Bug Fix: In a multisite setup, the user could not easily navigate to the network settings from the local site settings
* Security: Fixed an unescaped URL to prevent highly unlikely XSS

= Version 1.0.2 =
*Release Date - 13 Jul 2022*

* Improvement: Converted CURL requests to use WP http-api
* Improvement: Updated ChartJS to version 3.8
* Minor Improvement: Updated plugin description that is visible in the plugin management area
* Bug Fix: The "References To This" link in the row actions in the table was incorrect.
* Security: Implemented some xss escaping

= Version 1.0.1 =
*Release Date - 12 Jul 2022*

* Improvement: Added links to the settings and dashboard on the plugins management pages
* Minor Improvement: Updated license to GPLv3
* Minor Improvement: Increased minimum WordPress version to 5.1

= Version 1.0.0 =
*Release Date - 12 Jul 2022*

* Initial Release
