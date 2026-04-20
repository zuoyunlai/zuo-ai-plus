(function () {
    'use strict';

    function getArticleContext() {
        // 尝试获取当前文章的主要内容
        var selectors = [
            '.entry-content', '.post-content', '.article-content',
            '.single-post .entry-content', 'article .entry-content',
            '.content-area', 'main article', '.post-body'
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

    function initChat(el) {
        var model = el.dataset.model || 'zhipu';
        var messages = [];
        // 首次加载：注入文章上下文
        var articleContext = getArticleContext();
        if (articleContext && messages.length === 0) {
            messages.push({
                role: 'system',
                content: '【参考内容】以下是当前文章的全部内容，答题时请以它为依据：\n' + articleContext
            });
        }
        var inputEl = el.querySelector('.ai-plus-chat-input');
        var sendBtn = el.querySelector('.ai-plus-chat-send');
        var msgsEl = el.querySelector('.ai-plus-chat-messages');
        var loadingEl = el.querySelector('.ai-plus-chat-loading');

        if (!inputEl || !sendBtn) return;

        sendBtn.addEventListener('click', sendMessage);
        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        function sendMessage() {
            var text = inputEl.value.trim();
            if (!text) return;
            messages.push({ role: 'user', content: text });
            renderMessages();
            inputEl.value = '';
            showLoading();

            var articleContext = getArticleContext();
            var fetchBody = { model: model, messages: messages, max_tokens: 2048 };
            if (articleContext) fetchBody.context = articleContext;
            fetch(window.aiPlusConfig.apiUrl + 'chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.aiPlusConfig.nonce },
                body: JSON.stringify(fetchBody)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var reply = (data.code === 'success' && data.data) ? (data.data.choices?.[0]?.message?.content || data.data.content || '') : '';
                if (data.code !== 'success') { reply = data.message || data.error || '无响应'; }
                messages.push({ role: 'assistant', content: reply });
                hideLoading();
                renderMessages();
            })
            .catch(function () {
                messages.push({ role: 'assistant', content: '请求失败，请重试' });
                hideLoading();
                renderMessages();
            });
        }

        function renderMessages() {
            if (!msgsEl) return;
            msgsEl.innerHTML = '';
            messages.forEach(function (m) {
                var div = document.createElement('div');
                div.style.cssText = 'margin-bottom:8px;padding:8px 12px;border-radius:8px;font-size:14px;line-height:1.6;';
                if (m.role === 'user') {
                    div.style.background = '#e7f3ff';
                    div.style.border = '1px solid #b3d7ff';
                    div.style.textAlign = 'right';
                } else {
                    div.style.background = '#f5f5f5';
                    div.style.border = '1px solid #e0e0e0';
                }
                div.textContent = m.content;
                msgsEl.appendChild(div);
            });
            msgsEl.scrollTop = msgsEl.scrollHeight;
        }

        function showLoading() {
            if (loadingEl) loadingEl.style.display = 'inline';
            sendBtn.disabled = true;
        }

        function hideLoading() {
            if (loadingEl) loadingEl.style.display = 'none';
            sendBtn.disabled = false;
        }
    }

    // 初始化所有前端聊天区块
    document.querySelectorAll('.ai-plus-chat-rendered').forEach(initChat);

    // MutationObserver: 页面动态加载后也初始化
    if (typeof MutationObserver !== 'undefined') {
        var obs = new MutationObserver(function (records) {
            records.forEach(function (r) {
                [].slice.call(r.addedNodes).forEach(function (node) {
                    if (node.nodeType === 1) {
                        if (node.classList && node.classList.contains('ai-plus-chat-rendered')) {
                            initChat(node);
                        }
                        node.querySelectorAll && node.querySelectorAll('.ai-plus-chat-rendered').forEach(initChat);
                    }
                });
            });
        });
        obs.observe(document.body, { childList: true, subtree: true });
    }
})();
