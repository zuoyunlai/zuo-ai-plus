(function (wp) {
    'use strict';

    window.addEventListener('error', function(e) {
        if (e.filename && e.filename.indexOf('blocks.js') !== -1) {
            console.error('[AI+ blocks.js error]', e.message, 'at', e.filename + ':' + e.lineno);
        }
    });

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
    var TextControl = wp.components.TextControl;
    var Button = wp.components.Button;
    var Placeholder = wp.components.Placeholder;

    // ========== Chat Block ==========
    registerBlockType('ai-plus/chat', {
        title: 'AI Plus 聊天',
        icon: 'format-chat',
        category: 'widgets',
        apiVersion: 3,
        attributes: {
            model: { type: 'string', default: 'minimax' },
            title: { type: 'string', default: 'AI 助手' },
            messages: { type: 'array', default: [] },
            chatInput: { type: 'string', default: '' },
            chatLoading: { type: 'boolean', default: false },
            postContext: { type: 'string', default: '' },
        },
        edit: function (props) {
            var model = props.attributes.model || 'minimax';
            // 自动获取当前文章内容作为上下文
            if (!props.attributes.postContext) {
                var postContent = '';
                try { postContent = wp.data.select('core/editor').getEditedPostAttribute('content') || ''; } catch(e) {}
                if (postContent) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = postContent;
                    postContent = (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
                }
                if (postContent.length > 200) {
                    props.setAttributes({ postContext: postContent });
                }
            }
            var title = props.attributes.title;
            var messages = props.attributes.messages || [];
            var input = props.attributes.chatInput || '';
            var loading = props.attributes.chatLoading || false;
            
            function deleteBlock() {
                var clientId = props.clientId;
                if (clientId && wp.data && wp.data.dispatch && wp.data.dispatch('core/block-editor')) {
                    wp.data.dispatch('core/block-editor').removeBlock(clientId);
                }
            }

            function sendMessage() {
                if (!input.trim() || loading) return;
                var newMessages = messages.concat([{ role: 'user', content: input }]);
                props.setAttributes({ chatLoading: true, messages: newMessages, _input: '' });

                var postContext = props.attributes.postContext || '';
                var fetchBody = { model: model, messages: newMessages, max_tokens: 2048 };
                if (postContext) fetchBody.context = postContext;
                fetch(window.aiPlusConfig.apiUrl + 'chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.aiPlusConfig.nonce },
                    body: JSON.stringify(fetchBody)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var reply = (data.choices && data.choices[0] && data.choices[0].message && data.choices[0].message.content) || data.content || data.error || '无响应';
                    props.setAttributes({ chatLoading: false, messages: newMessages.concat([{ role: 'assistant', content: reply }]) });
                })
                .catch(function () {
                    props.setAttributes({ chatLoading: false, messages: newMessages.concat([{ role: 'assistant', content: '请求失败，请重试' }]) });
                });
            }

            return el(Fragment, null,
                el(InspectorControls, null,
                    el('div', { style: { padding: '16px' } },
                        el(TextControl, {
                            label: '聊天标题',
                            __next40pxDefaultSize: true,
                            __nextHasNoMarginBottom: true,
                            value: title,
                            onChange: function (v) { props.setAttributes({ title: v }); }
                        }),
                        el('select', {
                            value: model,
                            onChange: function (e) { props.setAttributes({ model: e.target.value }); },
                            style: { width: '100%', marginTop: '8px' }
                        },
                            el('option', { value: 'minimax' }, 'MiniMax'),
                            el('option', { value: 'tongyi' }, '通义千问'),
                            el('option', { value: 'zhipu' }, '智谱 GLM'),
                            el('option', { value: 'kimi' }, 'Kimi'),
                            el('option', { value: 'deepseek' }, 'DeepSeek')
                        )
                    )
                ),
                el('div', { className: 'ai-plus-chat-block wp-block', style: { border: '1px solid #dcdcde', borderRadius: '4px', padding: '20px', background: '#fff', maxWidth: '840px', margin: '16px auto' } },
                    title ? el('div', { style: { fontWeight: 'bold', marginBottom: '12px', fontSize: '15px' } }, title) : null,
                    el('div', { style: { maxHeight: '300px', overflowY: 'auto', marginBottom: '12px' } },
                        messages.length === 0
                            ? el('p', { style: { color: '#666', fontSize: '13px' } }, '输入内容开始对话...')
                            : messages.map(function (m, i) {
                                return el('div', {
                                    key: i,
                                    style: {
                                        marginBottom: '8px',
                                        padding: '8px 12px',
                                        borderRadius: '8px',
                                        background: m.role === 'user' ? '#e7f3ff' : '#f5f5f5',
                                        border: m.role === 'user' ? '1px solid #b3d7ff' : '1px solid #e0e0e0',
                                        fontSize: '14px',
                                        lineHeight: '1.6'
                                    }
                                }, m.content);
                            })
                    ),
                    loading ? el('span', { style: { color: '#2271b1' } }, '⏳ 生成中...') : null,
                    el('div', { style: { display: 'flex', gap: '8px', marginTop: '8px' } },
                        el('textarea', {
                            value: input,
                            rows: 2,
                            placeholder: '输入消息...',
                            onChange: function (e) { props.setAttributes({ chatInput: e.target.value }); },
                            onKeyDown: function (e) {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    sendMessage();
                                }
                            },
                            style: { resize: 'none', fontSize: '14px', padding: '8px' }
                        }),
                        el(Button, { onClick: sendMessage, isPrimary: true, disabled: loading }, '发送'),
                        el(Button, { onClick: deleteBlock, isDestructive: true, isSmall: true, style: { marginLeft: '8px' } }, '🗑️ 删除')
                    )
                )
            );
        },
        save: function () {
            return null; // Dynamic block, rendered server-side
        }
    });

    // ========== Image Generation Block ==========
    registerBlockType('ai-plus/image-generator', {
        title: 'AI Plus 图片生成',
        icon: 'format-image',
        category: 'media',
        apiVersion: 3,
        supports: {
            // 允许通过工具栏删除
            lock: false,
            // 允许在列表视图中操作
            className: true,
        },
        attributes: {
            prompt: { type: 'string', default: '' },
            imageUrl: { type: 'string', default: '' },
            imagePrompt: { type: 'string', default: '' },
            englishPrompt: { type: 'string', default: '' },
            chineseAlt: { type: 'string', default: '' },
            chineseDesc: { type: 'string', default: '' },
            attachmentId: { type: 'number', default: 0 },
            model: { type: 'string', default: 'tongyi' },
            caption: { type: 'string', default: '' },
            imgLoading: { type: 'boolean', default: false },
            align: { type: 'string', default: 'wide' },
        },
        edit: function (props) {
            var prompt = props.attributes.prompt;
            var imageUrl = props.attributes.imageUrl;
            var imagePrompt = props.attributes.imagePrompt;
            var model = props.attributes.model;
            var caption = props.attributes.caption;
            var englishPrompt = props.attributes.englishPrompt || '';
            var chineseAlt = props.attributes.chineseAlt || '';
            var chineseDesc = props.attributes.chineseDesc || '';
            var attachmentId = props.attributes.attachmentId || 0;
            var loading = props.attributes.imgLoading || false;

            function generate() {
                if (!prompt.trim() || loading) return;
                props.setAttributes({ imgLoading: true });
                fetch(window.aiPlusConfig.apiUrl + 'generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.aiPlusConfig.nonce },
                    body: JSON.stringify({ action: 'image', model: model, content: prompt })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var imgUrl = data.url || '';
                    // 优先使用中文元数据
                    var cDesc = data.chinese_desc || data.content || '';
                    var cAlt = data.chinese_alt || cDesc || '';
                    var ePrompt = data.image_prompt || prompt || '';
                    
                    props.setAttributes({
                        imgLoading: false,
                        imageUrl: imgUrl,
                        imagePrompt: ePrompt,
                        englishPrompt: ePrompt,
                        chineseAlt: cAlt,
                        chineseDesc: cDesc,
                        caption: cDesc // 用中文描述作为 caption
                    });
                    
                    if (!imgUrl) { 
                        console.warn('[AI+] 图片生成失败，无图片URL');
                        return; 
                    }
                    
                    // 获取 post_id
                    var postId = 0;
                    try { postId = wp.data.select('core/editor').getEditedPostAttribute('id') || 0; } catch(e) {}
                    
                    // 保存到媒体库 - 使用中文元数据
                    var saveData = { 
                        post_id: postId, 
                        image_url: imgUrl, 
                        post_title: cDesc,      // 中文描述作为标题
                        image_prompt: ePrompt,   // 英文提示词
                        alt_text: cAlt,          // 中文替代文本
                        chinese_desc: cDesc,     // 中文描述
                        chinese_alt: cAlt        // 中文替代文本
                    };
                    
                    console.log('[AI+] 保存到媒体库:', saveData);
                    
                    fetch(window.aiPlusConfig.apiUrl + 'featured-image-set', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.aiPlusConfig.nonce },
                        body: JSON.stringify(saveData)
                    }).then(function(resp) {
                        return resp.json();
                    })
                    .then(function(up) {
                        if (up.attachment_id) {
                            props.setAttributes({ 
                                imageUrl: up.url || imgUrl, 
                                attachmentId: up.attachment_id 
                            });
                            console.log('[AI+] 图片已保存到媒体库:', up.attachment_id);
                        } else {
                            if (up.error) console.warn('[AI+] 保存媒体库失败:', up.error);
                        }
                    })
                    .catch(function(err) {
                        console.error('[AI+] 保存媒体库网络错误:', err);
                    });
                })
                .catch(function (err) { 
                    console.error('[AI+] 生成图片失败:', err);
                    props.setAttributes({ imgLoading: false }); 
                });
            }

            // 已生成图片，显示图片 - 与其他区块保持一致 840px 居中
            if (imageUrl) {
                return el(Fragment, null,
                    el('figure', { 
                        className: 'wp-block-image alignwide',
                        style: { 
                            maxWidth: '840px',
                            margin: '16px auto'
                        } 
                    },
                        el('img', { 
                            src: imageUrl, 
                            alt: chineseAlt || caption || prompt, 
                            style: { maxWidth: '100%', height: 'auto', display: 'block' } 
                        }),
                        caption ? el('figcaption', { style: { fontSize: '13px', color: '#555', textAlign: 'center', marginTop: '8px' } }, caption) : null
                    ),
                    el('div', { 
                        className: 'ai-plus-image-actions',
                        style: { 
                            maxWidth: '840px',
                            margin: '12px auto',
                            textAlign: 'center',
                            display: 'flex',
                            gap: '8px',
                            justifyContent: 'center'
                        } 
                    },
                        el(Button, {
                            onClick: function () { 
                                props.setAttributes({ 
                                    imageUrl: '', attachmentId: 0, imagePrompt: '', 
                                    englishPrompt: '', chineseAlt: '', chineseDesc: '', 
                                    caption: '', prompt: '' 
                                }); 
                            },
                            isSecondary: true,
                            isSmall: true
                        }, '🔄 重新生成'),
                        el(Button, {
                            onClick: function () {
                                // 使用 Gutenberg API 删除当前区块
                                var clientId = props.clientId;
                                if (clientId && wp.data.dispatch('core/block-editor')) {
                                    wp.data.dispatch('core/block-editor').removeBlock(clientId);
                                }
                            },
                            isDestructive: true,
                            isSmall: true
                        }, '🗑️ 删除区块')
                    )
                );
            }

            // 未生成，显示中央输入界面 - 与其他区块对齐 840px
            // 使用 ref 和 state 处理输入法合成状态
            var inputRef = React.useRef(null);
            var isComposing = React.useRef(false);
            var [localPrompt, setLocalPrompt] = React.useState(prompt || '');
            
            // 同步外部 prompt 变化到本地状态
            React.useEffect(function() {
                setLocalPrompt(prompt || '');
            }, [prompt]);
            
            return el('div', { 
                style: { 
                    padding: '20px', 
                    background: '#f0f0f1', 
                    borderRadius: '4px',
                    maxWidth: '840px',
                    margin: '0 auto'
                } 
            },
                el('div', { style: { marginBottom: '16px' } },
                    el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '500' } }, '选择图片生成模型：'),
                    el('select', {
                        value: model,
                        onChange: function (e) { props.setAttributes({ model: e.target.value }); },
                        style: { width: '100%', padding: '6px', fontSize: '14px', boxSizing: 'border-box' }
                    },
                        el('option', { value: 'tongyi' }, '通义千问 — qwen-image-2.0-pro'),
                        el('option', { value: 'zhipu' }, '智谱 GLM — cogview-3'),
                        el('option', { value: 'minimax' }, 'MiniMax — image-01')
                    )
                ),
                el('div', { style: { marginBottom: '16px' } },
                    el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: '500' } }, '图片描述：'),
                    el('textarea', {
                        ref: inputRef,
                        value: localPrompt,
                        rows: 4,
                        placeholder: '输入图片描述，例如：一张现代简约风格的铝合金衣柜，高清写实摄影\n\nAI 将自动生成英文绘图提示词，并创建中文标题、说明和替代文本',
                        onCompositionStart: function() { isComposing.current = true; },
                        onCompositionEnd: function(e) { 
                            isComposing.current = false;
                            setLocalPrompt(e.target.value);
                            props.setAttributes({ prompt: e.target.value });
                        },
                        onChange: function (e) { 
                            var value = e.target.value;
                            setLocalPrompt(value);
                            // 只有在非输入法合成状态下才更新属性
                            if (!isComposing.current) {
                                props.setAttributes({ prompt: value });
                            }
                        },
                        onBlur: function(e) {
                            // 失去焦点时确保同步
                            props.setAttributes({ prompt: e.target.value });
                        },
                        style: { 
                            width: '100%', 
                            maxWidth: '100%',
                            resize: 'vertical', 
                            fontSize: '14px', 
                            padding: '10px', 
                            borderRadius: '4px', 
                            border: '1px solid #c5c5c5',
                            boxSizing: 'border-box'
                        }
                    })
                ),
                el(Button, {
                    onClick: generate,
                    isPrimary: true,
                    disabled: loading || !localPrompt.trim(),
                    style: { width: '100%', justifyContent: 'center' }
                }, loading ? '⏳ 生成中...' : '✨ 生成图片')
            );
        },
        save: function (props) {
            var imageUrl = props.attributes.imageUrl;
            var caption = props.attributes.caption;
            if (imageUrl) {
                var align = props.attributes.align || 'wide';
                var figClass = 'wp-block-image align' + align;
                return el('figure', { className: figClass },
                    el('img', { src: imageUrl, alt: (props.attributes.chineseAlt || caption || '') }),
                    caption ? el('figcaption', null, caption) : null
                );
            }
            return el('div', { className: 'ai-plus-image-placeholder' },
                'AI 图片区块（编辑模式下输入描述生成图片）'
            );
        }
    });

})(
    window.wp || {}
);
