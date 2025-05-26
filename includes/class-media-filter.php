<?php

class GMM_Media_Filter {
    
    public function __construct() {
        // Only filter for non-admin users
        if (!current_user_can('manage_options')) {
            add_action('pre_get_posts', array($this, 'filter_media_library'));
        }
        
        add_action('add_attachment', array($this, 'set_default_privacy'));
        add_filter('wp_get_attachment_url', array($this, 'filter_attachment_url'), 10, 2);
        add_filter('wp_get_attachment_image_src', array($this, 'filter_attachment_image_src'), 10, 4);
    }
    
    public function filter_media_library($query) {
        if (!$query->is_main_query()) {
            return;
        }
        
        if ($query->get('post_type') === 'attachment' || (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'attachment')) {
            $this->apply_group_filter($query);
        }
    }
    
    public function apply_group_filter($query) {
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            $this->filter_to_public_only($query);
            return;
        }
        
        $user_groups = wp_get_object_terms($current_user_id, 'user_group', array('fields' => 'ids'));
        
        if (empty($user_groups)) {
            $this->filter_to_public_and_own($query, $current_user_id);
            return;
        }
        
        $group_users = array();
        foreach ($user_groups as $group_id) {
            $users_in_group = get_objects_in_term($group_id, 'user_group');
            $group_users = array_merge($group_users, $users_in_group);
        }
        
        $group_users = array_unique($group_users);
        $group_users[] = $current_user_id;
        
        $this->filter_to_group_media($query, $group_users);
    }
    
    private function filter_to_public_only($query) {
        global $wpdb;
        
        $privacy_table = $wpdb->prefix . 'gmm_media_privacy';
        $public_ids = $wpdb->get_col("SELECT attachment_id FROM $privacy_table WHERE privacy_status = 'public'");
        
        if (!empty($public_ids)) {
            $query->set('post__in', $public_ids);
        } else {
            $query->set('post__in', array(0));
        }
    }
    
    private function filter_to_public_and_own($query, $user_id) {
        global $wpdb;
        
        $privacy_table = $wpdb->prefix . 'gmm_media_privacy';
        $public_ids = $wpdb->get_col("SELECT attachment_id FROM $privacy_table WHERE privacy_status = 'public'");
        $own_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_author = %d AND post_type = 'attachment'", $user_id));
        
        $allowed_ids = array_merge($public_ids, $own_ids);
        
        if (!empty($allowed_ids)) {
            $query->set('post__in', array_unique($allowed_ids));
        } else {
            $query->set('post__in', array(0));
        }
    }
    
    private function filter_to_group_media($query, $group_users) {
        global $wpdb;
        
        $privacy_table = $wpdb->prefix . 'gmm_media_privacy';
        $public_ids = $wpdb->get_col("SELECT attachment_id FROM $privacy_table WHERE privacy_status = 'public'");
        
        $user_ids_string = implode(',', array_map('intval', $group_users));
        $group_media_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_author IN ($user_ids_string) AND post_type = 'attachment'");
        
        $allowed_ids = array_merge($public_ids, $group_media_ids);
        
        if (!empty($allowed_ids)) {
            $query->set('post__in', array_unique($allowed_ids));
        } else {
            $query->set('post__in', array(0));
        }
    }
    
    public function set_default_privacy($attachment_id) {
        $default_privacy = get_option('gmm_default_privacy', 'private');
        
        global $wpdb;
        $privacy_table = $wpdb->prefix . 'gmm_media_privacy';
        
        $wpdb->insert($privacy_table, array(
            'attachment_id' => $attachment_id,
            'privacy_status' => $default_privacy,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ));
    }
    
    public function filter_attachment_url($url, $attachment_id) {
        if (!$this->can_access_attachment($attachment_id)) {
            return '';
        }
        return $url;
    }
    
    public function filter_attachment_image_src($image, $attachment_id, $size, $icon) {
        if (!$this->can_access_attachment($attachment_id)) {
            return false;
        }
        return $image;
    }
    
    private function can_access_attachment($attachment_id) {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        global $wpdb;
        $privacy_table = $wpdb->prefix . 'gmm_media_privacy';
        $privacy = $wpdb->get_var($wpdb->prepare(
            "SELECT privacy_status FROM $privacy_table WHERE attachment_id = %d",
            $attachment_id
        ));
        
        if ($privacy === 'public') {
            return true;
        }
        
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return false;
        }
        
        $post_author = get_post_field('post_author', $attachment_id);
        if ($post_author == $current_user_id) {
            return true;
        }
        
        $user_groups = wp_get_object_terms($current_user_id, 'user_group', array('fields' => 'ids'));
        $author_groups = wp_get_object_terms($post_author, 'user_group', array('fields' => 'ids'));
        
        $common_groups = array_intersect($user_groups, $author_groups);
        return !empty($common_groups);
    }
}