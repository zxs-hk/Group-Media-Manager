<?php

class GMM_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_gmm_toggle_privacy', array($this, 'ajax_toggle_privacy'));
        add_action('wp_ajax_gmm_bulk_action', array($this, 'ajax_bulk_action'));
        
        add_filter('manage_media_columns', array($this, 'add_media_columns'));
        add_action('manage_media_custom_column', array($this, 'manage_media_columns'), 10, 2);
        add_action('admin_footer', array($this, 'media_library_scripts'));
        
        add_action('restrict_manage_users', array($this, 'add_user_group_filter'));
        add_filter('pre_get_users', array($this, 'filter_users_by_group'));
        
        new GMM_User_Profile();
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Group Media Manager',
            'Media Groups',
            'manage_options',
            'group-media-manager',
            array($this, 'overview_page'),
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            'group-media-manager',
            'Overview',
            'Overview',
            'manage_options',
            'group-media-manager',
            array($this, 'overview_page')
        );
        
        add_submenu_page(
            'group-media-manager',
            'Private Media',
            'Private Media',
            'manage_options',
            'gmm-private-media',
            array($this, 'private_media_page')
        );
        
        add_submenu_page(
            'group-media-manager',
            'User Groups',
            'User Groups',
            'manage_options',
            'gmm-user-groups',
            array($this, 'user_groups_page')
        );
        
        add_submenu_page(
            'group-media-manager',
            'Settings',
            'Settings',
            'manage_options',
            'gmm-settings',
            array($this, 'settings_page')
        );
    }
    
    public function add_user_group_filter($which) {
        if ($which === 'top') {
            $groups = get_terms(array(
                'taxonomy' => 'user_group',
                'hide_empty' => false,
            ));
            
            if (!empty($groups)) {
                $selected = isset($_GET['user_group']) ? sanitize_text_field($_GET['user_group']) : '';
                echo '<select name="user_group" id="user_group_filter">';
                echo '<option value="">All Groups</option>';
                foreach ($groups as $group) {
                    echo '<option value="' . esc_attr($group->term_id) . '" ' . selected($selected, $group->term_id, false) . '>';
                    echo esc_html($group->name);
                    echo '</option>';
                }
                echo '</select>';
            }
        }
    }
    
    public function filter_users_by_group($query) {
        global $pagenow;
        
        if ($pagenow === 'users.php' && isset($_GET['user_group']) && !empty($_GET['user_group'])) {
            $group_id = intval($_GET['user_group']);
            $users_in_group = get_objects_in_term($group_id, 'user_group');
            
            if (!empty($users_in_group)) {
                $query->set('include', $users_in_group);
            } else {
                $query->set('include', array(0));
            }
        }
    }
    
    public function overview_page() {
        $this->add_page_styles();
        
        global $wpdb;
        
        $total_media = wp_count_attachments();
        $total_users = count_users();
        $total_groups = wp_count_terms('user_group');
        
        $privacy_table = $wpdb->prefix . 'gmm_media_privacy';
        $public_media = $wpdb->get_var("SELECT COUNT(*) FROM $privacy_table WHERE privacy_status = 'public'");
        $private_media = $wpdb->get_var("SELECT COUNT(*) FROM $privacy_table WHERE privacy_status = 'private'");
        
        ?>
        <div class="wrap gmm-admin-wrap">
            <div class="gmm-header">
                <div>
                    <h1>Group Media Manager</h1>
                    <p class="gmm-subtitle">Manage media access by user groups with modern interface</p>
                </div>
            </div>
            
            <div class="gmm-dashboard">
                <div class="gmm-stats-grid">
                    <div class="gmm-stat-card">
                        <div class="gmm-stat-icon">
                            <span class="dashicons dashicons-format-gallery"></span>
                        </div>
                        <div class="gmm-stat-content">
                            <h3><?php echo array_sum((array)$total_media); ?></h3>
                            <p>Total Media Files</p>
                        </div>
                    </div>
                    
                    <div class="gmm-stat-card">
                        <div class="gmm-stat-icon gmm-public">
                            <span class="dashicons dashicons-visibility"></span>
                        </div>
                        <div class="gmm-stat-content">
                            <h3><?php echo $public_media ?: 0; ?></h3>
                            <p>Public Media</p>
                        </div>
                    </div>
                    
                    <div class="gmm-stat-card">
                        <div class="gmm-stat-icon gmm-private">
                            <span class="dashicons dashicons-lock"></span>
                        </div>
                        <div class="gmm-stat-content">
                            <h3><?php echo $private_media ?: 0; ?></h3>
                            <p>Private Media</p>
                        </div>
                    </div>
                    
                    <div class="gmm-stat-card">
                        <div class="gmm-stat-icon gmm-users">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="gmm-stat-content">
                            <h3><?php echo $total_users['total_users']; ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    
                    <div class="gmm-stat-card">
                        <div class="gmm-stat-icon gmm-groups">
                            <span class="dashicons dashicons-tag"></span>
                        </div>
                        <div class="gmm-stat-content">
                            <h3><?php echo $total_groups; ?></h3>
                            <p>User Groups</p>
                        </div>
                    </div>
                </div>
                
                <div class="gmm-quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="gmm-action-buttons">
                        <a href="<?php echo admin_url('admin.php?page=gmm-private-media'); ?>" class="gmm-btn gmm-btn-primary">
                            <span class="dashicons dashicons-lock"></span>
                            Manage Private Media
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=gmm-user-groups'); ?>" class="gmm-btn gmm-btn-secondary">
                            <span class="dashicons dashicons-groups"></span>
                            Manage User Groups
                        </a>
                        <a href="<?php echo admin_url('upload.php'); ?>" class="gmm-btn gmm-btn-secondary">
                            <span class="dashicons dashicons-format-gallery"></span>
                            Media Library
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=gmm-settings'); ?>" class="gmm-btn gmm-btn-outline">
                            <span class="dashicons dashicons-admin-settings"></span>
                            Settings
                        </a>
                    </div>
                </div>
                
                <div class="gmm-recent-activity">
                    <h2>Recent Activity</h2>
                    <?php $this->display_recent_activity(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function user_groups_page() {
        $this->add_page_styles();
        
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'edit':
                $this->edit_user_group_page();
                break;
            default:
                $this->list_user_groups_page();
                break;
        }
    }
    
    private function add_page_styles() {
        ?>
        <style type="text/css">
        /* Group Media Manager Styles */
        .gmm-admin-wrap {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            margin: 20px 0 !important;
        }
        
        .gmm-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            padding: 2rem !important;
            border-radius: 12px !important;
            margin-bottom: 2rem !important;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.15) !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 1rem !important;
        }
        
        .gmm-header h1 {
            margin: 0 0 0.5rem 0 !important;
            font-size: 2rem !important;
            font-weight: 700 !important;
            color: white !important;
        }
        
        .gmm-subtitle {
            margin: 0 !important;
            font-size: 1.1rem !important;
            opacity: 0.9 !important;
            font-weight: 400 !important;
            color: white !important;
        }
        
        .gmm-user-groups-management {
            display: grid !important;
            gap: 2rem !important;
            max-width: 100% !important;
            margin: 0 auto !important;
        }
        
        .gmm-card {
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08) !important;
            border: 1px solid #f0f0f1 !important;
            overflow: hidden !important;
            margin-bottom: 2rem !important;
        }
        
        .gmm-card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
            padding: 1.5rem 2rem !important;
            border-bottom: 1px solid #e9ecef !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 1rem !important;
        }
        
        .gmm-add-group-section .gmm-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            border-bottom: none !important;
        }
        
        .gmm-card-header h2 {
            margin: 0 !important;
            color: #1f2937 !important;
            font-size: 1.25rem !important;
            font-weight: 600 !important;
        }
        
        .gmm-add-group-section .gmm-card-header h2 {
            color: white !important;
        }
        
        .gmm-count {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            padding: 0.25rem 0.75rem !important;
            border-radius: 20px !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
            box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2) !important;
        }
        
        .gmm-card-body {
            padding: 2rem !important;
        }
        
        .gmm-form-row {
            display: grid !important;
            grid-template-columns: 1fr 1fr auto !important;
            gap: 1rem !important;
            align-items: end !important;
            width: 100% !important;
        }
        
        .gmm-form-group {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.5rem !important;
            min-width: 0 !important;
        }
        
        .gmm-form-group label {
            font-weight: 600 !important;
            color: #374151 !important;
            font-size: 14px !important;
            margin-bottom: 0.25rem !important;
        }
        
        .gmm-input {
            padding: 0.75rem 1rem !important;
            border: 2px solid #e5e7eb !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            transition: all 0.2s ease !important;
            width: 100% !important;
            box-sizing: border-box !important;
            background: white !important;
        }
        
        .gmm-input:focus {
            outline: none !important;
            border-color: #667eea !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
        }
        
        .gmm-btn {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            padding: 0.75rem 1.5rem !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 500 !important;
            text-decoration: none !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            font-size: 14px !important;
            text-align: center !important;
            justify-content: center !important;
            white-space: nowrap !important;
            box-sizing: border-box !important;
        }
        
        .gmm-btn-primary {
            background: #667eea !important;
            color: white !important;
        }
        
        .gmm-btn-primary:hover {
            background: #5a67d8 !important;
            color: white !important;
            text-decoration: none !important;
        }
        
        .gmm-btn-secondary {
            background: #6b7280 !important;
            color: white !important;
        }
        
        .gmm-btn-secondary:hover {
            background: #4b5563 !important;
            color: white !important;
            text-decoration: none !important;
        }
        
        .gmm-btn-outline {
            background: transparent !important;
            color: #667eea !important;
            border: 2px solid #667eea !important;
        }
        
        .gmm-btn-outline:hover {
            background: #667eea !important;
            color: white !important;
            text-decoration: none !important;
        }
        
        .gmm-btn-danger {
            background: #ef4444 !important;
            color: white !important;
        }
        
        .gmm-btn-danger:hover {
            background: #dc2626 !important;
            color: white !important;
            text-decoration: none !important;
        }
        
        .gmm-btn-sm {
            padding: 0.5rem 1rem !important;
            font-size: 12px !important;
        }
        
        .gmm-groups-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)) !important;
            gap: 1.5rem !important;
            margin-top: 1rem !important;
        }
        
        .gmm-group-card {
            background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%) !important;
            border: 2px solid #f3f4f6 !important;
            border-radius: 12px !important;
            padding: 1.5rem !important;
            transition: all 0.3s ease !important;
            position: relative !important;
            overflow: hidden !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }
        
        .gmm-group-card::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: 4px !important;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%) !important;
            opacity: 0 !important;
            transition: opacity 0.3s ease !important;
        }
        
        .gmm-group-card:hover {
            border-color: #667eea !important;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15) !important;
            transform: translateY(-4px) !important;
        }
        
        .gmm-group-card:hover::before {
            opacity: 1 !important;
        }
        
        .gmm-group-header {
            display: flex !important;
            align-items: flex-start !important;
            gap: 1rem !important;
            margin-bottom: 1.5rem !important;
        }
        
        .gmm-group-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            padding: 0.875rem !important;
            border-radius: 12px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex-shrink: 0 !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3) !important;
        }
        
        .gmm-group-icon .dashicons {
            font-size: 20px !important;
            width: 20px !important;
            height: 20px !important;
        }
        
        .gmm-group-info {
            flex: 1 !important;
            min-width: 0 !important;
        }
        
        .gmm-group-info h4 {
            margin: 0 0 0.5rem 0 !important;
            color: #1f2937 !important;
            font-size: 1.25rem !important;
            font-weight: 700 !important;
            line-height: 1.3 !important;
            word-wrap: break-word !important;
        }
        
        .gmm-group-info p {
            margin: 0 !important;
            color: #6b7280 !important;
            font-size: 0.875rem !important;
            line-height: 1.5 !important;
            word-wrap: break-word !important;
        }
        
        .gmm-group-stats {
            display: flex !important;
            gap: 1rem !important;
            margin-bottom: 1.5rem !important;
            padding: 1rem !important;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
            border-radius: 10px !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05) !important;
        }
        
        .gmm-stat {
            text-align: center !important;
            flex: 1 !important;
            padding: 0.5rem !important;
        }
        
        .gmm-stat-number {
            display: block !important;
            font-size: 1.75rem !important;
            font-weight: 800 !important;
            color: #667eea !important;
            line-height: 1 !important;
            margin-bottom: 0.25rem !important;
        }
        
        .gmm-stat-label {
            font-size: 0.75rem !important;
            color: #6b7280 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            font-weight: 600 !important;
        }
        
        .gmm-group-actions {
            display: flex !important;
            gap: 0.5rem !important;
            flex-wrap: wrap !important;
            margin-top: 1rem !important;
        }
        
        .gmm-group-actions .gmm-btn {
            flex: 1 !important;
            min-width: fit-content !important;
            font-size: 11px !important;
            padding: 0.5rem 0.75rem !important;
        }
        
        .gmm-empty-state {
            text-align: center !important;
            padding: 4rem 2rem !important;
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%) !important;
            border-radius: 12px !important;
            border: 2px dashed #d1d5db !important;
        }
        
        .gmm-empty-icon {
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%) !important;
            color: #6b7280 !important;
            width: 100px !important;
            height: 100px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 auto 1.5rem !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
        }
        
        .gmm-empty-icon .dashicons {
            font-size: 40px !important;
            width: 40px !important;
            height: 40px !important;
        }
        
        .gmm-empty-state h3 {
            margin: 0 0 1rem 0 !important;
            color: #374151 !important;
            font-size: 1.5rem !important;
            font-weight: 700 !important;
        }
        
        .gmm-empty-state p {
            margin: 0 !important;
            color: #6b7280 !important;
            font-size: 1rem !important;
            line-height: 1.6 !important;
            max-width: 400px !important;
            margin: 0 auto !important;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .gmm-form-row {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }
            
            .gmm-groups-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }
            
            .gmm-group-actions {
                flex-direction: column !important;
                gap: 0.5rem !important;
            }
            
            .gmm-group-stats {
                flex-direction: column !important;
                gap: 0.5rem !important;
                text-align: center !important;
            }
            
            .gmm-stat {
                padding: 0.75rem !important;
                background: white !important;
                border-radius: 6px !important;
                border: 1px solid #e5e7eb !important;
            }
        }
        </style>
        <?php
    }
    
    private function list_user_groups_page() {
        if (isset($_POST['action']) && $_POST['action'] === 'add_group') {
            $this->handle_add_group();
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['group_id'])) {
            $this->delete_user_group();
        }
        
        $groups = get_terms(array(
            'taxonomy' => 'user_group',
            'hide_empty' => false,
        ));
        
        ?>
        <div class="wrap gmm-admin-wrap">
            <div class="gmm-header">
                <div>
                    <h1>User Groups Management</h1>
                    <p class="gmm-subtitle">Create and manage user groups for media access control</p>
                </div>
            </div>
            
            <div class="gmm-user-groups-management">
                <div class="gmm-add-group-section">
                    <div class="gmm-card">
                        <div class="gmm-card-header">
                            <h2>Add New Group</h2>
                        </div>
                        <div class="gmm-card-body">
                            <form method="post" action="" class="gmm-add-group-form">
                                <?php wp_nonce_field('gmm_add_group'); ?>
                                <input type="hidden" name="action" value="add_group">
                                
                                <div class="gmm-form-row">
                                    <div class="gmm-form-group">
                                        <label for="group_name">Group Name</label>
                                        <input type="text" name="group_name" id="group_name" class="gmm-input" required>
                                    </div>
                                    <div class="gmm-form-group">
                                        <label for="group_description">Description</label>
                                        <input type="text" name="group_description" id="group_description" class="gmm-input" placeholder="Optional description">
                                    </div>
                                    <div class="gmm-form-group">
                                        <button type="submit" class="gmm-btn gmm-btn-primary">
                                            <span class="dashicons dashicons-plus"></span>
                                            Add Group
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="gmm-groups-list-section">
                    <div class="gmm-card">
                        <div class="gmm-card-header">
                            <h2>Existing Groups</h2>
                            <span class="gmm-count"><?php echo count($groups); ?> groups</span>
                        </div>
                        <div class="gmm-card-body">
                            <?php if (empty($groups)): ?>
                                <div class="gmm-empty-state">
                                    <div class="gmm-empty-icon">
                                        <span class="dashicons dashicons-groups"></span>
                                    </div>
                                    <h3>No groups created yet</h3>
                                    <p>Create your first group using the form above to start organizing users.</p>
                                </div>
                            <?php else: ?>
                                <div class="gmm-groups-grid">
                                    <?php foreach ($groups as $group): ?>
                                        <div class="gmm-group-card">
                                            <div class="gmm-group-header">
                                                <div class="gmm-group-icon">
                                                    <span class="dashicons dashicons-tag"></span>
                                                </div>
                                                <div class="gmm-group-info">
                                                    <h4><?php echo esc_html($group->name); ?></h4>
                                                    <?php if ($group->description): ?>
                                                        <p><?php echo esc_html($group->description); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="gmm-group-stats">
                                                <div class="gmm-stat">
                                                    <span class="gmm-stat-number"><?php echo $this->get_group_user_count($group->term_id); ?></span>
                                                    <span class="gmm-stat-label">Members</span>
                                                </div>
                                                <div class="gmm-stat">
                                                    <span class="gmm-stat-number"><?php echo $this->get_group_media_count($group->term_id); ?></span>
                                                    <span class="gmm-stat-label">Media</span>
                                                </div>
                                            </div>
                                            <div class="gmm-group-actions">
                                                <a href="<?php echo admin_url('users.php?user_group=' . $group->term_id); ?>" class="gmm-btn gmm-btn-sm gmm-btn-secondary" title="View users in this group">
                                                    <span class="dashicons dashicons-admin-users"></span>
                                                    View Users
                                                </a>
                                                <a href="<?php echo add_query_arg(array('action' => 'edit', 'group_id' => $group->term_id)); ?>" class="gmm-btn gmm-btn-sm gmm-btn-outline">
                                                    <span class="dashicons dashicons-edit"></span>
                                                    Edit
                                                </a>
                                                <a href="<?php echo add_query_arg(array('action' => 'delete', 'group_id' => $group->term_id)); ?>" 
                                                   class="gmm-btn gmm-btn-sm gmm-btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this group?')">
                                                    <span class="dashicons dashicons-trash"></span>
                                                    Delete
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function edit_user_group_page() {
        $group_id = intval($_GET['group_id']);
        $group = get_term($group_id, 'user_group');
        
        if (!$group || is_wp_error($group)) {
            wp_die('Invalid group.');
        }
        
        if (isset($_POST['action']) && $_POST['action'] === 'update_group') {
            $this->handle_update_group($group_id);
            $group = get_term($group_id, 'user_group');
        }
        
        ?>
        <div class="wrap gmm-admin-wrap">
            <div class="gmm-header">
                <div>
                    <h1>Edit Group: <?php echo esc_html($group->name); ?></h1>
                </div>
                <a href="<?php echo admin_url('admin.php?page=gmm-user-groups'); ?>" class="gmm-btn gmm-btn-outline">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    Back to Groups
                </a>
            </div>
            
            <div class="gmm-edit-group">
                <div class="gmm-card">
                    <div class="gmm-card-header">
                        <h2>Edit Group Details</h2>
                    </div>
                    <div class="gmm-card-body">
                        <form method="post" action="">
                            <?php wp_nonce_field('gmm_update_group'); ?>
                            <input type="hidden" name="action" value="update_group">
                            
                            <div class="gmm-form-group">
                                <label for="group_name">Group Name</label>
                                <input type="text" name="group_name" id="group_name" class="gmm-input" value="<?php echo esc_attr($group->name); ?>" required>
                            </div>
                            
                            <div class="gmm-form-group">
                                <label for="group_description">Description</label>
                                <textarea name="group_description" id="group_description" class="gmm-input" rows="3"><?php echo esc_textarea($group->description); ?></textarea>
                            </div>
                            
                            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid #e5e7eb;">
                                <button type="submit" class="gmm-btn gmm-btn-primary">
                                    <span class="dashicons dashicons-update"></span>
                                    Update Group
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function handle_add_group() {
        check_admin_referer('gmm_add_group');
        
        $name = sanitize_text_field($_POST['group_name']);
        $description = sanitize_text_field($_POST['group_description']);
        
        $result = wp_insert_term($name, 'user_group', array(
            'description' => $description,
        ));
        
        if (!is_wp_error($result)) {
            echo '<div class="notice notice-success"><p>Group created successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
        }
    }
    
    private function handle_update_group($group_id) {
        check_admin_referer('gmm_update_group');
        
        $name = sanitize_text_field($_POST['group_name']);
        $description = sanitize_text_field($_POST['group_description']);
        
        $result = wp_update_term($group_id, 'user_group', array(
            'name' => $name,
            'description' => $description,
        ));
        
        if (!is_wp_error($result)) {
            echo '<div class="notice notice-success"><p>Group updated successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
        }
    }
    
    private function delete_user_group() {
        $group_id = intval($_GET['group_id']);
        
        if ($group_id) {
            $result = wp_delete_term($group_id, 'user_group');
            
            if (!is_wp_error($result)) {
                echo '<div class="notice notice-success"><p>Group deleted successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
            }
        }
    }
    
    private function get_group_user_count($group_id) {
        $users = get_objects_in_term($group_id, 'user_group');
        return is_array($users) ? count($users) : 0;
    }
    
    private function get_group_media_count($group_id) {
        $users = get_objects_in_term($group_id, 'user_group');
        if (empty($users) || is_wp_error($users)) {
            return 0;
        }
        
        global $wpdb;
        $user_ids = array_map('intval', $users);
        $user_ids_string = implode(',', $user_ids);
        
        $count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_author IN ($user_ids_string)
        ");
        
        return $count ? intval($count) : 0;
    }
    
    private function display_recent_activity() {
        echo '<p class="gmm-no-activity" style="text-align: center; color: #6b7280; font-style: italic; padding: 3rem;">No recent activity.</p>';
    }
    
    // Placeholder methods for other functionality
    public function private_media_page() {
        echo '<div class="wrap"><h1>Private Media</h1><p>Coming soon...</p></div>';
    }
    
    public function settings_page() {
        echo '<div class="wrap"><h1>Settings</h1><p>Coming soon...</p></div>';
    }
    
    public function add_media_columns($columns) {
        return $columns;
    }
    
    public function manage_media_columns($column_name, $attachment_id) {
        // Empty for now
    }
    
    public function media_library_scripts() {
        // Empty for now
    }
    
    public function ajax_toggle_privacy() {
        wp_die();
    }
    
    public function ajax_bulk_action() {
        wp_die();
    }
}