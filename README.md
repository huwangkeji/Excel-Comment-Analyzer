#  Excel 评论分析器（Comment Analyzer）

> 纯本地运行的 Excel 评论数据分析工具 — 零依赖、无数据库、无 AI、无需联网

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![Version](https://img.shields.io/badge/Version-1.0-brightgreen)]()

##  简介

Excel 评论分析器是一款专门用于分析评论数据的本地工具。只需上传 Excel / CSV 文件，即可自动解析评论数据，并对评论进行统计、分类、筛选、搜索、关键词分析、高频观点分析等操作，帮助用户快速整理评论内容，为短视频选题研究、产品调研等场景提供数据支持。

**整个系统仅作为分析工具，不保存任何数据。**

##  特性

- ✔ **纯本地运行** — 无需数据库、无需联网、无需 AI
- ✔ **零外部依赖** — 纯 PHP 原生实现 XLSX 解析，不需要 Composer
- ✔ **即开即用** — 浏览器打开即可使用，无需复杂配置
- ✔ **无需账号** — 没有用户系统，打开即用
- ✔ **数据安全** — 基于 Session 临时存储，关闭页面即清除
- ✔ **响应快速** — 所有算法本地执行，无网络延迟

##  功能一览

###  数据导入
| 功能 | 说明 |
|------|------|
| 格式支持 | XLSX / XLS / CSV |
| 上传方式 | 拖拽上传 / 点击选择 |
| 智能识别 | 自动识别评论内容、点赞数、时间、用户名、作品标题、平台等字段 |
| 字段映射 | 自动匹配表头，支持中文 / 英文列名 |

###  数据分析
| 模块 | 说明 |
|------|------|
| 评论浏览 | 分页列表、多列排序、滚动加载 |
| 实时搜索 | 模糊搜索评论内容和用户名，即时响应 |
| 组合筛选 | 点赞范围、评论长度、时间范围、关键词、用户名等多条件叠加 |
| 关键词统计 | N-gram 中文分词 + 停用词过滤，词云可视化 |
| 高频观点 | 归一化文本匹配，自动聚合重复观点 |
| 评论聚类 | 10 个分类（广告 / 收费 / 会员 / 更新 / 功能 / 兼容 / 体验 / 客服 / 竞品 / 内容） |
| 热门排行 | TOP 10 / 50 / 100 热门评论 |
| 重复检测 | 自动检测刷屏评论，统计重复率和重复组数 |
| 长度统计 | 最长 / 最短 / 平均长度 + 字数分布柱状图 |
| 数据面板 | 评论总数、平均点赞、最高 / 最低点赞、关键词数、平台分布、24h 活跃热力图 |

###  数据导出
| 功能 | 说明 |
|------|------|
| 快速复制 | 单条复制 / 整行复制 / JSON 复制 / Markdown 复制 |
| 批量导出 | JSON / CSV / TXT / Markdown 一键下载 |
| 数据清洗 | HTML 标签去除、空格规范化、特殊字符过滤、连续标点压缩 |

##  技术栈

| 层级 | 技术 |
|------|------|
| 语言 | PHP 7.4+ |
| 运行环境 | PHP 内置服务器 / Nginx / Apache |
| 前端 | HTML5 + CSS3 + 原生 JavaScript（无框架） |
| Excel 解析 | ZipArchive + SimpleXML（纯 PHP，无第三方库） |
| 数据库 | 无 |
| 存储 | PHP Session（临时） |

##  快速开始

### 环境要求

- PHP >= 7.4
- PHP 扩展：`zip`、`xml`、`mbstring`、`session`（均为 PHP 默认内置）

### 运行

```bash
# 1. 进入项目目录
cd comment-analyzer

# 2. 启动 PHP 内置服务器
php -S 127.0.0.1:8080

# 3. 浏览器打开
# http://127.0.0.1:8080/index.php
```

### Nginx 配置示例

```nginx
server {
    listen 80;
    server_name localhost;
    root /path/to/comment-analyzer;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

##  项目结构

```
comment-analyzer/
├── index.php                  # 前端单页应用入口
├── api.php                    # API 路由（所有 AJAX 端点）
├── config.php                 # 全局配置（session、路径、限制）
├── includes/
│   ├── ExcelReader.php        # XLSX/CSV/XLS 纯 PHP 解析器
│   ├── DataCleaner.php        # 数据清洗（HTML 标签、空格、特殊字符等）
│   └── CommentAnalyzer.php    # 核心分析引擎（全部本地算法）
├── assets/
│   ├── css/
│   │   └── style.css          # 样式表
│   └── js/
│       └── app.js             # 前端交互逻辑
├── sessions/                  # 临时 Session 存储（自动创建）
├── uploads/                   # 临时上传目录（自动创建）
├── LICENSE                    # 开源协议
└── README.md                  # 本文件
```

##  API 文档

所有 API 通过 `api.php` 路由，使用 `action` 参数指定操作。

### 端点列表

| Action | 方法 | 说明 | 参数 |
|--------|------|------|------|
| `upload` | POST | 上传 Excel 文件 | `file` (multipart) |
| `parse` | GET | 解析已上传的文件 | — |
| `comments` | GET | 获取评论列表 | `page`, `per_page`, `sort`, `order` |
| `search` | GET | 搜索评论 | `q` (关键词), `page`, `per_page` |
| `filter` | GET | 筛选评论 | `filters` (JSON), `page`, `per_page` |
| `keywords` | GET | 关键词统计 | `limit`, `keyword` |
| `opinions` | GET | 高频观点统计 | `limit` |
| `clusters` | GET | 评论聚类分析 | — |
| `hot` | GET | 热门评论排行 | `limit` (10/50/100) |
| `duplicates` | GET | 重复评论检测 | — |
| `length_stats` | GET | 评论长度统计 | — |
| `stats` | GET | 整体数据统计 | — |
| `export` | GET | 导出数据 | `format` (json/csv/txt/markdown), `mode` |
| `copy_all` | GET | 批量复制 | `format`, `mode` |
| `fields` | GET | 获取字段映射 | — |

### 筛选参数

```json
{
  "likes_min": "最小点赞数",
  "likes_max": "最大点赞数",
  "length_min": "最短字数",
  "length_max": "最长字数",
  "keyword": "内容关键词",
  "username": "用户名关键词",
  "time_from": "开始时间 (YYYY-MM-DD)",
  "time_to": "结束时间 (YYYY-MM-DD)",
  "platform": "平台关键词"
}
```

##  数据格式

上传的 Excel / CSV 文件建议包含以下列（工具会自动识别并映射）：

| 列名示例 | 字段说明 |
|----------|----------|
| 评论内容 / 评论 / 内容 / 正文 / content | 评论文本 |
| 点赞数量 / 点赞 / 赞 / likes | 点赞数 |
| 评论时间 / 时间 / 日期 / time | 发布时间 |
| 用户名 / 用户 / 昵称 / username | 评论者 |
| 作品标题 / 标题 / title | 相关作品 |
| 平台 / 来源 / platform | 评论来源 |

列顺序不限，缺失的字段会留空。

##  工作原理

### XLSX 解析

XLSX 文件本质是 ZIP 压缩包。解析流程：

```
.xlsx 文件 → ZipArchive 解压 → 读取 sharedStrings.xml（共享字符串表）
         → 读取 workbook.xml（工作簿结构）
         → 读取 sheet1.xml（工作表数据）
         → 映射共享字符串 → 输出结构化数据
```

不使用 PhpSpreadsheet 等第三方库，完全基于 PHP 内置的 ZipArchive + SimpleXML 实现。

### 关键词提取

采用 N-gram 滑动窗口 + 停用词过滤算法：

1. 去除 URL、日期时间、标点符号
2. 对中文文本提取 2-4 字 N-gram
3. 非中文单词保留完整单词
4. 过滤停用词和纯数字
5. 按出现频率排序

### 聚类规则

基于关键词规则匹配，10 个类别各有对应的触发词：

| 类别 | 触发词示例 |
|------|-----------|
| 广告类 | 广告、推广、营销、推广 |
| 收费类 | 收费、付费、价格、太贵 |
| 会员类 | 会员、VIP、充值、订阅 |
| 更新类 | 更新、升级、新版、版本 |
| 功能类 | 功能、建议、需求、希望 |
| 兼容类 | 兼容、闪退、崩溃、bug |
| 体验类 | 体验、好用、难用、流畅 |
| 客服类 | 客服、售后、服务、态度 |
| 竞品类 | WPS、Office、Google、替代 |
| 内容类 | 教程、视频、学习、干货 |

##  开源协议

本项目采用 [MIT License](LICENSE) 开源协议。你可以自由使用、修改和分发本项目。

##  贡献

欢迎提交 Issue 和 Pull Request。

##  作者

Excel Comment Analyzer V1.0

---

**Made with ❤️ for data analysts & content creators**
