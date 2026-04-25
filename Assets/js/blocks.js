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
    var useBlockProps = wp.blockEditor ? wp.blockEditor.useBlockProps : (wp.editor ? wp.editor.useBlockProps : null);
    var TextControl = wp.components.TextControl;
    var Button = wp.components.Button;
    var Placeholder = wp.components.Placeholder;
    var apiFetch = wp.apiFetch;

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
        supports: {
            lock: false,
            removing: true,
            customClassName: true,
            reusable: true,
            html: false,
        },
        edit: function (props) {
            var chatBlockProps = useBlockProps ? useBlockProps({ className: 'wp-block-ai-plus-chat' }) : { className: 'wp-block-ai-plus-chat' };
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
                apiFetch({
                    path: 'ai-plus/v1/chat',
                    method: 'POST',
                    data: fetchBody
                })
                .then(function (resp) {
                    var data = (resp && resp.code === 'success' && resp.data) ? resp.data : (resp || {});
                    var reply = data.choices ? (data.choices[0] && data.choices[0].message && data.choices[0].message.content || data.content || '') : (data.content || '');
                    if (resp && resp.code !== 'success') { reply = resp.message || resp.error || '无响应'; }
                    props.setAttributes({ chatLoading: false, messages: newMessages.concat([{ role: 'assistant', content: reply }]) });
                })
                .catch(function () {
                    props.setAttributes({ chatLoading: false, messages: newMessages.concat([{ role: 'assistant', content: '请求失败，请重试' }]) });
                });
            }

            return el('div', chatBlockProps,
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
                el('div', { className: 'ai-plus-chat-block wp-block', style: { 
                        border: '1px solid #e5e7eb', 
                        borderRadius: '12px', 
                        padding: '20px', 
                        background: 'linear-gradient(180deg, #fff 0%, #f9fafb 100%)', 
                        maxWidth: '700px', 
                        margin: '20px auto',
                        boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
                    } },
                    // 标题栏
                    el('div', { style: { 
                        display: 'flex', 
                        alignItems: 'center', 
                        justifyContent: 'space-between',
                        borderBottom: '2px solid #f0f0f0',
                        paddingBottom: '12px',
                        marginBottom: '16px'
                    } },
                        title ? el('div', { style: { fontWeight: '600', fontSize: '16px', color: '#1a1a1a' } }, '💬 ' + title) : el('div', { style: { fontWeight: '600', fontSize: '16px', color: '#1a1a1a' } }, '💬 AI 助手'),
                        el(Button, { 
                            onClick: deleteBlock, 
                            isDestructive: true, 
                            isSmall: true,
                            style: { padding: '4px 8px', fontSize: '12px' }
                        }, '🗑️ 删除区块')
                    ),
                    // 消息区 - flex 布局，左右对齐
                    el('div', { style: { 
                        maxHeight: '320px', 
                        overflowY: 'auto', 
                        marginBottom: '16px',
                        padding: '12px',
                        background: '#f9fafb',
                        borderRadius: '8px',
                        display: 'flex',
                        flexDirection: 'column',
                        gap: '10px'
                    } },
                        messages.length === 0
                            ? el('p', { style: { color: '#9ca3af', fontSize: '14px', textAlign: 'center', padding: '40px 0', margin: '0' } }, '💬 输入内容开始对话...')
                            : messages.map(function (m, i) {
                                // 用户消息右对齐蓝色，AI消息左对齐灰色
                                var isUser = m.role === 'user';
                                return el('div', {
                                    key: i,
                                    style: {
                                        maxWidth: '80%',
                                        padding: '10px 14px',
                                        borderRadius: isUser ? '12px 12px 4px 12px' : '12px 12px 12px 4px',
                                        background: isUser ? '#2271b1' : '#f0f0f0',
                                        color: isUser ? '#fff' : '#333',
                                        fontSize: '14px',
                                        lineHeight: '1.5',
                                        alignSelf: isUser ? 'flex-end' : 'flex-start',
                                        boxShadow: '0 1px 2px rgba(0,0,0,0.05)',
                                        wordBreak: 'break-word'
                                    }
                                }, m.content);
                            })
                    ),
                    // Loading 指示
                    loading ? el('div', { style: { 
                        textAlign: 'center', 
                        padding: '8px',
                        marginBottom: '8px'
                    } },
                        el('span', { style: { color: '#2271b1', fontSize: '14px' } }, '⏳ AI 正在思考...')
                    ) : null,
                    // 输入区
                    el('div', { style: { display: 'flex', gap: '10px', alignItems: 'flex-end' } },
                        el('textarea', {
                            value: input,
                            rows: 2,
                            placeholder: '输入消息... (Enter 发送)',
                            onChange: function (e) { props.setAttributes({ chatInput: e.target.value }); },
                            onKeyDown: function (e) {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    sendMessage();
                                }
                            },
                            style: { 
                                flex: '1',
                                resize: 'none', 
                                fontSize: '14px', 
                                padding: '10px 14px',
                                border: '1px solid #dcdcde',
                                borderRadius: '8px',
                                lineHeight: '1.5',
                                outline: 'none'
                            }
                        }),
                        el(Button, { 
                            onClick: sendMessage, 
                            isPrimary: true, 
                            disabled: loading,
                            style: { padding: '10px 20px', fontSize: '14px', fontWeight: '500' }
                        }, '发送')
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
            lock: false,
            removing: true,
            customClassName: true,
            className: true,
            reusable: true,
            html: false,
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
            // ===== Hooks 必须在顶层调用，不能在条件分支内 =====
            var blockProps = useBlockProps ? useBlockProps({ className: 'wp-block-ai-plus-image-generator' }) : { className: 'wp-block-ai-plus-image-generator' };
            var inputRef = React.useRef(null);
            var isComposing = React.useRef(false);
            var localPromptState = React.useState(props.attributes.prompt || '');
            var localPrompt = localPromptState[0];
            var setLocalPrompt = localPromptState[1];
            
            React.useEffect(function() {
                setLocalPrompt(props.attributes.prompt || '');
            }, [props.attributes.prompt]);

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
                apiFetch({
                    path: 'ai-plus/v1/image',
                    method: 'POST',
                    data: { model: model, prompt: prompt }
                })
                .then(function (resp) {
                    // wp.apiFetch 返回完整 REST 响应: { code: 'success', data: { ... } }
                    var data = (resp && resp.code === 'success' && resp.data) ? resp.data : (resp || {});
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
                        chinese_alt: cAlt,       // 中文替代文本
                        set_featured: false      // 插图不设置特色图
                    };
                    
                    console.log('[AI+] 保存到媒体库:', saveData);
                    
                    apiFetch({
                        path: 'ai-plus/v1/featured-image-set',
                        method: 'POST',
                        data: saveData
                    })
                    .then(function(resp) {
                        var up = (resp && resp.code === 'success' && resp.data) ? resp.data : (resp || {});
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
                    var errMsg = (err && err.message) ? err.message : '图片生成失败，请重试';
                    props.setAttributes({ imgLoading: false, prompt: prompt ? '❌ ' + errMsg + '\n' + prompt : '❌ ' + errMsg }); 
                });
            }

            // 已生成图片，显示图片 - 与其他区块保持一致 840px 居中
            if (imageUrl) {
                return el('div', blockProps,
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
            return el('div', Object.assign({}, blockProps, { 
                style: { 
                    padding: '20px', 
                    background: '#f0f0f1', 
                    borderRadius: '4px',
                    maxWidth: '840px',
                    margin: '0 auto'
                }
            }),
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
                el('div', { style: { display: 'flex', gap: '8px' } },
                    el(Button, {
                        onClick: generate,
                        isPrimary: true,
                        disabled: loading || !localPrompt.trim(),
                        style: { flex: 1, justifyContent: 'center' }
                    }, loading ? '⏳ 生成中...' : '✨ 生成图片'),
                    el(Button, {
                        onClick: function () {
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
