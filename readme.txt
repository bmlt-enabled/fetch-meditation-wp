=== Fetch Meditation ===

Contributors: bmltenabled, pjaudiomv
Plugin URI: https://wordpress.org/plugins/fetch-meditation/
Tags: na, fetch meditation, jft, spad, bmlt
Requires PHP: 8.1
Tested up to: 6.9
Stable tag: 1.4.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Fetch Meditation is a plugin that pulls either the Spiritual Principle A Day or Just For Today and puts it on your page or post.

== Description ==

Fetch Meditation is a plugin that pulls either the Spiritual Principle A Day or Just For Today and puts it on your page or post.

Use one of the following shortcodes in your page or post:
- [fetch_meditation] - General shortcode (requires book attribute)
- [jft] - Just For Today meditation
- [spad] - Spiritual Principle A Day meditation

SHORTCODES
Basic JFT: [jft]
Basic SPAD: [spad]
Both (Tabbed): [fetch_meditation book="both"]
General: [fetch_meditation book="jft"]
Layout: table, block [jft layout="block"] or [spad layout="table"]
Language: JFT: english, french, german, italian, portuguese, russian, spanish, swedish. SPAD: english, german [jft language="spanish"] or [spad language="german"]
Timezone (English Only): Any valid IANA [timezone](https://www.php.net/manual/en/timezones.php) [jft timezone="America/New_York"]
Theme: default, jft-style, spad-style [jft theme="default"] or [fetch_meditation theme="spad-style"] (Note: [jft] defaults to jft-style, [spad] defaults to spad-style)
Excerpt: Show quote and metadata with read more link (hides paragraphs/thought) [jft excerpt="true" read_more_url="/full-page/"]

TABBED DISPLAY (book="both" only)
Display both JFT and SPAD meditations in an interactive interface:
- Basic (horizontal tabs): [fetch_meditation book="both"]
- Accordion layout: [fetch_meditation book="both" tabs_layout="accordion"]
- Tabs layout (default): [fetch_meditation book="both" tabs_layout="tabs"]

EXCERPT MODE
Show meditation preview on front page with link to full reading.
Displays date, title, page, quote, and source with "Read more" link (skips paragraphs, thought, copyright).
Typical workflow: Use excerpt on homepage, full meditation on dedicated page:
- Homepage: [jft excerpt="true" read_more_url="/daily-meditation/"]
- Full meditation page (/daily-meditation/): [jft excerpt="false"]

MORE INFORMATION

<a href="https://github.com/bmlt-enabled/fetch-meditation-wp" target="_blank">https://github.com/bmlt-enabled/fetch-meditation-wp</a>


== Installation ==

1. Upload `the fetch-meditation-wp` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add [jft], [spad], or [fetch_meditation] shortcode to your WordPress page/post.

== Changelog ==

= 1.4.9 =

* Fix Italian encoding issues.

= 1.4.8 =

* Add tab translations.

= 1.4.7 =

* Added excerpt mode with excerpt and read_more_url attributes for displaying meditation previews.

= 1.4.6 =

* Move how to section from settings.

= 1.4.5 =

* Added tabbed interface support with book="both" attribute.
* New tabs_layout attribute (tabs/accordion) for controlling display style.
* Added excerpt mode with read_more_url parameter for displaying meditation previews.

= 1.4.4 =

* Attempt to bust cache.

= 1.4.3 =

* Version bump.

= 1.4.2 =

* German language fixes.

= 1.4.1 =

* Update library for fixes to French, Russian and Portuguese.

= 1.4.0 =

* Added theme support with three visual styles: default, jft-style, and spad-style.
* Added theme parameter to all shortcodes: [jft theme="default"] or [fetch_meditation theme="spad-style"].

= 1.3.1 =

* Forgot to bump fetch meditaiton package.

= 1.3.0 =

* Added [jft] shortcode for Just For Today meditations.
* Added [spad] shortcode for Spiritual Principle A Day meditations.
* New shortcodes accept all existing attributes (language, layout, timezone) while defaulting to their respective book types.
* Updated documentation with new shortcode examples.

= 1.2.0 =

* Add better error handling.

= 1.1.4 =

* Change SPAD source.

= 1.1.3 =

* Moved Portuguese translation to NA Portugal
* Fixed Russian translation.

* Updated instruction.

= 1.1.2 =

* Updated instruction.

= 1.1.1 =

* Added Larger Time Zone selection list.

= 1.1.0 =

* Added Time Zone and Language selection.

= 1.0.5 =

* French language fixes.

= 1.0.4 =

* Spanish fix.
* Fallback english servers.

= 1.0.3 =

* Add nonce.

= 1.0.2 =

* Spanish language fix.

= 1.0.1 =

* Initial WordPress release.

= 1.0.0 =

* Initial release.
