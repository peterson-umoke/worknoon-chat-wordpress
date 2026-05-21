# Worknoon Chat WordPress Plugin

WordPress plugin that embeds Worknoon real-time chat into WooCommerce pages.

## What It Does

- Provides a floating widget via shortcode
- Creates/uses backend chat sessions through WP REST
- Syncs logged-in WP users to backend (`/api/auth/wordpress-sync`)
- Maps WP roles to backend roles
- Captures product/order context for support conversations
- Stores chat sessions as a custom post type (`chat_session`)

## Plugin Components

```text
worknoon-chat/
    worknoon-chat.php
    includes/
        class-chat-cpt.php
        class-chat-api.php
        class-chat-woocommerce.php
        class-chat-shortcode.php
        class-elementor-widget.php
    assets/
        chat-widget.js
        chat-widget.css
```

## Settings (WP Admin)

WP Admin -> Settings -> Worknoon Chat

- Backend URL (`worknoon_backend_url`)
- Sync Secret (`worknoon_sync_secret`)

The sync secret must match backend `WORDPRESS_SYNC_SECRET`.

## WordPress REST Endpoints

Namespace: `worknoon-chat/v1`

- `POST /sync-user`
- `GET /chat-token`
- `POST /session`

All require logged-in WordPress user.

## Shortcode

```text
[worknoon_chat]
```

Supported attributes:

- `label` (default: `Chat with us`)
- `position` (default: `bottom-right`)
- `type` (`customer-to-agent`, `customer-to-designer`, `customer-to-merchant`, `general`)

Example:

```text
[worknoon_chat label="Need help?" type="customer-to-merchant"]
```

## WooCommerce Context

When available, widget payload includes:

- `productId`
- `productName`
- `productImage`
- `productPrice`
- `orderId`

Context is captured from product page, `product_id` query param, or `order_id` query param.

## Local Development

```bash
npm install
npm run dev
```

This starts WordPress Playground and mounts plugin source into the Playground WP instance.

## Backend Dependency

This plugin expects the Node backend to be running and reachable from WordPress.

Minimum backend support required:

- `POST /api/auth/wordpress-sync`
- `POST /api/conversations`
- `GET /api/messages/:conversationId`
- Socket.IO endpoint for realtime messaging

