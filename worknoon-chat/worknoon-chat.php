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
define('WORKNOON_CHAT_BACKEND_URL', get_option('worknoon_backend_url', 'http://localhost:3001'));
define('WORKNOON_CHAT_SYNC_SECRET', get_option('worknoon_sync_secret', 'worknoon-wordpress-dev-secret'));

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
    add_option('worknoon_backend_url', 'http://localhost:3001');
    add_option('worknoon_sync_secret', 'worknoon-wordpress-dev-secret');
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

add_action('admin_enqueue_scripts', function ($hook_suffix) {
    if ($hook_suffix !== 'settings_page_worknoon-chat') {
        return;
    }

    wp_enqueue_style('dashicons');
    wp_enqueue_script('jquery');

    $css = '
        .worknoon-secret-field {
            align-items: center;
            display: flex;
            gap: 6px;
        }

        .worknoon-secret-toggle {
            align-items: center;
            display: inline-flex;
            justify-content: center;
            min-width: 32px;
            padding: 0 7px;
        }

        .worknoon-secret-toggle .dashicons {
            font-size: 18px;
            height: 18px;
            line-height: 18px;
            width: 18px;
        }
    ';

    wp_add_inline_style('dashicons', $css);

    $script = '
        document.addEventListener("DOMContentLoaded", function () {
            var input = document.getElementById("sync_secret");
            var toggle = document.querySelector("[data-worknoon-secret-toggle]");

            if (!input || !toggle) {
                return;
            }

            var icon = toggle.querySelector(".dashicons");
            var showLabel = toggle.getAttribute("data-show-label");
            var hideLabel = toggle.getAttribute("data-hide-label");

            toggle.addEventListener("click", function () {
                var shouldShow = input.type === "password";

                input.type = shouldShow ? "text" : "password";
                toggle.setAttribute("aria-pressed", shouldShow ? "true" : "false");
                toggle.setAttribute("aria-label", shouldShow ? hideLabel : showLabel);
                toggle.setAttribute("title", shouldShow ? hideLabel : showLabel);

                if (icon) {
                    icon.classList.toggle("dashicons-visibility", !shouldShow);
                    icon.classList.toggle("dashicons-hidden", shouldShow);
                }
            });
        });
    ';

    wp_add_inline_script('jquery', $script);
});

function worknoon_chat_settings_page() {
    if (isset($_POST['worknoon_backend_url']) && check_admin_referer('worknoon_chat_settings')) {
        update_option('worknoon_backend_url', esc_url_raw(wp_unslash($_POST['worknoon_backend_url'])));
        update_option('worknoon_sync_secret', sanitize_text_field(wp_unslash($_POST['worknoon_sync_secret'] ?? '')));
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $backend_url = get_option('worknoon_backend_url', 'http://localhost:3001');
    $sync_secret = get_option('worknoon_sync_secret', 'worknoon-wordpress-dev-secret');
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
                               class="regular-text" placeholder="http://localhost:3001" />
                        <p class="description">URL of your Node.js/Socket.IO backend server.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sync_secret">Sync Secret</label></th>
                    <td>
                        <span class="worknoon-secret-field">
                            <input type="password" id="sync_secret" name="worknoon_sync_secret"
                                   value="<?php echo esc_attr($sync_secret); ?>"
                                   class="regular-text" autocomplete="new-password" />
                            <button type="button"
                                    class="button worknoon-secret-toggle"
                                    data-worknoon-secret-toggle
                                    data-show-label="<?php echo esc_attr__('Show sync secret', 'worknoon-chat'); ?>"
                                    data-hide-label="<?php echo esc_attr__('Hide sync secret', 'worknoon-chat'); ?>"
                                    aria-label="<?php echo esc_attr__('Show sync secret', 'worknoon-chat'); ?>"
                                    aria-pressed="false"
                                    title="<?php echo esc_attr__('Show sync secret', 'worknoon-chat'); ?>">
                                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                            </button>
                        </span>
                        <p class="description">Must match the backend <code>WORDPRESS_SYNC_SECRET</code> value.</p>
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
