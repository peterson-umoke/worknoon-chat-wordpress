<?php
if (!defined('ABSPATH')) {
    exit;
}

class Worknoon_Chat_Shortcode {

    public function register() {
        add_shortcode('worknoon_chat', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'position' => 'bottom-right',
            'label' => 'Chat with us',
        ], $atts, 'worknoon_chat');

        if (!is_user_logged_in()) {
            return '<p>Please log in to start chatting.</p>';
        }

        $current_user = wp_get_current_user();
        $backend_url = get_option('worknoon_backend_url', 'http://localhost:5000');

        $config = [
            'backendUrl' => $backend_url,
            'position' => $atts['position'],
            'label' => $atts['label'],
            'user' => [
                'username' => $current_user->user_login,
                'email' => $current_user->user_email,
                'displayName' => $current_user->display_name,
            ],
            'nonce' => wp_create_nonce('worknoon_chat_nonce'),
        ];

        wp_localize_script('worknoon-chat-widget', 'worknoonConfig', $config);

        ob_start();
        ?>
        <div id="worknoon-chat-widget" data-position="<?php echo esc_attr($atts['position']); ?>">
            <button id="worknoon-chat-trigger" class="worknoon-chat-trigger" aria-label="Open chat">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </button>
            <div id="worknoon-chat-panel" class="worknoon-chat-panel" style="display:none;">
                <div class="worknoon-chat-header">
                    <h3><?php echo esc_html($atts['label']); ?></h3>
                    <button id="worknoon-chat-close" aria-label="Close chat">&times;</button>
                </div>
                <div id="worknoon-chat-messages" class="worknoon-chat-messages"></div>
                <div class="worknoon-chat-input-area">
                    <input type="text" id="worknoon-chat-input" placeholder="Type a message..." />
                    <button id="worknoon-chat-send">Send</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function enqueue_assets() {
        wp_enqueue_script(
            'worknoon-chat-widget',
            WORKNOON_CHAT_PLUGIN_URL . 'assets/chat-widget.js',
            [],
            WORKNOON_CHAT_VERSION,
            true
        );

        wp_enqueue_style(
            'worknoon-chat-style',
            WORKNOON_CHAT_PLUGIN_URL . 'assets/chat-widget.css',
            [],
            WORKNOON_CHAT_VERSION
        );
    }
}
