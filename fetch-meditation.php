<?php
/**
 * Plugin Name:       Fetch Meditation
 * Plugin URI:        https://wordpress.org/plugins/fetch-meditation/
 * Description:       Display a daily meditation on your site. To use this, specify [fetch_meditation] in your text code.
 * Install:           Drop this directory in the "wp-content/plugins/" directory and activate it. You need to specify "[fetch_meditation]" in the code section of a page or a post.
 * Contributors:      pjaudiomv, bmltenabled
 * Authors:           bmltenabled
 * Version:           1.0.3
 * Requires PHP:      8.1
 * Requires at least: 6.2
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace FetchMeditationPlugin;

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

if ( basename( $_SERVER['PHP_SELF'] ) == basename( __FILE__ ) ) {
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
		if ( isset( $_POST['fetch_meditation_nonce'] ) && wp_verify_nonce( $_POST['fetch_meditation_nonce'], 'fetch_meditation_action' ) ) {
			if ( isset( $_POST[ $option ] ) ) {
				// Form data option
				return sanitize_text_field( strtolower( $_POST[ $option ] ) );
			}
		}
		if ( isset( $_GET[ $option ] ) ) {
			// Query String Option
			return sanitize_text_field( strtolower( $_GET[ $option ] ) );
		} elseif ( ! empty( $attrs[ $option ] ) ) {
			// Shortcode Option
			return sanitize_text_field( strtolower( $attrs[ $option ] ) );
		} else {
			// Settings Option or Default
			return sanitize_text_field( strtolower( get_option( 'fetch_meditation_' . $option ) ) );
		}
	}

	public static function render_shortcode( string|array $attrs = [] ): string {
		$language = self::determine_option( $attrs, 'language' );
		$book     = self::determine_option( $attrs, 'book' );
		$layout   = self::determine_option( $attrs, 'layout' );

		$selected_language = ( 'spad' === $book )
			? SPADLanguage::English
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

		// SPAD only supports english
		$settings = ( 'spad' === $book ) ? new SPADSettings( $selected_language ) : new JFTSettings( $selected_language );
		$instance = ( 'spad' === $book ) ? SPAD::getInstance( $settings ) : JFT::getInstance( $settings );
		$entry    = $instance->fetch();
		return static::build_layout( $entry, 'block' === $layout );
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
	}

	public static function register_settings(): void {
		// Register plugin settings with WordPress
		register_setting(
			self::SETTINGS_GROUP,
			'fetch_meditation_language',
			[
				'type'              => 'string',
				'default'           => self::DEFAULT_LANGUAGE,
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
		$meditation_language = esc_attr( get_option( 'fetch_meditation_language' ) );
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
					<tr valign="top" id="language-container">
						<th scope="row">Language</th>
						<td>
							<?php
							echo wp_kses(
								static::render_select_option(
									'fetch_meditation_language',
									$meditation_language,
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
