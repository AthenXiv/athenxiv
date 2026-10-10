[English](README.md) · **简体中文** · [日本語](README.ja.md)

# AthenXiv

随着 AGI 时代的到来，人类对知识边界的拓展正变得触手可及。在与数学家合作的过程中，GPT-5 为悬置数十年的 **Erdős 第 848 号问题**补上了证明里缺失的那一环——很多人因此第一次看到，模型站进了一个「还没有人知道的结论」之中，而不只是在复述已知的知识。

探索的权利，以及把探索结果发表出来的权利，应当属于每一个人。它不该等某个机构点头，也不该先过一遍学位、职称与经费的筛选。**AthenXiv 因此建立，并且把网站的源代码开源至此，以便更多的人能够参与进来，搭建更多的开放论文发布平台。**

开放的跨学科研究论文存档，提供 **OpenTimestamps** 证明、浏览器内 PDF 阅读，以及三十种语言的界面。

线上站点：<https://athenxiv.com/>

AthenXiv 接受任何人的投稿——不要求学位、职位、所属机构或资助方。每次上传都会做哈希并生成 OpenTimestamps 存证，读者因此可以验证某个文件在某一时刻确实已经存在。本站也会保存已在别处发表的开放获取成果，加以标注，并显示其原始发表日期与许可。

## 为什么值得自己搭一份

有两件事已经接好了，所以新实例一上线就能用，不需要额外配置：

1. **谷歌学术已经处理好了。** 无论是 athenxiv.com 还是这份开源版本，都会输出 Google Scholar 读取的 Highwire `citation_*` 元数据，以 `Content-Type: application/pdf` 响应 `/paper/{uid}.pdf`，并在每次请求时从数据库生成 `/sitemap.xml`。把自己的实例搭起来，只要保证良好的内容质量，谷歌学术就能收录。
2. **OpenTimestamps 已经配置好了。** 公开的 [opentimestamps.com](https://opentimestamps.com) 日历默认就已接入，因此就算站长什么都不改，每篇上传的论文照样会得到一份免费、可独立验证的时间戳——一份关于发布时间的密码学凭证，用于保护作者的优先权。

站点自己的内容页（关于、指南、政策）也用同样方式存证：首次部署后运行一次 `php bin/ots-stamp-pages.php`，此后每次编辑页面都会自动重新存证。

## 包含的内容

* **零依赖。** 没有 Composer，也没有 npm 构建步骤：纯 PHP 8.1+，配一个小型 MVC 内核，用 PDO 存储，CSS/JS 保持原生、直接从磁盘提供。
* **双数据库。** MySQL（生产环境）与 SQLite（开发与测试）共用同一套 schema 层；SQL 只写一次，再按驱动改写。
* **OpenTimestamps。** 每次上传都会得到一份 `.ots` 证明，多个日历的结果会被合并，证明也会自行从等待中升级为已确认。
* **PDF.js 阅读器**由应用本身提供，因此不认识 `.mjs` 的主机也能正确渲染。
* **三十种语言**，支持 `Accept-Language` 协商与逐页 hreflang，还有管理员可编辑的内容页面。
* **已为 Google Scholar 做好准备**：Highwire `citation_*` 元数据、一个以 `Content-Type: application/pdf` 响应的 `/paper/{uid}.pdf` 地址，以及每次请求都从数据库生成的站点地图。
* **审核流程**：可选的 AI 预审、不让未经审核的修订流到读者眼前的版本历史，以及为拿不到访问日志的主机准备的爬虫访问记录。

## 环境要求

* PHP 8.1 或更新版本，需带 `pdo`、`pdo_mysql` 或 `pdo_sqlite`、`mbstring`、`json`、`curl` 与 `openssl`
* MySQL 5.7+ 或 SQLite 3
* 一台 Web 服务器；本应用支持两种布局——文档根目录指向 `public/`，或者在不支持 URL 重写的主机上，把整个项目放进 Web 根目录并搭配前端控制器（`/index.php/...`）

## 安装

```bash
git clone https://github.com/AthenXiv/athenxiv.git
cd athenxiv

# 1. 配置：config/config.php 默认就是 SQLite，下面的命令可以直接跑。
#    MySQL 请改 config/config.php，或把凭据放进不进版本库的
#    config/config.local.php。所有选项都在 config/config.example.php 里说明。

# 2. 数据库（只是先看看的话，SQLite 就够了）
php bin/install.php --driver=sqlite \
    --email=you@example.com --password='change-me-please' --nickname=Keeper
php bin/migrate.php

# 3. 跑起来
php -S 127.0.0.1:8000 -t public public/router.php
```

然后打开 <http://127.0.0.1:8000/>。管理员账号就是你在第 2 步创建的那个。

生产主机请把文档根目录指向 `public/`。如果主机做不到（共享主机通常做不到），就把 `public/` 里的内容复制到 Web 根目录，并在配置中把 `app.front_controller` 设为 `index.php`，这样每个 URL 都会带上 `/index.php` 前缀。

## 测试

```bash
php tests/lang_audit.php                        # 代码里用到的每个键都存在于 en.php
php tests/v2_test.php                           # 服务、模型、邮件、版本、OTS 编解码
php tests/e2e_smoke.py   http://127.0.0.1:8000  # 公开页面与流程
php tests/e2e_admin.py   http://127.0.0.1:8000  # 管理员后台
php tests/e2e_i18n.py    http://127.0.0.1:8000  # 三十种语言
php tests/scholar_check.py https://athenxiv.com # Google Scholar 合规性（线上检查）
```

Python 套件针对一个正在运行的实例；PHP 套件则独立运行，用一个一次性的 SQLite 数据库。有些套件需要 `tests/fixtures/` 里的假外部服务（AI 端点、日历、SMTP）。这些套件使用的凭据（`*-password-2026`、演示管理员）属于测试自己创建的数据库——它们是 fixture，不是配置。

## 目录结构

```
app/          内核（路由器、请求、响应、i18n、设置）、模型、服务、控制器
bin/          CLI：安装、迁移、导入、翻译写入、OTS 升级
config/       配置模板与你的本地覆盖文件（永不提交）
database/     schema、种子数据、内容页的翻译源
public/       Web 根目录：前端控制器、静态资源、内置的 PDF.js
resources/    视图（原生 PHP 模板）与三十个语言文件
routes/       路由表
tests/        类单元测试与端到端套件，外加 fixture
```

## 参与贡献

欢迎提交 bug 报告与 pull request。提 pull request 之前，请先跑一遍上面的测试套件，并保持语言文件完整：当模板里用到的某个字符串在 `resources/lang/en.php` 中缺失时，`tests/lang_audit.php` 会失败。

## 许可

MIT —— 详见 [LICENSE](LICENSE)。线上站点发表的论文与其他内容**不**在本许可的覆盖范围内；每份成果都保留自己的权利与许可，站点会在论文页面显示它们。
