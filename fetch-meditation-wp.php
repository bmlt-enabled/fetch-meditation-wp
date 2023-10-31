<?php

/**
 * Plugin Name: Fetch Meditation
 * Plugin URI: https://wordpress.org/plugins/fetch-meditation-wp/
 * Contributors:  pjaudiomv, bmltenabled
 * Author: bmlt-enabled
 * Description: Display a daily meditation on your site. To use this, specify [fetch_meditation] in your text code.
 * Version: 1.0.0
 * Install: Drop this directory in the "wp-content/plugins/" directory and activate it. You need to specify "[fetch_meditation]" in the code section of a page or a post.
 */

namespace FetchMeditationPlugin;

require_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php');

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Sorry, but you cannot access this page directly.');
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
class FETCHMEDITATION
{
    private const SETTINGS_GROUP = 'fetch-meditation-group';
    private const DEFAULT_LANGUAGE = 'English';
    private const DEFAULT_BOOK = 'JFT';

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
    public function __construct()
    {
        add_action('init', [$this, 'pluginSetup']);
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
    public function pluginSetup(): void
    {
        if (is_admin()) {
            add_action('admin_menu', [static::class, 'createMenu']);
            add_action('admin_init', [static::class, 'registerSettings']);
            add_action("admin_enqueue_scripts", [$this, "enqueueBackendFiles"], 500);
        } else {
            add_action("wp_enqueue_scripts", [$this, "enqueueFrontendFiles"]);
            add_shortcode('fetch_meditation', [static::class, 'renderShortcode']);
        }
    }

    public static function renderShortcode(string|array $attrs = []): string
    {
        $book = sanitize_text_field(strtolower($attrs['book'] ?? get_option('fetch_meditation_book')));
        $layout = sanitize_text_field(strtolower($attrs['layout'] ?? get_option('fetch_meditation_layout')));
        $language = sanitize_text_field(strtolower($attrs['language'] ?? get_option('fetch_meditation_language')));

        $selectedLanguage = ($book === "spad")
            ? SPADLanguage::English
            : match ($language) {
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

        $settings = ($book === "spad") ? new SPADSettings($selectedLanguage) : new JFTSettings($selectedLanguage);
        $instance = ($book === "spad") ? SPAD::getInstance($settings) : JFT::getInstance($settings);
        $entry = $instance->fetch();
        return static::buildLayout($entry, $layout === "block");
    }

    private static function buildLayout(object $entry, bool $inBlock): string
    {
        $cssIdentifier = $inBlock ? 'meditation' : 'meditation-table';

        $paragraphContent = '';
        $count = 1;

        foreach ($entry->content as $c) {
            if ($inBlock) {
                $paragraphContent .= "\n    <p id=\"$cssIdentifier-content-$count\" class=\"$cssIdentifier-rendered-element\">$c</p>";
            } else {
                $paragraphContent .= "$c<br><br>";
            }
            $count++;
        }
        $paragraphContent .= "\n";

        $content = "\n<div id=\"$cssIdentifier-container\" class=\"meditation-rendered-element\">\n";
        if (!$inBlock) {
            $content .= '<table align="center">' . "\n";
        }

        $data = [
            'date' => $entry->date,
            'title' => $entry->title,
            'page' => $entry->page,
            'quote' => $entry->quote,
            'source' => $entry->source,
            'paragraphs' => $paragraphContent,
            'thought' => $entry->thought,
            'copyright' => $entry->copyright,
        ];

        foreach ($data as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if ($key === 'quote' && !$inBlock) {
                $element = '<i>' . $value . '</i>';
            } elseif ($key === 'title' && !$inBlock) {
                $element = '<h1>' . $value . '</h1>';
            } elseif ($key === 'date' && !$inBlock) {
                $element = '<h2>' . $value . '</h2>';
            } else {
                $element = $value;
            }

            if ($inBlock) {
                $content .= "  <div id=\"$cssIdentifier-$key\" class=\"$cssIdentifier-rendered-element\">$element</div>\n";
            } else {
                $alignment = in_array($key, ['title', 'page', 'source']) ? 'center' : 'left';
                $lineBreak = in_array($key, ['quote-source', 'quote', 'thought', 'page']) ? '<br><br>' : '';
                $content .= "<tr><td align=\"$alignment\">$element$lineBreak</td></tr>\n";
            }
        }

        $content .= $inBlock ? "</div>\n" : "</table>\n</div>\n";
        return $content;
    }


    public function enqueueBackendFiles(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::PLUG_SLUG) {
            return;
        }
        $baseUrl = plugin_dir_url(__FILE__);
        wp_enqueue_script('fetch-meditation-admin', $baseUrl . 'js/fetch-meditation.js', ['jquery'], filemtime(plugin_dir_path(__FILE__) . 'js/fetch-meditation.js'), false);
    }

    public function enqueueFrontendFiles(): void
    {
        wp_enqueue_style(self::PLUG_SLUG, plugin_dir_url(__FILE__) . 'css/fetch-meditation.css', false, '1.0.0', 'all');
    }

    public static function registerSettings(): void
    {
        // Register plugin settings with WordPress
        register_setting(self::SETTINGS_GROUP, 'fetch_meditation_language', [
            'type' => 'string',
            'default' => self::DEFAULT_LANGUAGE,
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting(self::SETTINGS_GROUP, 'fetch_meditation_book', [
            'type' => 'string',
            'default' => self::DEFAULT_BOOK,
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting(self::SETTINGS_GROUP, 'fetch_meditation_layout', [
            'type' => 'string',
            'default' => self::DEFAULT_LAYOUT,
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    public static function createMenu(): void
    {
        // Create the plugin's settings page in the WordPress admin menu
        add_options_page(
            esc_html__('Fetch Meditation Settings'), // Page Title
            esc_html__('Fetch Meditation'),          // Menu Title
            'manage_options',                        // Capability
            self::PLUG_SLUG,                         // Menu Slug
            [static::class, 'drawSettings']          // Callback function to display the page content
        );
        // Add a settings link in the plugins list
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [static::class, 'settingsLink']);
    }

    public static function settingsLink(array $links): array
    {
        // Add a "Settings" link for the plugin in the WordPress admin
        $settings_url = admin_url('options-general.php?page=' . self::PLUG_SLUG);
        $links[] = "<a href='{$settings_url}'>Settings</a>";
        return $links;
    }

    public static function drawSettings(): void
    {
        // Display the plugin's settings page
        $meditationBook = esc_attr(get_option('fetch_meditation_book'));
        $meditationLayout = esc_attr(get_option('fetch_meditation_layout'));
        $meditationLanguage = esc_attr(get_option('fetch_meditation_language'));
        ?>
        <div class="wrap">
            <h2>Fetch Meditation Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>
                <?php do_settings_sections(self::SETTINGS_GROUP); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Book</th>
                        <td>
                            <?php echo static::renderSelectOption('fetch_meditation_book', $meditationBook, [
                                'jft' => 'JFT',
                                'spad' => 'SPAD',
                            ]); ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Layout</th>
                        <td>
                            <?php echo static::renderSelectOption('fetch_meditation_layout', $meditationLayout, [
                                'table' => 'Table',
                                'block' => 'Block (CSS)',
                            ]); ?>
                        </td>
                    </tr>
                    <tr valign="top" id="language-container">
                        <th scope="row">Language</th>
                        <td>
                            <?php echo static::renderSelectOption('fetch_meditation_language', $meditationLanguage, [
                                'english' => 'English',
                                'french' => 'French',
                                'german' => 'German',
                                'italian' => 'Italian',
                                'japanese' => 'Japanese',
                                'portuguese' => 'Portuguese',
                                'russian' => 'Russian',
                                'spanish' => 'Spanish',
                                'swedish' => 'Swedish',
                            ]); ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private static function renderSelectOption(string $name, string $selectedValue, array $options): string
    {
        // Render a dropdown select input for settings
        $selectHtml = "<select id='$name' name='$name'>";
        foreach ($options as $value => $label) {
            $selected = selected($selectedValue, $value, false);
            $selectHtml .= "<option value='$value' $selected>$label</option>";
        }
        $selectHtml .= "</select>";

        return $selectHtml;
    }

    public static function getInstance(): self
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

FETCHMEDITATION::getInstance();
