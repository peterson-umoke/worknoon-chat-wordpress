<?php
if (!defined('ABSPATH')) {
    exit;
}

class Worknoon_Chat_CPT {

    public function register() {
        add_action('init', [$this, 'register_cpt']);
    }

    public function register_cpt() {
        register_post_type('chat_session', [
            'labels' => [
                'name' => 'Chat Sessions',
                'singular_name' => 'Chat Session',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Chat Session',
                'edit_item' => 'Edit Chat Session',
                'view_item' => 'View Chat Session',
                'search_items' => 'Search Chat Sessions',
                'not_found' => 'No chat sessions found',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-format-chat',
            'supports' => ['title', 'custom-fields'],
            'rewrite' => false,
            'capability_type' => 'post',
        ]);

        register_meta('post', 'chat_session_key', [
            'object_subtype' => 'chat_session',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', 'chat_session_status', [
            'object_subtype' => 'chat_session',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'default' => 'active',
        ]);

        register_meta('post', 'chat_session_user_id', [
            'object_subtype' => 'chat_session',
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
        ]);

        register_meta('post', 'chat_session_product_id', [
            'object_subtype' => 'chat_session',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
    }
}
