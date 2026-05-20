# Worknoon Chat WordPress Plugin

WordPress plugin that embeds the Worknoon real-time chat widget into any WooCommerce store.

## Technologies

- **PHP 8+** — WordPress plugin development
- **WordPress REST API** — User sync with the Node.js backend
- **WooCommerce** — Product and order context integration
- **Socket.IO Client (CDN)** — Real-time messaging in the browser
- **Elementor** — Drag-and-drop widget support
- **WordPress Playground** — Zero-dependency local development

## Features

- Floating chat widget with `[worknoon_chat]` shortcode
- Elementor widget for visual placement
- Automatic WooCommerce product context (image, name, price)
- Custom Post Type: `chat_session` for tracking conversations
- REST API endpoints for user sync and JWT token retrieval
- WordPress role mapping (administrator → admin, shop_manager → merchant)
- Configurable backend URL via WP Admin settings page
- Responsive widget with mobile support

## Project Structure

```
worknoon-chat/
├── worknoon-chat.php          # Main plugin file & settings page
├── includes/
│   ├── class-chat-cpt.php     # Chat Session CPT registration
│   ├── class-chat-api.php     # REST API sync endpoints
│   ├── class-chat-woocommerce.php  # Product/order context
│   ├── class-chat-shortcode.php    # Shortcode & asset enqueue
│   └── class-elementor-widget.php  # Elementor widget
└── assets/
    ├── chat-widget.js         # Frontend Socket.IO client
    └── chat-widget.css        # Widget styles
```

## Setup

### Local Development (WordPress Playground)

```bash
# Install dependencies
npm install

# Start WordPress with WooCommerce pre-installed
npm run dev
```

This boots a virtual WordPress instance with:
- WooCommerce installed and activated
- The plugin mounted and active
- Auto-login as administrator

### Manual Installation

1. Upload the `worknoon-chat` folder to `/wp-content/plugins/`
2. Activate via WP Admin → Plugins
3. Go to Settings → Worknoon Chat to configure the backend URL

### Usage

Place the shortcode on any page:

```
[worknoon_chat label="Chat with us" position="bottom-right"]
```

Or use the **Worknoon Chat** Elementor widget for drag-and-drop placement.

## REST API Endpoints

- `POST /wp-json/worknoon-chat/v1/sync-user` — Register logged-in WP user in the Node.js backend
- `GET /wp-json/worknoon-chat/v1/chat-token` — Get JWT token for Socket.IO authentication

Both endpoints require the user to be logged into WordPress.

## Challenges

- **Cross-platform auth** — WordPress users are automatically registered in the Express/MongoDB backend via REST API sync, then authenticated with JWT for Socket.IO.
- **WooCommerce context capture** — Product details (image, name, price) are captured on product pages and passed to the chat widget via `wp_localize_script`.
- **Elementor integration** — Custom widget category and controls for label/position configuration, delegating to the shortcode for rendering.

## Demo

[Demo video walkthrough](#) — *Add your Loom/YouTube link here*
