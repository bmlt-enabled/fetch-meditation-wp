<?php
/**
 * Plugin Name:       Fetch Meditation
 * Plugin URI:        https://wordpress.org/plugins/fetch-meditation/
 * Description:       Display a daily meditation on your site. Use [fetch_meditation], [jft], or [spad] shortcodes.
 * Install:           Drop this directory in the "wp-content/plugins/" directory and activate it. You need to specify "[fetch_meditation]", "[jft]", or "[spad]" in the code section of a page or a post.
 * Contributors:      pjaudiomv, bmltenabled
 * Author:            bmltenabled
 * Version:           1.4.0
 * Requires PHP:      8.1
 * Requires at least: 6.2
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace FetchMeditationPlugin;

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

if ( isset( $_SERVER['PHP_SELF'] ) && basename( sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) ) == basename( __FILE__ ) ) {
	die( 'Sorry, but you cannot access this page directly.' );
}


use FetchMeditation\JFTLanguage;
use FetchMeditation\JFTSettings;
use FetchMeditation\JFT;
use FetchMeditation\SPADLanguage;
use FetchMeditation\SPADSettings;
use FetchMeditation\SPAD;

/**
 * Class FETCHMEDITATION
 * @package FetchMeditationPlugin
 */
class FETCHMEDITATION {

	private const SETTINGS_GROUP   = 'fetch-meditation-group';
	private const DEFAULT_LANGUAGE = 'English';
	private const DEFAULT_BOOK     = 'JFT';

	private const DEFAULT_LAYOUT = 'block';
	private const DEFAULT_THEME = 'default';

	private const PLUG_SLUG = 'fetch-meditation';

	/**
	 * Singleton instance of the class.
	 *
	 * @var null|self
	 */
	private static ?self $instance = null;

	/**
	 * Constructor method for initializing the plugin.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'plugin_setup' ] );
	}

	/**
	 * Setup method for initializing the plugin.
	 *
	 * This method checks if the current context is in the admin dashboard or not.
	 * If in the admin dashboard, it registers admin-related actions and settings.
	 * If not in the admin dashboard, it sets up a shortcode and associated actions.
	 *
	 * @return void
	 */
	public function plugin_setup(): void {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ static::class, 'create_menu' ] );
			add_action( 'admin_init', [ static::class, 'register_settings' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_backend_files' ], 500 );
		} else {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_files' ] );
			add_shortcode( 'fetch_meditation', [ static::class, 'render_shortcode' ] );
			add_shortcode( 'jft', [ static::class, 'render_jft_shortcode' ] );
			add_shortcode( 'spad', [ static::class, 'render_spad_shortcode' ] );
		}
	}

	/**
	 * Determines the option value based on the provided attributes or fallbacks to a default value.
	 *
	 * @param string|array $attrs An string or associative array of attributes where the key is the option name.
	 * @param string $option The specific option to fetch (e.g., 'language', 'book', 'layout', 'theme').
	 * @return string Sanitized and lowercased value of the determined option.
	 */
	private static function determine_option( string|array $attrs, string $option ): string {
		if ( isset( $_POST['fetch_meditation_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fetch_meditation_nonce'] ) ), 'fetch_meditation_action' ) ) {
			if ( isset( $_POST[ $option ] ) ) {
				// Form data option
				$value = sanitize_text_field( wp_unslash( $_POST[ $option ] ) );
				return sanitize_text_field( strtolower( $value ) );
			}
		}
		if ( isset( $_GET[ $option ] ) ) {
			// Query String Option
			$value = sanitize_text_field( wp_unslash( $_GET[ $option ] ) );
			return sanitize_text_field( strtolower( $value ) );
		} elseif ( ! empty( $attrs[ $option ] ) ) {
			// Shortcode Option
			return sanitize_text_field( strtolower( $attrs[ $option ] ) );
		} else {
			// Settings Option or Default
			if ( 'language' === $option ) {
				// Determine which book is being used and get the corresponding language
				$book = self::determine_option( $attrs, 'book' );
				return sanitize_text_field( strtolower( get_option( 'fetch_meditation_' . $book . '_language' ) ) );
			} elseif ( 'timezone' === $option ) {
				return sanitize_text_field( get_option( 'fetch_meditation_timezone' ) );
			}
			return sanitize_text_field( strtolower( get_option( 'fetch_meditation_' . $option ) ) );
		}
	}

	/**
	 * Get the appropriate language enum based on book type and language code
	 *
	 * @param string $book The book type (jft or spad)
	 * @param string $language The language code
	 * @return JFTLanguage|SPADLanguage The language enum
	 */
	private static function get_language_enum( string $book, string $language ): JFTLanguage|SPADLanguage {
		if ( 'spad' === $book ) {
			// Try to find matching SPAD language by name
			foreach ( SPADLanguage::cases() as $case ) {
				if ( strtolower( $case->name ) === $language ) {
					return $case;
				}
			}
			return SPADLanguage::English; // Default
		} else {
			// Convert language name to match enum case names
			$normalized = self::normalize_language_name( $language );
			// Try to find matching JFT language by name
			foreach ( JFTLanguage::cases() as $case ) {
				if ( $case->name === $normalized ) {
					return $case;
				}
			}
			return JFTLanguage::English; // Default
		}
	}

	/**
	 * Normalize language name to match enum case names
	 *
	 * @param string $language The language code (e.g., 'portuguese-pt')
	 * @return string The normalized name (e.g., 'PortuguesePT')
	 */
	private static function normalize_language_name( string $language ): string {
		// Special case mappings
		$special_cases = [
			'portuguese-pt' => 'PortuguesePT',
		];

		if ( isset( $special_cases[ $language ] ) ) {
			return $special_cases[ $language ];
		}

		// Default: capitalize first letter
		return ucfirst( $language );
	}

	/**
	 * Get available languages for a book type
	 *
	 * @param string $book The book type (jft or spad)
	 * @return array Associative array of language code => display name
	 */
	private static function get_available_languages( string $book ): array {
		$languages = [];

		if ( 'spad' === $book ) {
			foreach ( SPADLanguage::cases() as $case ) {
				$code = strtolower( $case->name );
				$languages[ $code ] = ucfirst( $case->name );
			}
		} else {
			foreach ( JFTLanguage::cases() as $case ) {
				// Convert enum case name to user-friendly format
				$code = self::enum_case_to_code( $case->name );
				$display = self::enum_case_to_display( $case->name );
				$languages[ $code ] = $display;
			}
		}

		return $languages;
	}

	/**
	 * Convert enum case name to language code
	 *
	 * @param string $case_name The enum case name (e.g., 'PortuguesePT')
	 * @return string The language code (e.g., 'portuguese-pt')
	 */
	private static function enum_case_to_code( string $case_name ): string {
		// Special case mappings
		$special_cases = [
			'PortuguesePT' => 'portuguese-pt',
		];

		if ( isset( $special_cases[ $case_name ] ) ) {
			return $special_cases[ $case_name ];
		}

		return strtolower( $case_name );
	}

	/**
	 * Convert enum case name to display name
	 *
	 * @param string $case_name The enum case name (e.g., 'PortuguesePT')
	 * @return string The display name (e.g., 'Portuguese (PT)')
	 */
	private static function enum_case_to_display( string $case_name ): string {
		// Special case mappings for display names
		$special_cases = [
			'PortuguesePT' => 'Portuguese (PT)',
			'Portuguese'   => 'Portuguese (BR)',
		];

		if ( isset( $special_cases[ $case_name ] ) ) {
			return $special_cases[ $case_name ];
		}

		return ucfirst( $case_name );
	}

	/**
	 * Render JFT shortcode with book defaulted to 'jft' and theme to 'jft-style'
	 *
	 * @param string|array $attrs Shortcode attributes
	 * @return string Rendered shortcode content
	 */
	public static function render_jft_shortcode( string|array $attrs = [] ): string {
		$attrs = is_array( $attrs ) ? $attrs : [];
		$attrs['book'] = 'jft';
		if ( empty( $attrs['theme'] ) ) {
			$attrs['theme'] = 'jft-style';
		}
		return static::render_shortcode( $attrs );
	}

	/**
	 * Render SPAD shortcode with book defaulted to 'spad' and theme to 'spad-style'
	 *
	 * @param string|array $attrs Shortcode attributes
	 * @return string Rendered shortcode content
	 */
	public static function render_spad_shortcode( string|array $attrs = [] ): string {
		$attrs = is_array( $attrs ) ? $attrs : [];
		$attrs['book'] = 'spad';
		if ( empty( $attrs['theme'] ) ) {
			$attrs['theme'] = 'spad-style';
		}
		return static::render_shortcode( $attrs );
	}

	public static function render_shortcode( string|array $attrs = [] ): string {
		$language = self::determine_option( $attrs, 'language' );
		$book     = self::determine_option( $attrs, 'book' );
		$layout   = self::determine_option( $attrs, 'layout' );
		$timezone = self::determine_option( $attrs, 'timezone' );
		$theme    = self::determine_option( $attrs, 'theme' );
		// Enqueue the appropriate CSS file based on theme
		self::enqueue_theme_css( $theme );

		$selected_language = self::get_language_enum( $book, $language );

		// Only apply timezone for English language
		$use_timezone = 'english' === $language && ! empty( $timezone ) ? $timezone : null;
		if ( 'spad' === $book ) {
			$settings = new SPADSettings( $selected_language, $use_timezone );
		} else {
			$settings = new JFTSettings( $selected_language, $use_timezone );
		}
		try {
			$instance = ( 'spad' === $book ) ? SPAD::getInstance( $settings ) : JFT::getInstance( $settings );
			$entry    = $instance->fetch();
			if ( is_string( $entry ) ) {
				return static::render_error_message( $entry, $book, $language );
			}
			return static::build_layout( $entry, 'block' === $layout );
		} catch ( \Exception $e ) {
			return static::render_error_message( $e->getMessage(), $book, $language );
		} catch ( \Error $e ) {
			return static::render_error_message( 'Service temporarily unavailable', $book, $language );
		}
	}

	/**
	 * Render a user-friendly error message with fallback content.
	 *
	 * @param string $error_message The error message from the library
	 * @param string $book The book type (jft or spad)
	 * @param string $language The selected language
	 * @return string HTML formatted error message
	 */
	private static function render_error_message( string $error_message, string $book, string $language ): string {
		$clean_error = esc_html( $error_message );
		$user_message = static::get_user_friendly_message( $error_message, $book, $language );
		$content = "\n<div class=\"meditation-error-container\" style=\"padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-left: 4px solid #ffba00; background-color: #fff8e1;\">\n";
		$content .= "\t<p style=\"margin: 0 0 10px 0; font-weight: bold; color: #e65100;\">Meditation Content Unavailable</p>\n";
		$content .= "\t<p style=\"margin: 0; color: #333;\">" . esc_html( $user_message ) . "</p>\n";
		$content .= "\t<details style=\"margin-top: 10px;\">\n";
		$content .= "\t\t<summary style=\"cursor: pointer; font-size: 0.9em; color: #666;\">Technical Details</summary>\n";
		$content .= "\t\t<pre style=\"background: #f5f5f5; padding: 8px; margin-top: 5px; font-size: 0.8em; border-radius: 3px; overflow-x: auto;\">" . $clean_error . "</pre>\n";
		$content .= "\t</details>\n";
		$content .= "</div>\n";
		return $content;
	}

	/**
	 * Get a user-friendly error message based on the technical error.
	 *
	 * @param string $error_message The technical error message
	 * @param string $book The book type
	 * @param string $language The selected language
	 * @return string User-friendly message
	 */
	private static function get_user_friendly_message( string $error_message, string $book, string $language ): string {
		$lower_error = strtolower( $error_message );
		if ( str_contains( $lower_error, 'network' ) || str_contains( $lower_error, 'connection' ) || str_contains( $lower_error, 'timeout' ) ) {
			return 'Unable to connect to the meditation service. Please check your internet connection and try again later.';
		}
		if ( str_contains( $lower_error, 'curl' ) || str_contains( $lower_error, 'http' ) ) {
			return 'The meditation service is temporarily unavailable. Please try again in a few minutes.';
		}
		if ( str_contains( $lower_error, 'parse' ) || str_contains( $lower_error, 'html' ) || str_contains( $lower_error, 'dom' ) ) {
			return 'The meditation content format has changed. Please contact the site administrator.';
		}
		if ( str_contains( $lower_error, 'language' ) || str_contains( $lower_error, 'not supported' ) ) {
			return sprintf( 'The selected language (%s) is not available for %s meditations. Please try a different language.', ucfirst( $language ), strtoupper( $book ) );
		}
		if ( str_contains( $lower_error, 'service temporarily unavailable' ) ) {
			return 'The meditation service is temporarily down for maintenance. Please try again later.';
		}
		return sprintf( 'Unable to load today\'s %s meditation at this time. Please try refreshing the page or check back later.', strtoupper( $book ) );
	}

	private static function build_layout( object $entry, bool $in_block ): string {
		// Render Content As HTML Table or CSS Block Elements
		$css_identifier = $in_block ? 'meditation' : 'meditation-table';

		$paragraph_content = '';
		$count            = 1;

		foreach ( $entry->content as $c ) {
			if ( $in_block ) {
				$paragraph_content .= "\n    <p id=\"$css_identifier-content-$count\" class=\"$css_identifier-rendered-element\">$c</p>";
			} else {
				$paragraph_content .= "$c<br><br>";
			}
			++$count;
		}
		$paragraph_content .= "\n";

		$content = "\n<div id=\"$css_identifier-container\" class=\"meditation-rendered-element\">\n";
		if ( ! $in_block ) {
			$content .= '<table align="center">' . "\n";
		}

		$data = [
			'date'       => $entry->date,
			'title'      => $entry->title,
			'page'       => $entry->page,
			'quote'      => $entry->quote,
			'source'     => $entry->source,
			'paragraphs' => $paragraph_content,
			'thought'    => $entry->thought,
			'copyright'  => $entry->copyright,
		];

		foreach ( $data as $key => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			if ( 'quote' === $key && ! $in_block ) {
				$element = '<i>' . $value . '</i>';
			} elseif ( 'title' === $key && ! $in_block ) {
				$element = '<h1>' . $value . '</h1>';
			} elseif ( 'date' === $key && ! $in_block ) {
				$element = '<h2>' . $value . '</h2>';
			} else {
				$element = $value;
			}

			if ( $in_block ) {
				$content .= "  <div id=\"$css_identifier-$key\" class=\"$css_identifier-rendered-element\">$element</div>\n";
			} else {
				$alignment = in_array( $key, [ 'title', 'page', 'source' ] ) ? 'center' : 'left';
				$line_break = in_array( $key, [ 'quote-source', 'quote', 'thought', 'page' ] ) ? '<br><br>' : '';
				$content  .= "<tr><td align=\"$alignment\">$element$line_break</td></tr>\n";
			}
		}

		$content .= $in_block ? "</div>\n" : "</table>\n</div>\n";
		return $content;
	}


	public function enqueue_backend_files( string $hook ): void {
		if ( 'settings_page_' . self::PLUG_SLUG !== $hook ) {
			return;
		}
		$base_url = plugin_dir_url( __FILE__ );
		wp_enqueue_script( 'fetch-meditation-admin', $base_url . 'js/fetch-meditation.js', [ 'jquery' ], filemtime( plugin_dir_path( __FILE__ ) . 'js/fetch-meditation.js' ), false );
	}

	public function enqueue_frontend_files(): void {
		// We can't determine the theme at enqueue time since shortcodes haven't been processed
		// Instead, we'll enqueue CSS dynamically in the shortcode render methods
	}

	/**
	 * Enqueue the appropriate CSS file based on theme
	 *
	 * @param string $theme The theme to use
	 * @return void
	 */
	private static function enqueue_theme_css( string $theme ): void {
		static $enqueued_theme = null;
		if ( $theme === $enqueued_theme ) {
			return;
		}
		if ( null !== $enqueued_theme ) {
			wp_dequeue_style( self::PLUG_SLUG );
		}
		$enqueued_theme = $theme;
		$css_file = match ( strtolower( $theme ) ) {
			'jft-style' => 'fetch-meditation-jft.css',
			'spad-style' => 'fetch-meditation-spad.css',
			default => 'fetch-meditation.css',
		};
		wp_enqueue_style( self::PLUG_SLUG, plugin_dir_url( __FILE__ ) . 'css/' . $css_file, false, '1.0.0', 'all' );
	}

	public static function register_settings(): void {
		// Register plugin settings with WordPress
		register_setting(
			self::SETTINGS_GROUP,
			'fetch_meditation_jft_language',
			[
				'type'              => 'string',
				'default'           => self::DEFAULT_LANGUAGE,
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		register_setting(
			self::SETTINGS_GROUP,
			'fetch_meditation_spad_language',
			[
				'type'              => 'string',
				'default'           => self::DEFAULT_LANGUAGE,
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		register_setting(
			self::SETTINGS_GROUP,
			'fetch_meditation_timezone',
			[
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		register_setting(
			self::SETTINGS_GROUP,
			'fetch_meditation_book',
			[
				'type'              => 'string',
				'default'           => self::DEFAULT_BOOK,
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		register_setting(
			self::SETTINGS_GROUP,
			'fetch_meditation_layout',
			[
				'type'              => 'string',
				'default'           => self::DEFAULT_LAYOUT,
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		register_setting(
			self::SETTINGS_GROUP,
			'fetch_meditation_theme',
			[
				'type'              => 'string',
				'default'           => self::DEFAULT_THEME,
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
	}

	public static function create_menu(): void {
		// Create the plugin's settings page in the WordPress admin menu
		add_options_page(
			esc_html__( 'Fetch Meditation Settings', 'fetch-meditation' ), // Page Title
			esc_html__( 'Fetch Meditation', 'fetch-meditation' ),          // Menu Title
			'manage_options',                        // Capability
			self::PLUG_SLUG,                         // Menu Slug
			[ static::class, 'draw_settings' ]         // Callback function to display the page content
		);
		// Add a settings link in the plugins list
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ static::class, 'settings_link' ] );
	}

	public static function settings_link( array $links ): array {
		// Add a "Settings" link for the plugin in the WordPress admin
		$settings_url = admin_url( 'options-general.php?page=' . self::PLUG_SLUG );
		$links[]      = "<a href='{$settings_url}'>Settings</a>";
		return $links;
	}

	public static function draw_settings(): void {
		// Display the plugin's settings page
		$meditation_book     = esc_attr( get_option( 'fetch_meditation_book' ) );
		$meditation_layout   = esc_attr( get_option( 'fetch_meditation_layout', self::DEFAULT_LAYOUT ) );
		$meditation_theme    = esc_attr( get_option( 'fetch_meditation_theme' ) );
		$jft_language       = esc_attr( get_option( 'fetch_meditation_jft_language' ) );
		$spad_language      = esc_attr( get_option( 'fetch_meditation_spad_language' ) );
		$timezone           = esc_attr( get_option( 'fetch_meditation_timezone' ) );
		$allowed_html = [
			'select' => [
				'id'   => [],
				'name' => [],
			],
			'option' => [
				'value'   => [],
				'selected'   => [],
			],
		];
		?>
		<div class="wrap">
			<h2>Fetch Meditation Settings</h2>
			
			<div class="card" style="max-width: 800px; margin-bottom: 20px;">
				<h3>How to Use</h3>
				<p>Add one of the following shortcodes to your page or post to display the meditation:</p>
				<ul>
					<li><code>[fetch_meditation]</code> - General shortcode (requires book attribute)</li>
					<li><code>[jft]</code> - Displays Just For Today meditation</li>
					<li><code>[spad]</code> - Displays Spiritual Principle A Day meditation</li>
				</ul>
				
				<h4>Available Options:</h4>
				<ul>
					<li><strong>Book:</strong> Choose between JFT or SPAD (not needed for [jft] and [spad] shortcodes)<br>
					<code>[fetch_meditation book="jft"]</code> or <code>[fetch_meditation book="spad"]</code></li>
					
					<li><strong>Layout:</strong> Choose between table or block layout<br>
					<code>[jft layout="block"]</code> or <code>[spad layout="table"]</code></li>
					
					<li><strong>Theme:</strong> Choose visual appearance (default, jft-style, spad-style)<br>
					<code>[fetch_meditation theme="jft-style"]</code> or <code>[jft theme="default"]</code><br>
					Note: [jft] shortcode defaults to jft-style theme, [spad] shortcode defaults to spad-style theme</li>

					<li><strong>Language:</strong><br>
					<strong>JFT:</strong> <?php echo esc_html( implode( ', ', array_keys( static::get_available_languages( 'jft' ) ) ) ); ?><br>
					<strong>SPAD:</strong> <?php echo esc_html( implode( ', ', array_keys( static::get_available_languages( 'spad' ) ) ) ); ?><br>
					<code>[jft language="spanish"]</code> or <code>[spad language="german"]</code></li>

					<li><strong>Timezone (English Only):</strong> Set timezone for English language only<br>
					<code>[jft timezone="America/New_York"]</code><br>
					Common timezones: America/New_York, America/Chicago, America/Denver, America/Los_Angeles, Europe/London, etc.</li>
				</ul>
				
				<h4>Examples:</h4>
				<ul>
					<li><code>[jft]</code> - Simple JFT meditation (uses JFT style theme by default)</li>
					<li><code>[spad language="german"]</code> - German SPAD meditation (uses SPAD style theme by default)</li>
					<li><code>[jft layout="block" language="spanish" timezone="Europe/Madrid"]</code> - Spanish JFT with block layout and Madrid timezone</li>
					<li><code>[fetch_meditation book="jft" layout="block" language="english" timezone="America/New_York"]</code> - Using the general shortcode</li>
					<li><code>[jft theme="default"]</code> - JFT meditation with default theme instead of JFT style</li>
					<li><code>[fetch_meditation book="spad" theme="jft-style"]</code> - SPAD meditation with JFT style theme</li>
				</ul>
			</div>

			<form method="post" action="options.php">
				<?php wp_nonce_field( 'fetch_meditation_action', 'fetch_meditation_nonce' ); ?>
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<?php do_settings_sections( self::SETTINGS_GROUP ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Book</th>
						<td>
							<?php
							echo wp_kses(
								static::render_select_option(
									'fetch_meditation_book',
									$meditation_book,
									[
										'jft' => 'JFT',
										'spad' => 'SPAD',
									]
								),
								$allowed_html
							);
							?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Layout</th>
						<td>
							<?php
							echo wp_kses(
								static::render_select_option(
									'fetch_meditation_layout',
									$meditation_layout,
									[
										'block' => 'Block (CSS)',
										'table' => 'Table',
									]
								),
								$allowed_html
							);
							?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">Theme</th>
						<td>
							<?php
							echo wp_kses(
								static::render_select_option(
									'fetch_meditation_theme',
									$meditation_theme,
									[
										'default' => 'Default',
										'jft-style' => 'JFT Style',
										'spad-style' => 'SPAD Style',
									]
								),
								$allowed_html
							);
							?>
							<p class="description">Choose the visual theme for the meditation display. Note: [jft] shortcode defaults to JFT Style, [spad] shortcode defaults to SPAD Style.</p>
						</td>
					</tr>
					<tr valign="top" id="jft-language-container">
						<th scope="row">JFT Language</th>
						<td>
							<?php
							echo wp_kses(
								static::render_select_option(
									'fetch_meditation_jft_language',
									$jft_language,
									static::get_available_languages( 'jft' )
								),
								$allowed_html
							);
							?>
						</td>
					</tr>
					<tr valign="top" id="spad-language-container">
						<th scope="row">SPAD Language</th>
						<td>
							<?php
							echo wp_kses(
								static::render_select_option(
									'fetch_meditation_spad_language',
									$spad_language,
									static::get_available_languages( 'spad' )
								),
								$allowed_html
							);
							?>
						</td>
					</tr>
					<tr valign="top" id="timezone-container">
						<th scope="row">Timezone (English Only)</th>
						<td>
							<?php
							$timezone_options = [
								'' => 'Server Default',
								'America/New_York' => 'America/New_York',
								'America/Chicago' => 'America/Chicago',
								'America/Denver' => 'America/Denver',
								'America/Los_Angeles' => 'America/Los_Angeles',
								'America/Anchorage' => 'America/Anchorage',
								'America/Honolulu' => 'America/Honolulu',
								'America/Phoenix' => 'America/Phoenix',
								'Europe/London' => 'Europe/London',
								'Europe/Paris' => 'Europe/Paris',
								'Europe/Berlin' => 'Europe/Berlin',
								'Australia/Sydney' => 'Australia/Sydney',
								'Asia/Tokyo' => 'Asia/Tokyo',
							];
							echo wp_kses(
								static::render_select_option(
									'fetch_meditation_timezone',
									$timezone,
									$timezone_options
								),
								$allowed_html
							);
							?>
							<p class="description">Only applies when English language is selected. Leave blank to use server default.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private static function render_select_option( string $name, string $selected_value, array $options ): string {
		// Render a dropdown select input for settings
		$select_html = "<select id='$name' name='$name'>";
		foreach ( $options as $value => $label ) {
			$selected    = selected( $selected_value, $value, false );
			$select_html .= "<option value='$value' $selected>$label</option>";
		}
		$select_html .= '</select>';

		return $select_html;
	}

	public static function get_instance(): self {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

FETCHMEDITATION::get_instance();
