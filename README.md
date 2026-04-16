# Zuo AI Plus - WordPress AI 内容助手插件

> 集成国内主流大模型（智谱GLM、阿里通义、MiniMax、Kimi、DeepSeek）的 WordPress 站点 AI 助手

**版本**: 1.2.4  
**测试环境**: WordPress 6.0-6.8, PHP 8.1+

## 功能

- ⚡ **多模型支持**：智谱GLM、阿里通义千问、MiniMax、Kimi、DeepSeek，后台自由切换
- 📝 **文章生成**：根据标题/提示生成完整文章
- 🔄 **内容改写**：重写/润色现有内容，优化表达方式
- 🎭 **风格语气**：专业正式、轻松随性、温暖亲切等多种文风选择
- 📝 **内容续写**：在现有内容基础上续写更多细节
- ⚖️ **智能续写**：根据上下文自动判断续写方向
- 🏷️ **自动摘要/关键词/标签**：一键提取文章摘要、关键词、SEO友好标签
- 🎯 **标题优化**：AI 自动生成 SEO 友好的最佳标题
- 🔗 **别名生成**：自动生成 WordPress 友好的 URL slug
- 📚 **知识库**：设置背景知识，AI 生成时自动参考品牌/产品信息
- 🖼️ **特色图生成**：根据文章内容生成封面图
- 🖼️ **插图生成**：在文章任意位置插入 AI 生成的配图
- 🌐 **多语言翻译**：中英日韩法德等 12 种语言互译
- 💬 **客服聊天窗口**：前台浮动聊天按钮，短代码 `[ai_plus_chat]` 嵌入
- 🔍 **SEO 诊断**：全站文章批量诊断、得分分析、AI 一键优化
- 📊 **使用统计**：实时追踪 Token 消耗、操作次数、各模型使用分布
- 🔧 **多模型切换**：后台任意位置快速切换 AI 模型对比测试
- 🧱 **Gutenberg 区块**：聊天区块 + 图片生成区块
- 🔄 **Playground**：自由对话测试所有模型

## 安装

1. WordPress 后台
2. WordPress 后台 → 插件 → 启用 AI Plus
3. 进入 **AI Plus → API设置**，填入各模型 API Key
4. 开始使用

## API Key 申请

| 模型 | 申请地址 |
|------|---------|
| 智谱 GLM | https://open.bigmodel.cn/ |
| 阿里通义千问 | https://dashscope.console.aliyun.com/ |
| MiniMax | https://www.minimaxi.com/ |
| Kimi | https://platform.moonshot.cn/ |

## 使用方式

### 文章编辑器 AI 助手
在文章编辑页面右侧边栏，点击按钮即可使用各项 AI 功能。

### 客服聊天窗口
浮动按钮自动出现在前台页面右下角，短代码可嵌入文章/页面：

```
[ai_plus_chat model="zhipu"]
```

### REST API
```
POST /wp-json/ai-plus/v1/chat
POST /wp-json/ai-plus/v1/generate
POST /wp-json/ai-plus/v1/image
POST /wp-json/ai-plus/v1/translate
POST /wp-json/ai-plus/v1/seo
GET  /wp-json/ai-plus/v1/models
```

## 系统要求

- WordPress 6.0+
- PHP 8.1+
- OpenSSL PHP 扩展（用于HTTPS请求）

## 更新日志

### v1.2.4 (2025-04-15)
- ✨ 新增内容改写功能
- ✨ 新增风格语气选择（专业/轻松/温暖等）
- ✨ 新增知识库功能
- ✨ 新增插图生成功能
- ✨ 新增智能续写
- ✨ 新增多模型快速切换
- ✨ 新增使用统计面板
- 📱 移动端后台全面优化
- 🐛 修复统计功能，支持所有AI操作类型
