(function () {
    'use strict';

    var config = window.worknoonConfig || {};
    var ecommerceContext = config.context || window.worknoonProductContext || null;
    var backendUrl = config.backendUrl || 'http://localhost:3001';
    var restUrl = config.restUrl || '/wp-json/worknoon-chat/v1';
    var token = null;
    var socket = null;
    var currentConversationId = null;
    var typingTimer = null;

    var trigger = document.getElementById('worknoon-chat-trigger');
    var panel = document.getElementById('worknoon-chat-panel');
    var closeBtn = document.getElementById('worknoon-chat-close');
    var contextEl = document.getElementById('worknoon-chat-context');
    var statusEl = document.getElementById('worknoon-chat-status');
    var messagesEl = document.getElementById('worknoon-chat-messages');
    var inputEl = document.getElementById('worknoon-chat-input');
    var sendBtn = document.getElementById('worknoon-chat-send');

    if (!trigger || !panel || !messagesEl || !inputEl || !sendBtn) {
        return;
    }

    trigger.addEventListener('click', function () {
        panel.style.display = 'flex';
        trigger.style.display = 'none';
        renderContext();
        if (!token) {
            startSession();
        }
    });

    closeBtn.addEventListener('click', function () {
        panel.style.display = 'none';
        trigger.style.display = 'flex';
    });

    sendBtn.addEventListener('click', sendMessage);
    inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    inputEl.addEventListener('input', function () {
        if (typingTimer) clearTimeout(typingTimer);
        if (socket && currentConversationId) {
            socket.emit('typing', { conversationId: currentConversationId });
        }
        typingTimer = setTimeout(function () {
            if (socket && currentConversationId) {
                socket.emit('stopTyping', { conversationId: currentConversationId });
            }
        }, 2000);
    });

    function startSession() {
        setStatus('Connecting...');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', restUrl + '/session', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-WP-Nonce', config.nonce || '');
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                var data = JSON.parse(xhr.responseText);
                token = data.token;
                currentConversationId = data.conversationId || (data.conversation && data.conversation._id);
                connectSocket();
                loadMessages();
                setStatus('');
            } else {
                setStatus(readError(xhr, 'Could not start chat.'));
            }
        };
        xhr.onerror = function () {
            setStatus('Could not reach chat service.');
        };
        xhr.send(JSON.stringify({
            type: config.type || 'customer-to-agent',
            context: ecommerceContext || {}
        }));
    }

    function connectSocket() {
        if (typeof io === 'undefined') {
            loadScript('https://cdn.socket.io/4.8.3/socket.io.min.js', function () {
                initSocket();
            });
        } else {
            initSocket();
        }
    }

    function initSocket() {
        socket = io(backendUrl, { auth: { token: token } });

        socket.on('connect', function () {
            if (currentConversationId) {
                socket.emit('joinRoom', currentConversationId);
            }
        });

        socket.on('messageReceived', function (msg) {
            if (msg.conversationId === currentConversationId) {
                appendMessage(msg);
            }
        });

        socket.on('typingIndicator', function (data) {
            if (data.conversationId === currentConversationId) {
                showTyping(data.username);
            }
        });

        socket.on('stopTypingIndicator', function (data) {
            if (data.conversationId === currentConversationId) {
                hideTyping();
            }
        });
    }

    function loadScript(url, callback) {
        var s = document.createElement('script');
        s.src = url;
        s.onload = callback;
        document.head.appendChild(s);
    }

    function loadMessages() {
        if (!currentConversationId) return;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', backendUrl + '/api/messages/' + currentConversationId, true);
        xhr.setRequestHeader('Authorization', 'Bearer ' + token);
        xhr.onload = function () {
            if (xhr.status === 200) {
                var messages = JSON.parse(xhr.responseText);
                messagesEl.innerHTML = '';
                messages.forEach(function (msg) {
                    appendMessage(msg);
                });
                scrollToBottom();
            }
        };
        xhr.send();

        if (socket) {
            socket.emit('joinRoom', currentConversationId);
        }
    }

    function sendMessage() {
        var content = inputEl.value.trim();
        if (!content || !socket || !currentConversationId) return;

        inputEl.value = '';
        socket.emit('sendMessage', {
            conversationId: currentConversationId,
            content: content,
        });
    }

    function appendMessage(msg) {
        var div = document.createElement('div');
        div.className = 'worknoon-message ' + (msg.sender && msg.sender.username === config.user.username ? 'worknoon-message-own' : 'worknoon-message-other');

        var sender = msg.sender ? msg.sender.username : 'Unknown';
        var time = msg.createdAt ? new Date(msg.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';

        var contentHtml = '';
        if (msg.fileType === 'image') {
            contentHtml = '<img src="' + escapeHtml(msg.content) + '" alt="Shared image" style="max-width:200px;border-radius:8px;" />';
        } else if (msg.fileType === 'document') {
            contentHtml = '<a href="' + escapeHtml(msg.content) + '" target="_blank" rel="noopener">📎 ' + escapeHtml(msg.content.split('/').pop()) + '</a>';
        } else {
            contentHtml = escapeHtml(msg.content);
        }

        div.innerHTML =
            '<div class="worknoon-message-sender">' + escapeHtml(sender) + '</div>' +
            '<div class="worknoon-message-content">' + contentHtml + '</div>' +
            '<div class="worknoon-message-time">' + time + '</div>';

        messagesEl.appendChild(div);
        scrollToBottom();
    }

    function renderContext() {
        if (!contextEl || !ecommerceContext || (!ecommerceContext.productName && !ecommerceContext.orderId)) {
            return;
        }

        var title = ecommerceContext.productName || ('Order #' + ecommerceContext.orderId);
        var price = ecommerceContext.productPrice || '';
        var image = ecommerceContext.productImage || '';

        contextEl.innerHTML =
            (image ? '<img src="' + escapeHtml(image) + '" alt="" />' : '') +
            '<div><strong>' + escapeHtml(title) + '</strong>' +
            (price ? '<span>' + escapeHtml(price) + '</span>' : '') +
            '</div>';
        contextEl.style.display = 'flex';
    }

    function setStatus(message) {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.style.display = message ? 'block' : 'none';
    }

    function readError(xhr, fallback) {
        try {
            var data = JSON.parse(xhr.responseText);
            return data.message || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function showTyping(username) {
        var existing = document.getElementById('worknoon-typing');
        if (existing) return;
        var div = document.createElement('div');
        div.id = 'worknoon-typing';
        div.className = 'worknoon-typing';
        div.textContent = username + ' is typing...';
        messagesEl.appendChild(div);
        scrollToBottom();
    }

    function hideTyping() {
        var el = document.getElementById('worknoon-typing');
        if (el) el.remove();
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
})();
