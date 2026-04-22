/**
 * Zuo AI Plus - 导航模块前端 JS
 * 动态数据通过 wp_localize_script 注入 zuoNav 对象
 */
(function () {
    'use strict';

    // 从 WordPress 注入的配置（wp_localize_script）
    var cfg = window.zuoNav || {};

    // ── HTML 转义工具（防 XSS） ───────────────────────────────────────────────
    var _escDiv = document.createElement('div');
    function esc(str) {
        if (!str) return '';
        _escDiv.textContent = String(str);
        return _escDiv.innerHTML;
    }
    function escAttr(str) {
        return esc(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ── 视图切换 ──────────────────────────────────────────────────────────────
    window.switchView = function (view) {
        document.querySelectorAll('.nav-quick-link').forEach(function (btn) {
            btn.classList.remove('active');
        });
        var activeBtn = document.querySelector('.nav-quick-link[data-view="' + view + '"]');
        if (activeBtn) activeBtn.classList.add('active');

        var titles = { 'all': '全部网站', 'favorites': '我的收藏', 'history': '最近访问' };
        var titleEl = document.getElementById('main-title');
        if (titleEl) titleEl.textContent = titles[view];

        document.querySelectorAll('.nav-view-section').forEach(function (section) {
            section.style.display = 'none';
        });
        var viewEl = document.getElementById('view-' + view);
        if (viewEl) viewEl.style.display = 'block';

        if (view === 'favorites') showFavorites();
        else if (view === 'history') showHistory();
    };

    // ── 更新快捷入口数字 ──────────────────────────────────────────────────────
    function updateQuickCounts() {
        var favs = JSON.parse(localStorage.getItem('nav_favorites') || '[]');
        var history = JSON.parse(localStorage.getItem('nav_history') || '[]');
        var favCount = document.getElementById('fav-count');
        var histCount = document.getElementById('history-count');
        if (favCount) favCount.textContent = favs.length;
        if (histCount) histCount.textContent = history.length;
    }
    document.addEventListener('DOMContentLoaded', updateQuickCounts);

    // ── 记录点击 ──────────────────────────────────────────────────────────────
    window.recordNavClick = function (postId) {
        if (!postId) return;
        fetch(cfg.clickUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: parseInt(postId) })
        }).catch(function () {});
    };

    // ── 渲染卡片 HTML ────────────────────────────────────────────────────────
    function renderCard(site, meta, cats, tags, showVisitLabel) {
        var url = escAttr(meta.url || '#');
        var name = esc(meta.name || site.title.rendered);
        var desc = esc(meta.description || '');
        var logo = escAttr(meta.logo || '');
        var firstLetter = esc(name.charAt(0).toUpperCase());
        var blurBg = logo ? '<div class="blur-bg" style="background-image:url(\'' + logo + '\')"></div>' : '';

        var tagsHtml = '';
        if (cats && cats.length) {
            tagsHtml += '<a href="' + escAttr(cats[0].link) + '" class="nav-card-tag tag-cat">' + esc(cats[0].name) + '</a>';
        }
        if (tags) {
            tags.slice(0, 2).forEach(function (tag) {
                tagsHtml += '<a href="' + escAttr(tag.link) + '" class="nav-card-tag"># ' + esc(tag.name) + '</a>';
            });
        }

        return '<article class="nav-card">' +
            '<a href="' + escAttr(site.link) + '" class="nav-card-main">' +
            '<div class="nav-card-media">' + blurBg +
            (logo ? '<img src="' + logo + '" alt="' + name + '" class="nav-card-img" loading="lazy">' : '<span class="nav-card-letter">' + firstLetter + '</span>') +
            '</div>' +
            '<div class="nav-card-body">' +
            '<h3 class="nav-card-title"><b>' + name + '</b></h3>' +
            (showVisitLabel ? '<div class="nav-card-desc">最近访问</div>' : (desc ? '<div class="nav-card-desc">' + desc + '</div>' : '')) +
            '</div></a>' +
            '<div class="nav-card-footer">' +
            '<div class="nav-card-tags">' + tagsHtml + '</div>' +
            '<a href="' + url + '" target="_blank" rel="noopener" class="nav-card-togo" title="直达">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>' +
            '</a></div></article>';
    }

    // ── 从 _embedded 提取分类标签 ─────────────────────────────────────────────
    function extractTerms(site) {
        var embedded = (site._embedded && site._embedded['wp:term']) ? site._embedded['wp:term'] : [];
        return { cats: embedded[0] || [], tags: embedded[1] || [] };
    }

    // ── 显示收藏 ──────────────────────────────────────────────────────────────
    function showFavorites() {
        var favs = JSON.parse(localStorage.getItem('nav_favorites') || '[]');
        var list = document.getElementById('favorites-list');
        if (!list) return;

        if (favs.length === 0) {
            list.innerHTML = '<div class="nav-empty">暂无收藏网站<br><a href="' + cfg.archiveUrl + '" onclick="switchView(\'all\')">浏览全部网站</a></div>';
            return;
        }
        list.innerHTML = '<div class="nav-empty">加载中...</div>';

        fetch(cfg.restBase + '?_embed&include=' + favs.join(','))
            .then(function (r) { return r.json(); })
            .then(function (sites) {
                if (!sites || !sites.length) {
                    list.innerHTML = '<div class="nav-empty">收藏的网站已失效</div>';
                    return;
                }
                var sorted = favs.map(function (id) {
                    return sites.find(function (s) { return s.id == id; });
                }).filter(Boolean);

                list.innerHTML = sorted.map(function (site) {
                    var meta = site.nav_meta || {};
                    var terms = extractTerms(site);
                    return renderCard(site, meta, terms.cats, terms.tags, false);
                }).join('');
            })
            .catch(function () {
                list.innerHTML = '<div class="nav-empty">加载失败，请刷新重试</div>';
            });
    }

    // ── 显示历史 ──────────────────────────────────────────────────────────────
    function showHistory() {
        var history = JSON.parse(localStorage.getItem('nav_history') || '[]');
        var list = document.getElementById('history-list');
        if (!list) return;

        if (history.length === 0) {
            list.innerHTML = '<div class="nav-empty">暂无访问记录</div>';
            return;
        }

        var postIds = history.filter(function (item) { return item.id; }).map(function (item) { return item.id; });

        if (postIds.length === 0) {
            list.innerHTML = history.map(function (item) {
                var firstLetter = esc(item.name.charAt(0).toUpperCase());
                var logoUrl = escAttr(item.logo || '');
                var itemUrl = escAttr(item.url || '#');
                var itemName = esc(item.name || '');
                var blurBg = logoUrl ? '<div class="blur-bg" style="background-image:url(\'' + logoUrl + '\')"></div>' : '';
                return '<article class="nav-card">' +
                    '<a href="' + itemUrl + '" class="nav-card-main" target="_blank" rel="noopener">' +
                    '<div class="nav-card-media">' + blurBg +
                    (logoUrl ? '<img src="' + logoUrl + '" alt="' + itemName + '" class="nav-card-img" loading="lazy">' : '<span class="nav-card-letter">' + firstLetter + '</span>') +
                    '</div><div class="nav-card-body">' +
                    '<h3 class="nav-card-title"><b>' + itemName + '</b></h3>' +
                    '<div class="nav-card-desc">最近访问</div></div></a>' +
                    '<div class="nav-card-footer"><div class="nav-card-tags"></div>' +
                    '<a href="' + itemUrl + '" target="_blank" rel="noopener" class="nav-card-togo" title="直达">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>' +
                    '</a></div></article>';
            }).join('');
            return;
        }

        list.innerHTML = '<div class="nav-empty">加载中...</div>';

        fetch(cfg.restBase + '?_embed&include=' + postIds.join(','))
            .then(function (r) { return r.json(); })
            .then(function (sites) {
                if (!sites || !sites.length) {
                    list.innerHTML = '<div class="nav-empty">历史记录已失效</div>';
                    return;
                }
                var sorted = history.map(function (item) {
                    if (item.id) {
                        return sites.find(function (s) { return s.id == item.id; });
                    }
                    return null;
                }).filter(Boolean);

                list.innerHTML = sorted.map(function (site) {
                    var meta = site.nav_meta || {};
                    var terms = extractTerms(site);
                    return renderCard(site, meta, terms.cats, terms.tags, false);
                }).join('');
            })
            .catch(function () {
                list.innerHTML = '<div class="nav-empty">加载失败，请刷新重试</div>';
            });
    }

    // ── 详情页：分享功能 ──────────────────────────────────────────────────────
    var shareData = { title: '', url: '' };

    window.openShare = function (title, url) {
        shareData = { title: title, url: url };
        var overlay = document.getElementById('shareOverlay');
        if (overlay) overlay.classList.add('active');
    };

    window.closeShare = function (e) {
        if (!e || e.target.id === 'shareOverlay') {
            var overlay = document.getElementById('shareOverlay');
            if (overlay) overlay.classList.remove('active');
        }
    };

    window.shareTo = function (platform) {
        var url = encodeURIComponent(shareData.url);
        var title = encodeURIComponent(shareData.title);
        var shareUrl = '';
        switch (platform) {
            case 'weixin':
                var qrImg = document.getElementById('qrImage');
                if (qrImg) qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(shareData.url);
                var qrOverlay = document.getElementById('qrOverlay');
                if (qrOverlay) qrOverlay.classList.add('active');
                closeShare();
                return;
            case 'weibo':
                shareUrl = 'https://service.weibo.com/share/share.php?url=' + url + '&title=' + title;
                break;
            case 'qq':
                shareUrl = 'https://connect.qq.com/widget/shareqq/index.html?url=' + url + '&title=' + title;
                break;
        }
        if (shareUrl) window.open(shareUrl, '_blank', 'width=600,height=500');
        closeShare();
    };

    window.copyLink = function () {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(shareData.url).then(function () {
                alert('链接已复制到剪贴板');
                closeShare();
            });
        } else {
            var input = document.createElement('input');
            input.value = shareData.url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            alert('链接已复制到剪贴板');
            closeShare();
        }
    };

    window.showQrCode = function (url) {
        var qrImg = document.getElementById('qrImage');
        if (qrImg) qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(url);
        var qrOverlay = document.getElementById('qrOverlay');
        if (qrOverlay) qrOverlay.classList.add('active');
    };

    window.closeQrCode = function (e) {
        if (e && e.target.id === 'qrOverlay') {
            var qrOverlay = document.getElementById('qrOverlay');
            if (qrOverlay) qrOverlay.classList.remove('active');
        }
    };

    // ── 详情页：评分功能 ──────────────────────────────────────────────────────
    var visitorId = localStorage.getItem('nav_visitor_id') || Math.random().toString(36).substring(2, 15);
    localStorage.setItem('nav_visitor_id', visitorId);

    function loadRating() {
        if (!cfg.ratingUrl || !cfg.postId) return;
        fetch(cfg.ratingUrl + encodeURIComponent(visitorId))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                var data = res.data;
                var scoreEl = document.getElementById('rating-score');
                var countEl = document.getElementById('rating-count');
                if (scoreEl) scoreEl.textContent = data.avg.toFixed(1);
                if (countEl) countEl.textContent = data.count + ' 人评分';

                var starsHtml = '';
                var fullStars = Math.floor(data.avg);
                var hasHalf = data.avg % 1 >= 0.5;
                for (var i = 1; i <= 5; i++) {
                    if (i <= fullStars) starsHtml += '<span class="star">★</span>';
                    else if (i === fullStars + 1 && hasHalf) starsHtml += '<span class="star" style="opacity:0.6;">★</span>';
                    else starsHtml += '<span class="star empty">★</span>';
                }
                var starsEl = document.getElementById('rating-stars-display');
                if (starsEl) starsEl.innerHTML = starsHtml;

                if (res.user_rated) {
                    var actionEl = document.getElementById('rating-action');
                    if (actionEl) actionEl.style.display = 'none';
                    if (countEl) countEl.textContent += ' · 您已评分 ' + res.user_rating + ' 星';
                }
            });
    }

    window.submitRating = function (rating) {
        if (!cfg.rateUrl || !cfg.postId) return;
        fetch(cfg.rateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: cfg.postId, rating: rating, visitor_id: visitorId })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            var msgEl = document.getElementById('rating-message');
            if (!msgEl) return;
            if (res.success) {
                msgEl.textContent = '感谢您的评分！';
                msgEl.className = 'nav-rating-message success';
                loadRating();
            } else {
                msgEl.textContent = res.message || '评分失败';
                msgEl.className = 'nav-rating-message';
            }
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        loadRating();
    });

    // ── 详情页：访问历史 + 点击记录 ───────────────────────────────────────────
    window.recordVisit = function (postId, name, url, logo) {
        var visit = { id: postId, name: name, url: url, logo: logo, time: Date.now() };
        var history = JSON.parse(localStorage.getItem('nav_history') || '[]');
        history = history.filter(function (h) { return h.id !== postId; });
        history.unshift(visit);
        history = history.slice(0, 20);
        localStorage.setItem('nav_history', JSON.stringify(history));
    };

    // 详情页点击记录（含历史写入）
    window.recordDetailClick = function (postId, name, permalink, logo) {
        fetch(cfg.clickUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId })
        }).catch(function () {});
        recordVisit(postId, name, permalink, logo);
    };

    // ── 详情页：SEO 权重查询 ──────────────────────────────────────────────────
    (function () {
        if (!cfg.weightUrl || !cfg.siteDomain) return;
        var box = document.getElementById('seo-weight-content');
        if (!box) return;

        // 先检查 HTTP 状态，未登录用户访问需带上 cookie（WordPress 允许公开访问但有nonce检查）
        fetch(cfg.weightUrl + '?domain=' + encodeURIComponent(cfg.siteDomain), { credentials: 'include' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            })
            .then(function (res) {
                if (!res.success) {
                    box.innerHTML = '<span style="color:var(--zhuoer-color-text-muted, #5f6368);font-size:0.8125rem;">' + (res.message || '暂无数据') + '</span>';
                    return;
                }
                var d = res.data;
                if (!d || !d.weights) {
                    box.innerHTML = '<span style="color:var(--zhuoer-color-text-muted, #5f6368);font-size:0.8125rem;">暂无数据</span>';
                    return;
                }
                var weights = d.weights;
                var html = '<div class="seo-weight-grid">';
                var engines = {
                    'baidu': { name: '百度', color: '#2932e1' },
                    'baidu_m': { name: '移动', color: '#ffa500' },
                    '360': { name: '360', color: '#1eb300' },
                    'sogou': { name: '神马', color: '#ff6600' },
                    'toutiao': { name: '头条', color: '#ea4141' }
                };
                for (var key in engines) {
                    if (weights[key] !== undefined) {
                        var w = parseInt(weights[key]) || 0;
                        var info = engines[key];
                        var bg = w > 0 ? info.color : '#999';
                        html += '<div class="seo-capsule">';
                        html += '<div class="seo-capsule-left">' + info.name + '</div>';
                        html += '<div class="seo-capsule-right" style="background:' + bg + ';">' + w + '</div>';
                        html += '</div>';
                    }
                }
                html += '</div>';
                box.innerHTML = html;
            })
            .catch(function () {
                box.innerHTML = '<span style="color:var(--zhuoer-color-text-muted, #5f6368);font-size:0.8125rem;">查询失败</span>';
            });
    })();

    // ── 详情页：收藏功能 ──────────────────────────────────────────────────────
    (function () {
        if (!cfg.postId) return;
        var btn = document.getElementById('favBtn');
        var icon = document.getElementById('favIcon');
        var text = document.getElementById('favText');
        if (!btn) return;

        var favorites = JSON.parse(localStorage.getItem('nav_favorites') || '[]');
        var isFav = favorites.indexOf(cfg.postId) > -1;

        updateFavUI(isFav);

        function updateFavUI(isFavorite) {
            if (icon) icon.textContent = isFavorite ? '❤️' : '🤍';
            if (text) text.textContent = isFavorite ? '已收藏' : '收藏';
            if (isFavorite) btn.classList.add('active');
            else btn.classList.remove('active');
        }

        window.toggleFavorite = function (pid) {
            var favs = JSON.parse(localStorage.getItem('nav_favorites') || '[]');
            var idx = favs.indexOf(pid);
            if (idx > -1) {
                favs.splice(idx, 1);
                updateFavUI(false);
            } else {
                favs.push(pid);
                updateFavUI(true);
            }
            localStorage.setItem('nav_favorites', JSON.stringify(favs));
            updateQuickCounts();
        };
    })();

    // ── page-nav 专用：Tab 切换 ───────────────────────────────────────────────
    window.switchTab = function (tab) {
        document.querySelectorAll('.nav-tab-btn').forEach(function (btn) {
            btn.classList.remove('active');
        });
        var activeBtn = document.querySelector('.nav-tab-btn[data-tab="' + tab + '"]') ||
                        document.querySelector('.nav-tab-btn[onclick*="' + tab + '"]');
        if (activeBtn) activeBtn.classList.add('active');

        document.querySelectorAll('.nav-tab-content').forEach(function (el) {
            el.style.display = 'none';
        });
        var content = document.getElementById('tab-' + tab);
        if (content) content.style.display = 'block';

        if (tab === 'favorites') showFavorites();
        else if (tab === 'history') showHistory();
    };

    // ── page-nav 专用：快速收藏（卡片上的❤️按钮）──────────────────────────────
    window.quickFavorite = function (postId, name, url, logo, btnEl) {
        var favs = JSON.parse(localStorage.getItem('nav_favorites') || '[]');
        var idx = favs.indexOf(postId);
        if (idx > -1) {
            favs.splice(idx, 1);
            if (btnEl) btnEl.textContent = '🤍';
        } else {
            favs.push(postId);
            if (btnEl) btnEl.textContent = '❤️';
            // 同时记入历史
            recordVisit(postId, name, url, logo);
        }
        localStorage.setItem('nav_favorites', JSON.stringify(favs));
        updateQuickCounts();
    };

    // ── page-nav 专用：数据管理 ────────────────────────────────────────────────
    window.showDataManager = function () {
        var overlay = document.getElementById('dataManagerOverlay');
        if (overlay) overlay.classList.add('active');
    };

    window.closeDataManager = function (e) {
        if (!e || e.target.id === 'dataManagerOverlay') {
            var overlay = document.getElementById('dataManagerOverlay');
            if (overlay) overlay.classList.remove('active');
        }
    };

    window.exportData = function () {
        var data = {
            favorites: JSON.parse(localStorage.getItem('nav_favorites') || '[]'),
            history: JSON.parse(localStorage.getItem('nav_history') || '[]'),
            exportTime: new Date().toISOString()
        };
        var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'nav-data-' + new Date().toISOString().slice(0, 10) + '.json';
        a.click();
        URL.revokeObjectURL(a.href);
    };

    window.importData = function (input) {
        var file = input.files && input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            try {
                var data = JSON.parse(e.target.result);
                if (data.favorites && Array.isArray(data.favorites)) {
                    localStorage.setItem('nav_favorites', JSON.stringify(data.favorites));
                }
                if (data.history && Array.isArray(data.history)) {
                    localStorage.setItem('nav_history', JSON.stringify(data.history));
                }
                updateQuickCounts();
                alert('数据导入成功！页面即将刷新。');
                location.reload();
            } catch (err) {
                alert('导入失败：文件格式错误');
            }
        };
        reader.readAsText(file);
    };

    window.clearAllData = function () {
        if (!confirm('确定要清空所有收藏和访问记录吗？此操作不可撤销。')) return;
        localStorage.removeItem('nav_favorites');
        localStorage.removeItem('nav_history');
        updateQuickCounts();
        closeDataManager();
        alert('数据已清空');
        location.reload();
    };

    // ── 侧边栏分类下拉展开/折叠 ──────────────────────────────────────────────
    window.toggleCatChildren = function (trigger) {
        var row = trigger.closest('.cat-item');
        var childList = row.querySelector('.cat-children');
        if (!childList) return;
        var isOpen = childList.style.display !== 'none';
        childList.style.display = isOpen ? 'none' : 'block';
        var icon = row.querySelector('.cat-toggle-icon');
        if (icon) icon.textContent = isOpen ? '▸' : '▾';
    };

})();
