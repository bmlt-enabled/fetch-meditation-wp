<?php
/**
 * Plugin Name:       Fetch Meditation
 * Plugin URI:        https://wordpress.org/plugins/fetch-meditation/
 * Description:       Display a daily meditation on your site. Use [fetch_meditation], [jft], or [spad] shortcodes.
 * Install:           Drop this directory in the "wp-content/plugins/" directory and activate it. You need to specify "[fetch_meditation]", "[jft]", or "[spad]" in the code section of a page or a post.
 * Contributors:      pjaudiomv, bmltenabled
 * Authors:           bmltenabled
 * Version:           1.3.1
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

	private const DEFAULT_LAYOUT = 'table';

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
	 * @param string $option The specific option to fetch (e.g., 'language', 'book', 'layout').
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
	 * Render JFT shortcode with book defaulted to 'jft'
	 *
	 * @param string|array $attrs Shortcode attributes
	 * @return string Rendered shortcode content
	 */
	public static function render_jft_shortcode( string|array $attrs = [] ): string {
		$attrs = is_array( $attrs ) ? $attrs : [];
		$attrs['book'] = 'jft';
		return static::render_shortcode( $attrs );
	}

	/**
	 * Render SPAD shortcode with book defaulted to 'spad'
	 *
	 * @param string|array $attrs Shortcode attributes
	 * @return string Rendered shortcode content
	 */
	public static function render_spad_shortcode( string|array $attrs = [] ): string {
		$attrs = is_array( $attrs ) ? $attrs : [];
		$attrs['book'] = 'spad';
		return static::render_shortcode( $attrs );
	}

	public static function render_shortcode( string|array $attrs = [] ): string {
		$language = self::determine_option( $attrs, 'language' );
		$book     = self::determine_option( $attrs, 'book' );
		$layout   = self::determine_option( $attrs, 'layout' );
		$timezone = self::determine_option( $attrs, 'timezone' );

		$selected_language = ( 'spad' === $book )
			? match ( $language ) {
				'english' => SPADLanguage::English,
				'german' => SPADLanguage::German,
				default => SPADLanguage::English
			}
			: match ( $language ) {
				'english' => JFTLanguage::English,
				'french' => JFTLanguage::French,
				'german' => JFTLanguage::German,
				'italian' => JFTLanguage::Italian,
				'japanese' => JFTLanguage::Japanese,
				'portuguese' => JFTLanguage::Portuguese,
				'russian' => JFTLanguage::Russian,
				'spanish' => JFTLanguage::Spanish,
				'swedish' => JFTLanguage::Swedish,
				default => JFTLanguage::English
			};

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
		wp_enqueue_style( self::PLUG_SLUG, plugin_dir_url( __FILE__ ) . 'css/fetch-meditation.css', false, '1.0.0', 'all' );
		wp_enqueue_script( self::PLUG_SLUG, plugin_dir_url( __FILE__ ) . 'js/fetch-meditation.js', [], '1.0.0', true );
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
		$meditation_layout   = esc_attr( get_option( 'fetch_meditation_layout' ) );
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
					
					<li><strong>Language:</strong><br>
					<strong>JFT:</strong> english, french, german, italian, portuguese, russian, spanish, swedish<br>
					<strong>SPAD:</strong> english, german<br>
					<code>[jft language="spanish"]</code> or <code>[spad language="german"]</code></li>

					<li><strong>Timezone (English Only):</strong> Set timezone for English language only<br>
					<code>[jft timezone="America/New_York"]</code><br>
					Common timezones: America/New_York, America/Chicago, America/Denver, America/Los_Angeles, Europe/London, etc.</li>
				</ul>
				
				<h4>Examples:</h4>
				<ul>
					<li><code>[jft]</code> - Simple JFT meditation</li>
					<li><code>[spad language="german"]</code> - German SPAD meditation</li>
					<li><code>[jft layout="block" language="spanish" timezone="Europe/Madrid"]</code> - Spanish JFT with block layout and Madrid timezone</li>
					<li><code>[fetch_meditation book="jft" layout="block" language="english" timezone="America/New_York"]</code> - Using the general shortcode</li>
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
										'table' => 'Table',
										'block' => 'Block (CSS)',
									]
								),
								$allowed_html
							);
							?>
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
									[
										'english'    => 'English',
										'french'     => 'French',
										'german'     => 'German',
										'italian'    => 'Italian',
										'japanese'   => 'Japanese',
										'portuguese' => 'Portuguese',
										'russian'    => 'Russian',
										'spanish'    => 'Spanish',
										'swedish'    => 'Swedish',
									]
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
									[
										'english'    => 'English',
										'german'     => 'German',
									]
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
