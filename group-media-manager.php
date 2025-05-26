<?php
/**
 * Plugin Name: Group Media Manager
 * Plugin URI: https://yoursite.com
 * Description: Advanced media management with group-based access control and modern UI
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: group-media-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GMM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GMM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GMM_VERSION', '1.0.0');

class GroupMediaManager {
    
    public function __construct() {
        add_action('init', array($this, 'register_taxonomy'), 0);
        add_action('init', array($this, 'init'), 10);
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function register_taxonomy() {
        if (!taxonomy_exists('user_group')) {
            register_taxonomy('user_group', 'user', array(
                'labels' => array(
                    'name' => 'User Groups',
                    'singular_name' => 'User Group',
                    'menu_name' => 'User Groups',
                    'all_items' => 'All Groups',
                    'edit_item' => 'Edit Group',
                    'view_item' => 'View Group',
                    'update_item' => 'Update Group',
                    'add_new_item' => 'Add New Group',
                    'new_item_name' => 'New Group Name',
                ),
                'public' => false,
                'show_ui' => true,
                'show_admin_column' => false,
                'hierarchical' => true,
                'rewrite' => false,
                'show_in_menu' => false,
                'show_tagcloud' => false,
                'show_in_quick_edit' => false,
                'meta_box_cb' => false,
                'capabilities' => array(
                    'manage_terms' => 'manage_options',
                    'edit_terms' => 'manage_options',
                    'delete_terms' => 'manage_options',
                    'assign_terms' => 'edit_users',
                ),
            ));
        }
    }
    
    public function init() {
        // Include files
        $this->include_files();
        
        // Initialize classes
        if (is_admin()) {
            new GMM_Admin();
        }
        
        new GMM_Media_Filter();
        
        load_plugin_textdomain('group-media-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    private function include_files() {
        $includes = array(
            'includes/class-admin.php',
            'includes/class-media-filter.php',
            'includes/class-user-profile.php'
        );
        
        foreach ($includes as $file) {
            $filepath = GMM_PLUGIN_PATH . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            }
        }
    }
    
    public function admin_scripts($hook) {
        wp_enqueue_style('gmm-admin-style', GMM_PLUGIN_URL . 'assets/admin-style.css', array(), GMM_VERSION);
        wp_enqueue_script('gmm-admin-script', GMM_PLUGIN_URL . 'assets/admin-script.js', array('jquery'), GMM_VERSION, true);
        
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
        
        wp_localize_script('gmm-admin-script', 'gmm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gmm_nonce'),
        ));
    }
    
    public function frontend_scripts() {
        wp_enqueue_style('gmm-frontend-style', GMM_PLUGIN_URL . 'assets/frontend-style.css', array(), GMM_VERSION);
    }
    
    public function activate() {
        $this->register_taxonomy();
        $this->create_tables();
        
        add_option('gmm_allowed_roles', array('administrator'));
        add_option('gmm_default_privacy', 'private');
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'gmm_media_privacy';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) NOT NULL,
            privacy_status varchar(20) NOT NULL DEFAULT 'private',
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY attachment_id (attachment_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

new GroupMediaManager();