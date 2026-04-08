/**
 * AI Plus Gutenberg Sidebar - Uses wp.ajax for saving (most reliable)
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

    // 从 PHP 传入的模型配置，格式：{ id: { name, default, url }, ... }
    var modelDefs = window.aiPlusConfig.models || {};
    var apiKeys   = window.aiPlusConfig.apiKeys || {};

    // 生成带模型名的标签，格式："智谱 GLM — glm-4-flashx"
    function getModelLabel(k) {
        var m = modelDefs[k] || {};
        var savedModel = (apiKeys[k] && apiKeys[k].model) ? apiKeys[k].model : (m.default || '');
        return (m.name || k) + (savedModel ? (' — ' + savedModel) : '');
    }
    // 只保留已配置 API Key 的模型（api_key 非空）
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

    // Use wp.ajax to save (includes correct auth cookie automatically)
    function savePostContent(postId, content) {
        return new Promise(function(resolve, reject) {
            wp.ajax.post('ai_plus_save_content', {
                post_id: postId,
                content: content,
                nonce: window.aiPlusConfig.nonce, _ajax_nonce: window.aiPlusConfig.nonce
            }).done(function(response) {
                console.log('[AI Plus] Save done:', response);
                resolve(response);
            }).fail(function(err) {
                console.error('[AI Plus] Save failed:', err);
                reject(err);
            });
        });
    }

    function getCurrentContent() {
        try {
            return wp.data.select('core/editor').getEditedPostContent() || '';
        } catch (e) {
            return '';
        }
    }

    function insertContent(postId, newContent) {
        var current = getCurrentContent();
        var merged = current.trim() ? (current + '\n\n' + newContent) : newContent;
        console.log('[AI Plus] insertContent, merged len:', merged.length);
        savePostContent(postId, merged)
            .then(function(resp) {
                console.log('[AI Plus] Save response:', Object.keys(resp));
                if (resp.html) {
                    if (wp.blocks && wp.blocks.parse) {
                        var b = wp.blocks.parse(resp.html);
                        console.log('[AI Plus] Parsed blocks:', b.length);
                        if (b.length > 0) {
                            // 替换所有现有块
                            var existingBlocks = wp.data.select('core/editor').getBlocks();
                            if (existingBlocks.length > 0) {
                                var clientIds = existingBlocks.map(function(blk) { return blk.clientId; });
                                wp.data.dispatch('core/editor').replaceBlocks(clientIds, b);
                            } else {
                                wp.data.dispatch('core/editor').insertBlocks(b);
                            }
                            setGlobalResult('✅ 已写入编辑器！（' + b.length + ' 个块）\n\n' + newContent.slice(0, 200) + '...');
                            return;
                        }
                    }
                    // 回退
                    wp.data.dispatch('core/editor').editPost({ content: resp.html });
                    setGlobalResult('✅ 已保存！（' + newContent.length + ' 字）\n\n（请查看编辑器或保存后刷新）');
                } else {
                    setGlobalResult('✅ 已保存，请刷新查看');
                }
            })
            .catch(function(e) {
                wp.data.dispatch('core/editor').editPost({ content: merged });
                setGlobalResult('⚠️ 已保存到数据库（编辑器更新失败: ' + e + '）');
            });
    }

    var setGlobalResult = function(){};

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

        var btnStyle = {
            background: '#2271b1', color: '#fff', border: 'none',
            padding: '6px 12px', cursor: 'pointer', borderRadius: '3px',
            fontSize: '13px', marginRight: '6px', marginTop: '4px',
            display: 'inline-block'
        };
        var sectionStyle = { marginBottom: '14px' };
        var labelStyle = { fontSize: '12px', color: '#555', display: 'block', marginBottom: '3px' };
        var resultStyle = {
            marginTop: '8px', padding: '8px', background: '#f0f6fc',
            borderLeft: '3px solid #2271b1', fontSize: '13px',
            maxHeight: '200px', overflowY: 'auto', whiteSpace: 'pre-wrap'
        };

        function doAction(action, payload, onSuccess) {
            var postId = 0;
            var title = '';
            var content = getCurrentContent();
            try {
                postId = wp.data.select('core/editor').getEditedPostAttribute('id') || 0;
                title = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
            } catch (e) {}

            if (!title && !content && action !== 'generate') {
                setGlobalResult('请先输入文章标题或内容');
                return;
            }

            setLoading(true);
            setGlobalResult('');

            // 续写发送选中文字，全文/摘要发送全文
            var apiContent = content;
            if (action === 'expand') {
                try {
                    var sel = wp.data.select('core/editor').getEditorSelection();
                    if (sel && sel.length > 0) {
                        apiContent = sel;
                    }
                } catch (e) {}
            }
            var data = Object.assign({
                model: model,
                action: action,
                content: title || apiContent,
                extra_prompt: extraPrompt,
                post_id: postId
            }, payload || {});

            apiRequest('generate', data)
                .then(function (r) {
                    setLoading(false);
                    console.log('[AI Plus] API response OK');
                    if (r.error) {
                        setGlobalResult('错误: ' + r.error);
                    } else if (r.content) {
                        // 优先用后端已转换的 HTML，写入 Gutenberg 编辑器
                        var html = r.html || r.content;
                        if (html && html !== r.content) {
                            // r.html 是后端已转好的 HTML，直接写入
                            wp.data.dispatch('core/editor').editPost({ content: html });
                            savePostContent(postId, html);
                            setGlobalResult('✅ 已写入编辑器！\n\n' + html.slice(0, 200) + '...');
                        } else {
                            // 只有原始 markdown，没转 HTML，走旧逻辑
                            onSuccess(html, postId, r);
                        }
                    } else if (r.slug) {
                        wp.data.dispatch('core/editor').editPost({ slug: r.slug });
                        setGlobalResult('✅ 别名已设置: ' + r.slug);
                    } else if (r.excerpt) {
                        wp.data.dispatch('core/editor').editPost({ excerpt: r.excerpt });
                        setGlobalResult('✅ 摘要已写入！');
                    } else if (r.tags && r.tags.length) {
                        setGlobalResult('✅ 提取到标签: ' + r.tags.join(', '));
                    } else {
                        setGlobalResult(JSON.stringify(r).slice(0, 200));
                    }
                })
                .catch(function (e) {
                    setLoading(false);
                    setGlobalResult('请求失败: ' + e.message);
                });
        }

        function handleGenerate() {
            doAction('generate', {}, function (content, postId) {
                console.log('[AI Plus] handleGenerate, postId:', postId);
                if (postId) {
                    insertContent(postId, content);
                } else {
                    wp.data.dispatch('core/editor').editPost({ content: content });
                    setGlobalResult('✅ 已写入编辑器（新建草稿）');
                }
            });
        }

        function handleExpand() {
            doAction('expand', {}, function (content, postId, r) {
                // 后端返回已转换的 HTML 时直接写入
                if (r && r.html) {
                    var b = wp.blocks.parse(r.html);
                    if (b && b.length > 0) {
                        wp.data.dispatch('core/editor').insertBlocks(b);
                        savePostContent(postId, r.html);
                        setGlobalResult('✅ 内容已续写！');
                        return;
                    }
                }
                if (postId) {
                    insertContent(postId, content);
                } else {
                    wp.data.dispatch('core/editor').editPost({ content: content });
                    setGlobalResult('✅ 内容已续写！');
                }
            });
        }

        function handleSummarize() {
            doAction('summarize', {}, function (content) {
                wp.data.dispatch('core/editor').editPost({ excerpt: content });
                setGlobalResult('✅ 摘要已写入！');
            });
        }

        function handleTranslate() {
            var rawContent = '';
            try {
                rawContent = wp.data.select('core/editor').getEditedPostAttribute('content') || '';
            } catch (e) {}
            if (!rawContent || rawContent.replace(/<[^>]+>/g, '').trim().length < 2) {
                setGlobalResult('⚠️ 编辑器内容为空，请先写入内容'); return;
            }
            // Strip HTML tags for translation, preserve paragraph structure
            var plainText = rawContent.replace(/<p[^>]*>/g, '\n').replace(/<[^>]+>/g, '').replace(/\n+/g, '\n').trim();
            doAction('translate', {
                content: plainText,
                source_lang: translateSource,
                target_lang: translateTarget
            }, function (translated) {
                // Convert plain text back to HTML paragraphs
                var paragraphs = translated.split(/\n+/).filter(function (p) { return p.trim(); });
                var html = paragraphs.map(function (p) { return '<p>' + p.replace(/^-+$/g, '').trim() + '</p>'; }).join('\n');
                // Replace post content
                var block = wp.blocks.parse(html);
                if (block && block.length > 0) {
                    wp.data.dispatch('core/editor').replaceBlocks(
                        wp.data.select('core/editor').getBlocks().map(function (b) { return b.clientId; }),
                        block
                    );
                }
                setGlobalResult('✅ 翻译完成，已替换编辑器内容！');
            });
        }

        function handleFeaturedImage() {
            var postId = 0;
            try { postId = wp.data.select('core/editor').getEditedPostAttribute('id') || 0; } catch (e) {}
            var title = '';
            try { title = wp.data.select('core/editor').getEditedPostAttribute('title') || ''; } catch (e) {}
            if (!postId) { setGlobalResult('请先保存文章后再生成特色图'); return; }
            if (!title) { setGlobalResult('请先输入文章标题'); return; }
            var content = getCurrentContent();
            if (!content) { setGlobalResult('请先输入文章内容'); return; }
            setLoading(true);
            setGlobalResult('');

            // 去除HTML标签和解码HTML实体，得到纯文本
            var tmp = document.createElement('div');
            tmp.innerHTML = content;
            var plainText = tmp.textContent || tmp.innerText || content || '';
            // 去除多余空白，保留换行
            plainText = plainText.replace(/[ \t]+/g, ' ').replace(/\n{3,}/g, '\n\n').trim();

            // 发送标题+纯文本正文，让AI基于干净文本生成相关图片
            var fullText = '文章标题：' + title + '\n\n文章内容：\n' + plainText;
            apiRequest('generate', {
                model: model,
                action: 'featured_image',
                content: fullText,
                extra_prompt: extraPrompt
            }).then(function (r) {
                setLoading(false);
                if (r.error) { setGlobalResult('错误: ' + r.error); return; }
                var prompt = r.image_prompt || r.content || '';
                if (!prompt) { setGlobalResult('未生成图片描述'); return; }

                // 优先使用AI生成的真实图片URL，否则降级到Picsum
                var imageUrl = r.url || '';
                if (!imageUrl) {
                    var seed = prompt.replace(/[^a-zA-Z0-9]/g, '_').substring(0, 30);
                    imageUrl = 'https://picsum.photos/seed/' + seed + '/1200/630';
                    setGlobalResult('📷 图片描述已生成（AI真实图片生成中...）：\n' + prompt + '\n\n正在设置特色图...');
                } else {
                    setGlobalResult('📷 AI图片已生成：\n' + prompt + '\n\n正在设置特色图...');
                }

                // 调用 REST API 下载并设置特色图
                var apiUrl = window.aiPlusConfig.apiUrl + 'featured-image-set';
                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.aiPlusConfig.nonce
                    },
                    body: JSON.stringify({ post_id: postId, image_url: imageUrl })
                }).then(function(resp) { return resp.json(); })
                .then(function(data) {
                    if (data.error) {
                        setGlobalResult('⚠️ 设置特色图失败：' + data.error + '\n\n描述：' + prompt);
                    } else {
                        // 通知 Gutenberg 编辑器实时更新特色图
                        if (data.attachment_id) {
                            try {
                                wp.data.dispatch('core/editor').editPost({
                                    featured_media: parseInt(data.attachment_id)
                                });
                            } catch (e) {}
                        }
                        setGlobalResult('✅ 特色图已设置！\n(' + (data.url || '') + ')');
                    }
                }).catch(function(err) {
                    setGlobalResult('⚠️ 网络错误：' + err.message + '\n描述：' + prompt);
                });
            }).catch(function(e) {
                setLoading(false);
                setGlobalResult('请求失败: ' + e.message);
            });
        }

        function handleKeyword() {
            var postId = 0;
            try { postId = wp.data.select('core/editor').getEditedPostAttribute('id') || 0; } catch (e) {}
            var content = getCurrentContent();
            if (!postId) { setGlobalResult('请先保存文章后再提取标签'); return; }
            if (!content) { setGlobalResult('请先输入文章内容'); return; }
            setLoading(true);
            setGlobalResult('');
            apiRequest('generate', {
                model: model,
                action: 'keyword',
                content: content,
                extra_prompt: extraPrompt
            }).then(function (r) {
                setLoading(false);
                if (r.error) { setGlobalResult('错误: ' + r.error); return; }
                var tagStr = r.content || '';
                var tagNames = tagStr.split(/[,，]/).map(function(t) { return t.trim(); }).filter(Boolean);
                if (!tagNames.length) { setGlobalResult('未提取到标签'); return; }
                var apiUrl = window.aiPlusConfig.apiUrl + 'tags-save';
                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.aiPlusConfig.nonce
                    },
                    body: JSON.stringify({ post_id: postId, tag_names: tagNames })
                }).then(function(resp) { return resp.json(); })
                .then(function(data) {
                    if (data.error) {
                        setGlobalResult('⚠️ 保存失败：' + data.error);
                    } else {
                        setGlobalResult('✅ 标签已保存: ' + (data.tags || tagNames).join(', '));
                    }
                }).catch(function(err) {
                    setGlobalResult('⚠️ 网络错误：' + err.message);
                });
            }).catch(function(e) {
                setLoading(false);
                setGlobalResult('请求失败: ' + e.message);
            });
        }

        function handleSlug() {
            var title = '';
            try { title = wp.data.select('core/editor').getEditedPostAttribute('title') || ''; } catch (e) {}
            if (!title) { setGlobalResult('请先输入文章标题'); return; }
            doAction('slug', { title: title }, function (slug) {
                wp.data.dispatch('core/editor').editPost({ slug: slug });
                setGlobalResult('✅ 别名: ' + slug);
            });
        }

        return wp.element.createElement(
            PluginDocumentSettingPanel,
            { title: 'AI Plus', icon: 'dashicons-visibility' },
            wp.element.createElement('div', { style: { padding: '0 16px 16px' } },
                wp.element.createElement('div', { style: sectionStyle },
                    wp.element.createElement('label', { style: labelStyle }, '选择模型'),
                    wp.element.createElement('select', {
                        value: model,
                        onChange: function (e) { setModel(e.target.value); },
                        style: { width: '100%', fontSize: '13px' } },
                        Object.keys(modelLabels).map(function (k) {
                            return wp.element.createElement('option', { key: k, value: k }, modelLabels[k]);
                        })
                    )
                ),
                wp.element.createElement('div', { style: sectionStyle },
                    wp.element.createElement('label', { style: labelStyle }, '附加提示（可选）'),
                    wp.element.createElement('input', {
                        type: 'text', value: extraPrompt,
                        onChange: function (e) { setExtraPrompt(e.target.value); },
                        placeholder: '语言风格、字数要求等',
                        style: { width: '100%', fontSize: '12px', padding: '4px' }
                    })
                ),
                wp.element.createElement('div', { style: sectionStyle },
                    wp.element.createElement('label', { style: labelStyle },
                        wp.element.createElement('strong', null, '文章生成')
                    ),
                    wp.element.createElement('button', { style: btnStyle, onClick: handleGenerate, disabled: loading }, '✏️ 生成文章'),
                    wp.element.createElement('button', { style: btnStyle, onClick: handleExpand, disabled: loading }, '续写内容')
                ),
                wp.element.createElement('div', { style: sectionStyle },
                    wp.element.createElement('label', { style: labelStyle },
                        wp.element.createElement('strong', null, '内容处理')
                    ),
                    wp.element.createElement('button', { style: btnStyle, onClick: handleSummarize, disabled: loading }, '生成摘要'),
                    wp.element.createElement('button', { style: btnStyle, onClick: handleKeyword, disabled: loading }, '提取标签')
                ),
                wp.element.createElement('div', { style: sectionStyle },
                    wp.element.createElement('label', { style: labelStyle },
                        wp.element.createElement('strong', null, '🌐 翻译')
                    ),
                    wp.element.createElement('div', { style: { display: 'flex', gap: '6px', alignItems: 'center', marginBottom: '6px' } },
                        wp.element.createElement('select', {
                            value: translateSource,
                            onChange: function(e) { setTranslateSource(e.target.value); },
                            style: { fontSize: '12px', padding: '4px', flex: 1 }
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
                            wp.element.createElement('option', { value: 'pt' }, 'Português'),
                            wp.element.createElement('option', { value: 'ru' }, 'Русский'),
                            wp.element.createElement('option', { value: 'ar' }, 'العربية'),
                            wp.element.createElement('option', { value: 'th' }, 'ภาษาไทย'),
                            wp.element.createElement('option', { value: 'vi' }, 'Tiếng Việt')
                        ),
                        wp.element.createElement('span', { style: { fontSize: '12px', color: '#666', flex: '0 0 auto' } }, ' → '),
                        wp.element.createElement('select', {
                            value: translateTarget,
                            onChange: function(e) { setTranslateTarget(e.target.value); },
                            style: { fontSize: '12px', padding: '4px', flex: 1 }
                        },
                            wp.element.createElement('option', { value: 'zh' }, '中文'),
                            wp.element.createElement('option', { value: 'en' }, 'English'),
                            wp.element.createElement('option', { value: 'zt' }, '繁体中文'),
                            wp.element.createElement('option', { value: 'ja' }, '日本語'),
                            wp.element.createElement('option', { value: 'ko' }, '한국어'),
                            wp.element.createElement('option', { value: 'fr' }, 'Français'),
                            wp.element.createElement('option', { value: 'de' }, 'Deutsch'),
                            wp.element.createElement('option', { value: 'es' }, 'Español'),
                            wp.element.createElement('option', { value: 'pt' }, 'Português'),
                            wp.element.createElement('option', { value: 'ru' }, 'Русский'),
                            wp.element.createElement('option', { value: 'ar' }, 'العربية'),
                            wp.element.createElement('option', { value: 'th' }, 'ภาษาไทย'),
                            wp.element.createElement('option', { value: 'vi' }, 'Tiếng Việt')
                        )
                    ),
                    wp.element.createElement('button', {
                        style: { background: '#2271b1', color: '#fff', border: 'none', padding: '6px 14px', cursor: 'pointer', borderRadius: '3px', fontSize: '13px', width: '100%', marginTop: '4px' },
                        onClick: handleTranslate,
                        disabled: loading
                    }, '🌐 翻译并替换编辑器内容')
                ),
                wp.element.createElement('div', { style: sectionStyle },
                    wp.element.createElement('label', { style: labelStyle },
                        wp.element.createElement('strong', null, '别名')
                    ),
                    wp.element.createElement('button', { style: btnStyle, onClick: handleSlug, disabled: loading }, '生成别名')
                ),
                wp.element.createElement('div', { style: sectionStyle },
                    wp.element.createElement('label', { style: labelStyle },
                        wp.element.createElement('strong', null, '特色图')
                    ),
                    wp.element.createElement('button', { style: btnStyle, onClick: handleFeaturedImage, disabled: loading }, '📷 生成特色图')
                ),
                loading && wp.element.createElement('div', { style: { color: '#666', fontSize: '12px', marginTop: '4px' } }, '⏳ AI 处理中...'),
                result && wp.element.createElement('div', { style: resultStyle }, result)
            )
        );
    };

    registerPlugin('ai-plus-sidebar', { render: AISidebarPanel });
    console.log('AI Plus Gutenberg sidebar registered');
})(window.wp);
