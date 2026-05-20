(function () {
    'use strict';

    var config = window.worknoonConfig || {};
    var productContext = window.worknoonProductContext || null;
    var backendUrl = config.backendUrl || 'http://localhost:5000';
    var token = null;
    var socket = null;
    var currentConversationId = null;
    var typingTimer = null;

    var trigger = document.getElementById('worknoon-chat-trigger');
    var panel = document.getElementById('worknoon-chat-panel');
    var closeBtn = document.getElementById('worknoon-chat-close');
    var messagesEl = document.getElementById('worknoon-chat-messages');
    var inputEl = document.getElementById('worknoon-chat-input');
    var sendBtn = document.getElementById('worknoon-chat-send');

    if (!trigger || !panel || !messagesEl || !inputEl || !sendBtn) {
        return;
    }

    trigger.addEventListener('click', function () {
        panel.style.display = 'flex';
        trigger.style.display = 'none';
        if (!token) {
            authenticate();
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

    function authenticate() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', backendUrl + '/api/auth/login', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onload = function () {
            if (xhr.status === 200) {
                var data = JSON.parse(xhr.responseText);
                token = data.token;
                connectSocket();
                loadOrCreateConversation();
            }
        };
        xhr.send(JSON.stringify({
            emailOrUsername: config.user.email,
            password: 'Password123!'
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

    function loadOrCreateConversation() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', backendUrl + '/api/conversations', true);
        xhr.setRequestHeader('Authorization', 'Bearer ' + token);
        xhr.onload = function () {
            if (xhr.status === 200) {
                var conversations = JSON.parse(xhr.responseText);
                if (conversations.length > 0) {
                    currentConversationId = conversations[0]._id;
                    loadMessages();
                    socket.emit('joinRoom', currentConversationId);
                    return;
                }
            }
            createConversation();
        };
    }

    function createConversation() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', backendUrl + '/api/conversations', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('Authorization', 'Bearer ' + token);
        xhr.onload = function () {
            if (xhr.status === 201) {
                var conv = JSON.parse(xhr.responseText);
                currentConversationId = conv._id;
                socket.emit('joinRoom', currentConversationId);
            }
        };

        var body = { participantIds: [], type: 'general' };
        if (productContext) {
            body.context = {
                productId: productContext.productId,
                productName: productContext.productName,
                productImage: productContext.productImage,
                productPrice: productContext.productPrice,
            };
        }
        xhr.send(JSON.stringify(body));
    }

    function loadMessages() {
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
