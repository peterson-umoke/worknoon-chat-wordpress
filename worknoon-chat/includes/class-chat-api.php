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
    }

    public function sync_user($request) {
        $current_user = wp_get_current_user();
        $backend_url = get_option('worknoon_backend_url', 'http://localhost:5000');

        $user_data = [
            'username' => $current_user->user_login,
            'email' => $current_user->user_email,
            'role' => $this->map_wp_role($current_user),
        ];

        $response = wp_remote_post(trailingslashit($backend_url) . 'api/auth/register', [
            'body' => wp_json_encode($user_data),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('backend_unreachable', 'Could not reach backend server', ['status' => 502]);
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status === 201 || $status === 200) {
            return rest_ensure_response([
                'success' => true,
                'user' => $body,
            ]);
        }

        return new WP_Error('sync_failed', $body['message'] ?? 'Sync failed', ['status' => $status]);
    }

    public function get_chat_token($request) {
        $current_user = wp_get_current_user();
        $backend_url = get_option('worknoon_backend_url', 'http://localhost:5000');

        $response = wp_remote_post(trailingslashit($backend_url) . 'api/auth/login', [
            'body' => wp_json_encode([
                'emailOrUsername' => $current_user->user_email,
                'password' => 'Password123!',
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('backend_unreachable', 'Could not reach backend server', ['status' => 502]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status = wp_remote_retrieve_response_code($response);

        if ($status === 200 && isset($body['token'])) {
            return rest_ensure_response([
                'token' => $body['token'],
                'user' => [
                    'id' => $body['_id'],
                    'username' => $body['username'],
                    'email' => $body['email'],
                    'role' => $body['role'],
                    'avatar' => $body['avatar'],
                ],
            ]);
        }

        return new WP_Error('auth_failed', 'Could not authenticate with backend', ['status' => $status]);
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
}
