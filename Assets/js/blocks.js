(function (wp) {
    'use strict';

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.editor.InspectorControls;
    var TextControl = wp.components.TextControl;
    var Button = wp.components.Button;
    var Placeholder = wp.components.Placeholder;

    // ========== Chat Block ==========
    registerBlockType('ai-plus/chat', {
        title: 'AI Plus 聊天',
        icon: 'format-chat',
        category: 'widgets',
        attributes: {
            model: { type: 'string', default: 'zhipu' },
            title: { type: 'string', default: 'AI 助手' },
            messages: { type: 'array', default: [] },
            chatInput: { type: 'string', default: '' },
            chatLoading: { type: 'boolean', default: false },
            postContext: { type: 'string', default: '' },
        },
        edit: function (props) {
            var model = props.attributes.model;
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
                            el('option', { value: 'zhipu' }, '智谱 GLM'),
                            el('option', { value: 'tongyi' }, '通义千问'),
                            el('option', { value: 'minimax' }, 'MiniMax'),
                            el('option', { value: 'kimi' }, 'Kimi'),
                            el('option', { value: 'deepseek' }, 'DeepSeek')
                        )
                    )
                ),
                el('div', { className: 'ai-plus-chat-block', style: { border: '1px solid #dcdcde', borderRadius: '4px', padding: '16px', background: '#fff' } },
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
                        el(Button, { onClick: sendMessage, isPrimary: true, disabled: loading }, '发送')
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
        attributes: {
            prompt: { type: 'string', default: '' },
            imageUrl: { type: 'string', default: '' },
            imagePrompt: { type: 'string', default: '' },
            model: { type: 'string', default: 'tongyi' },
            caption: { type: 'string', default: '' },
            imgLoading: { type: 'boolean', default: false },
        },
        edit: function (props) {
            var prompt = props.attributes.prompt;
            var imageUrl = props.attributes.imageUrl;
            var imagePrompt = props.attributes.imagePrompt;
            var model = props.attributes.model;
            var caption = props.attributes.caption;
            var loading = props.attributes.chatLoading || false;

            function generate() {
                if (!prompt.trim() || loading) return;
                props.setAttributes({ chatLoading: true });
                fetch(window.aiPlusConfig.apiUrl + 'generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.aiPlusConfig.nonce },
                    body: JSON.stringify({ action: 'image', model: model, content: prompt })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    props.setAttributes({
                        _loading: false,
                        imageUrl: data.url || '',
                        imagePrompt: data.image_prompt || prompt,
                        caption: data.image_prompt || prompt
                    });
                })
                .catch(function () {
                    props.setAttributes({ chatLoading: false });
                });
            }

            // 已生成图片，显示图片
            if (imageUrl) {
                return el('figure', { className: 'wp-block-image' },
                    el('img', { src: imageUrl, alt: caption || prompt, style: { maxWidth: '100%', height: 'auto' } }),
                    caption ? el('figcaption', { style: { fontSize: '13px', color: '#555' } }, caption) : null,
                    el('div', { style: { marginTop: '8px' } },
                        el(Button, {
                            onClick: function () { props.setAttributes({ imageUrl: '', imagePrompt: '', caption: '', prompt: '' }); },
                            isSmall: true
                        }, '重新生成')
                    )
                );
            }

            // 未生成，显示输入界面
            return el(InspectorControls, null,
                el('div', { style: { padding: '16px' } },
                    el('p', { style: { fontSize: '13px', color: '#555', marginBottom: '12px' } }, '选择图片生成模型：'),
                    el('select', {
                        value: model,
                        onChange: function (e) { props.setAttributes({ model: e.target.value }); },
                        style: { width: '100%', marginBottom: '12px' }
                    },
                        el('option', { value: 'tongyi' }, '通义千问 — qwen-image-2.0-pro'),
                        el('option', { value: 'zhipu' }, '智谱 GLM — cogview-3'),
                        el('option', { value: 'minimax' }, 'MiniMax — image-01')
                    )
                ),
                el(Placeholder, { icon: 'format-image', label: 'AI 图片生成' },
                    el('textarea', {
                        value: prompt,
                        rows: 3,
                        placeholder: '输入图片描述，例如：一张现代简约风格的铝合金衣柜，高清写实摄影',
                        onChange: function (e) { props.setAttributes({ prompt: e.target.value }); },
                        style: { width: '100%', resize: 'vertical', fontSize: '14px', padding: '8px', marginBottom: '12px' }
                    }),
                    el(Button, {
                        onClick: generate,
                        isPrimary: true,
                        disabled: loading || !prompt.trim()
                    }, loading ? '⏳ 生成中...' : '✨ 生成图片')
                )
            );
        },
        save: function (props) {
            var imageUrl = props.attributes.imageUrl;
            var caption = props.attributes.caption;
            if (imageUrl) {
                return el('figure', { className: 'wp-block-image' },
                    el('img', { src: imageUrl, alt: caption || '' }),
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
