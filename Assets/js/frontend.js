/**
 * AI Plus Frontend JS - 客服聊天窗口
 */
(function () {
    'use strict';

    // ========== 浮窗聊天 ==========
    var aiChatOpen = false;

    window.toggleAiChat = function () {
        aiChatOpen = !aiChatOpen;
        var win = document.getElementById('ai-plus-chat-window');
        var btn = document.getElementById('ai-plus-chat-btn');
        if (aiChatOpen) {
            win.style.display = 'flex';
            btn.innerHTML = '✕';
        } else {
            win.style.display = 'none';
            btn.innerHTML = '💬';
        }
    };

    window.sendAiChat = function () {
        var input = document.getElementById('ai-chat-msg'); if (!input) return;
        var msg = input.value.trim();
        if (!msg) return;

        var model = aiPlusChat && aiPlusChat.defaultModel ? aiPlusChat.defaultModel : 'zhipu';
        var container = document.getElementById('ai-chat-messages');

        addMessage(msg, 'user', container);
        chatHistory.push({ role: 'user', content: msg });
        input.value = '';

        var loadingEl = document.createElement('div');
        loadingEl.className = 'ai-chat-msg assistant';
        loadingEl.innerHTML = '<em>思考中...</em>';
        container.appendChild(loadingEl);
        container.scrollTop = container.scrollHeight;

        fetch(aiPlusChat.apiUrl + 'chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': aiPlusChat.nonce
            },
            body: JSON.stringify({ model: model, messages: chatHistory, context: getArticleContextForChat(), session_id: 'web_' + Date.now() })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            loadingEl.remove();
            var reply = data.choices?.[0]?.message?.content || data.content || '抱歉出错了: ' + (data.error || '未知错误');
            addMessage(reply, 'assistant', container);
            chatHistory.push({ role: 'assistant', content: reply });
        })
        .catch(function (err) {
            loadingEl.remove();
            addMessage('网络错误，请稍后重试', 'assistant', container);
        });
    };

    var chatHistory = [];

    function getArticleContextForChat() {
        var selectors = [
            '.entry-content', '.post-content', '.article-content',
            '.single-post .entry-content', 'article .entry-content',
            '.content-area', 'main article', '.post-body',
            '.yoo-content', '.article-body', '.post-inner'
        ];
        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el) {
                var tmp = document.createElement('div');
                tmp.innerHTML = el.innerHTML;
                var text = (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
                if (text.length > 50) return text;
            }
        }
        return '';
    }

    // ========== 嵌入聊天初始化 ==========
    function initEmbedChat(chat) {
        var modelSel = chat.querySelector('.embed-model-sel');
        var container = chat.querySelector('.embed-messages');
        var input = chat.querySelector('.embed-msg-input');
        var sendBtn = chat.querySelector('.embed-send-btn');
        var history = [];

        if (!input || !sendBtn) return;

        function send() {
            var msg = input.value.trim();
            if (!msg) return;

            var model = modelSel ? modelSel.value : chat.dataset.model;
            var el = document.createElement('div');
            el.className = 'ai-chat-msg user';
            el.textContent = msg;
            container.appendChild(el);
            history.push({ role: 'user', content: msg });
            input.value = '';

            var loading = document.createElement('div');
            loading.className = 'ai-chat-msg assistant';
            loading.innerHTML = '<em>思考中...</em>';
            container.appendChild(loading);
            container.scrollTop = container.scrollHeight;

            fetch(aiPlusChat.apiUrl + 'chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': aiPlusChat.nonce },
                body: JSON.stringify({ model: model, messages: history, session_id: 'embed_' + Date.now() })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                loading.remove();
                var reply = data.choices?.[0]?.message?.content || data.content || data.error || '无响应';
                addMessage(reply, 'assistant', container);
                history.push({ role: 'assistant', content: reply });
            })
            .catch(function () {
                loading.remove();
                addMessage('网络错误，请重试', 'assistant', container);
            });
        }

        sendBtn.addEventListener('click', send);
        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') send();
        });
    }

    function addMessage(text, role, container) {
        var el = document.createElement('div');
        el.className = 'ai-chat-msg ' + role;
        el.textContent = text;
        container.appendChild(el);
        container.scrollTop = container.scrollHeight;
    }

    // 初始化所有嵌入聊天
    document.querySelectorAll('.ai-plus-embed-chat').forEach(initEmbedChat);

})();
