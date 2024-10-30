<?php
/**
 * Calendar Press Plugin
 * 
 * @category Plugins
 * @package  BetterRssWidget
 * @author   Shane Lambert <grandslambert@gmail.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     https://grandslambert.com/plugins/better-rss-widget
 * 
 * Plugin Name: Better RSS Widget
 * Plugin URI: http://grandslambert.com/plugins/better-rss-widget
 * Description: Replacement for the built in RSS widget that adds an optional link target, shortcode_handler, and page conditionals.
 * Author: grandslambert
 * Version: 2.8.1
 * Author URI: http://grandslambert.com/
 */

/**
 * Class for Calendar Press Plugin
 * 
 * @category   Widget
 * @package    Calendar_Press
 * @subpackage Widget
 * @author     GrandSlambert <grandslambert@gmail.com>
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link       https://grandslambert.com/plugins/better-rss-weiget
 */
class Better_Rss_Widget extends WP_Widget
{
    var $version = '2.8.1';

    /* Plugin settings */
    var $optionsName = 'better-rss-widget-options';
    var $menuName = 'better-rss-widget-settings';
    var $pluginName = 'Better RSS Widget';
    var $options = array();

    /**
     * Class constructor - initializes the widget.
     * 
     * @return null
     */
    function __construct()
    {
        parent::__construct(
            'better_rss_widget',
            __('Better RSS Widget', ' better-rss-widget'),
            array(
            'description' => __('A Better RSS Widget', 'better-rss-widget'),
            'show_instance_in_rest' => true,
            )
        );

           add_action('init', array($this, 'init'));

           $this->pluginPath = WP_PLUGIN_DIR . '/' . basename(dirname(__FILE__));
           $this->pluginURL = WP_PLUGIN_URL . '/' . basename(dirname(__FILE__));
           $this->loadSettings();
        
        /* WordPress Actions */
           add_action('admin_menu', array(&$this, 'adminMenu'));
           add_action('admin_init', array(&$this, 'adminInit'));
           add_action('update_option_' . $this->optionsName, array(&$this, 'updateOptions'), 10);

           add_filter('plugin_action_links', array(&$this, 'pluginActionLinks'), 10, 2);
           add_shortcode('better-rss', array($this, 'shortcodeHandler'));
    }
    
    /**
     * Load the language file during WordPress init.
     * 
     * @return null
     */
    function init()
    {
        /* Load Langague Files */
        $langDir = dirname(plugin_basename(__FILE__)) . '/lang';
        load_plugin_textdomain('better-rss-widget', false, $langDir, $langDir);
    }
    
    /**
     * The widget display code
     * 
     * @param array $args     Arguments for the widget.
     * @param array $instance The widget instance data
     * 
     * @return null
     */
    public function widget($args, $instance)
    {
        $instance = $this->defaults($instance);

        if (isset($instance['error']) && $instance['error']) {
            return;
        }

        extract($args, EXTR_SKIP);

        $url = $instance['rss_url'];
        while (stristr($url, 'http') != $url) {
            $url = substr($url, 1);
        }

        if (empty($url)) {
            return;
        }

        $rss = fetch_feed($url);
        $desc = '';
        $link = '';

        if (!is_wp_error($rss)) {
            $desc = esc_attr(strip_tags(@html_entity_decode($rss->get_description(), ENT_QUOTES, get_option('blog_charset'))));
            if (empty($instance['title'])) {
                $instance['title'] = esc_html(strip_tags($rss->get_title()));
            }
            $link = esc_url(strip_tags($rss->get_permalink()));
            while (stristr($link, 'http') != $link) {
                $link = substr($link, 1);
            }
        }

        if (empty($instance['title'])) {
            $instance['title'] = empty($desc) ? __('Unknown Feed', 'better-rss-widget') : $desc;
        }

        $instance['title'] = apply_filters('widget_title', $instance['title']);
        $url = esc_url(strip_tags($url));
        $icon = includes_url('images/rss.png');

        if ($instance['title_url']) {
            $url = $link = $instance['title_url'];
        }

        $target = '';

        if ($instance['link_target'] != 'none') {
            $target = 'target="' . $instance['link_target'] . '"';
        }

        if ($instance['title']) {
            if (!$instance['no_link_title']) {
                $instance['title'] = '<a class="rsswidget" href="' . $link . '" title="' . $desc . '" ' . $target . '>' . $instance['title'] . '</a>';
            }

            if ($instance['show_icon']) {
                $instance['title'] = "<a class='rsswidget' href='" . $instance[$instance['link_icon']] . "' title='" . esc_attr(__('Syndicate this content', 'better-rss-widget')) . "' . $target . '><img style='background:orange;color:white;border:none;' width='14' height='14' src='$icon' alt='RSS' /></a> " . $instance['title'];
            }
        }

        print $before_widget;
        if ($instance['title']) {
            print $before_title . $instance['title'] . $after_title;
        }

        if (true == $this->options->allow_intro && !empty($instance['intro_text'])) {
            print '<div class="better-rss-intro-text">' . $instance['intro_text'] . '</div>';
        }

        $this->rssOutput($rss, $instance);
        print $after_widget;
    }
    
    /**
     * The widget form.
     * 
     * @param array $instance The widget instance data
     * 
     * @return null
     */
    public function form($instance)
    {
        if (count($instance) < 1) {
            $instance = $this->defaults($instance);
        }
        include $this->pluginPath . '/includes/widget-form.php';
    }
    
    /**
     * Method to update the instance.
     * 
     * @param array $new_instance The new instance data.
     * @param array $old_instance The old instance data.
     * 
     * @return array
     */
    public function update( $new_instance, $old_instance )
    {
        $instance = array();
        $instance['rss_url'] = ( ! empty($new_instance['rss_url']) ) ? strip_tags($new_instance['rss_url']) : '';
        $instance['title'] = ( ! empty($new_instance['title']) ) ? strip_tags($new_instance['title']) : '';
        $instance['title_url'] = ( ! empty($new_instance['title_url']) ) ? strip_tags($new_instance['title_url']) : '';
        $instance['no_link_title'] = ( ! empty($new_instance['no_link_title']) ) ? strip_tags($new_instance['no_link_title']) : '';
        $instance['show_icon'] = ( ! empty($new_instance['show_icon']) ) ? strip_tags($new_instance['show_icon']) : '';
        $instance['link_icon'] = ( ! empty($new_instance['link_icon']) ) ? strip_tags($new_instance['link_icon']) : '';
        $instance['show_summary'] = ( ! empty($new_instance['show_summary']) ) ? strip_tags($new_instance['show_summary']) : '';
        $instance['show_author'] = ( ! empty($new_instance['show_author']) ) ? strip_tags($new_instance['show_author']) : '';
        $instance['show_date'] = ( ! empty($new_instance['show_date']) ) ? strip_tags($new_instance['show_date']) : '';
        $instance['show_time'] = ( ! empty($new_instance['show_time']) ) ? strip_tags($new_instance['show_time']) : '';
        $instance['link_target'] = ( ! empty($new_instance['link_target']) ) ? strip_tags($new_instance['link_target']) : '';
        $instance['nofollow'] = ( ! empty($new_instance['nofollow']) ) ? strip_tags($new_instance['nofollow']) : '';
        $instance['enable_cache'] = ( ! empty($new_instance['enable_cache']) ) ? strip_tags($new_instance['enable_cache']) : '';
        $instance['cache_duration'] = ( ! empty($new_instance['cache_duration']) ) ? strip_tags($new_instance['cache_duration']) : '';
        $instance['is_home'] = ( ! empty($new_instance['is_home']) ) ? strip_tags($new_instance['is_home']) : '';
        $instance['is_front'] = ( ! empty($new_instance['is_front']) ) ? strip_tags($new_instance['is_front']) : '';
        $instance['is_archive'] = ( ! empty($new_instance['is_archive']) ) ? strip_tags($new_instance['is_archive']) : '';
        $instance['is_search'] = ( ! empty($new_instance['is_search']) ) ? strip_tags($new_instance['is_search']) : '';
        $instance['is_category'] = ( ! empty($new_instance['is_category']) ) ? strip_tags($new_instance['is_category']) : '';
        $instance['is_tag'] = ( ! empty($new_instance['is_tag']) ) ? strip_tags($new_instance['is_tag']) : '';
        $instance['is_single'] = ( ! empty($new_instance['is_single']) ) ? strip_tags($new_instance['is_single']) : '';
        $instance['is_date'] = ( ! empty($new_instance['is_date']) ) ? strip_tags($new_instance['is_date']) : '';
        $instance['limit_title_length'] = ( ! empty($new_instance['limit_title_length']) ) ? strip_tags($new_instance['limit_title_length']) : '';
        $instance['title_length'] = ( ! empty($new_instance['title_length']) ) ? strip_tags($new_instance['title_length']) : '';
        $instance['excerpt'] = ( ! empty($new_instance['excerpt']) ) ? strip_tags($new_instance['excerpt']) : '';
        $instance['items'] = ( ! empty($new_instance['items']) ) ? strip_tags($new_instance['items']) : '';
        $instance['intro_text'] = ( ! empty($new_instance['intro_text']) ) ? strip_tags($new_instance['intro_text']) : '';
        return $instance;
    }
    
    /**
     * Load the plugin settings.
     * 
     * @return null
     */
    function loadSettings()
    {
        $options = get_option($this->optionsName);

        $defaults = array(
            'link_target' => '_blank',
            'allow_intro' => (is_array($options)) ? isset($options['allow_intro']) : true,
            'show_summary' => false,
            'show_author' => false,
            'show_date' => false,
            'show_time' => false,
            'nofollow' => false,
            'enable_cache' => (is_array($options)) ? isset($options['enable_cache']) : true,
            'cache_duration' => 3600,
            'items' => 10,
            'title_length' => (is_array($options) && !empty($options['title_length'])) ? isset($options['title_length']) : 0,
            'excerpt' => 360,
            'suffix' => ' [&hellip;]',
        'is_home_default' => false,
            'is_front_default' => false,
            'is_archive_default' => false,
            'is_search_default' => false,
            'is_category_default' => false,
            'is_tag_default' => false,
            'is_single_default' => false,
            'is_date_default' => false
        );

        $this->options = (object) wp_parse_args($options, $defaults);
    }
    
    /**
     * Load the instance defaults.
     *
     * @param array $instance Instance object
     * 
     * @return array
     */
    function defaults($instance)
    {

        /* Fix any old instances to use new naming convention. */
        if (isset($instance['url'])) {
            $instance['rss-url'] = $instance['url'];
            $instance['title_url'] = $instance['titleurl'];
            $instance['show_icon'] = $instance['showicon'];
            $instance['show_summary'] = $instance['showsummary'];
            $instance['show_author'] = $instance['showauthor'];
            $instance['show_date'] = $instance['showdate'];
            $instance['show_time'] = $instance['showtime'];
            $instance['link_target'] = $instance['linktarget'];
            $instance['title_legnth'] = (isset($instance['title_length']) ? $instance['title_length'] : $this->options->title_length);
        }

        /* This is the new naming convention for the form fields */
        $new_defaults = array(
            'rss_url' => '',
            'title' => __('RSS Feed', 'better-rss-widget'),
            'title_url' => '',
            'no_link_title' => false,
            'show_icon' => false,
            'link_icon' => 'rss_url',
            'show_summary' => $this->options->show_summary,
            'show_author' => $this->options->show_author,
            'show_date' => $this->options->show_date,
            'show_time' => $this->options->show_time,
            'link_target' => $this->options->link_target,
            'nofollow' => $this->options->nofollow,
            'enable_cache' => $this->options->enable_cache,
            'cache_duration' => $this->options->cache_duration,
            'is_home' => $this->options->is_home_default,
            'is_front' => $this->options->is_front_default,
            'is_archive' => $this->options->is_archive_default,
            'is_search' => $this->options->is_search_default,
            'is_category' => $this->options->is_category_default,
            'is_tag' => $this->options->is_tag_default,
            'is_single' => $this->options->is_single_default,
            'is_date' => $this->options->is_date_default,
            'title_length' => $this->options->title_length,
            'excerpt' => $this->options->excerpt,
            'items' => $this->options->items
        );

        return wp_parse_args($instance, $new_defaults);
    }

    /**
     * Method to output the RSS for the widget and shortcode_handler
     *
     * @param string $rss  The RSS URL to fetch.
     * @param array  $args Arguments for the output
     * 
     * @return null
     */
    function rssOutput($rss, $args = array())
    {
        if (is_string($rss)) {
            $rss = fetch_feed($rss);
        } elseif (is_array($rss) && isset($rss['url'])) {
            $args = $rss;
            $rss = fetch_feed($rss['url']);
        } elseif (!is_object($rss)) {
            return;
        }

        if (is_wp_error($rss)) {
            if (is_admin() || current_user_can('manage_options')) {
                print '<p>' . sprintf(__('<strong>RSS Error</strong>: %s', 'better-rss-widget'), $rss->get_error_message()) . '</p>';
            }

            return;
        }

        $args = wp_parse_args($args, $this->defaultsArgs());
        extract($args, EXTR_SKIP);

        $items = (int) $items;
        if ($items < 1 || 20 < $items) {
            $items = 10;
        }
        $show_summary = (int) $show_summary;
        $show_author = (int) $show_author;
        $show_date = (int) $show_date;

        // Set the cache duration
        $rss->enable_cache($enable_cache);
        $rss->set_cache_duration($cache_duration);
        $rss->init();

        if (!$rss->get_item_quantity()) {
            print '<ul><li>' . __('An error has occurred; the feed is probably down. Try again later.', 'better-rss-widget') . '</li></ul>';
            return;
        }

        if (strtolower($link_target) != 'none') {
            $target = 'target="' . $link_target . '"';
        } else {
            $target = '';
        }

        print '<' . $args['html_parent'] . '>';
        
        foreach ($rss->get_items(0, $items) as $item) {
            $link = $item->get_link();
            while (stristr($link, 'http') != $link) {
                $link = substr($link, 1);
            }
            $link = esc_url(strip_tags($link));
            $title = esc_attr(strip_tags($item->get_title()));
            
            if (empty($title)) {
                $title = __('Untitled', 'better-rss-widget');
            }

            $desc = str_replace(array("\n", "\r"), ' ', esc_attr(strip_tags(@html_entity_decode($item->get_description(), ENT_QUOTES, get_option('blog_charset')))));

            if (!$hide_title) {
                $desc = wp_html_excerpt($desc, $excerpt) . $this->options->suffix;
                $desc = esc_html($desc);
            }

            if ($show_summary) {
                $summary = "<div class='rssSummary'>$desc</div>";
            } else {
                $summary = '';
            }

            $date = '';
            if ($show_date) {
                $date = $item->get_date();

                if ($date) {
                    if ($date_stamp = strtotime($date)) {
                        $date = ' <span class="rss-date">' . date_i18n(get_option('date_format'), $date_stamp) . '</span>';
                    } else {
                        $date = '';
                    }
                }
            }

            $time = '';
            if ($show_time) {
                $time = $item->get_date();

                if ($time) {
                    if ($date_stamp = strtotime($time)) {
                        $time = ' <span class="rss-time">' . date_i18n(get_option('time_format'), $date_stamp) . '</span>';
                    } else {
                        $time = '';
                    }
                }
            }

            $author = '';
            if ($show_author) {
                $author = $item->get_author();
                if (is_object($author)) {
                    $author = $author->get_name();
                    $author = ' <cite>' . esc_html(strip_tags($author)) . '</cite>';
                }
            }

            if ($hide_title && $item->get_description()) {
                $title = $item->get_description();
            }

            if (true == $args['limit_title_length'] && $args['title_length'] > 0) {
                $title = substr($title, 0, $args['title_length']);
            }
            
            if ($args['html_parent'] === 'dl') {
                $html_open = '<dt>';
                $html_after_title = '</dt>';
                $html_before_details = '<dd>';
                $html_close = '</dd>';
            } else {
                $html_open = '<' . $args['html_item'] . '>';
                $html_after_title = '';
                $html_before_details = '';
                $html_close = '</' . $args['html_item'] . '>';
            }

            if ($link == '' or $hide_link) {
                print $html_open . $title . $html_after_title;
                print $html_before_details . $date . $summary . $author . $html_close;
            } else {
                print $html_open . '<a ';
                
                if ($nofollow) {
                    print ' rel="nofollow" ';
                }
                
                print 'class="rsswidget" href="' . $link . '" title="' . $desc . '" ' .  $target . '>' . $title . '</a>' . $html_after_title;
                print $html_before_details . $date . $time . $summary . $author . $html_close;
            }
        }
        print '</' . $args['html_parent'] . '>';
    }
    
    /**
     * Method for the [better-rss] short code.
     *
     * @param array $atts Shortcode Attributes
     * 
     * @return string
     */
    function shortcodeHandler($atts)
    {
        global $post;

        $atts = (object) wp_parse_args($atts, $this->defaultsArgs());

        if (!$atts->feed) {
            return false;
        }

        if ($atts->use_title) {
            $add_url[] = str_replace(' ', '+', $post->post_title);
        }

        if ($atts->use_tags) {
            foreach (get_the_tags() as $tag) {
                $add_url[] = str_replace(' ', '+', $tag->name);
            }
        }

        if (isset($add_url) and is_array($add_url)) {
            $atts->feed = $atts->feed . implode('+', $add_url);
        }

        ob_start();
        $this->rssOutput($atts->feed, $atts);
        $output.= ob_get_contents();
        ob_end_clean();

        return $output;
    }

    /**
     * Load the default arguments.
     * 
     * @return NULL[]|boolean[]|number[]|string[]
     */    
    function defaultsArgs()
    {
        return array(
            // Query Attributes
            'feed' => null,
            'use_title' => false,
            'use_tags' => false,
            'use_category' => false,
            'items' => 10,
            'hide_title' => false,
            'hide_link' => false,
            'show_author' => $this->options->show_author,
            'show_date' => $this->options->show_author,
            'show_time' => $this->options->show_time,
            'show_summary' => $this->options->show_summary,
            'link_target' => $this->options->link_target,
            'nofollow' => $this->options->nofollow,
            'cache_duration' => $this->options->cache_duration,
            'excerpt' => $this->options->excerpt,
            'html_parent' => 'ul',
            'html_item' => 'li'
        );
    }
    /**
     * Add the admin page for the settings panel.
     *
     * @global string $wp_version
     * 
     * @return null
     */
    function adminMenu()
    {
        $page = add_options_page($this->pluginName . __(' Settings', 'better-rss-widget'), $this->pluginName, 'manage_options', $this->menuName, array(&$this, 'optionsPanel'));
    }

    /**
     * Register the options for Wordpress MU Support
     * 
     * @return null
     */
    function adminInit()
    {
        register_setting($this->optionsName, $this->optionsName);
    }
    
    /**
     * Add a configuration link to the plugins list.
     *
     * @param array $links An array of existing links
     * @param array $file  The file we are adding.
     * 
     * @staticvar object $this_plugin
     * 
     * @return array
     */
    function pluginActionLinks($links, $file)
    {
        static $this_plugin;

        if (!$this_plugin) {
            $this_plugin = plugin_basename(__FILE__);
        }

        if ($file == $this_plugin) {
            $settings_link = '<a href="' . admin_url('options-general.php?page=' . $this->menuName) . '">' . __('Settings', 'better-rss-widget') . '</a>';
            array_unshift($links, $settings_link);
        }

        return $links;
    }

    /**
     * Check on update option to see if we need to reset the options.
     *
     * @param array $input The options array
     * 
     * @return <boolean>
     */
    function updateOptions($input)
    {
        $tab = sanitize_text_field($_POST['active_tab']);
        
        if ($_REQUEST['confirm-reset-options']) {
            delete_option($this->optionsName);
            wp_redirect(admin_url('options-general.php?page=' . $this->menuName . '&tab=' . $tab . '&reset=true'));
            exit();
        } else {
            wp_redirect(admin_url('options-general.php?page=' . $this->menuName . '&tab=' . $tab . '&updated=true'));
            exit();
        }
    }

    /**
     * Settings management panel.
     * 
     * @return null
     */
    function optionsPanel()
    {
        include $this->pluginPath . '/includes/settings.php';
    }
    
    /**
     * Displayes any data sent in textareas.
     *
     * @param string $input The input to wrap in a debug window.
     * 
     * @return null
     */
    function debug($input)
    {
        $contents = func_get_args();

        foreach ($contents as $content) {
            print '<textarea style="width:49%; height:250px; float: left;">';
            print_r($content);
            print '</textarea>';
        }

        echo '<div style="clear: both"></div>';
    }
}

/**
 * Add the widget code to the initialization action
 * 
 * @return null
 */
function Better_Rss_register()
{
    register_widget('Better_Rss_Widget');
}
add_action('widgets_init', 'Better_Rss_register');

/**
 * Method called when plugin is activated.
 * 
 * @return null
 */
function Better_Rss_activate()
{
    /* Compile old options into new options Array */
    $new_options = '';
    $options = array('link_target', 'items', 'show_summary', 'show_author', 'show_date', 'show_time', 'enable_cache', 'cache_duration', 'is_home_default', 'is_front_default', 'is_archive_default', 'is_search_default', 'is_category_default', 'is_tag_default', 'is_single_default', 'is_date_default');

    foreach ($options as $option) {
        if ($old_option = get_option('better_rss_' . $option)) {
            $new_options[$option] = $old_option;
            delete_option('better_rss_' . $option);
        }
    }

    if (is_array($new_options) and ! add_option('better-rss-widget-options', $new_options)) {
        update_option('better-rss-widget-options', $new_options);
    }
}
register_activation_hook(__FILE__, 'Better_Rss_activate');
