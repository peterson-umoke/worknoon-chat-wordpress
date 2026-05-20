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
            'type' => 'customer-to-agent',
        ], $atts, 'worknoon_chat');

        if (!is_user_logged_in()) {
            return '<p>Please log in to start chatting.</p>';
        }

        $current_user = wp_get_current_user();
        $backend_url = get_option('worknoon_backend_url', 'http://localhost:3001');
        $allowed_types = ['customer-to-agent', 'customer-to-designer', 'customer-to-merchant', 'general'];
        $conversation_type = in_array($atts['type'], $allowed_types, true) ? $atts['type'] : 'customer-to-agent';
        $context = $this->get_ecommerce_context();

        $config = [
            'backendUrl' => $backend_url,
            'restUrl' => esc_url_raw(rest_url('worknoon-chat/v1')),
            'position' => $atts['position'],
            'label' => $atts['label'],
            'type' => $conversation_type,
            'user' => [
                'id' => $current_user->ID,
                'username' => $current_user->user_login,
                'email' => $current_user->user_email,
                'displayName' => $current_user->display_name,
            ],
            'nonce' => wp_create_nonce('wp_rest'),
            'context' => $context,
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
                <div id="worknoon-chat-context" class="worknoon-chat-context" style="display:none;"></div>
                <div id="worknoon-chat-status" class="worknoon-chat-status" aria-live="polite"></div>
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

    private function get_ecommerce_context() {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        if (is_product()) {
            $product = wc_get_product(get_the_ID());
            return $product ? $this->format_product_context($product) : null;
        }

        $product_id = isset($_GET['product_id']) ? absint(wp_unslash($_GET['product_id'])) : 0;
        if ($product_id) {
            $product = wc_get_product($product_id);
            return $product ? $this->format_product_context($product) : null;
        }

        $order_id = isset($_GET['order_id']) ? absint(wp_unslash($_GET['order_id'])) : 0;
        if ($order_id && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if (!$order || (int) $order->get_user_id() !== get_current_user_id()) {
                return null;
            }

            $items = $order->get_items();
            $first_item = reset($items);

            return [
                'orderId' => (string) $order->get_id(),
                'productId' => $first_item && $first_item->get_product_id() ? (string) $first_item->get_product_id() : '',
                'productName' => $first_item ? $first_item->get_name() : '',
                'productImage' => '',
                'productPrice' => wp_strip_all_tags(wc_price($order->get_total())),
                'sku' => '',
            ];
        }

        return null;
    }

    private function format_product_context($product) {
        $image_id = $product->get_image_id();
        $image_url = '';
        if ($image_id) {
            $src = wp_get_attachment_image_src($image_id, 'thumbnail');
            $image_url = $src ? $src[0] : '';
        }

        return [
            'productId' => (string) $product->get_id(),
            'productName' => $product->get_name(),
            'productImage' => $image_url,
            'productPrice' => wp_strip_all_tags($product->get_price_html()),
            'sku' => $product->get_sku(),
            'orderId' => '',
        ];
    }
}
