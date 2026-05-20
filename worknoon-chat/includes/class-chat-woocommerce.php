<?php
if (!defined('ABSPATH')) {
    exit;
}

class Worknoon_Chat_WooCommerce {

    public function register() {
        add_action('wp', [$this, 'capture_product_context']);
    }

    public function capture_product_context() {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $product = null;

        if (is_product()) {
            $product = wc_get_product(get_the_ID());
        } elseif (isset($_GET['product_id'])) {
            $product = wc_get_product(absint($_GET['product_id']));
        }

        if (!$product) {
            return;
        }

        $context = [
            'productId' => (string) $product->get_id(),
            'productName' => $product->get_name(),
            'productImage' => $this->get_product_image_url($product),
            'productPrice' => $product->get_price_html(),
            'sku' => $product->get_sku(),
        ];

        wp_localize_script('worknoon-chat-widget', 'worknoonProductContext', $context);
    }

    public function get_product_context($product_id = null) {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        $id = $product_id ?: get_the_ID();
        $product = wc_get_product($id);

        if (!$product) {
            return null;
        }

        return [
            'productId' => (string) $product->get_id(),
            'productName' => $product->get_name(),
            'productImage' => $this->get_product_image_url($product),
            'productPrice' => $product->get_price_html(),
            'sku' => $product->get_sku(),
        ];
    }

    public function get_order_context($order_id) {
        if (!function_exists('wc_get_order')) {
            return null;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return null;
        }

        $items = $order->get_items();
        $first_item = reset($items);

        return [
            'orderId' => (string) $order->get_id(),
            'productName' => $first_item ? $first_item->get_name() : '',
            'productPrice' => wc_price($order->get_total()),
        ];
    }

    private function get_product_image_url($product) {
        $image_id = $product->get_image_id();
        if ($image_id) {
            $src = wp_get_attachment_image_src($image_id, 'thumbnail');
            return $src ? $src[0] : '';
        }
        return '';
    }
}
