<?php

/**
 * Plugin Name: Fetch Meditation
 * Plugin URI: https://wordpress.org/plugins/fetch-meditation-wp/
 * Contributors:  pjaudiomv, bmltenabled
 * Author: bmlt-enabled
 * Description: To use this, specify [fetch_meditation] in your text code.
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
        } else {
            add_shortcode('fetch_meditation', [static::class, 'setupShortcode']);
        }
    }

    public static function setupShortcode(string|array $attrs = []): string
    {
        $language = !empty($attrs['language']) ? sanitize_text_field(strtolower($attrs['language'])) : get_option('fetch_meditation_language');
        $book = !empty($attrs['book']) ? sanitize_text_field(strtolower($attrs['book'])) : get_option('fetch_meditation_book');
        print_r($attrs);
        if ($book === "spad") {
            $selectedLanguage = SPADLanguage::English;
        } else {
            $selectedLanguage = match ($language) {
                'english' => JFTLanguage::English,
                'french' => JFTLanguage::French,
                'italian' => JFTLanguage::Italian,
                'japanese' => JFTLanguage::Japanese,
                'portuguese' => JFTLanguage::Portuguese,
                'russian' => JFTLanguage::Russian,
                'spanish' => JFTLanguage::Spanish,
                'swedish' => JFTLanguage::Swedish,
                default => JFTLanguage::English
            };
        }

        if ($book === "spad") {
            $settings = new SPADSettings($selectedLanguage);
            $spad = SPAD::getInstance($settings);
            $entry = $spad->fetch();
        } else {
            $settings = new JFTSettings($selectedLanguage);
            $jft = JFT::getInstance($settings);
            $entry = $jft->fetch();
        }

        $paragraphs = "";
        foreach ($entry->content as $c) {
            $paragraphs .= "<p>{$c}</p>";
        }
        $content = '';
        $data = [
            'date' => $entry->date,
            'title' => $entry->title,
            'page' => $entry->page,
            'quote' => $entry->quote,
            'source' => $entry->source,
            'paragraphs' => $paragraphs,
            'thought' => $entry->thought,
            'copyright' => $entry->copyright,
        ];

        foreach ($data as $key => $value) {
            if (!empty($value)) {
                $content .= '<tr><td align="' . ($key === 'title' ? 'center' : 'left') . '">' . ($key === 'quote' ? '<i>' : '') . $value . ($key === 'quote' ? '</i>' : '') . '<br><br></td></tr>';
            }
        }
        return <<<HTML
<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Just for Today Meditation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1,user-scalable=yes">
    <meta http-equiv="expires" content="-1">
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Cache-Control" content="no-cache" />
    <meta charset="UTF-8" />
</head>
<body>
<table align="center">
    {$content}
</table>
</body>
</html>
HTML;
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
    }

    public static function createMenu(): void
    {
        // Create the plugin's settings page in the WordPress admin menu
        add_options_page(
            esc_html__('Fetch Meditation Settings'), // Page Title
            esc_html__('Fetch Meditation'),          // Menu Title
            'manage_options',            // Capability
            'fetch-meditation',                      // Menu Slug
            [static::class, 'drawSettings']      // Callback function to display the page content
        );
        // Add a settings link in the plugins list
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [static::class, 'settingsLink']);
    }

    public static function settingsLink(array $links): array
    {
        // Add a "Settings" link for the plugin in the WordPress admin
        $settings_url = admin_url('options-general.php?page=fetch-meditation');
        $links[] = "<a href='{$settings_url}'>Settings</a>";
        return $links;
    }

    public static function drawSettings(): void
    {
        // Display the plugin's settings page
        $meditationLanguage = esc_attr(get_option('fetch_meditation_language'));
        $meditationBook = esc_attr(get_option('fetch_meditation_book'));
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
                    <tr valign="top" id="language-container">
                        <th scope="row">Language</th>
                        <td>
                            <?php echo static::renderSelectOption('fetch_meditation_language', $meditationLanguage, [
                                'english' => 'English',
                                'french' => 'French',
                                'italian' => 'Italian',
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
        <script>
            jQuery(document).ready(function($) {
                // Only show language dropdown for JFT
                if ($('#fetch_meditation_book').val() === 'spad') {
                    $('#language-container').hide();
                }
                $('#fetch_meditation_book').change(function() {
                    if ($(this).val() === 'jft') {
                        $('#language-container').show();
                    } else {
                        $('#language-container').hide();
                    }
                });
            });
        </script>
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
