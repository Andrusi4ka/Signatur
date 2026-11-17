=== Signatures ===
Contributors: andrusi4ka
Tags: email signature, signature, gmail, outlook
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.9.0
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
* Languages: English and Norwegian (Bokmål).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the zip under Plugins → Add New → Upload Plugin.
2. Activate it. Updates then come from the plugin's GitHub releases under Plugins, like any other update.

== Changelog ==

= 1.9.0 =
* Help for whoever builds a signature: an "i" beside the fields of every window with what it does, and a fuller tip on the editor's icon buttons.
* Two buttons under the editor's title: "Instructions for admins" (how to build a signature) and "Instructions for the site" (how visitors put it into Gmail / Outlook), each in a window with a Copy button.
* Languages: English and Norwegian only (Ukrainian and Russian removed). Padding and Margin keep these names in Norwegian.

= 1.8.0 =
* The built-in Facebook, Instagram, LinkedIn and Telegram icons are PNG: email clients do not show SVG.
* SVG can no longer be uploaded for images and icons; one uploaded before is pointed out in its window.
* With a "Profile photo" field, the page notes that some email programs (Gmail) may not show the photo after pasting.

= 1.7.0 =
* Padding and Margin in a box as WP Banner's: a field on each side, the link in the middle sets the four sides together.
* Colours with a picker as WP Banner's: the theme's colours, black, white and greys, Default and an eyedropper.
* The "Copy signature" button as WP Banner's Button: Background (Filled, Border, Underline), Corners, preset Size, its colour and Text color. Buttons saved before keep their colours.

= 1.6.0 =
* Signatures → Settings: what each role may do with the signatures (edit, add, delete, the WPBakery element). Administrators may always do all of it.
* A "Signatures" element for WPBakery (in WoodMart's "Theme elements"): choose a signature, edit or add one in a new tab, show or hide the "Copy signature" button.
* The menu icon takes the admin menu's colours and size.
* Missing translations of "Type" and "Placeholder" added.

= 1.5.0 =
* First release on GitHub: updates from GitHub releases right in the WordPress admin ("View details", "Check for updates").
