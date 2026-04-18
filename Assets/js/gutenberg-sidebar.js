/**
 * AI Plus Gutenberg Sidebar - Redesigned Beautiful UI
 */
(function (wp) {
    'use strict';

    if (!wp.plugins || !wp.data) {
        console.warn('AI Plus: Gutenberg APIs not ready');
        return;
    }

    var registerPlugin = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editor.PluginDocumentSettingPanel;
    var useState = wp.element.useState;

    var modelDefs = window.aiPlusConfig.models || {};
    var apiKeys   = window.aiPlusConfig.apiKeys || {};

    function getModelLabel(k) {
        var m = modelDefs[k] || {};
        // 新结构：apiKeys[k].configured (bool) + apiKeys[k].customModel (string)
        var savedModel = (apiKeys[k] && apiKeys[k].customModel) ? apiKeys[k].customModel : (m.default || '');
        return (m.name || k) + (savedModel ? (' — ' + savedModel) : '');
    }

    var modelLabels = {};
    Object.keys(modelDefs).forEach(function(k) {
        // 只检查是否已配置（不暴露 key 内容）
        if (apiKeys[k] && apiKeys[k].configured) {
            modelLabels[k] = getModelLabel(k);
        }
    });

    function apiRequest(endpoint, data) {
        // 添加超时控制（200秒 = 略小于PHP 300秒限制）
        var controller = new AbortController();
        var timeoutId = setTimeout(function() {
            controller.abort();
        }, 200000); // 200秒超时
        
        return fetch(window.aiPlusConfig.apiUrl + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.aiPlusConfig.nonce
            },
            body: JSON.stringify(data),
            signal: controller.signal
        }).then(function (r) { 
            clearTimeout(timeoutId);
            if (!r.ok) {
                return r.json().then(function(data) {
                    throw new Error(data.error || data.message || 'HTTP ' + r.status);
                }).catch(function() {
                    throw new Error('HTTP ' + r.status + ' ' + r.statusText);
                });
            }
            return r.json(); 
        }).catch(function(e) {
            clearTimeout(timeoutId);
            if (e.name === 'AbortError') {
                throw new Error('请求超时，服务器响应时间过长');
            }
            throw e;
        });
    }

    function savePostContent(postId, content) {
        return new Promise(function(resolve, reject) {
            wp.ajax.post('ai_plus_save_content', {
                post_id: postId,
                content: content,
                nonce: window.aiPlusConfig.nonce, _ajax_nonce: window.aiPlusConfig.nonce
            }).done(resolve).fail(reject);
        });
    }

    function getCurrentContent() {
        try {
            return wp.data.select('core/editor').getEditedPostContent() || '';
        } catch (e) { return ''; }
    }

    function stripTitleFromContent(content) {
        // 过滤规则：去除 AI 生成内容开头的文章标题部分
        // 支持格式：<h1>标题</h1>、<h2>标题</h2>、# 标题、## 标题、### 标题、纯文标题
        var lines = content.split('\n');
        var start = 0;

        // 跳过开头的空行
        while (start < lines.length && !lines[start].trim()) start++;
        if (start >= lines.length) return content;

        var first = lines[start].trim();

        // 规则1：HTML 标题标签 <h1>、<h2>
        if (/^<h[1-6][^>]*>/i.test(first)) {
            var hMatch = first.match(/^<h[1-6][^>]*>([\s\S]*?)<\/h[1-6]>/i);
            if (hMatch) {
                start++;
                while (start < lines.length && !lines[start].trim()) start++;
                return lines.slice(start).join('\n').trim();
            }
        }

        // 规则2：Markdown 标题 # ## ### （1-6个 #）
        if (/^#{1,6}\s+/.test(first)) {
            start++;
            while (start < lines.length && !lines[start].trim()) start++;
            return lines.slice(start).join('\n').trim();
        }

        // 规则3：纯文本标题（较短的中文行，后面紧跟空行）
        if (first.length < 60 && /[\u4e00-\u9fa5]/.test(first) && !first.match(/^[\-\*\d\.\>\#]/)) {
            if (start + 1 < lines.length && !lines[start + 1].trim()) {
                start += 2;
                while (start < lines.length && !lines[start].trim()) start++;
                return lines.slice(start).join('\n').trim();
            }
        }

        return content;
    }

    function insertContent(postId, newContent, replaceMode) {
        // replaceMode=true：丢弃原文，直接替换；false（默认）：追加到末尾
        console.log('=== insertContent START ===');

        // 去掉 AI 内容中可能混入的标题（只在 replaceMode=false 时需要；
        // replaceMode=true 时先不处理，等判断完再决定，防止误删正文）
        var strippedContent = newContent;

        var realPostId = 0;
        try { realPostId = wp.data.select('core/editor').getEditedPostAttribute('id') || 0; } catch(e) {
            console.error('get post id error:', e);
            setGlobalResult({ type: 'err', text: '❌ 无法获取文章ID，请刷新页面重试' });
            return;
        }

        console.log('insertContent called, postId:', postId, 'realPostId:', realPostId, 'replaceMode:', replaceMode);

        // replaceMode=true：直接用新内容（rewrite/expand）；false：追加到现有内容后面
        // 对于 replaceMode，stripTitleFromContent 可能把整个内容清空，先处理再判断
        if (replaceMode) {
            strippedContent = stripTitleFromContent(strippedContent);
            merged = strippedContent;
            // strip 后内容为空时，fallback 到追加模式，防止正文被意外清空
            if (!merged || !merged.trim()) {
                replaceMode = false;
                merged = newContent; // 用原始未 strip 的内容追加
            }
        }
        if (!replaceMode) {
            var current = getCurrentContent();
            merged = current.trim() ? (current + '\n\n' + newContent) : newContent;
        }

        if (!merged || !merged.trim()) {
            console.error('Empty content to insert');
            setGlobalResult({ type: 'err', text: '❌ 没有可插入的内容' });
            return;
        }

        console.log('merged content length:', merged.length);

        // 只有纯文本（非HTML）才包裹为<p>段落；AI返回的HTML（以<开头）直接使用
        if (!merged.trim().match(/^</)) {
            merged = '<p>' + merged.replace(/\n\n+/g, '</p><p>').replace(/\n/g, '<br/>') + '</p>';
        }

        // ── 辅助：执行插入并等待完成 ──────────────────────────────────────
        function doInsert(blockSel, blockDisp, blocks) {
            var newClientIds = blocks.map(function(b) { return b.clientId; });

            if (replaceMode) {
                blockDisp.resetBlocks(blocks);
            } else {
                var cur = blockSel.getBlocks();
                if (cur && cur.length > 0) {
                    var last = cur[cur.length - 1];
                    blockDisp.insertBlocks(blocks, undefined, last && last.clientId ? last.clientId : undefined);
                } else {
                    blockDisp.resetBlocks(blocks);
                }
            }

            // 等待这些块的 clientId 出现在编辑器中
            var retries = 0;
            var iv = setInterval(function() {
                retries++;
                var all = blockSel.getBlocks() || [];
                var ids = all.map(function(b) { return b.clientId; });
                if (newClientIds.every(function(id) { return ids.indexOf(id) >= 0; })) {
                    // 块成功确认：保存并提示成功
                    clearInterval(iv);
                    // 等 savePost 完成后再提示（确保内容真正持久化）
                    var saveDone = function() {
                        setGlobalResult({ type: 'ok', text: '✅ 已写入编辑器！' });
                    };
                    try {
                        var saveResult = wp.data.dispatch('core/editor').savePost();
                        if (saveResult && typeof saveResult.then === 'function') {
                            saveResult.then(saveDone).catch(function() { setGlobalResult({ type: 'ok', text: '✅ 已写入编辑器！' }); });
                        } else {
                            setTimeout(saveDone, 500); // fallback: 500ms后确认
                        }
                    } catch(e) {
                        setTimeout(saveDone, 500);
                    }
                } else if (retries > 15) {
                    // 超时未确认：提示失败，提供复制选项
                    clearInterval(iv);
                    setGlobalResult({ type: 'err', text: '❌ 编辑器写入超时（块插入未被确认），内容已复制到剪贴板，请手动粘贴' });
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(merged).catch(function() {});
                    }
                }
            }, 200);
        }

        // ── 获取 block editor store（兼容新旧版本） ──────────────────────────
        function getBlockStore() {
            // Gutenberg 5.8+ / WP 5.8+
            if (wp.data && wp.data.select && wp.data.select('core/block-editor')) {
                return {
                    sel: wp.data.select('core/block-editor'),
                    disp: wp.data.dispatch('core/block-editor'),
                    version: 'block-editor'
                };
            }
            // Gutenberg 5.0-5.7
            if (wp.data && wp.data.select && wp.data.select('core/editor')) {
                return {
                    sel: wp.data.select('core/editor'),
                    disp: wp.data.dispatch('core/editor'),
                    version: 'editor'
                };
            }
            return null;
        }

        var store = getBlockStore();
        if (!store) {
            // 没有 Gutenberg 环境，直接 editPost
            try {
                var ed = wp.data && wp.data.dispatch && wp.data.dispatch('core/editor');
                if (ed) {
                    ed.editPost({ content: merged });
                    setTimeout(function() {
                        try { wp.data.dispatch('core/editor').savePost(); } catch(e) {}
                        setGlobalResult({ type: 'ok', text: '✅ 已写入编辑器！' });
                    }, 400);
                } else {
                    setGlobalResult({ type: 'warn', text: '⚠️ 编辑器写入失败，请手动粘贴。' });
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(merged).catch(function() {});
                    }
                }
            } catch(e) {
                setGlobalResult({ type: 'warn', text: '⚠️ 编辑器写入失败。' });
            }
            return;
        }

        var newBlocks = null;
        var parseErr = null;

        // ── 方案A：wp.blockEditor.parse（Gutenberg 5.8+）──────────────────────
        if (!newBlocks && wp.blockEditor && wp.blockEditor.parse) {
            try {
                newBlocks = wp.blockEditor.parse(merged);
                console.log('[ZuoAI] wp.blockEditor.parse →', newBlocks ? newBlocks.length : 0, 'blocks');
            } catch(e) {
                parseErr = e;
            }
        }

        // ── 方案B：wp.blocks.parse（旧版 Gutenberg 5.0-5.7）─────────────────
        if (!newBlocks && wp.blocks && wp.blocks.parse) {
            try {
                newBlocks = wp.blocks.parse(merged);
                console.log('[ZuoAI] wp.blocks.parse →', newBlocks ? newBlocks.length : 0, 'blocks');
            } catch(e) {
                parseErr = e;
            }
        }

        // ── 方案C：手动 DOM 解析 + wp.blocks.createBlock（极旧版）────────────
        if (!newBlocks && wp.blocks && wp.blocks.createBlock) {
            try {
                var tmp = document.createElement('div');
                tmp.innerHTML = merged;
                newBlocks = [];
                var children = tmp.children;
                if (!children || children.length === 0) {
                    // 纯文本回退
                    var txt = (tmp.textContent || '').trim();
                    var paras = txt.split(/\n\n+/);
                    paras.forEach(function(p) {
                        p = p.trim();
                        if (!p) return;
                        if (/^#{1,3}\s/.test(p)) {
                            newBlocks.push(wp.blocks.createBlock('core/heading', {
                                content: p.replace(/^#+\s*/, ''),
                                level: Math.min(p.match(/^#+/)[0].length, 3)
                            }));
                        } else {
                            newBlocks.push(wp.blocks.createBlock('core/paragraph', { content: p }));
                        }
                    });
                } else {
                    for (var i = 0; i < children.length; i++) {
                        var el = children[i];
                        var tn = (el.tagName || '').toLowerCase();
                        var txt = (el.textContent || '').trim();
                        if (!txt) continue;
                        if (/^h[1-6]$/.test(tn)) {
                            newBlocks.push(wp.blocks.createBlock('core/heading', {
                                content: txt, level: Math.min(parseInt(tn.slice(1), 10), 3)
                            }));
                        } else if (tn === 'ul' || tn === 'ol') {
                            var items = [];
                            Array.prototype.forEach.call(el.children, function(li) {
                                items.push((li.textContent || '').trim());
                            });
                            if (items.length) {
                                newBlocks.push(wp.blocks.createBlock('core/list', {
                                    ordered: tn === 'ol', values: items
                                }));
                            }
                        } else if (tn === 'blockquote') {
                            newBlocks.push(wp.blocks.createBlock('core/quote', { content: txt }));
                        } else {
                            newBlocks.push(wp.blocks.createBlock('core/paragraph', { content: txt }));
                        }
                    }
                }
                console.log('[ZuoAI] manual DOM →', newBlocks.length, 'blocks');
            } catch(e) {
                parseErr = e;
            }
        }

        if (newBlocks && newBlocks.length > 0) {
            doInsert(store.sel, store.disp, newBlocks);
        } else {
            // 无法解析成块，最后回退到 editPost
            console.error('[ZuoAI] All parse methods failed:', parseErr ? parseErr.message : 'unknown');
            var ed = wp.data && wp.data.dispatch && wp.data.dispatch('core/editor');
            if (ed) {
                ed.editPost({ content: merged });
                setTimeout(function() {
                    try { wp.data.dispatch('core/editor').savePost(); } catch(e) {}
                    setGlobalResult({ type: 'ok', text: '✅ 已写入编辑器！' });
                }, 400);
            } else {
                setGlobalResult({ type: 'warn', text: '⚠️ 编辑器写入失败，请手动粘贴。' });
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(merged).catch(function() {});
                }
            }
        }
    }

var setGlobalResult = function(){};

    // ─── Design System ───────────────────────────────────────────────
    var C = {
        accent:        '#6366f1',
        accentHover:   '#4f46e5',
        accentLight:   '#eef2ff',
        green:         '#059669',
        greenLight:    '#d1fae5',
        orange:        '#d97706',
        orangeLight:   '#fef3c7',
        red:           '#dc2626',
        redLight:      '#fee2e2',
        purple:        '#7c3aed',
        purpleLight:   '#ede9fe',
        gray50:        '#f9fafb',
        gray100:       '#f3f4f6',
        gray200:       '#e5e7eb',
        gray300:       '#d1d5db',
        gray500:       '#6b7280',
        gray700:       '#374151',
        gray900:       '#111827',
        white:         '#ffffff',
        radius:        '8px',
        shadow:        '0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)',
        shadowMd:      '0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06)',
    };

    // Reusable merge-like shadow override not available in React; use explicit objects
    function shadow(s) { return s; }

    var wrapStyle = {
        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        fontSize: '13px',
        color: C.gray700,
        padding: '0',
        background: C.gray50,
    };

    var headerStyle = {
        background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)',
        borderRadius: C.radius + ' ' + C.radius + ' 0 0',
        padding: '16px 16px 14px',
        margin: '0 -1px',
    };

    var headerTitleStyle = {
        color: C.white,
        fontSize: '15px',
        fontWeight: '700',
        margin: '0 0 2px',
        letterSpacing: '0.3px',
    };

    var headerSubStyle = {
        color: 'rgba(255,255,255,0.75)',
        fontSize: '11px',
        margin: '0',
    };

    var bodyStyle = {
        padding: '12px',
        background: C.gray50,
    };

    function cardStyle() {
        return {
            background: C.white,
            borderRadius: C.radius,
            border: '1px solid ' + C.gray200,
            boxShadow: C.shadow,
            marginBottom: '10px',
            overflow: 'hidden',
        };
    }

    function cardHeadStyle(accent) {
        return {
            padding: '10px 14px',
            borderBottom: '1px solid ' + C.gray100,
            background: accent ? C.accentLight : C.gray100,
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
        };
    }

    var cardHeadLabelStyle = {
        fontSize: '12px',
        fontWeight: '600',
        color: C.gray900,
        margin: '0',
    };

    var cardBodyStyle = {
        padding: '12px 14px',
    };

    function sectionLabelStyle(icon) {
        return {
            fontSize: '11px',
            fontWeight: '600',
            color: C.gray500,
            textTransform: 'uppercase',
            letterSpacing: '0.5px',
            margin: '0 0 8px',
            display: 'flex',
            alignItems: 'center',
            gap: '4px',
        };
    }

    function btnBase(background, hover) {
        return {
            background: background,
            color: C.white,
            border: 'none',
            borderRadius: '6px',
            padding: '7px 12px',
            cursor: 'pointer',
            fontSize: '12px',
            fontWeight: '500',
            display: 'inline-flex',
            alignItems: 'center',
            gap: '5px',
            transition: 'all 0.15s ease',
            boxShadow: '0 1px 2px rgba(0,0,0,0.1)',
            marginRight: '6px',
            marginTop: '4px',
            lineHeight: '1.4',
        };
    }

    var btnAccent   = btnBase(C.accent,   C.accentHover);
    var btnGreen    = btnBase(C.green,    '#047857');
    var btnOrange   = btnBase(C.orange,   '#b45309');
    var btnPurple   = btnBase(C.purple,   '#6d28d9');
    var btnGray     = btnBase(C.gray500,  C.gray700);

    function btnHover(btn, hoverBg) {
        return Object.assign({}, btn, { background: hoverBg });
    }

    var selectStyle = {
        width: '100%',
        padding: '7px 10px',
        borderRadius: '6px',
        border: '1px solid ' + C.gray300,
        fontSize: '12px',
        color: C.gray700,
        background: C.white,
        marginBottom: '8px',
        outline: 'none',
        boxSizing: 'border-box',
    };

    var inputStyle = {
        width: '100%',
        padding: '7px 10px',
        borderRadius: '6px',
        border: '1px solid ' + C.gray300,
        fontSize: '12px',
        color: C.gray700,
        background: C.white,
        outline: 'none',
        boxSizing: 'border-box',
        marginBottom: '8px',
    };

    var resultBoxStyle = {
        marginTop: '10px',
        padding: '10px 12px',
        borderRadius: '8px',
        fontSize: '12px',
        lineHeight: '1.6',
        whiteSpace: 'pre-wrap',
        wordBreak: 'break-word',
        maxHeight: '220px',
        overflowY: 'auto',
    };

    function resultBox(type) {
        if (type === 'ok')   return Object.assign({}, resultBoxStyle, { background: C.greenLight, color: C.green, borderLeft: '3px solid ' + C.green });
        if (type === 'warn') return Object.assign({}, resultBoxStyle, { background: C.orangeLight, color: C.orange, borderLeft: '3px solid ' + C.orange });
        if (type === 'err')  return Object.assign({}, resultBoxStyle, { background: C.redLight, color: C.red, borderLeft: '3px solid ' + C.red });
        return Object.assign({}, resultBoxStyle, { background: C.accentLight, color: C.accent, borderLeft: '3px solid ' + C.accent });
    }

    var loadingStyle = {
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        padding: '10px 0',
        color: C.gray500,
        fontSize: '12px',
    };

    var spinnerStyle = {
        width: '16px',
        height: '16px',
        border: '2px solid ' + C.gray200,
        borderTopColor: C.accent,
        borderRadius: '50%',
        animation: 'aiPlusSpin 0.7s linear infinite',
        flexShrink: '0',
    };

    var dividerStyle = {
        border: 'none',
        borderTop: '1px solid ' + C.gray200,
        margin: '10px 0',
    };

    // ─── Inject keyframes ────────────────────────────────────────────
    var styleEl = document.createElement('style');
    styleEl.textContent = [
        '@keyframes aiPlusSpin { to { transform: rotate(360deg); } }',
        '@keyframes aiPlusPulse { 0%,100% { opacity:1; } 50% { opacity:0.5; } }',
        '.ai-plus-btn:hover { filter: brightness(0.92); transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,0.15) !important; }',
        '.ai-plus-btn:active { transform: translateY(0); filter: brightness(0.95); }',
    ].join('\n');
    document.head.appendChild(styleEl);

    // ─── Loading Spinner ─────────────────────────────────────────────
    function Spinner() {
        return wp.element.createElement('div', { style: spinnerStyle });
    }

    // ─── Main Panel Component ─────────────────────────────────────────
    // 保存原文用于翻译（避免重新翻译时带入编辑器中的旧译文）
    var originalContentForTranslate = '';

    var AISidebarPanel = function () {
        var _useState = useState('');
        var result = _useState[0];
        setGlobalResult = _useState[1];
        var _useState2 = useState(false);
        var loading = _useState2[0];
        var setLoading = _useState2[1];
        var _useState3 = useState(window.aiPlusConfig.defaultModel && modelLabels[window.aiPlusConfig.defaultModel] ? window.aiPlusConfig.defaultModel : (Object.keys(modelLabels)[0] || 'minimax'));
        var model = _useState3[0];
        var setModel = _useState3[1];
        var _useState4 = useState('');
        var extraPrompt = _useState4[0];
        var setExtraPrompt = _useState4[1];
        var _useState5 = useState('auto');
        var translateSource = _useState5[0];
        var setTranslateSource = _useState5[1];
        var _useState6 = useState('zh');
        var translateTarget = _useState6[0];
        var setTranslateTarget = _useState6[1];
        var _useState7 = useState('professional');
        var writeStyle = _useState7[0];
        var setWriteStyle = _useState7[1];
        var _useState8 = useState('balanced');
        var writeTone = _useState8[0];
        var setWriteTone = _useState8[1];

        // ── Actions ───────────────────────────────────────────────────
        function doAction(action, payload, onSuccess, onError) {
            var postId = 0, title = '';
            var content = getCurrentContent();
            try {
                postId = wp.data.select('core/editor').getEditedPostAttribute('id') || 0;
                title  = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
            } catch (e) {}

            if (!title && !content && action !== 'generate') {
                setGlobalResult({ type: 'warn', text: '⚠️ 请先输入文章标题或内容' });
                return;
            }

            setLoading(true);
            setGlobalResult({ type: 'info', text: '' });

            var apiContent = content;
            // ── Translate: use /translate endpoint with correct param names ──
            if (action === 'translate') {
                var src = (payload && payload.source_lang) ? payload.source_lang : translateSource;
                var tgt = (payload && payload.target_lang) ? payload.target_lang : translateTarget;
                // 优先使用 handleTranslate 传入的原文（避免带入编辑器中的旧译文）
                var translateContent = (payload && payload._content_for_translate) ? payload._content_for_translate : content;
                apiRequest('translate', {
                    model: model,
                    content: translateContent,
                    source_lang: src,
                    target_lang: tgt
                }).then(function(r) {
                    setLoading(false);
                    if (r.error) { setGlobalResult({ type: 'err', text: '❌ ' + r.error }); if (onError) onError(r.error); return; }
                    setGlobalResult({ type: 'ok', text: '✅ 完成' });
                    if (onSuccess) onSuccess(r, postId);
                }).catch(function(e) { 
                setLoading(false); 
                var errorMsg = e.message || '未知错误';
                // 超时错误特殊处理
                if (errorMsg.indexOf('timeout') !== -1 || errorMsg.indexOf('超时') !== -1) {
                    setGlobalResult({ 
                        type: 'err', 
                        text: '⏱️ 请求超时（' + errorMsg + '）\n\n建议：\n1. 切换到响应更快的模型（智谱/通义）\n2. 减少生成内容长度\n3. 检查网络连接后重试' 
                    });
                } else {
                    setGlobalResult({ type: 'err', text: '❌ ' + errorMsg }); 
                }
                if (onError) onError(errorMsg);
            });
                return;
            }
            // ── End translate ──

            if (action === 'expand') {
                try {
                    var sel = wp.data.select('core/editor').getEditorSelection();
                    if (sel && sel.length > 0) apiContent = sel;
                } catch (e) {}
            }

            // Build effective extra_prompt with style/tone context
            var effectiveExtra = extraPrompt || '';
            if ((action === 'generate' || action === 'rewrite' || action === 'expand') && (writeStyle !== 'default' || writeTone !== 'default')) {
                var styleMap = {
                    'default': '', 'professional': '专业严谨', 'casual': '轻松随意',
                    'friendly': '亲切友好', 'technical': '技术详尽', 'marketing': '营销有感染力'
                };
                var toneMap = {
                    'default': '', 'formal': '正式书面', 'conversational': '口语化自然',
                    'balanced': '不偏不倚', 'passionate': '富有激情', 'calm': '冷静理性'
                };
                var styleStr = styleMap[writeStyle] || '';
                var toneStr = toneMap[writeTone] || '';
                var stylePart = (styleStr && toneStr) ? ('写作风格：' + styleStr + '；语气：' + toneStr) : (styleStr || toneStr || '');
                effectiveExtra = effectiveExtra ? (stylePart + '。' + effectiveExtra) : stylePart;
            }
            apiRequest('generate', Object.assign({
                model: model, action: action,
                content: title || apiContent,
                extra_prompt: effectiveExtra, post_id: postId
            }, payload || {}))
            .then(function(r) {
                setLoading(false);
                if (r.error) {
                    setGlobalResult({ type: 'err', text: '❌ ' + r.error });
                    if (onError) onError(r.error);
                    return;
                }
                setGlobalResult({ type: 'ok', text: '✅ 完成' });
                if (onSuccess) onSuccess(r, postId);
            })
            .catch(function(e) {
                setLoading(false);
                setGlobalResult({ type: 'err', text: '❌ 网络错误：' + e.message });
            });
        }

        function handleGenerate() {
            doAction('generate', {}, function(r) {
                if (r.content) insertContent(0, r.content);
            });
        }

        function handleExpand() {
            doAction('expand', {}, function(r) {
                // expand 续写 = 替换全文（AI 返回的是续写后的完整内容）
                if (r.content) insertContent(0, r.content, true);
            });
        }

        function handleRewrite() {
            var content = getCurrentContent();
            if (!content) { setGlobalResult({ type: 'warn', text: '⚠️ 请先输入文章内容再改写' }); return; }
            doAction('rewrite', {}, function(r) {
                // rewrite 和 expand 必须用 replaceMode=true（替换全文），不能用追加
                if (r.content) insertContent(0, r.content, true);
            });
        }

        function handleSummarize() {
            var postId = 0;
            try { postId = wp.data.select('core/editor').getEditedPostAttribute('id') || 0; } catch (e) {}
            if (!postId) { setGlobalResult({ type: 'warn', text: '⚠️ 请先保存文章后再提取摘要' }); return; }
            doAction('summarize', {}, function(r) {
                if (r.content) {
                    // 写入 Gutenberg 摘要字段 + 右侧面板显示
                    try {
                        wp.data.dispatch('core/editor').editPost({ excerpt: r.content });
                    } catch(e) {}
                    setGlobalResult({ type: 'ok', text: '✅ 摘要已写入右侧「摘要」面板：\n' + r.content });
                } else {
                    setGlobalResult({ type: 'warn', text: '⚠️ 未提取到摘要，请重试' });
                }
            });
        }

function handleKeyword() {
            var postId = 0;
            try { postId = wp.data.select('core/editor').getEditedPostAttribute('id') || 0; } catch (e) {}
            var content = getCurrentContent();
            if (!postId) { setGlobalResult({ type: 'warn', text: '请先保存文章后再提取标签' }); return; }
            if (!content) { setGlobalResult({ type: 'warn', text: '请先输入文章内容' }); return; }
            doAction('keyword', {}, function(r) {
                var tagStr = (r.content || '').trim();
                if (!tagStr) { setGlobalResult({ type: 'warn', text: '未提取到标签' }); return; }
                var tagArray = tagStr.split(/[,，、]/).map(function(t){return t.trim();}).filter(Boolean);
                fetch(window.aiPlusConfig.apiUrl + 'tags-save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.aiPlusConfig.nonce },
                    body: JSON.stringify({ post_id: postId, tags: tagStr })
                }).then(function(resp){ return resp.json().then(function(d){ return { ok: resp.ok, data: d }; }); })
                .then(function(resp) {
                    if (resp.ok && resp.data.success) {
                        // 优先使用后端过滤后的标签名（与实际写入的一致）
                        var savedTagStr = (resp.data.tag_names && resp.data.tag_names.length > 0)
                            ? resp.data.tag_names.join('，')
                            : tagStr;
                        // 使用接口返回的 term_ids（Gutenberg 需要 ID，而非标签名）
                        if (resp.data.tag_ids && resp.data.tag_ids.length > 0) {
                            try {
                                // 强制刷新 Gutenberg 标签面板
                                wp.data.dispatch('core/editor').editPost({ tags: resp.data.tag_ids });
                                // 强制触发标签 meta box 刷新
                                setTimeout(function() {
                                    var tagInput = document.getElementById('tags-input') || 
                                                   document.querySelector('.tags-input') ||
                                                   document.querySelector('[name="tax_input[post_tag]"]');
                                    if (tagInput) {
                                        tagInput.value = resp.data.tag_ids.join(',');
                                        // 触发 change 事件让 WordPress 重新加载标签
                                        tagInput.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                    // 刷新 Gutenberg 数据
                                    wp.data.dispatch('core/editor').savePost();
                                }, 100);
                                console.log('[ZuoAI] Tags set in Gutenberg:', resp.data.tag_ids);
                            } catch(e) {
                                console.error('[ZuoAI] editPost tags failed:', e);
                            }
                        }
                        setGlobalResult({ type: 'ok', text: '✅ 标签已写入（' + resp.data.tag_ids.length + '个）：' + savedTagStr });
                    } else {
                        setGlobalResult({ type: 'warn', text: '标签已生成：' + tagStr + ' (保存失败：' + (resp.data.error || '未知原因') + ')' });
                    }
                })
                .catch(function() {
                    setGlobalResult({ type: 'warn', text: '标签已生成：' + tagStr + ' (网络错误)' });
                });
            });
        }
        function handleTranslate() {
            var content = getCurrentContent();
            if (!content) { setGlobalResult({ type: 'warn', text: '⚠️ 请先输入文章内容' }); return; }

            // 首次翻译：保存原文到模块变量；后续翻译：优先用已保存的原文
            if (!originalContentForTranslate) {
                originalContentForTranslate = content;
            } else {
                // 用户已翻译过一次，用保存的原文重新翻译（避免带入编辑器中的旧译文）
                content = originalContentForTranslate;
            }

            doAction('translate', {
                source_lang: translateSource, target_lang: translateTarget,
                _content_for_translate: content
            }, function(r) {
                if (r.content) {
                    insertContent(0, r.content, true); // replace mode：覆盖编辑器内容
                }
            });
        }

        function handleSlug() {
            var title = '';
            try { title = wp.data.select('core/editor').getEditedPostAttribute('title') || ''; } catch (e) {}
            if (!title) { setGlobalResult({ type: 'warn', text: '⚠️ 请先输入文章标题' }); return; }
            doAction('slug', { title: title }, function(r) {
                var slug = (r.content || '').trim();
                if (slug) {
                    wp.data.dispatch('core/editor').editPost({ slug: slug });
                    setGlobalResult({ type: 'ok', text: '✅ 别名已设置：' + slug });
                }
            });
        }

        function handleTitleOptimize() {
            var title = '';
            try { title = wp.data.select('core/editor').getEditedPostAttribute('title') || ''; } catch (e) {}
            if (!title) { setGlobalResult({ type: 'warn', text: '⚠️ 请先在左侧输入文章标题' }); return; }
            setLoading(true);
            setGlobalResult({ type: 'info', text: '' });
            apiRequest('generate', {
                model: model, action: 'title_optimize', content: title
            }).then(function(r) {
                setLoading(false);
                if (r.error) { setGlobalResult({ type: 'err', text: '❌ 优化失败：' + r.error }); return; }
                var newTitle = (r.content || '').trim();
                if (!newTitle) { setGlobalResult({ type: 'err', text: '❌ 未返回结果，请重试' }); return; }
                wp.data.dispatch('core/editor').editPost({ title: newTitle });
                setGlobalResult({ type: 'ok', text: '🎉 标题已优化！\n\n新标题：' + newTitle });
            }).catch(function(e) {
                setLoading(false);
                setGlobalResult({ type: 'err', text: '❌ 网络错误：' + e.message });
            });
        }

        function handleFeaturedImage() {
            var postId = 0;
            try { postId = wp.data.select('core/editor').getEditedPostAttribute('id') || 0; } catch (e) {}
            var title = '', content = getCurrentContent();
            try { title = wp.data.select('core/editor').getEditedPostAttribute('title') || ''; } catch (e) {}
            if (!title && !content) {
                setGlobalResult({ type: 'warn', text: '⚠️ 请先输入文章标题或内容' }); return;
            }
            setLoading(true);
            setGlobalResult({ type: 'info', text: '正在生成图片提示词…' });
            var fullText = '文章标题：' + title + '\n\n文章内容：\n' + content;
            apiRequest('generate', {
                model: model, action: 'featured_image',
                content: fullText, extra_prompt: extraPrompt
            }).then(function(r) {
                if (r.error) { setLoading(false); setGlobalResult({ type: 'err', text: '❌ ' + r.error }); return; }
                var prompt = r.image_prompt || r.content || '';
                var imageUrl = r.url || '';
                if (!imageUrl) {
                    setGlobalResult({ type: 'warn', text: '📷 图片提示词已生成，正在设置特色图…\n\n' + prompt });
                } else {
                    setGlobalResult({ type: 'info', text: '🖼️ AI图片已生成，正在设置…' });
                }
                return fetch(window.aiPlusConfig.apiUrl + 'featured-image-set', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.aiPlusConfig.nonce },
                    body: JSON.stringify({
                        post_id: postId, image_url: imageUrl,
                        post_title: title, image_prompt: prompt,
                        // 优先使用中文替代文本，不再使用英文 prompt
                        alt_text: (r.chinese_alt || r.chinese_desc || ''),
                        chinese_desc: (r.chinese_desc || ''),
                        chinese_alt:  (r.chinese_alt || ''),
                    })
                }).then(function(resp) { return resp.json(); })
                .then(function(data) {
                    setLoading(false);
                    if (data.error) {
                        setGlobalResult({ type: 'warn', text: '⚠️ 特色图设置失败\n\n提示词：' + prompt });
                    } else if (data.attachment_id) {
                        try { wp.data.dispatch('core/editor').editPost({ featured_media: parseInt(data.attachment_id) }); } catch(e) {}
                        var metaInfo = (data.title ?       ('📝 标题：'       + data.title + '\n') : '') +
                                       (data.description ? ('📝 说明文字：' + data.description + '\n') : '') +
                                       (data.alt ?        ('📝 替代文本：'  + data.alt) : '');
                        setGlobalResult({ type: 'ok', text: '✅ 特色图已设置！\n\n' + metaInfo + (imageUrl ? ('\n🔗 ' + imageUrl) : '') });
                    }
                });
            }).catch(function(e) {
                setLoading(false);
                setGlobalResult({ type: 'err', text: '❌ 请求失败：' + e.message });
            });
        }

        // ── Result Box ────────────────────────────────────────────────
        var resultType = typeof result === 'object' ? (result || {}).type : 'info';
        var resultText = typeof result === 'object' ? (result || {}).text : result;
        var showResult = resultText && resultText.length > 0;
        var resultEl = showResult
            ? wp.element.createElement('div', { style: resultBox(resultType) }, resultText)
            : null;

        // ── Loading Overlay ───────────────────────────────────────────
        var loadingEl = loading
            ? wp.element.createElement('div', { style: loadingStyle },
                Spinner(),
                wp.element.createElement('span', null, 'AI 处理中，请稍候…')
            )
            : null;

        // ── Model Select ─────────────────────────────────────────────
        var modelOptions = Object.keys(modelLabels).map(function(k) {
            return wp.element.createElement('option', { key: k, value: k }, modelLabels[k]);
        });
        var modelSelect = Object.keys(modelLabels).length > 0
            ? wp.element.createElement('select', {
                value: model,
                onChange: function(e) { setModel(e.target.value); },
                style: selectStyle,
              }, modelOptions)
            : wp.element.createElement('div', { style: { color: C.red, fontSize: '12px', padding: '8px 0' } },
                '⚠️ 未配置任何 AI 模型，请先去设置里配置 API Key'
              );

        // ─── Render ────────────────────────────────────────────────────
        return wp.element.createElement(
            PluginDocumentSettingPanel,
            { title: 'AI Plus', icon: 'dashicons-visibility' },

            wp.element.createElement('div', { style: wrapStyle },

                // ══ Header ════════════════════════════════════════════
                wp.element.createElement('div', { style: headerStyle },
                    wp.element.createElement('p', { style: headerTitleStyle }, '🤖 AI Plus 助手'),
                    wp.element.createElement('p', { style: headerSubStyle }, '文章生成 · SEO优化 · 翻译配图')
                ),

                // ══ Body ═══════════════════════════════════════════════
                wp.element.createElement('div', { style: bodyStyle },

                    // ── 模型选择 ──────────────────────────────────────
                    wp.element.createElement('div', { style: cardStyle() },
                        wp.element.createElement('div', { style: cardHeadStyle(false) },
                            wp.element.createElement('span', { style: { fontSize: '13px' } }, '⚙️'),
                            wp.element.createElement('p', { style: cardHeadLabelStyle }, '模型配置')
                        ),
                        wp.element.createElement('div', { style: cardBodyStyle },
                            wp.element.createElement('p', { style: sectionLabelStyle() }, '选择 AI 模型'),
                            modelSelect,
                            wp.element.createElement('input', {
                                type: 'text', value: extraPrompt,
                                onChange: function(e) { setExtraPrompt(e.target.value); },
                                placeholder: '💬 附加要求（可选），如：语言风格、字数、重点强调…',
                                style: inputStyle,
                            })
                        )
                    ),

                    // ── SEO 标题优化（突出卡片） ────────────────────────
                    wp.element.createElement('div', { style: Object.assign({}, cardStyle(), { border: '1px solid #c7d2fe', boxShadow: '0 2px 8px rgba(99,102,241,0.15)' }) },
                        wp.element.createElement('div', { style: cardHeadStyle(true) },
                            wp.element.createElement('span', { style: { fontSize: '14px' } }, '🎯'),
                            wp.element.createElement('p', { style: Object.assign({}, cardHeadLabelStyle, { color: C.accent }) }, 'SEO 标题优化（推荐）')
                        ),
                        wp.element.createElement('div', { style: cardBodyStyle },
                            wp.element.createElement('p', { style: { fontSize: '11px', color: C.gray500, margin: '0 0 10px', lineHeight: '1.5' } },
                                '输入标题后点击按钮，AI 自动生成 SEO 友好、符合搜索意图的最佳标题（30-60字）'
                            ),
                            wp.element.createElement('button', {
                                className: 'ai-plus-btn',
                                style: btnGreen,
                                onClick: handleTitleOptimize,
                                disabled: loading,
                            }, '🎯 优化标题'),
                            resultType === 'ok' && resultText && resultText.indexOf('标题已优化') !== -1
                                ? wp.element.createElement('div', { style: resultBox('ok'), dangerouslySetInnerHTML: { __html: resultText.replace(/\n/g, '<br>') } })
                                : null
                        )
                    ),

                    // ── 文章生成 ──────────────────────────────────────
                    wp.element.createElement('div', { style: cardStyle() },
                        wp.element.createElement('div', { style: cardHeadStyle(false) },
                            wp.element.createElement('span', { style: { fontSize: '13px' } }, '✏️'),
                            wp.element.createElement('p', { style: cardHeadLabelStyle }, '文章生成')
                        ),
                        wp.element.createElement('div', { style: cardBodyStyle },
                            // 风格选择
                            wp.element.createElement('div', { style: { display: 'flex', gap: '6px', marginBottom: '8px' } },
                                wp.element.createElement('select', {
                                    value: writeStyle,
                                    onChange: function(e) { setWriteStyle(e.target.value); },
                                    style: Object.assign({}, selectStyle, { flex: 1, marginBottom: 0 }),
                                },
                                    wp.element.createElement('option', { value: 'default' }, '默认'),
                                    wp.element.createElement('option', { value: 'professional' }, '专业严谨'),
                                    wp.element.createElement('option', { value: 'casual' }, '轻松随意'),
                                    wp.element.createElement('option', { value: 'friendly' }, '亲切友好'),
                                    wp.element.createElement('option', { value: 'technical' }, '技术详尽'),
                                    wp.element.createElement('option', { value: 'marketing' }, '营销有感染力'),
                                ),
                                wp.element.createElement('select', {
                                    value: writeTone,
                                    onChange: function(e) { setWriteTone(e.target.value); },
                                    style: Object.assign({}, selectStyle, { flex: 1, marginBottom: 0 }),
                                },
                                    wp.element.createElement('option', { value: 'default' }, '默认语气'),
                                    wp.element.createElement('option', { value: 'formal' }, '正式书面'),
                                    wp.element.createElement('option', { value: 'conversational' }, '口语化'),
                                    wp.element.createElement('option', { value: 'balanced' }, '不偏不倚'),
                                    wp.element.createElement('option', { value: 'passionate' }, '富有激情'),
                                ),
                            ),
                            wp.element.createElement('button', {
                                className: 'ai-plus-btn', style: btnAccent,
                                onClick: handleGenerate, disabled: loading,
                            }, '✏️ 生成文章'),
                            wp.element.createElement('button', {
                                className: 'ai-plus-btn', style: btnPurple,
                                onClick: handleRewrite, disabled: loading,
                            }, '🔄 改写内容'),
                            wp.element.createElement('button', {
                                className: 'ai-plus-btn', style: Object.assign({}, btnGray, { marginRight: 0 }),
                                onClick: handleExpand, disabled: loading,
                            }, '📝 续写内容')
                        )
                    ),


                    // ── 内容处理 ──────────────────────────────────────
                    wp.element.createElement('div', { style: cardStyle() },
                        wp.element.createElement('div', { style: cardHeadStyle(false) },
                            wp.element.createElement('span', { style: { fontSize: '13px' } }, '📋'),
                            wp.element.createElement('p', { style: cardHeadLabelStyle }, '内容处理')
                        ),
                        wp.element.createElement('div', { style: cardBodyStyle },
                            wp.element.createElement('button', {
                                className: 'ai-plus-btn', style: btnOrange,
                                onClick: handleSummarize, disabled: loading,
                            }, '📋 生成摘要'),
                            wp.element.createElement('button', {
                                className: 'ai-plus-btn', style: btnGray,
                                onClick: handleKeyword, disabled: loading,
                            }, '🏷️ 提取标签')
                        )
                    ),

                    // ── 翻译 ──────────────────────────────────────────
                    wp.element.createElement('div', { style: cardStyle() },
                        wp.element.createElement('div', { style: cardHeadStyle(false) },
                            wp.element.createElement('span', { style: { fontSize: '13px' } }, '🌐'),
                            wp.element.createElement('p', { style: cardHeadLabelStyle }, '翻译')
                        ),
                        wp.element.createElement('div', { style: cardBodyStyle },
                            wp.element.createElement('div', { style: { display: 'flex', gap: '6px', marginBottom: '8px' } },
                                wp.element.createElement('select', {
                                    value: translateSource,
                                    onChange: function(e) { setTranslateSource(e.target.value); },
                                    style: Object.assign({}, selectStyle, { flex: 1, marginBottom: 0 }),
                                },
                                    wp.element.createElement('option', { value: 'auto' }, '自动检测'),
                                    wp.element.createElement('option', { value: 'en' }, 'English'),
                                    wp.element.createElement('option', { value: 'zh' }, '中文'),
                                    wp.element.createElement('option', { value: 'zt' }, '繁体中文'),
                                    wp.element.createElement('option', { value: 'ja' }, '日本語'),
                                    wp.element.createElement('option', { value: 'ko' }, '한국어'),
                                    wp.element.createElement('option', { value: 'fr' }, 'Français'),
                                    wp.element.createElement('option', { value: 'de' }, 'Deutsch'),
                                    wp.element.createElement('option', { value: 'es' }, 'Español'),
                                ),
                                wp.element.createElement('span', { style: { color: C.gray500, fontSize: '12px', lineHeight: '2', flexShrink: 0 } }, '→'),
                                wp.element.createElement('select', {
                                    value: translateTarget,
                                    onChange: function(e) { setTranslateTarget(e.target.value); },
                                    style: Object.assign({}, selectStyle, { flex: 1, marginBottom: 0 }),
                                },
                                    wp.element.createElement('option', { value: 'zh' }, '中文'),
                                    wp.element.createElement('option', { value: 'en' }, 'English'),
                                    wp.element.createElement('option', { value: 'zt' }, '繁体中文'),
                                    wp.element.createElement('option', { value: 'ja' }, '日本語'),
                                    wp.element.createElement('option', { value: 'ko' }, '한국어'),
                                    wp.element.createElement('option', { value: 'fr' }, 'Français'),
                                    wp.element.createElement('option', { value: 'de' }, 'Deutsch'),
                                    wp.element.createElement('option', { value: 'es' }, 'Español'),
                                )
                            ),
                            wp.element.createElement('button', {
                                className: 'ai-plus-btn',
                                style: Object.assign({}, btnAccent, { width: '100%', justifyContent: 'center' }),
                                onClick: handleTranslate, disabled: loading,
                            }, '🌐 翻译并替换')
                        )
                    ),

                    // ── 其他工具 ─────────────────────────────────────
                    wp.element.createElement('div', { style: cardStyle() },
                        wp.element.createElement('div', { style: cardHeadStyle(false) },
                            wp.element.createElement('span', { style: { fontSize: '13px' } }, '🔧'),
                            wp.element.createElement('p', { style: cardHeadLabelStyle }, '其他工具')
                        ),
                        wp.element.createElement('div', { style: cardBodyStyle },
                            wp.element.createElement('button', {
                                className: 'ai-plus-btn', style: btnGray,
                                onClick: handleSlug, disabled: loading,
                            }, '🔗 生成别名'),
                            wp.element.createElement('button', {
                                className: 'ai-plus-btn', style: btnPurple,
                                onClick: handleFeaturedImage, disabled: loading,
                            }, '🖼️ 生成特色图')
                        )
                    ),

                    // ── 状态区 ────────────────────────────────────────
                    loadingEl,
                    resultEl

                ) // end body
            ) // end wrapStyle
        );
    };

    registerPlugin('ai-plus-sidebar', { render: AISidebarPanel });
})(window.wp);
