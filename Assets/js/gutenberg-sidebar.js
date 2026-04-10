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
        var savedModel = (apiKeys[k] && apiKeys[k].model) ? apiKeys[k].model : (m.default || '');
        return (m.name || k) + (savedModel ? (' — ' + savedModel) : '');
    }

    var modelLabels = {};
    Object.keys(modelDefs).forEach(function(k) {
        if (apiKeys[k] && apiKeys[k].api_key) {
            modelLabels[k] = getModelLabel(k);
        }
    });

    function apiRequest(endpoint, data) {
        return fetch(window.aiPlusConfig.apiUrl + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.aiPlusConfig.nonce
            },
            body: JSON.stringify(data)
        }).then(function (r) { return r.json(); });
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

    function insertContent(postId, newContent) {
        var current = getCurrentContent();
        var merged = current.trim() ? (current + '\n\n' + newContent) : newContent;
        savePostContent(postId, merged)
            .then(function(resp) {
                if (resp.html && wp.blocks && wp.blocks.parse) {
                    var b = wp.blocks.parse(resp.html);
                    if (b.length > 0) {
                        var existingBlocks = wp.data.select('core/editor').getBlocks();
                        if (existingBlocks.length > 0) {
                            wp.data.dispatch('core/editor').replaceBlocks(
                                existingBlocks.map(function(blk) { return blk.clientId; }), b
                            );
                        } else {
                            wp.data.dispatch('core/editor').insertBlocks(b);
                        }
                        setGlobalResult({ type: 'ok', text: '✅ 已写入编辑器！' });
                        return;
                    }
                }
                if (resp.html) {
                    wp.data.dispatch('core/editor').editPost({ content: resp.html });
                }
                setGlobalResult({ type: 'ok', text: '✅ 已保存！' });
            })
            .catch(function(e) {
                wp.data.dispatch('core/editor').editPost({ content: merged });
                setGlobalResult({ type: 'warn', text: '⚠️ 已保存（编辑器更新失败）' });
            });
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
    var AISidebarPanel = function () {
        var _useState = useState('');
        var result = _useState[0];
        setGlobalResult = _useState[1];
        var _useState2 = useState(false);
        var loading = _useState2[0];
        var setLoading = _useState2[1];
        var _useState3 = useState(Object.keys(modelLabels)[0] || 'minimax');
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
            if (action === 'expand') {
                try {
                    var sel = wp.data.select('core/editor').getEditorSelection();
                    if (sel && sel.length > 0) apiContent = sel;
                } catch (e) {}
            }

            apiRequest('generate', Object.assign({
                model: model, action: action,
                content: title || apiContent,
                extra_prompt: extraPrompt, post_id: postId
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
                if (r.content) insertContent(0, r.content);
            });
        }

        function handleSummarize() {
            doAction('summarize', {}, function(r) {
                if (r.content) insertContent(0, r.content);
            });
        }

        function handleKeyword() {
            var postId = 0;
            try { postId = wp.data.select('core/editor').getEditedPostAttribute('id') || 0; } catch (e) {}
            var content = getCurrentContent();
            if (!postId) { setGlobalResult({ type: 'warn', text: '⚠️ 请先保存文章后再提取标签' }); return; }
            if (!content) { setGlobalResult({ type: 'warn', text: '⚠️ 请先输入文章内容' }); return; }
            doAction('keyword', {}, function(r) {
                var tagStr = (r.content || '').trim();
                if (tagStr) {
                    fetch(window.aiPlusConfig.apiUrl + 'tags-save', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.aiPlusConfig.nonce },
                        body: JSON.stringify({ post_id: postId, tags: tagStr })
                    }).then(function(resp) { return resp.json(); })
                    .then(function(data) {
                        if (data.ok) setGlobalResult({ type: 'ok', text: '✅ 标签已提取并保存：' + tagStr });
                        else setGlobalResult({ type: 'warn', text: '⚠️ 标签已生成：' + tagStr + '\n（保存失败）' });
                    })
                    .catch(function() {
                        setGlobalResult({ type: 'warn', text: '⚠️ 标签已生成：' + tagStr + '\n（保存失败）' });
                    });
                } else {
                    setGlobalResult({ type: 'warn', text: '⚠️ 未提取到标签' });
                }
            });
        }

        function handleTranslate() {
            doAction('translate', {
                source: translateSource, target: translateTarget
            }, function(r) {
                if (r.content) insertContent(0, r.content);
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
                    body: JSON.stringify({ post_id: postId, image_url: imageUrl })
                }).then(function(resp) { return resp.json(); })
                .then(function(data) {
                    setLoading(false);
                    if (data.error) {
                        setGlobalResult({ type: 'warn', text: '⚠️ 特色图设置失败\n\n提示词：' + prompt });
                    } else if (data.attachment_id) {
                        try { wp.data.dispatch('core/editor').editPost({ featured_media: parseInt(data.attachment_id) }); } catch(e) {}
                        setGlobalResult({ type: 'ok', text: '✅ 特色图已设置！\n\n' + (imageUrl ? imageUrl : prompt) });
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
                            wp.element.createElement('button', {
                                className: 'ai-plus-btn', style: btnAccent,
                                onClick: handleGenerate, disabled: loading,
                            }, '✏️ 生成文章'),
                            wp.element.createElement('button', {
                                className: 'ai-plus-btn', style: btnPurple,
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
    console.log('AI Plus Gutenberg sidebar registered (beautiful redesign)');
})(window.wp);
