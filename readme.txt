=== Signatures ===
Contributors: andrusi4ka
Tags: email signature, signature, gmail, outlook
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build email signatures in WordPress, let visitors fill in their details and copy the signature into Gmail or Outlook.

== Description ==

Signatures adds a "Signatures" menu (for administrators, and the roles they allow under Signatures → Settings) where you build reusable email signature templates and show them anywhere with `[signatur id="123"]` or the "Signatures" WPBakery element.

* A visual grid builder: columns and rows, merged cells (colspan / rowspan), column widths, row padding, borders and background.
* Elements for a cell: text (with link), image (with link), social icon (Facebook, Instagram, LinkedIn, Telegram or your own icon) and fillable fields.
* Fillable fields (text, email, phone, photo) that visitors fill in on the page; required fields are checked before copying.
* A "Copy signature" button with its own look; the copied signature is a plain table with inline styles, made to paste into Gmail and Outlook.
* `[signatur id="123" copy="no"]` shows the signature without the button.
* Translations: English, Ukrainian, Norwegian (Bokmål) and Russian.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the zip under Plugins → Add New → Upload Plugin.
2. Activate it. Updates then come from the plugin's GitHub releases under Plugins, like any other update.

== Changelog ==

= 1.6.0 =
* Signatures → Settings: what each role may do with the signatures (edit, add, delete, the WPBakery element). Administrators may always do all of it.
* A "Signatures" element for WPBakery (in WoodMart's "Theme elements"): choose a signature, edit or add one in a new tab, show or hide the "Copy signature" button.
* The menu icon takes the admin menu's colours and size.
* Missing translations of "Type" and "Placeholder" added.

= 1.5.0 =
* First release on GitHub: updates from GitHub releases right in the WordPress admin ("View details", "Check for updates").
