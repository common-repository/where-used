=== Helpers Library ===
Contributors: stevenayers63, jdorner
Requires PHP: 7.4.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

The Helpers Library is an add-on dependency for custom WordPress plugins. This library provides the code to easily do the following:

* Admin: Header with menu
* Admin: Settings pages
* Admin: Dashboard
* Admin: Notifications
* Admin: Debug area
* Scanning: Background scanning capabilities
* Security: Retrieved sanitized $_GET, $_POST, $_REQUEST, and $_SERVER data
* Code: Automatically creates PHP constants for reference

== Install Dependency Into Plugin ==

Notice: This plugin relies on your plugin having a PHP namespace so that the install will be as a sub namespace.

1. Download the latest version of Helpers Library from https://gitlab.com/sovdeveloping/helpers-library
2. Place the unzipped files in /wp-content/plugins/your-plugin/library/helpers-library
3. Read install instructions: /wp-content/plugins/your-plugin/library/helpers-library/install.txt

== Changelog ==

= Versions Key (Major.Minor.Patch) =
* Major - 1.x.x increase involves major changes to the visual or functional aspects of the plugin, or removing functionality that has been previously deprecated. (higher risk of breaking changes)
* Minor - x.1.x increase introduces new features, improvements to existing features, or introduces deprecations. (low risk of breaking changes)
* Patch - x.x.1 increase is a bug fix, security fix, or minor improvement and does not introduce new features. (non-breaking changes)

= Version 1.5.0 =
*Release Date - 21 Jul 2023*

* New Feature: Plugin dependency can now be loaded into a plugin as a sub namespace to prevent conflicts with other plugins that use older or newer versions of this dependency
* New Feature: Force proper installation of dependency
* Improvement: Added dependency install instructions
* Improvement: added method get_scan_types() so that we can easily modify the available scan types and have a single source of truth
* Minor Improvement: grammar improvements
* Minor Improvement: code formatting
* Minor Improvement: removed some unnecessary code
* Bug Fix: The status code cache wasn't getting cleared if a scan was cancelled
