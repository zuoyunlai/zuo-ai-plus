# Zuo AI Plus - WordPress AI 内容助手 + 导航模块

> 集成国内主流大模型的 WordPress 站点 AI 助手，附带智能导航目录模块

**版本**: 1.5.19
**更新**: 2026-04-23
**测试环境**: WordPress 6.0-6.9, PHP 8.1+

---

## 功能概览

### AI 内容创作

| 功能 | 说明 |
|------|------|
| ⚡ 多模型支持 | 智谱GLM-5、阿里通义千问、MiniMax M2.7、Kimi K2.5、DeepSeek V3.2、DashScope，后台自由切换 |
| 📝 文章生成 | 输入标题/提示，一键生成完整文章 |
| 🔄 内容改写 | 重写/润色现有内容，保持原意优化表达 |
| 🎭 风格语气 | 专业正式、轻松随性、温暖亲切等多种文风 |
| 📝 内容续写 | 智能续写现有草稿，自动判断续写方向 |
| 🏷️ 自动标签 | AI 提取 3-5 个 SEO 友好标签 |
| 🎯 标题优化 | AI 自动生成 30-60 字符 SEO 友好标题 |
| 📖 自动摘要 | 一键生成约 100 字文章摘要 |
| 🔗 别名生成 | 自动生成 WordPress 友好的 URL slug |
| 📚 知识库 | 设置背景知识，AI 生成时自动参考品牌/产品信息 |
| 🖼️ 特色图生成 | 根据文章内容生成封面图 |
| 🖼️ 插图生成 | 在文章任意位置插入 AI 生成的配图 |
| 🌐 多语言翻译 | 中英日韩法德等 12 种语言互译 |
| 🔍 SEO 诊断 | 全站批量诊断评分 + AI 一键优化 |
| 💬 客服聊天 | 浮动 AI 聊天窗口，短代码 `[ai_plus_chat]` 嵌入 |
| 📊 使用统计 | 实时追踪 Token 消耗、操作次数、各模型分布 |

### AI 导航模块

| 功能 | 说明 |
|------|------|
| 🔗 AI 全量获取 | 输入网址，自动抓取名称/关键词/描述/Logo/截图 |
| 📸 特色图自动化 | Chrome Headless 截图自动注册为 WP 媒体附件，设为特色图 + AI alt/标题/描述，中文 metadata 智能备用 |
| ✨ AI 生成简介 | 300-500 字网站详细介绍 |
| 🤖 标签自动提取 | 从简介中提取 nav_tag 分类标签，自动写入 WordPress 标签盒 |
| 📊 SEO 权重胶囊 | 百度/360/搜狗/头条/移动排名彩色胶囊显示 |
| 🌳 分类树侧栏 | 层级分类展开/折叠 |
| ⭐ 收藏&历史 | LocalStorage 本地化收藏和最近访问 |
| 🌙 暗色模式 | CSS 变量完整支持，与主题切换同步 |
| 📱 响应式设计 | 480/768/900px 三断点移动端适配 |
| 🔍 SEO 结构化数据 | Schema.org BreadcrumbList + WebSite JSON-LD |
| 📲 分享&二维码 | 移动端分享弹窗 + QR Code |

---

## 截图策略（3 层优先级）

1. **og:image** — 目标网站自带的预览图，最快、零成本
2. **Chrome Headless** — 本地截图保存到 `uploads/nav-screenshots/`，7 天缓存，注册为 WP 媒体附件
3. **thum.io** — 兜底在线截图服务

---

## 安装

1. 上传 `zuo-ai-plus` 到 `/wp-content/plugins/`
2. 在 WordPress 插件菜单激活
3. 进入 **Zuo AI Plus → 文本模型** 配置 API Key
4. 开始使用 Gutenberg 侧边栏或导航模块

## 配置要求

- WordPress 6.0+
- PHP 8.1+
- API Key（各模型提供商均有免费额度）
- Chrome/Chromium（可选，用于本地截图，无则用 thum.io 兜底）

---

## 目录结构

```
zuo-ai-plus/
├── zuo-ai-plus.php          # 主入口
├── about.php                # 关于页面
├── readme.txt               # WP官方readme
├── README.md                # 项目说明
├── src/
│   ├── Controllers/         # REST API 控制器
│   ├── Models/              # AI 模型封装
│   ├── Admin/               # 后台管理
│   │   └── views/           # 模板视图
│   ├── Services/            # 服务层(Crypto/Cache等)
│   └── Traits/              # 可复用特性
├── Templates/               # 前端模板
├── Assets/
│   ├── css/nav.v2.css       # 导航模块样式
│   └── js/nav.js           # 导航模块脚本
└── languages/               # 多语言翻译
```

---

## 更新日志

### 1.5.19 (2026-04-23)
- Fix: 特色图中文 metadata 智能备用 — AI 未返回中文时，从文章标题+内容自动提取关键词生成 alt/标题/描述
- Fix: 前端 JS 双层包装属性访问 bug（`d.*` 属性路径修复）

### 1.5.13 (2026-04-22)
- 新增：AI 导航模块（全量获取/简介/标签/截图/特色图）
- 新增：SEO 权重胶囊 + Schema.org 结构化数据
- 新增：暗色模式 CSS 变量 + 响应式三断点
- 修复：PHP exec() 命名空间 + disable_functions
- 修复：标签 # 前缀移除 + PHP代码泄露修复
- 修复：暗色模式 20+ 硬编码颜色 → CSS 变量

### 1.3.1
- 初始发布版本
