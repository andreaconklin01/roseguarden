=== Dëftesë PRO — Certificate Manager ===
Contributors: professionalstudio
Tags: certificates, school, education, a4, print, csv
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 7.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage, edit and print A4 lower-secondary school certificates, with CSV import,
per-user record ownership and two-factor authentication.

== Description ==

Dëftesë PRO renders a pixel-accurate A4 certificate you can edit in place,
print, and store. It works from wp-admin and from a front-end page, so teachers
never need to see the WordPress dashboard.

* WYSIWYG A4 editor with a dynamic subject table
* Bulk CSV import (batched, so hundreds of files can be queued at once)
* Per-user row-level ownership: editors only see their own records
* Administrator overview grouped by user, school, year or class teacher
* TOTP two-factor authentication (RFC 6238), no external service required
* Built-in layout manager: margins, row heights, column widths, section
  spacing, typography, borders, colours and a background watermark

== Installation ==

1. Upload the `deftese-pro` folder to `/wp-content/plugins/`, or install the
   zip through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Create a page containing the `[deftese_manager]` shortcode.

Everything is in this one plugin. If you are upgrading from 6.x, deactivate and
delete the old "Dëftesë PRO" and "Layout & Margin Manager" plugins first; your
certificates and layout settings are stored in the database and are picked up
automatically.

The manager page is what non-administrators are redirected to, so it must exist
before the access gate has any effect.

== Frequently Asked Questions ==

= Does uninstalling delete my certificates? =

No. Data is only removed if you explicitly define `DEFTESE_REMOVE_DATA` as true
in `wp-config.php` before deleting the plugin.

= Can I keep part of the site public? =

Yes. Return false from the `deftese_gate_frontend` filter for the requests you
want to let through.

== Changelog ==

= 7.1.0 =
* The layout manager is now part of this plugin, so a single install provides
  the full feature set. It stands down automatically if a standalone layout
  plugin is still active, so no duplicate menu appears during an upgrade.
* Layout settings are read and written through one schema, removing the last
  place where the two plugins could disagree about defaults or units.

= 7.0.0 =
* Rebuilt from a single 2,900-line file into a namespaced, autoloaded structure.
* Styles and scripts are now real asset files instead of inline attributes and
  heredocs; the interface was redesigned around a single token palette and
  supports dark mode.
* Fixed a stored cross-site-scripting hole in the grade table: subject text was
  written into the page with innerHTML.
* Editing a certificate no longer reassigns its owner to whoever saved it.
* Previous/next navigation no longer loads every visible record id into memory.
* The front-end access gate can no longer redirect to itself in a loop when no
  manager page exists, and wp-login.php password resets are no longer blocked.
* Two-factor verification now runs in constant time and fires `wp_login`.
* Schema migrations run once per version instead of on every request.
* Added an uninstaller, translation support and a `.pot` file.
