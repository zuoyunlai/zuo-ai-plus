# Zuo AI Plus - WordPress AI 内容助手插件

> 集成国内主流大模型（智谱GLM、阿里通义、MiniMax、Kimi）的 WordPress 站点 AI 助手

## 功能

- ⚡ **多模型支持**：智谱GLM、阿里通义千问、MiniMax、Kimi，后台自由切换
- 📝 **文章生成**：根据标题/提示生成完整文章、扩写段落、提炼摘要
- 🏷️ **自动摘要/关键词**：一键提取文章摘要、关键词、推荐分类
- 🖼️ **图片生成**：生成文章特色图和内容插图
- 🌐 **多语言翻译**：中英日韩法德等语言翻译发布
- 💬 **客服聊天窗口**：前台浮动聊天按钮，短代码 `[ai_plus_chat]` 嵌入
- 📊 **SEO 优化建议**：标题/描述/关键词优化分析
- 📈 **使用统计**：Token消耗、对话记录后台可查

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
