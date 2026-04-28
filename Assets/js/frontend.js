/**
 * AI Plus Frontend JS - 统一聊天窗口初始化
 * 职责：
 *   1. 浮窗聊天（.ai-plus-chat-btn / #ai-plus-chat-window）
 *   2. 嵌入聊天区块（.ai-plus-embed-chat，短代码 [ai_plus_chat]）
 *   3. 文章嵌入聊天（.ai-plus-chat-rendered，Gutenberg 区块渲染）
 */
(function () {
    'use strict';

    // ── 浮窗聊天 ──────────────────────────────────────────────────────────────
    var chatHistory = [];

    window.toggleAiChat = function () {
        var win = document.getElementById('ai-plus-chat-window');
        var btn = document.getElementById('ai-plus-chat-btn');
        if (!win || !btn) return;

        var isOpen = win.style.display !== 'none';
        if (isOpen) {
            win.style.display = 'none';
            btn.innerHTML = '💬';
        } else {
            win.style.display = 'flex';
            btn.innerHTML = '✕';
        }
    };

    window.sendAiChat = function () {
        var input = document.getElementById('ai-chat-msg');
        if (!input) return;
        var msg = input.value.trim();
        if (!msg) return;

        // 优先用 window.aiPlusChat（Frontend_Init 设置），兼容 window.aiPlusConfig
        var config = window.aiPlusChat || window.aiPlusConfig || {};
        var model = config.defaultModel || 'minimax';
        var apiUrl = config.apiUrl;
        var nonce = config.nonce;
        if (!apiUrl || !nonce) return;

        var container = document.getElementById('ai-chat-messages');

        // 浮窗客服：读取当前文章上下文（PHP 写入 hidden input）
        var ctxInput = document.getElementById('ai-chat-article-context');
	var articleContext = ctxInput ? atob(ctxInput.textContent.trim()) : '';

        // 渲染用户消息
        addMessageEl(container, msg, 'user', false);
        chatHistory.push({ role: 'user', content: msg });
        input.value = '';

        // 显示 loading
        var loadingEl = document.createElement('div');
        loadingEl.className = 'ai-chat-msg assistant';
        loadingEl.innerHTML = '<em>思考中...</em>';
        container.appendChild(loadingEl);
        container.scrollTop = container.scrollHeight;

        var fetchBody = { model: model, messages: chatHistory, session_id: 'web_' + Date.now() };
        if (articleContext) fetchBody.context = articleContext;

        fetch(apiUrl + 'chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify(fetchBody)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            loadingEl.remove();
            var reply = '';
            var isHtml = false;
            
            // 检查权限错误（包含登录链接）
            if (data.code === 'rest_forbidden_chat' || (data.message && data.message.indexOf('<a') !== -1)) {
                reply = data.message;
                isHtml = true;
            } else if (data.code === 'success' && data.data) {
                reply = data.data.choices?.[0]?.message?.content || data.data.content || '';
            } else {
                reply = data.message || data.error || '未知错误';
                if (reply.indexOf('<a') !== -1) isHtml = true;
            }
            
            addMessageEl(container, reply, 'assistant', isHtml);
            chatHistory.push({ role: 'assistant', content: reply });
        })
        .catch(function () {
            loadingEl.remove();
            addMessageEl(container, '网络错误，请稍后重试', 'assistant', false);
        });
    };

    // ── 嵌入聊天（短代码 / 文章内嵌入块）─────────────────────────────────────
    function initEmbedChat(chat) {
        var modelSel = chat.querySelector('.embed-model-sel');
        var container = chat.querySelector('.embed-messages');
        var input = chat.querySelector('.embed-msg-input');
        var sendBtn = chat.querySelector('.embed-send-btn');
        var history = [];

        if (!input || !sendBtn || !container) return;

        function send() {
            var msg = input.value.trim();
            if (!msg) return;

            var config = window.aiPlusChat || window.aiPlusConfig || {};
            var apiUrl = config.apiUrl;
            var nonce = config.nonce;
            if (!apiUrl || !nonce) return;

            var model = modelSel ? modelSel.value : (chat.dataset.model || 'minimax');

            // 渲染用户消息
            addMessageEl(container, msg, 'user', false);
            history.push({ role: 'user', content: msg });
            input.value = '';

            // 显示 loading
            var loadingEl = document.createElement('div');
            loadingEl.className = 'ai-chat-msg assistant';
            loadingEl.innerHTML = '<em>思考中...</em>';
            container.appendChild(loadingEl);
            container.scrollTop = container.scrollHeight;

            fetch(apiUrl + 'chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({ model: model, messages: history, session_id: 'embed_' + Date.now() })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                loadingEl.remove();
                var reply = '';
                var isHtml = false;
                
                // 检查权限错误（包含登录链接）
                if (data.code === 'rest_forbidden_chat' || (data.message && data.message.indexOf('<a') !== -1)) {
                    reply = data.message;
                    isHtml = true;
                } else if (data.code === 'success' && data.data) {
                    reply = data.data.choices?.[0]?.message?.content || data.data.content || '';
                } else {
                    reply = data.message || data.error || '无响应';
                    if (reply.indexOf('<a') !== -1) isHtml = true;
                }
                
                addMessageEl(container, reply, 'assistant', isHtml);
                history.push({ role: 'assistant', content: reply });
            })
            .catch(function () {
                loadingEl.remove();
                addMessageEl(container, '网络错误，请重试', 'assistant', false);
            });
        }

        sendBtn.addEventListener('click', send);
        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') send();
        });
    }

    // ── 文章嵌入聊天区块（Gutenberg 动态块）───────────────────────────────────
    function getArticleContext() {
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

    function initBlockChat(el) {
        var model = el.dataset.model || 'minimax';
        var messages = [];

        // 优先从 PHP 写入的隐藏 input 读取（最可靠）
        // 兜底：JS CSS 选择器（主题自定义结构时）
        var ctxInput = el.querySelector('input.ai-article-context-val');
        var articleContext = ctxInput ? (ctxInput.value || '') : (getArticleContext() || '');

        var inputEl = el.querySelector('.ai-plus-chat-input');
        var sendBtn = el.querySelector('.ai-plus-chat-send');
        var msgsEl = el.querySelector('.ai-plus-chat-messages');
        var loadingEl = el.querySelector('.ai-plus-chat-loading');

        if (!inputEl || !sendBtn) return;

        function sendMessage() {
            var text = inputEl.value.trim();
            if (!text) return;
            messages.push({ role: 'user', content: text });
            renderMessages();
            inputEl.value = '';
            showLoading();

            var config = window.aiPlusConfig || window.aiPlusChat || {};
            var apiUrl = config.apiUrl;
            var nonce = config.nonce;
            if (!apiUrl || !nonce) {
                hideLoading();
                renderMessages();
                return;
            }

            var fetchBody = { model: model, messages: messages, max_tokens: 2048, session_id: 'block_' + Date.now() };
            if (articleContext) fetchBody.context = articleContext;

            fetch(apiUrl + 'chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                body: JSON.stringify(fetchBody)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var reply = '';
                var isHtml = false;
                
                // 检查权限错误（包含登录链接）
                if (data.code === 'rest_forbidden_chat' || (data.message && data.message.indexOf('<a') !== -1)) {
                    reply = data.message;
                    isHtml = true;
                } else if (data.code === 'success' && data.data) {
                    reply = data.data.choices?.[0]?.message?.content || data.data.content || '';
                } else {
                    reply = data.message || data.error || '无响应';
                    if (reply.indexOf('<a') !== -1) isHtml = true;
                }
                
                messages.push({ role: 'assistant', content: reply, isHtml: isHtml });
                hideLoading();
                renderMessages();
            })
            .catch(function () {
                messages.push({ role: 'assistant', content: '请求失败，请重试' });
                hideLoading();
                renderMessages();
            });
        }

        sendBtn.addEventListener('click', sendMessage);
        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        function renderMessages() {
            if (!msgsEl) return;
            msgsEl.innerHTML = '';
            messages.forEach(function (m) {
                var div = document.createElement('div');
                div.className = 'ai-plus-msg ' + m.role;
                if (m.role === 'assistant') {
                    // 检查是否需要直接渲染 HTML（权限错误消息）
                    if (m.isHtml) {
                        div.innerHTML = m.content;
                    } else {
                        try { div.innerHTML = parseMarkdown(m.content); } catch(e) { div.textContent = m.content; }
                    }
                } else {
                    div.textContent = m.content;
                }
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

    // ── 通用消息渲染 ──────────────────────────────────────────────────────────
    function addMessageEl(container, text, role, isHtml) {
        if (!container) return;
        var el = document.createElement('div');
        el.className = 'ai-chat-msg ' + role;
        
        // 权限错误消息（包含 HTML 链接）直接渲染
        if (isHtml && role === 'assistant') {
            el.innerHTML = text;
        } else if (role === 'assistant') {
            // AI 回复才渲染 Markdown；用户输入始终纯文本
            try { el.innerHTML = parseMarkdown(text); } catch(e) { el.textContent = text; }
        } else {
            el.textContent = text;
        }
        
        container.appendChild(el);
        container.scrollTop = container.scrollHeight;
    }

    /**
     * Markdown → HTML（安全渲染浮窗/嵌入聊天消息）
     * 使用 marked.js CDN，渲染后清理危险标签
     */
    function parseMarkdown(text) {
        // 1. 先把危险字符转义（防止 XSS）
        var escaped = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // 2. 用 marked 渲染 Markdown
        var html;
        if (typeof marked !== 'undefined' && typeof marked.parse === 'function') {
            try { html = marked.parse(escaped); } catch(e) { html = escaped; }
        } else {
            // marked 未加载时，直接把换行替换为 <br>
            return escaped.replace(/\n/g, '<br>');
        }

        // 3. 清理危险标签（只保留安全标签白名单）
        // 安全标签：strong, em, b, i, code, pre, br, p, ul, ol, li, a, blockquote, hr, h1-h6
        var safeTags = ['strong','em','b','i','code','pre','br','p','ul','ol','li','a','blockquote','hr',
                        'h1','h2','h3','h4','h5','h6','table','thead','tbody','tr','th','td'];
        // 把所有 <tag ...> 逐个检查，非白名单标签名转为 &lt;tag ...&gt;
        html = html.replace(/<([a-z][a-z0-9]*)([^>]*)>/gi, function(match, tag, attrs) {
            tag = tag.toLowerCase();
            if (safeTags.indexOf(tag) === -1) {
                return '&lt;' + tag + attrs + '&gt;';
            }
            // a 标签补充安全属性
            if (tag === 'a') {
                if (attrs.indexOf('rel=') === -1) attrs += ' rel="noopener noreferrer"';
                if (attrs.indexOf('target=') === -1) attrs += ' target="_blank"';
                // 只允许 href 属性，过滤 style 和事件属性
                attrs = attrs.replace(/\s+on\w+\s*=\s*"[^"]*"/gi, '');
                attrs = attrs.replace(/\s+on\w+\s*=\s*'[^']*'/gi, '');
                attrs = attrs.replace(/\s+style\s*=\s*"[^"]*"/gi, '');
                attrs = attrs.replace(/\s+style\s*=\s*'[^']*'/gi, '');
            }
            // 所有标签：过滤 style 和事件属性
            attrs = attrs.replace(/\s+on\w+\s*=\s*"[^"]*"/gi, '');
            attrs = attrs.replace(/\s+on\w+\s*=\s*'[^']*'/gi, '');
            attrs = attrs.replace(/\s+style\s*=\s*"[^"]*"/gi, '');
            attrs = attrs.replace(/\s+style\s*=\s*'[^']*'/gi, '');
            return '<' + tag + attrs + '>';
        });

        return html;
    }

    // ── 初始化 ────────────────────────────────────────────────────────────────
    function initAll() {
        // 浮窗聊天
        document.querySelectorAll('.ai-plus-embed-chat').forEach(initEmbedChat);
        // 文章嵌入聊天区块
        document.querySelectorAll('.ai-plus-chat-rendered').forEach(initBlockChat);
    }

    // DOMReady 后初始化所有聊天
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // MutationObserver：支持 Ajax 动态加载后的聊天区块
    if (typeof MutationObserver !== 'undefined') {
        var obs = new MutationObserver(function (records) {
            var needsInitEmbed = false;
            var needsInitBlock = false;
            records.forEach(function (r) {
                [].slice.call(r.addedNodes).forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.classList) {
                        if (node.classList.contains('ai-plus-embed-chat')) needsInitEmbed = true;
                        if (node.classList.contains('ai-plus-chat-rendered')) needsInitBlock = true;
                    }
                    if (node.querySelectorAll) {
                        if (node.querySelectorAll('.ai-plus-embed-chat').length) needsInitEmbed = true;
                        if (node.querySelectorAll('.ai-plus-chat-rendered').length) needsInitBlock = true;
                    }
                });
            });
            if (needsInitEmbed) {
                document.querySelectorAll('.ai-plus-embed-chat:not([data-initialized])').forEach(function (el) {
                    el.setAttribute('data-initialized', '1');
                    initEmbedChat(el);
                });
            }
            if (needsInitBlock) {
                document.querySelectorAll('.ai-plus-chat-rendered:not([data-initialized])').forEach(function (el) {
                    el.setAttribute('data-initialized', '1');
                    initBlockChat(el);
                });
            }
        });
        obs.observe(document.body, { childList: true, subtree: true });
    }

})();