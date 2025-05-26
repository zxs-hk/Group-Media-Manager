<?php

class GMM_User_Profile {
    
    public function __construct() {
        add_action('show_user_profile', array($this, 'add_user_group_fields'));
        add_action('edit_user_profile', array($this, 'add_user_group_fields'));
        add_action('personal_options_update', array($this, 'save_user_group_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_group_fields'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_profile_scripts'));
    }
    
    public function enqueue_profile_scripts($hook) {
        if ('profile.php' === $hook || 'user-edit.php' === $hook) {
            wp_enqueue_script('select2');
            wp_enqueue_style('select2');
        }
    }
    
    public function add_user_group_fields($user) {
        if (!$this->can_manage_user_groups()) {
            return;
        }
        
        $user_groups = wp_get_object_terms($user->ID, 'user_group');
        $all_groups = get_terms(array(
            'taxonomy' => 'user_group',
            'hide_empty' => false,
        ));
        
        ?>
        <div class="gmm-user-groups-section">
            <h2>User Groups Management</h2>
            
            <div class="gmm-user-groups-container">
                <div class="gmm-groups-header">
                    <div class="gmm-groups-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="gmm-groups-info">
                        <h3>Group Membership</h3>
                        <p>Assign this user to groups to control media access permissions</p>
                    </div>
                </div>
                
                <table class="form-table gmm-user-groups-table">
                    <tr>
                        <th><label for="user_groups">Assign Groups</label></th>
                        <td>
                            <?php if (empty($all_groups)): ?>
                                <div class="gmm-no-groups">
                                    <div class="gmm-no-groups-icon">
                                        <span class="dashicons dashicons-warning"></span>
                                    </div>
                                    <div class="gmm-no-groups-content">
                                        <p><strong>No user groups available</strong></p>
                                        <p>Create user groups first to assign users to them.</p>
                                        <a href="<?php echo admin_url('admin.php?page=gmm-user-groups'); ?>" class="button button-primary">
                                            Create User Groups
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="gmm-groups-selector">
                                    <select name="user_groups[]" id="user_groups" class="gmm-groups-select" multiple="multiple">
                                        <?php foreach ($all_groups as $group): ?>
                                            <option value="<?php echo $group->term_id; ?>" 
                                                    <?php selected(in_array($group->term_id, wp_list_pluck($user_groups, 'term_id'))); ?>>
                                                <?php echo esc_html($group->name); ?>
                                                <?php if ($group->description): ?>
                                                    (<?php echo esc_html(wp_trim_words($group->description, 8, '...')); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="gmm-groups-help">
                                    <div class="gmm-help-item">
                                        <span class="dashicons dashicons-info"></span>
                                        Users in the same group can view each other's private media files
                                    </div>
                                    <div class="gmm-help-item">
                                        <span class="dashicons dashicons-search"></span>
                                        Use the search box above to quickly find groups
                                    </div>
                                    <div class="gmm-help-item">
                                        <span class="dashicons dashicons-admin-users"></span>
                                        A user can belong to multiple groups simultaneously
                                    </div>
                                </div>
                                
                                <?php if (!empty($user_groups)): ?>
                                    <div class="gmm-current-groups">
                                        <h4>Current Groups:</h4>
                                        <div class="gmm-group-badges">
                                            <?php foreach ($user_groups as $group): ?>
                                                <span class="gmm-group-badge">
                                                    <span class="dashicons dashicons-tag"></span>
                                                    <?php echo esc_html($group->name); ?>
                                                    <span class="gmm-group-count">
                                                        <?php 
                                                        $count = $this->get_group_user_count($group->term_id);
                                                        printf(_n('%d member', '%d members', $count, 'group-media-manager'), $count);
                                                        ?>
                                                    </span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <div class="gmm-groups-actions">
                    <a href="<?php echo admin_url('admin.php?page=gmm-user-groups'); ?>" class="button">
                        <span class="dashicons dashicons-admin-settings"></span>
                        Manage All Groups
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=group-media-manager'); ?>" class="button">
                        <span class="dashicons dashicons-dashboard"></span>
                        Media Groups Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <style>
        .gmm-user-groups-section {
            margin-top: 20px;
        }
        
        .gmm-user-groups-container {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .gmm-groups-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .gmm-groups-icon {
            background: rgba(255, 255, 255, 0.2);
            padding: 12px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .gmm-groups-icon .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
        }
        
        .gmm-groups-info h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: 600;
            color: white;
        }
        
        .gmm-groups-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .gmm-user-groups-table {
            margin: 0;
        }
        
        .gmm-user-groups-table th {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            color: #495057;
            width: 200px;
        }
        
        .gmm-user-groups-table td {
            padding: 20px;
        }
        
        .gmm-groups-selector {
            margin-bottom: 20px;
        }
        
        .gmm-groups-select {
            width: 100%;
            min-height: 120px;
        }
        
        .gmm-groups-help {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
        }
        
        .gmm-help-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 13px;
            color: #495057;
        }
        
        .gmm-help-item:last-child {
            margin-bottom: 0;
        }
        
        .gmm-help-item .dashicons {
            color: #667eea;
            font-size: 16px;
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }
        
        .gmm-current-groups h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 14px;
            font-weight: 600;
        }
        
        .gmm-group-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .gmm-group-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e3f2fd;
            color: #1565c0;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid #bbdefb;
        }
        
        .gmm-group-badge .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }
        
        .gmm-group-count {
            background: rgba(21, 101, 192, 0.1);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
        }
        
        .gmm-groups-actions {
            background: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
        }
        
        .gmm-groups-actions .button {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .gmm-groups-actions .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        
        .gmm-no-groups {
            text-align: center;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        
        .gmm-no-groups-icon {
            background: #fef3e2;
            color: #f59e0b;
            padding: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .gmm-no-groups-icon .dashicons {
            font-size: 32px;
            width: 32px;
            height: 32px;
        }
        
        .gmm-no-groups-content p {
            margin: 0 0 10px 0;
        }
        
        .gmm-no-groups-content p:last-child {
            margin-bottom: 0;
        }
        
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #8c8f94;
            border-radius: 4px;
            min-height: 120px;
        }
        
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #667eea;
            box-shadow: 0 0 0 1px #667eea;
        }
        
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #667eea;
            border-color: #5a67d8;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
        }
        
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: rgba(255, 255, 255, 0.8);
            margin-right: 6px;
        }
        
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            color: white;
        }
        
        @media (max-width: 768px) {
            .gmm-groups-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .gmm-groups-actions {
                flex-direction: column;
            }
            
            .gmm-group-badges {
                justify-content: center;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            if (typeof $.fn.select2 !== 'undefined') {
                $('#user_groups').select2({
                    placeholder: 'Search and select groups...',
                    allowClear: true,
                    width: '100%',
                    maximumSelectionLength: -1,
                    language: {
                        noResults: function() {
                            return 'No groups found';
                        },
                        searching: function() {
                            return 'Searching...';
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    public function save_user_group_fields($user_id) {
        if (!$this->can_manage_user_groups()) {
            return;
        }
        
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        $user_groups = isset($_POST['user_groups']) ? array_map('intval', $_POST['user_groups']) : array();
        
        wp_delete_object_term_relationships($user_id, 'user_group');
        
        if (!empty($user_groups)) {
            foreach ($user_groups as $group_id) {
                wp_set_object_terms($user_id, $group_id, 'user_group', true);
            }
        }
    }
    
    private function can_manage_user_groups() {
        $allowed_roles = get_option('gmm_allowed_roles', array('administrator'));
        $user = wp_get_current_user();
        
        foreach ($allowed_roles as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function get_group_user_count($group_id) {
        $users = get_objects_in_term($group_id, 'user_group');
        return is_array($users) ? count($users) : 0;
    }
}