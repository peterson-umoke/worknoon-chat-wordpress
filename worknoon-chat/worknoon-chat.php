<?php
/**
 * Plugin Name: Worknoon Chat
 * Description: Real-time chat widget for eCommerce — connects WordPress users to a Node.js/Socket.IO backend.
 * Version: 1.0.0
 * Author: Worknoon
 * Text Domain: worknoon-chat
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WORKNOON_CHAT_VERSION', '1.0.0');
define('WORKNOON_CHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WORKNOON_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WORKNOON_CHAT_BACKEND_URL', get_option('worknoon_backend_url', 'http://localhost:5000'));

require_once WORKNOON_CHAT_PLUGIN_DIR . 'includes/class-chat-cpt.php';
require_once WORKNOON_CHAT_PLUGIN_DIR . 'includes/class-chat-api.php';
require_once WORKNOON_CHAT_PLUGIN_DIR . 'includes/class-chat-woocommerce.php';
require_once WORKNOON_CHAT_PLUGIN_DIR . 'includes/class-chat-shortcode.php';

$worknoon_chat_cpt = new Worknoon_Chat_CPT();
$worknoon_chat_cpt->register();

$worknoon_chat_api = new Worknoon_Chat_API();
$worknoon_chat_api->register();

$worknoon_chat_wc = new Worknoon_Chat_WooCommerce();
$worknoon_chat_wc->register();

$worknoon_chat_shortcode = new Worknoon_Chat_Shortcode();
$worknoon_chat_shortcode->register();

register_activation_hook(__FILE__, function () {
    add_option('worknoon_backend_url', 'http://localhost:5000');
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

add_action('admin_menu', function () {
    add_options_page(
        'Worknoon Chat Settings',
        'Worknoon Chat',
        'manage_options',
        'worknoon-chat',
        'worknoon_chat_settings_page'
    );
});

function worknoon_chat_settings_page() {
    if (isset($_POST['worknoon_backend_url']) && check_admin_referer('worknoon_chat_settings')) {
        update_option('worknoon_backend_url', esc_url_raw(wp_unslash($_POST['worknoon_backend_url'])));
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $backend_url = get_option('worknoon_backend_url', 'http://localhost:5000');
    ?>
    <div class="wrap">
        <h1>Worknoon Chat Settings</h1>
        <form method="post">
            <?php wp_nonce_field('worknoon_chat_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="backend_url">Backend URL</label></th>
                    <td>
                        <input type="url" id="backend_url" name="worknoon_backend_url"
                               value="<?php echo esc_attr($backend_url); ?>"
                               class="regular-text" placeholder="http://localhost:5000" />
                        <p class="description">URL of your Node.js/Socket.IO backend server.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>
        <h2>Shortcode Usage</h2>
        <p>Place <code>[worknoon_chat]</code> on any page to display the floating chat widget.</p>
        <p>Or use the <strong>Worknoon Chat</strong> Elementor widget for drag-and-drop placement.</p>
    </div>
    <?php
}
