/**
 * AI Plus Admin JS - Playground & Image generation
 */
(function () {
    'use strict';

    function addMsg(container, text, role) {
        var el = document.createElement('div');
        el.className = 'ai-msg ' + role;
        el.textContent = text;
        container.appendChild(el);
        container.scrollTop = container.scrollHeight;
        // assistant 消息加辅助按钮(Playground 环境)
        if (role === 'assistant' && text) {
            var btnRow = document.createElement('div');
            btnRow.style.cssText = 'margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;';

            // 复制按钮
            var copyBtn = document.createElement('button');
            copyBtn.className = 'button button-small';
            copyBtn.style.cssText = 'font-size:12px;padding:2px 8px;height:auto;';
            copyBtn.textContent = '📋 复制';
            copyBtn.onclick = function() {
                navigator.clipboard.writeText(text).then(function() {
                    copyBtn.textContent = '✅ 已复制';
                    setTimeout(function() { copyBtn.textContent = '📋 复制'; }, 2000);
                }).catch(function() {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed'; ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    copyBtn.textContent = '✅ 已复制';
                    setTimeout(function() { copyBtn.textContent = '📋 复制'; }, 2000);
                });
            };
            btnRow.appendChild(copyBtn);

            // 保存到文章草稿按钮(自动 markdown→HTML)
            var draftBtn = document.createElement('button');
            draftBtn.className = 'button button-small button-primary';
            draftBtn.style.cssText = 'font-size:12px;padding:2px 8px;height:auto;';
            draftBtn.textContent = '📝 保存到草稿';
            draftBtn.onclick = function() {
                var btn = this;
                btn.disabled = true;
                btn.textContent = '保存中...';
                fetch(aiPlusAdmin.apiUrl + 'save-draft', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': aiPlusAdmin.nonce
                    },
                    body: JSON.stringify({
                        title: 'AI 生成-' + new Date().toLocaleString('zh-CN'),
                        content: text
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    if (resp.id) {
                        btn.textContent = '✅ 已保存';
                        var link = document.createElement('a');
                        link.href = resp.edit_url || '/wp-admin/post.php?post=' + resp.id + '&action=edit';
                        link.target = '_blank';
                        link.textContent = ' 打开草稿 →';
                        link.style.cssText = 'font-size:12px;margin-left:6px;color:#2271b1;';
                        btnRow.appendChild(link);
                    } else {
                        btn.textContent = '❌ 失败';
                        btn.disabled = false;
                    }
                })
                .catch(function(err) {
                    btn.textContent = '❌ 失败';
                    btn.disabled = false;
                });
            };
            btnRow.appendChild(draftBtn);
            el.appendChild(btnRow);
        }
        return el;
    }

    function addImage(container, url) {
        var el = document.createElement('div');
        el.className = 'ai-msg assistant';
        var img = document.createElement('img');
        img.src = url;
        img.className = 'ai-generated-img';
        img.style.maxWidth = '500px';
        img.onload = function () { container.scrollTop = container.scrollHeight; };
        el.appendChild(img);
        container.appendChild(el);
    }

    // ========== Playground (text) ==========
    var pgBtn = document.getElementById('ai-pg-send');
    if (pgBtn) {
        var pgMsgs = document.getElementById('ai-pg-messages');
        var pgPrompt = document.getElementById('ai-pg-prompt');
        var pgTokens = document.getElementById('pg_maxtokens');
        var pgLoading = false;

        pgBtn.addEventListener('click', sendPg);
        pgPrompt.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendPg();
            }
        });

        function sendPg() {
            if (pgLoading) return;
            var prompt = pgPrompt.value.trim();
            if (!prompt) return;

            var model = document.querySelector('input[name="pg_model"]:checked');
            if (!model) { alert('请先选择一个模型'); return; }

            pgLoading = true;
            pgBtn.disabled = true;
            pgBtn.textContent = '思考中...';

            addMsg(pgMsgs, '用户: ' + prompt, 'user');

            var loadingEl = addMsg(pgMsgs, '思考中...', 'loading');

            fetch(aiPlusAdmin.apiUrl + 'chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': aiPlusAdmin.nonce },
                body: JSON.stringify({
                    model: model.value,
                    messages: [{ role: 'user', content: prompt }],
                    max_tokens: parseInt(pgTokens ? pgTokens.value : 2048)
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                pgLoading = false;
                pgBtn.disabled = false;
                pgBtn.textContent = '发送';
                loadingEl.remove();
                // API 可能返回两种格式：
                // 1. 嵌套: {"code":"success","data":{"content":...}}
                // 2. 直接: {"content":...}
                var inner = (data.code === 'success' && data.data) ? data.data : data;
                var reply = inner.content || (inner.choices && inner.choices[0] && inner.choices[0].message && inner.choices[0].message.content) || data.error || '无响应';
                addMsg(pgMsgs, reply, 'assistant');
                pgPrompt.value = '';
            })
            .catch(function (err) {
                pgLoading = false;
                pgBtn.disabled = false;
                pgBtn.textContent = '发送';
                loadingEl.remove();
                addMsg(pgMsgs, '网络错误: ' + err.message, 'assistant');
            });
        }
    }

    // ========== Image Generation ==========
    var imgBtn = document.getElementById('ai-img-generate');
    if (imgBtn) {
        var imgResult = document.getElementById('ai-img-result');
        var imgPrompt = document.getElementById('ai-img-prompt');

        imgBtn.addEventListener('click', function () {
            var model = document.querySelector('input[name="img_model"]:checked');
            if (!model) { alert('请先在左侧选择一个模型'); return; }
            var prompt = imgPrompt.value.trim();
            if (!prompt) { alert('请输入图片描述'); return; }

            imgBtn.disabled = true;
            imgBtn.textContent = '生成中...';
            imgResult.innerHTML = '<div class="ai-msg loading">正在生成图片,请稍候(通常10-30秒)...</div>';

            fetch(aiPlusAdmin.apiUrl + 'generate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': aiPlusAdmin.nonce },
                body: JSON.stringify({
                    model: model.value,
                    action: 'featured_image',
                    content: prompt
                })
            })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status + ': ' + r.statusText);
                }
                return r.json();
            })
            .then(function (data) {
                imgBtn.disabled = false;
                imgBtn.textContent = '生成图片';
                imgResult.innerHTML = '';
                // 调试：页面可见的诊断信息
                var dbg = document.createElement('div');
                dbg.style = 'background:#f0f0f0;border:1px solid #ccc;padding:8px;margin:8px 0;font-size:11px;word-break:break-all;max-height:200px;overflow-y:auto;';
                dbg.textContent = '收到响应: ' + JSON.stringify(data).substring(0, 400);
                imgResult.appendChild(dbg);

                // API 可能返回两种格式：
                // 1. 嵌套: {"code":"success","data":{"url":...}}
                // 2. 直接: {"url":...}
                var inner = (data.code === 'success' && data.data) ? data.data : data;

                if (inner && inner.url) {
                    addMsg(imgResult, '中文说明: ' + (inner.chinese_desc || inner.image_prompt || prompt), 'assistant');
                    if (inner.chinese_alt) addMsg(imgResult, '替代文本: ' + inner.chinese_alt, 'assistant');
                    addImage(imgResult, inner.url);

                    // 保存到媒体库按钮(admin.js 用原生 DOM,aiPlusAdmin.nonce 来自 wp_localize_script)
                    var saveBtn = document.createElement('button');
                    saveBtn.className = 'button';
                    saveBtn.style.cssText = 'font-size:12px;padding:4px 12px;height:auto;';
                    saveBtn.textContent = '💾 保存到媒体库';
                    saveBtn.onclick = function() {
                        var btn = this;
                        btn.disabled = true;
                        btn.textContent = '上传中...';
                        fetch(inner.url)
                            .then(function(r) { return r.blob(); })
                            .then(function(blob) {
                                var filename = 'ai-plus-' + Date.now() + '.png';
                                var fd = new FormData();
                                fd.append('file', blob, filename);
                                // 标题:取中文图片说明的前80字符
                                var titleVal = (inner.chinese_desc || inner.content || prompt || filename).slice(0, 80);
                                fd.append('title', titleVal);
                                // 替代文本(alt):中文 alt,不再使用英文 prompt
                                var altText = (inner.chinese_alt || inner.chinese_desc || '').slice(0, 100);
                                if (altText) fd.append('alt_text', altText);
                                // 说明文字(description → post_content):中文描述
                                var descText = (inner.chinese_desc || inner.content || '');
                                if (descText) fd.append('description', descText);
                                // 摘要(caption → post_excerpt):中文 alt 摘要
                                if (altText) fd.append('caption', altText);
                                return fetch('/wp-json/wp/v2/media', {
                                    method: 'POST',
                                    body: fd,
                                    headers: { 'X-WP-Nonce': aiPlusAdmin.nonce }
                                });
                            })
                            .then(function(r) { return r.json(); })
                            .then(function(media) {
                                btn.textContent = '✅ 已保存';
                                btn.disabled = true;
                                var src = media.source_url || (media.guid && media.guid.rendered) || '';
                                var openUrl = media.link || src;
                                addMsg(imgResult, '已保存到媒体库: "' + openUrl + '"', 'assistant');
                            })
                            .catch(function(err) {
                                btn.textContent = '保存失败';
                                btn.disabled = false;
                                addMsg(imgResult, '保存到媒体库失败: ' + err.message, 'assistant');
                            });
                    };
                    var imgWrapper = imgResult.querySelector('img.ai-generated-img');
                    if (imgWrapper && imgWrapper.parentNode) {
                        var actions = document.createElement('div');
                        actions.className = 'ai-img-actions';
                        actions.appendChild(saveBtn);
                        imgWrapper.parentNode.insertBefore(actions, imgWrapper.nextSibling);
                    }
                } else {
                    var errMsg = data.error || (data.data && data.data.error) || '未返回图片，请检查模型是否支持图像生成';
                    addMsg(imgResult, '错误: ' + errMsg, 'assistant');
                }
            })
            .catch(function (err) {
                imgBtn.disabled = false;
                imgBtn.textContent = '生成图片';
                imgResult.innerHTML = '<div style="background:#ffe0e0;border:1px solid #c00;padding:8px;margin:8px 0;">网络错误: ' + err.message + '</div>';
                addMsg(imgResult, '网络错误: ' + err.message, 'assistant');
            });
        });
    }

})();
