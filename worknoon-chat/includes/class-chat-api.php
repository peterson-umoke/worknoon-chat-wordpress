<?php
if (!defined('ABSPATH')) {
    exit;
}

class Worknoon_Chat_API {

    public function register() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('worknoon-chat/v1', '/sync-user', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_user'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route('worknoon-chat/v1', '/chat-token', [
            'methods' => 'GET',
            'callback' => [$this, 'get_chat_token'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route('worknoon-chat/v1', '/session', [
            'methods' => 'POST',
            'callback' => [$this, 'create_session'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);
    }

    public function sync_user($request) {
        $response = $this->sync_current_user_with_backend();

        if (is_wp_error($response)) {
            return $response;
        }

        return rest_ensure_response([
            'success' => true,
            'user' => $this->format_backend_user($response),
        ]);
    }

    public function get_chat_token($request) {
        $response = $this->sync_current_user_with_backend();

        if (is_wp_error($response)) {
            return $response;
        }

        return rest_ensure_response([
            'token' => $response['token'],
            'user' => $this->format_backend_user($response),
        ]);
    }

    public function create_session($request) {
        $sync_response = $this->sync_current_user_with_backend();

        if (is_wp_error($sync_response)) {
            return $sync_response;
        }

        $type = sanitize_text_field($request->get_param('type') ?: 'customer-to-agent');
        $allowed_types = ['customer-to-agent', 'customer-to-designer', 'customer-to-merchant', 'general'];
        if (!in_array($type, $allowed_types, true)) {
            return new WP_Error('invalid_chat_type', 'Invalid chat type', ['status' => 400]);
        }

        $context = $this->sanitize_context((array) $request->get_param('context'));
        $backend_url = get_option('worknoon_backend_url', 'http://localhost:3001');

        $conversation_response = wp_remote_post(trailingslashit($backend_url) . 'api/conversations', [
            'body' => wp_json_encode([
                'type' => $type,
                'context' => $context,
            ]),
            'headers' => [
                'Authorization' => 'Bearer ' . $sync_response['token'],
                'Content-Type' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($conversation_response)) {
            return new WP_Error('backend_unreachable', 'Could not reach backend server', ['status' => 502]);
        }

        $status = wp_remote_retrieve_response_code($conversation_response);
        $conversation = json_decode(wp_remote_retrieve_body($conversation_response), true);

        if ($status < 200 || $status >= 300 || empty($conversation['_id'])) {
            return new WP_Error(
                'conversation_failed',
                $conversation['message'] ?? 'Could not create chat conversation',
                ['status' => $status ?: 500]
            );
        }

        $chat_session_id = $this->upsert_chat_session($conversation['_id'], $type, $context);

        return rest_ensure_response([
            'conversation' => $conversation,
            'conversationId' => $conversation['_id'],
            'chatSessionId' => $chat_session_id,
            'token' => $sync_response['token'],
            'user' => $this->format_backend_user($sync_response),
        ]);
    }

    private function sync_current_user_with_backend() {
        $current_user = wp_get_current_user();
        $backend_url = get_option('worknoon_backend_url', 'http://localhost:3001');
        $sync_secret = get_option('worknoon_sync_secret', 'worknoon-wordpress-dev-secret');

        $user_data = [
            'username' => $current_user->user_login,
            'email' => $current_user->user_email,
            'role' => $this->map_wp_role($current_user),
            'avatar' => get_avatar_url($current_user->ID),
            'wordpressUserId' => (string) $current_user->ID,
        ];

        $response = wp_remote_post(trailingslashit($backend_url) . 'api/auth/wordpress-sync', [
            'body' => wp_json_encode($user_data),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Worknoon-WP-Secret' => $sync_secret,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('backend_unreachable', 'Could not reach backend server', ['status' => 502]);
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status === 201 || $status === 200) {
            return $body;
        }

        return new WP_Error('sync_failed', $body['message'] ?? 'Sync failed', ['status' => $status]);
    }

    private function map_wp_role($user) {
        if (in_array('administrator', (array) $user->roles, true)) {
            return 'admin';
        }
        if (in_array('shop_manager', (array) $user->roles, true)) {
            return 'merchant';
        }
        return 'customer';
    }

    private function format_backend_user($body) {
        return [
            'id' => $body['_id'] ?? '',
            'username' => $body['username'] ?? '',
            'email' => $body['email'] ?? '',
            'role' => $body['role'] ?? 'customer',
            'avatar' => $body['avatar'] ?? '',
        ];
    }

    private function sanitize_context($context) {
        return [
            'productId' => sanitize_text_field($context['productId'] ?? ''),
            'productName' => sanitize_text_field($context['productName'] ?? ''),
            'productImage' => esc_url_raw($context['productImage'] ?? ''),
            'productPrice' => wp_kses_post($context['productPrice'] ?? ''),
            'orderId' => sanitize_text_field($context['orderId'] ?? ''),
        ];
    }

    private function upsert_chat_session($conversation_id, $type, $context) {
        $current_user = wp_get_current_user();

        $existing = get_posts([
            'post_type' => 'chat_session',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key' => 'backend_conversation_id',
            'meta_value' => $conversation_id,
            'fields' => 'ids',
        ]);

        $title_parts = ['Chat'];
        if (!empty($context['productName'])) {
            $title_parts[] = $context['productName'];
        }
        $title_parts[] = $current_user->display_name ?: $current_user->user_login;

        $post_data = [
            'post_type' => 'chat_session',
            'post_status' => 'publish',
            'post_title' => implode(' - ', $title_parts),
        ];

        if (!empty($existing)) {
            $post_data['ID'] = (int) $existing[0];
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($post_id)) {
            return 0;
        }

        update_post_meta($post_id, 'backend_conversation_id', $conversation_id);
        update_post_meta($post_id, 'chat_session_status', 'active');
        update_post_meta($post_id, 'chat_session_user_id', get_current_user_id());
        update_post_meta($post_id, 'chat_session_product_id', $context['productId'] ?? '');
        update_post_meta($post_id, 'chat_session_order_id', $context['orderId'] ?? '');
        update_post_meta($post_id, 'chat_session_type', $type);
        update_post_meta($post_id, 'chat_session_key', $conversation_id);

        return (int) $post_id;
    }
}
