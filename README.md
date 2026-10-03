# AthenXiv

> **接手本项目请先读 `PROJECT-GUIDE.local.md`**（本地文件，含数据库/FTP 凭据，已 gitignore）：
> 里面记录了生产环境事实、部署流程、数据模型与踩过的坑。本 README 是公开文档，不含凭据。

> 一个面向哲学的开放论文发布平台：投稿即自动做 **OpenTimestamps / 比特币区块链存证**，带 PDF 在线阅读、多语言界面与完整的管理员后台。

名字是拼出来的：**Athena**（雅典娜，智慧与技艺之神，也是柏拉图学园所供奉的神）＋ **-Xiv**（archive 的后缀，也是 arXiv 一类论文库的尾巴）——「雅典娜的文库」。中译名仍可读作「雅典学院」，灵感来自拉斐尔 1511 年的壁画《雅典学院》（*The School of Athens*）：柏拉图与亚里士多德走在中央，四周是几何学家、天文学家、语法学家，各有各的争论。

---

## 1. 它是什么

不是个人主页，而是 **philpapers.org 那样的论文发布平台**：

* 任何人可注册、投稿（PDF 必填），编辑审核后进入「一区／二区／三区／预印本」等**分区**展示；
* 每篇论文上传的瞬间就对文件做 SHA-256 摘要并提交到公开的 OpenTimestamps 日历，**无论审核是否通过**都会生成存证，用户可自行下载 `.ots` 证明到官网独立验证；
* 管理员可改名换 Logo、审核／下架／代理上传、建号／封禁用户、管理分区与分类、调整上传体积上限（并可「破例」放行超大文件）；
* 界面支持**三十种语言**（中文简繁、日、韩、英、法、德、西、葡、意、俄、乌、波、荷、瑞、丹、芬、土、
  阿拉伯、波斯、希伯来、印地、印尼、越、泰、希腊、捷、罗、匈、加泰罗尼亚），按浏览器语言自动切换，
  不支持的语言回退英文，阿拉伯/波斯/希伯来自动 RTL；
* 论文正文语言可从 **74 种**里选，列表里没有的可直接填写，审核通过后自动进入语言筛选栏；

## 2. 技术栈与设计取舍

| 方面 | 选择 | 理由 |
|---|---|---|
| 语言 | **纯 PHP 8.1+**，自写微型 MVC | 不依赖 Composer：虚拟主机上传即可运行，无需 `composer install` 或构建步骤 |
| 数据库 | **MySQL**（生产）／**SQLite**（本地验证） | PDO 双驱动，同一套 schema 语义；SQLite 用于无 MySQL 时自测 |
| 前端 | 原生 HTML + 扁平 CSS + 少量原生 JS | 无框架、无打包；JS 仅做增强，禁用 JS 也能投稿 |
| PDF 预览 | **PDF.js 4.10.38**（内置，Apache-2.0） | 成熟开源方案，不重复造轮子；含 CJK cmaps，中文/日文/韩文 PDF 无内嵌字体也能显示 |
| Markdown | **Parsedown 1.7.4**（内置，MIT，安全模式） | 单文件、无依赖；个人主页装饰用 |
| 存证 | **自写 OpenTimestamps 客户端**（纯 PHP + cURL） | 官方只有 Python/JS 实现；本项目实现了 `.ots` 的解析、合并、升级与序列化 |

**零第三方运行时依赖**是刻意的：整套系统只需 PHP + PDO 扩展，正是 Google 学术收录指南所期待的那种「普通主机也能跑的论文站」。

## 3. 目录结构

```
athenaeum/
├── public/                     ← 网站根目录（只把这里暴露给互联网）
│   ├── index.php               前端控制器
│   ├── router.php              php -S 开发服务器入口
│   ├── .htaccess               Apache 重写 + .mjs MIME + 安全头
│   ├── assets/{css,js,img}/    样式、脚本、favicon
│   └── vendor/pdfjs/           内置 PDF.js 阅读器（见 VENDOR.md）
├── app/
│   ├── bootstrap.php           配置加载、自动加载、目录准备
│   ├── helpers.php             e() / __() / url() / setting() …
│   ├── Core/                   框架内核（16 个类，见下）
│   ├── Models/                 User, Paper, Attachment, Timestamp, Section, Category, AuditLog …
│   ├── Services/               OpenTimestamps, PaperService, Uploader
│   ├── Controllers/            Home, Auth, Paper, User, Admin, Media, Api
│   └── Support/                Parsedown（内置 Markdown 解析器）
├── config/                     config.php + config.example.php + config.local.php
├── database/                   schema.mysql.sql / schema.sqlite.sql
├── resources/
│   ├── lang/{en,zh-CN,ja,ko,fr,de}.php   各 554 个键
│   └── views/                  布局 + 页面 + 局部模板
├── routes/web.php              全部路由（58 条）
├── storage/                    ← 不在 webroot，且用 .htaccess 全部拒绝
│   ├── uploads/{papers,attachments,avatars,branding}/
│   ├── ots/                    OpenTimestamps 证明文件
│   ├── database/               SQLite 文件
│   └── logs/                   运行日志
├── bin/                        install.php, create-admin.php, ots-upgrade.php, serve.cmd, serve.sh
└── tests/                      e2e_smoke.py, e2e_admin.py, e2e_i18n.py, ots_codec_test.php, lang_audit.php
```

内核类：`App`（内核/中间件）`Router` `Request` `Response`（含 HTTP Range 流式输出）`Database` `Model` `View` `Session` `Auth` `Csrf` `Settings` `I18n` `Validator` `Markdown` `Http`（cURL + CA 自动探测）`Logger` `Str` `Config`。

## 4. 安装

### 4.1 环境要求

* PHP **8.1+**，扩展：`pdo_mysql`（或 `pdo_sqlite`）、`curl`、`mbstring`、`openssl`、`json`；
  建议同时开启 `fileinfo`（MIME 识别）、`zip`/`gd`（图片缩放，可选）、`intl`（可选）。
* MySQL 5.7+ / MariaDB 10.3+（或 SQLite 3）。
* **Web 根目录必须指向 `public/`**（下面 4.5 有无法修改时的兜底方案）。

### 4.2 配置

```bash
cp config/config.example.php config/config.php
```

编辑 `config/config.php`（或另建 `config/config.local.php` 覆盖，适合放生产密码）：

* `app.key`：改成随机串，`php -r "echo bin2hex(random_bytes(32));"`
* `db.*`：MySQL 主机／库名／账号密码；本项目默认值为
  `127.0.0.1` / 库 `athenxiv` / 用户 `athenxiv`。
  **若该远程库不可达**（端口 3306 拒绝连接），本地验证可改用 SQLite：

```php
// config/config.local.php
return ['db' => ['driver' => 'sqlite']];
```

也支持环境变量覆盖：`ATHENAEUM_DB_DRIVER`、`ATHENAEUM_DB_HOST`、`ATHENAEUM_DB_USERNAME`、
`ATHENAEUM_DB_PASSWORD`、`ATHENAEUM_APP_URL`、`ATHENAEUM_CA_BUNDLE` 等（见 `app/Core/Config.php`）。

### 4.3 建表与初始化

```bash
php bin/install.php --fresh \
  --email=you@example.org --password='至少10位密码' --nickname=YourName
```

安装器会：建表（29 条语句）→ 写入 32 项默认设置 → 建立 4 个分区
（预印本／一区／二区／三区，均含 6 语名称）→ 建立 12 个哲学分类（同样 6 语）
→ 创建管理员账号。加 `--driver=sqlite` 可强制 SQLite。

**如果你是从旧版本升级上来的**，装完代码后还要跑一次迁移（全新安装可跳过，`install.php`
已经包含同样步骤）：

```bash
php bin/migrate.php --dry-run    # 先看看会改什么
php bin/migrate.php              # 建版本表/页面表、加 AI 列、播种页面与 599 个学科
```

后续管理账号：`php bin/create-admin.php --email=… --password=… --nickname=… --role=editor`

### 4.4 本地启动（含 Windows 便捷脚本）

```bash
bin\serve.cmd 8123          # Windows：自动加载所需扩展，无需改 php.ini
bin/serve.sh 8123           # Linux / macOS
# 或手动：
php -d extension=pdo_sqlite -d upload_max_filesize=32M -d post_max_size=40M \
    -S 127.0.0.1:8123 -t public public/router.php
```

打开 <http://127.0.0.1:8123>。

### 4.5 生产部署

**虚拟主机（推荐、最省事）**：把 `public/` 里的内容当作站点根目录上传；
若主机只允许站点根 = 项目根，则把 `public/` 内容放到根，再把其余目录放到上一级并修改
`public/index.php` 中的 `dirname(__DIR__)` 指向（或用根目录 `.htaccess` 的兜底规则，
它会拒绝访问 `app/ config/ database/ resources/ storage/ bin/ tests/ routes/`）。

必要的服务器配置：

* **Apache**：启用 `mod_rewrite`，允许 `.htaccess`（`public/.htaccess` 已含重写、
  `.mjs` MIME、安全响应头、静态缓存；如需更大上传体积，取消其中 `php_value` 注释）。
* **Nginx**（等价配置）：

```nginx
root /srv/athenaeum/public;
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.mjs$ { types { } default_type text/javascript; }
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
location ~ ^/(app|config|database|resources|storage|bin|tests|routes)/ { deny all; }
```

* **PHP 上传上限**：`upload_max_filesize` 与 `post_max_size`（后者必须 ≥ 前者）。
  后台「上传上限」设置不能突破 PHP 自身的限制，系统会在界面上明确提示两者中较小者才是有效上限。
* **中国大陆主机注意**：很多国内 CDN／机房屏蔽境外 IP，会导致
  Googlebot 与 OpenTimestamps 日历都无法访问，建议香港／海外机房或静态托管。
* **定时任务**（强烈建议，用于把 pending 证明升级为比特币确认）：

```cron
*/10 * * * * /usr/bin/php /srv/athenaeum/bin/ots-upgrade.php --limit=50
```

## 5. 功能与需求对照

| 需求 | 实现 |
|---|---|
| 纯 PHP、框架自选、前后端分离、MySQL | 自写微型 MVC；视图与逻辑分离；PDO MySQL 为主、SQLite 兜底验证 |
| 扁平简朴风格 | `public/assets/css/app.css`，无框架、无渐变阴影、单一主色 |
| 管理员改站名／Logo | 后台「站点设置」：`site.name`、`site.name_en`、`site.tagline*`、Logo／favicon 上传（走 `/media/branding/*` 路由） |
| 论文审核、下架、上传 | 后台论文列表+详情：**通过／打回（含理由）／下架（含理由）／恢复／彻底删除（需回填论文号）**、代理上传 |
| 后台建号／封禁 | 后台「用户」：新建账号（可指定角色）、改角色、封禁／解封、重置密码、编辑资料、删除账号及其全部论文 |
| 对任意用户论文下架／打回／代理上传 | 同上，代理上传支持选择任意作者账号（uid 或邮箱）并记录代理人与破例原因 |
| 论文分区 | 后台「分区」：任意分区，每区可设 6 语名称/简介、排序、是否公开、默认分区；前台按分区浏览。默认预置 预印本／一区／二区／三区 |
| 用户上传、撤回 | 用户中心：投稿、编辑、**撤回**、重新提交、删除（仅草稿/被打回/已撤回）、附件增删 |
| 上传时可设分类／名称／abstract／其他作者／附件／附加链接 | 投稿表单含：标题、副标题、摘要、语言、分区、学科分类、关键词、许可协议、DOI、**多作者**（姓名/单位/邮箱/ORCID/通讯作者）、**压缩包附件**、**附加链接**（DOI/arXiv/代码/数据集/视频/幻灯片/博客/其他） |
| 必须上传 PDF，默认 10MB；附件默认 20MB | 强制 `/paper/{uid}/file` 与 `%PDF-` 双重校验；`upload.max_pdf_mb=10`、`upload.max_attachment_mb=20` 可在后台调整 |
| 超限提示联系管理员邮箱 | 超限报错文案含 `site.contact_email`，管理员可在后台修改 |
| 管理员可设全局上限并可破例 | 后台可改上限；代理上传勾选「破例」后按 PHP 上限放行，并在论文页与审计日志中标注豁免原因 |
| 昵称可重复 + 唯一 uid | 昵称无唯一约束，注册即分配 `UXXXXXXXX` 公开 ID，个人页为 `/u/{uid}` |
| 昵称／头像／个性签名／Markdown 主页 | 个人设置四段：资料（昵称、署名、单位、简介、Markdown 主页带实时预览）、头像、关联账号、密码 |
| ORCID 与各类学术/社媒绑定 | 24 个平台的注册表（ORCID、Google Scholar、PhilPeople、PhilPapers、ResearchGate、Academia、SSRN、arXiv、GitHub、GitLab、个人网站、博客、Mastodon、Bluesky、X、知乎、微博、B站、豆瓣、LinkedIn、YouTube、Telegram、微信、公开邮箱），按平台校验格式并生成链接 |
| 上传即做时间戳存证（无论审核是否通过） | `PaperService::create()` 在事务提交后立即调用 `OpenTimestamps::stamp()`，逐文件（PDF 与每个附件）生成证明 |
| 论文页标注时间戳 + 小按钮跳转验证 + 悬停解释 | `partials/ots.php`：状态徽标（待确认 ⧗ / 已确认 ✓ / 失败 ×）、提交时间、确认后的比特币区块高度与时间、SHA-256、`.ots` 下载按钮，以及跳转 `opentimestamps.org` 的「Verify」小按钮（`title` 属性+hover 提示解释用途） |
| 多语言 6 种 + 自动检测 + 英文回退 | `I18n`：`?lang=`／用户偏好／Cookie／Session／`Accept-Language` 依次协商，6 语各 554 键，键缺失自动回退英文 |
| PDF 在线预览用现成开源项目 | 内置 PDF.js 4.10.38（含 CJK cmaps），`/paper/{uid}/preview` 全屏阅读器 + 详情页内嵌 iframe，缺失时回退 `<object>` |
| 下载按钮／附件下载／附加链接 | 详情页：PDF 下载（计数）、附件逐个下载（计数）、附加链接列表（含类型标签、`nofollow`） |

## 6. OpenTimestamps 存证说明（重要）

**协议实现**（与官方 Python 库逐字节对齐，见 `tests/ots_codec_test.php` 的 36 项检查）：

```
提交：POST {calendar}/digest      body = 原始 32 字节 SHA-256 摘要（非 hex、非表单编码）
      Accept: application/vnd.opentimestamps.v1
      → 返回该摘要的运算链（Timestamp 序列化）

.ots：HEADER_MAGIC(31B) + 0x01 + 0x08 + 摘要(32B) + 上面的运算链
      同时向 4 个官方聚合器提交并合并结果（a.pool / b.pool / a.pool.eternitywall / ots.btc.catallaxy）

升级：GET {calendar}/timestamp/{hex(承诺值)}    ← 承诺值是待定证明所在的节点消息，不是文件摘要
      404 "Pending confirmation in Bitcoin blockchain" = 还没进块（通常 1–3 小时）
      200 + 子树 = 合并进本地证明并重新序列化
```

**状态语义（不夸大）**：待确认期间，证明里**只有日历 URI、没有任何时间**。因此页面在
pending 状态只显示「本站记录的上传时间」并明确标注尚未获得密码学锚定；只有确认后才会显示
比特币区块时间。这符合官方设计（`notary.py` 注释：pending attestation 不记录时间）。

**独立验证方法**：论文页下载 `.ots` → 打开 <https://opentimestamps.org/#stamp-and-verify> →
把文件（连同 PDF 可选）拖入验证区，浏览器本地完成校验，无需信任本站。

## 7. 安全设计

* 上传文件存放在 **webroot 之外**（`storage/`），通过带权限校验的 PHP 路由流式输出；
  `storage/.htaccess` 全部拒绝，附件仅允许压缩包扩展名且**校验文件头签名**，PDF 校验 `%PDF-`。
* 数据库全部走 **预处理语句**；输出统一 `e()` 转义；Markdown 走 safe mode 并二次过滤
  `<script>/<iframe>/on*=`/`javascript:`。
* 所有写操作校验 **CSRF**；密码 `password_hash()`；登录失败按 IP+邮箱限流；
  会话 Cookie 为 `HttpOnly + SameSite=Lax`（HTTPS 下自动 `Secure`）。
* 管理员操作全部写入 **审计日志**（破例上传、封禁、下架、删除、设置变更…）。
* 下载流实现 `ETag`/`304`/`Range`，避免大 PDF 全量重传。

## 8. 测试

九个测试脚本，全部可在本地复现。**两套常驻 fixture 只在本地跑，不参与部署**：

```bash
# fixture：微型 OpenTimestamps 日历（模拟「未确认 → 已确认」）与假 AI / 假 SMTP
php -S 127.0.0.1:8199 tests/fixtures/fake_calendar.php
php -S 127.0.0.1:8198 tests/fixtures/fake_ai.php
php tests/fixtures/fake_smtp.php 8025

# 1) OpenTimestamps 编解码（36 项：与官方库逐字节对比 + 真实提交 4 个日历）
php tests/ots_codec_test.php --live

# 2) 存证升级流水线（29 项）：404 不动文件 → 拿到子树后合并 → 改成 confirmed
php tests/ots_upgrade_test.php --python

# 3) v2 功能单元/集成（156 项）：PDF 文本抽取、AI 审核全链路、版本管理、
#    多级学科、可编辑页面、邮件模板
php tests/v2_test.php

# 4) SMTP 协议（28 项）：EHLO/AUTH/信封/DATA、MIME 组装、注入防护、通知开关
php tests/mail_test.php

# 5) 语言包完整性（每个 __() 键都存在；30 语 × 800 键，键集与顺序一致）
php tests/lang_audit.php

# 6) 用户流程（64 项）：注册→投稿→存证→预览→下载/Range→引用→审核→公开→撤回
python tests/e2e_smoke.py http://127.0.0.1:8124 admin@… 密码

# 7) 管理后台（51 项）：站名/Logo、分区、分类、建号/封禁/改角色/改密、代理上传破例、
#    打回/恢复/下架/彻底删除、审计日志、越权访问
python tests/e2e_admin.py http://127.0.0.1:8124 admin@… 密码

# 8) 多语言（40 项）：各语种渲染、Accept-Language 协商、未支持语言回退英文、无键名泄漏
python tests/e2e_i18n.py http://127.0.0.1:8124

# 9) v2 界面通路（156 项）：可编辑页面、多级学科、公告颜色与可关闭、自定义语言筛选、
#    版本上传与旧版下载/存证、AI 控制台（半自动批审 + 全自动）、SMTP 测试发信、关联账号选择器
python tests/e2e_v2.py http://127.0.0.1:8124 admin@… 密码
```

另有 `python tests/verify_ots.py <proof.ots> <file>` 可用官方库校验任意一份证明。

**当前实测结果（共 561 项断言，全部通过）**

| 测试 | 结果 |
|---|---|
| `ots_codec_test.php --live` | **36 / 36** |
| `ots_upgrade_test.php --python` | **29 / 29** |
| `v2_test.php` | **156 / 156** |
| `mail_test.php` | **28 / 28** |
| `lang_audit.php` | 0 缺失键；**30 个语种各 800 键**，键集与顺序完全一致 |
| `e2e_smoke.py` | **64 / 64** |
| `e2e_admin.py` | **52 / 52** |
| `e2e_i18n.py` | **40 / 40** |
| `e2e_v2.py` | **156 / 156** |
| `php -l`（146 个 PHP 文件） | 0 语法错误 |

> `e2e_admin.py` 与 `e2e_v2.py` 会改动站点名、邮件与 AI 配置，因此两者都在运行前后调用
> `tests/fixtures/settings_snapshot.php` **备份并还原**生产配置——测试再也不会把你的 QQ 邮箱
> 改成 `127.0.0.1`、把站名改成测试字符串。`e2e_smoke.py` 用
> `tests/fixtures/set_setting.php` 临时关闭「注册需邮箱验证码」（它没有邮件服务器）；
> 该流程本身由 `e2e_v2.py` 对着本地假 SMTP 完整覆盖：发码 → 从信件里取出验证码 →
> 错码被拒 → 对码注册成功。若本机 `php` 未默认加载 PDO 扩展，用
> `PHP_CMD="php -d extension=pdo_sqlite" python tests/e2e_v2.py …` 指定。

其中三项是「拿别人的实现来验自己」的硬验证：

1. 用官方 `python-opentimestamps` 反序列化本项目 PHP 生成的 `.ots`
   （投稿时真实提交到 4 个公开日历），确认摘要与上传 PDF 完全一致、4 个日历 attestation 齐全、
   重新序列化后逐字节相同；
2. 升级流水线产出的 `.ots` 同样被官方库接受，并正确报告
   `bitcoin block height: 845123`；
3. SMTP 客户端对着一个真实的 SMTP 会话（本地 fixture）完成了 EHLO / AUTH / 信封 / DATA，
   产出的 MIME 报文可被解码回原文。

## 9. 与 Google 学术收录相关的设计

* 每篇论文一个**独立、稳定、语义化**的页面 `/paper/{uid}`，PDF 直链同域且可被爬虫抓取；
* `robots.txt` 放行正文、屏蔽后台与 API；`sitemap.xml` 动态列出所有公开论文（含 `lastmod`）；
* 页面含 `citation_*` 所需信息（标题、作者、摘要、发布时间、语言），HTML 摘要与 PDF 全文均可访问；
* 不依赖 JavaScript 渲染正文（禁用 JS 也能读到标题/摘要/作者/下载链接）；
* 下载端点支持 `Range`，且不设登录墙（公开论文）。

> 收录取决于 Google 的抓取，任何站点都无法保证；上述结构只是把「技术上不该被拒」的因素都做到位。

## 10. 已知限制与后续可做

* 检索使用 `LIKE`，未接全文索引（MySQL 可加 `FULLTEXT`，SQLite 可上 FTS5）；
* 头像/Logo 缩放依赖 GD，无 GD 时按原图存储（仍在体积上限内）；
* AI 审核读取的 PDF 文本由内置抽取器解析（支持未压缩与 FlateDecode 流）；**扫描件/纯图片 PDF
  抽不出文字**，此时模型只依据元数据与摘要判断，界面会明确标注；
* SMTP 密码以明文存于 `settings` 表（与 SMTP 生态的普遍做法一致）——请保护数据库备份；
  也可改用 `config/config.local.php` 或环境变量覆盖，避免落库；
* `mail.transport = mail` 走 PHP `mail()`，在没有本地 MTA 的主机上通常会失败，建议一律用 SMTP；
* 支付、DOI 注册、ORCID OAuth 登录、论文版本 diff 属于可扩展方向。

## 11. v2 新增功能（本次更新）

| 需求 | 实现要点 |
|---|---|
| **1. 论文版本管理** | 新表 `paper_versions`；`papers.version_no` 指向当前版本。上传新版会**保留旧文件与其独立 `.ots` 存证**（`versions.keep_files`），为新文件生成新存证；已发表论文换版后自动回到待审，避免读者看到未经审核的正文；论文页有版本历史（每版可下载、可看存证、可看 SHA-256、可写更新说明）。上传入口有两个：编辑页「上传新版本」+ 论文页 `#versions` 的独立表单（`POST /paper/{uid}/version`） |
| **2. 自定义语言 + 更多语种** | `Languages` 目录收录 **74 种论文语言**；上传表单的 `Other` 选项会展开一个自由输入框，填写后生成稳定代码 `x-<slug>` 并保留作者原文；**该论文通过审核后，语言筛选栏会自动出现这个语种**（筛选列表来自已发表论文的实际语言统计，而非硬编码）。界面语言从 6 种扩到 **30 种**（含 zh-TW、es、pt-BR、it、ru、uk、pl、nl、sv、da、fi、tr、ar、fa、he、hi、id、vi、th、el、cs、ro、hu、ca），`ar/fa/he` 自动切换 RTL 布局 |
| **3. 页面可后台编辑** | 新表 `pages`，`/admin/pages` 列表 + `/admin/page/{id}` 编辑器（**逐语言**编辑标题与 Markdown，带实时预览）。系统页：`about`（**关于本站**，已改写为宣传页）、`guidelines`（投稿指南）、`athenaeum`（关于 AthenXiv）、`timestamping`（**时间戳存证如何运作**，页脚入口）。导航最上方的「时间戳存证」已改为「关于本站」；管理员也可新建任意页面（`/p/{slug}`）。空语言自动回退英文，未编辑时回退内置文案 |
| **4. 关联账号选择器** | 个人设置里不再是一排输入框：点击「添加关联账号」滑出面板 → 选平台 → 填账号 → 加入列表（可逐个删除，JS 关闭时退化为普通表单）。平台表**移除了知乎/微博/B站/豆瓣/微信**，保留并补充国际平台（ORCID、Google Scholar、PhilPeople、PhilPapers、ResearchGate、Academia、SSRN、arXiv、GitHub、GitLab、个人网站、博客、Mastodon、Bluesky、X、Threads、LinkedIn、YouTube、Telegram、Wikipedia、公开邮箱） |
| **5. 公告颜色与可关闭** | 后台可设 5 种预设色或**任意自定义颜色**（`#rrggbb`，自动生成浅色底与边框）、Markdown 正文、以及「是否允许关闭」。游客点 × 后写入按公告版本号索引的 Cookie，**同一公告不再出现，换了新公告会重新展示**；设为不可关闭时只显示图钉、没有关闭按钮 |
| **6. 多级嵌套学科** | `categories.parent_id` 全树化：前台 `/categories` 树形浏览、分支页有面包屑与子领域 chips、**按父级筛选会包含全部子孙**；后台树形编辑（改名/移动/排序/新增子领域/删除空叶子，禁止把分支移进自身）。预置 **599 个节点 / 32 个一级学科 / 三层深度**（哲学、数学、物理、化学、生物、人工智能、计算机、医学、工程、材料、地球科学、天文、经济、管理、会计、金融、心理、语言学、历史、文学、政治学、社会学、人类学、艺术、传播、法学、教育、统计、农业、建筑、环境、气候），原有 12 个哲学二级领域自动**改挂到哲学之下而不是重复创建** |
| **7. 「(可选)」标注** | 上传表单的副标题、分区、学科、关键词、DOI、许可协议、作者列表、附件、附加链接、可见性等可选字段一律带 `(可选)`；必填项用 `*` |
| **8. AI 审核** | 后台 `/admin/ai`：可填任意 **OpenAI 兼容端点**（OpenAI / DeepSeek / Moonshot / 本地 Ollama…）、API Key、模型、温度、超时、输入上限、最低置信度，可自定义 system prompt 与追加规则，可一键测试连通性（列出模型）。模式三选一：**关闭 / 半自动（一键审核选中论文，支持全选与反选）/ 全自动（上传即审）**。模型返回 `{decision, confidence, reason, category_slug, section_slug, tags}`：置信度达标且开启自动发布时直接发表或打回（默认**只给建议**，由管理员决定）；始终会把学科与分区建议写回，理由直接展示给作者。输入包含元数据、摘要与**内置抽取器从 PDF 取出的正文**，界面上标注本次是否用到了 PDF 文本。审核动作与结果全部进审计日志，论文页有 AI 面板 |
| **9. SMTP 邮件通知** | 后台 `/admin/mail`：自写 SMTP 客户端（支持 **SSL(465) / STARTTLS(587) / 明文**，`AUTH PLAIN` 自动回退 `AUTH LOGIN`），可设发件人、回复地址、通知开关，并有**测试发信**按钮（失败时回显服务器应答与命令日志）。邮件模板随收件人语言（30 语种均有 `email.*` 键），覆盖：新投稿通知管理员、投稿确认、通过、打回（含理由）、撤回、下架、代理上传、测试信。QQ 邮箱已在界面提示：`smtp.qq.com:465` + 完整地址 + **16 位授权码** |

### v2 升级步骤

```bash
# 1. 覆盖代码后执行迁移（幂等，可反复运行；--dry-run 只看不改）
php bin/migrate.php --dry-run
php bin/migrate.php

# 2. 迁移会：建 paper_versions / pages 表 → 给 papers 加 9 个列
#    （version_no、language_custom、ai_* 共 9 个）→ 播种 4 个可编辑页面
#    → 播种 599 个学科的树 → 为已有论文补 v1 版本行 → 清理已下线的社媒平台绑定

# 3. 邮件与 AI 在后台配置即可，无需改代码：
#    /admin/mail   → smtp.qq.com / 465 / SSL / 完整邮箱 / 授权码 → 发送测试
#    /admin/ai     → 填写 base_url + key + model → 测试连接 → 选模式
```

`bin/install.php`（全新安装）现在也会自动调用同一套播种逻辑，无需额外操作。

## 12. v2.2 修复清单（本次 BUG 反馈）

| # | 现象 | 根因 | 修复 |
|---|---|---|---|
| 1 | AI 审核总是「失败 1 篇」，置信度不变且看不到原因 | 模型名残留在设置里（`test-model-a`，由测试写入），接口返回 `Model Not Exist`；而失败原因只写进了数据库、界面只在有结论时才显示 | 后台「测试连接」会把接口返回的**模型列表缓存成下拉候选**（`ai.models`），模型名可直接选；失败时论文页用红框显示**服务器原文错误**+失败时间，并标注「上次结论（本次审核失败，未更新）」；批量结果页也列出每篇的失败原因 |
| 2 | 邮件测试报 `cannot connect to ssl://127.0.0.1:8025` | 端到端测试把 `mail.host/port` 写成了本地 fixture 值，且被测配置未还原 | 连接失败时给出**人类可读提示**（点名 host:port 并提示该填服务商 SMTP 主机、别填 127.0.0.1）；新增 `tests/fixtures/settings_snapshot.php`，`e2e_v2.py` 运行前后自动**备份/还原** AI 与邮件配置；生产配置已写回 `smtp.qq.com:465 (SSL)` + `admin@example.org` |
| 3 | 主页「按领域浏览」把 599 个标签全铺在页面上 | 首页直接渲染整棵学科树 | 改为**一个跳转按钮**（→ 搜索界面 `#areas`）；搜索界面新增**可搜索的多选领域筛选**（复选框 + 搜索框 + 已选计数），后端支持 `?categories[]=a&categories[]=b` 取**并集**且含各自子孙，未知领域返回空而不是忽略 |
| 4 | 页脚出现两个「关于 AthenXiv」 | 两个键（`page.about_title` / `page.athenaeum_title`）取值相同 | 第一个改为「关于本站」（`nav.about_site`），第二个保留「关于 AthenXiv」；已加断言防止回归 |
| 5 | 审核界面摘要过长会溢出 | 摘要放在无换行约束的 `<p class="prose">` 里 | 新增 `.review-abstract`（`overflow-wrap:anywhere` + `max-height` 可滚动），并给表格单元、AI 理由、公告正文统一加换行约束 |
| 6 | 多语言「翻译都是空着的」 | ① 未翻译语种的**回退顺序是 zh-CN 在前**，泰语/越南语用户看到的是中文；② 后台页面编辑器对空语种只给两个空白框 | 回退顺序改为 **请求语言 → 英文 → 中文**（页面、分区、学科三处统一）；后台页面编辑器对空语种显示「此页面仍显示内置文本」+ **一键从英文填充**按钮 + 英文内容作为占位符；学科编辑器同样给出双语占位 |
| 7 | 上传页语言/领域列表太长；缺少「其它」领域 | 原生 `<select>` 列出 74 种语言 / 599 个领域 | 两个选择器都加了**搜索框**（语言可按**母语名、英文名、代码、别名**搜索，例如「中文」「Chinese」「zh」都能搜到；领域可按层级路径与 slug 搜索）；领域新增**「其它／未被列出的分类」**，选中后填写自由文本；**未重新分类的「其它」论文禁止通过审核**（服务端 + 按钮禁用 + 后台可一键指派或新建领域）；允许新建领域的主体是**管理员或 AI**（新增设置 `ai.create_categories`，AI 在无合适领域时会提出新领域名并由系统创建） |
| 8 | 右上角语言菜单溢出屏幕 | 30 个语种没有高度约束 | `.lang-switch__list` / `.user-menu__list` 加 `max-height:min(70vh,26rem)` + 滚动条 + `overscroll-behavior:contain` |

> 本次新增 16 个语言键，已由 4 个并行翻译批次补齐到**全部 30 个语种**（780 键/语种，无缺键）；
> 同时把 fr/de/es/pt-BR 里新键的「AI」改为与各自文件一致的 IA/KI/IA/IA。

## 13. v2.3 修复与改进（本轮反馈）

| # | 需求 | 实现 |
|---|---|---|
| 1 | 站名改为 **AthenXiv** | 站点名、页脚、SEO 标题、邮件模板、30 个语言包、四个介绍页正文全部改名；「关于」页重写了「名字」一节（**Athena** + **-Xiv**，archive/arXiv 式后缀），不再自称古罗马的 Athenaeum。内部 PHP 命名空间保持 `Athenaeum\`——改名会破坏自动加载，且对用户不可见 |
| 2 | 普通用户编辑自己的论文报 403 | 根因：`Paper::EDITABLE_BY_OWNER` 不含「已发表」，论文过审后作者点编辑必然 403，而「我的论文」列表仍显示编辑按钮。现在作者**始终可编辑自己的论文**（仅被下架者除外，按钮改为文字说明）；**编辑已发表论文会重新进入审核队列**（与上传新版本同一规则），表单顶部有明确提示 |
| 3 | 论文页堆叠多个时间戳证明 | 默认只展示**当前版本**的存证；历史存证收进折叠块，每条标注所属版本（`版本 v1`）并使用紧凑样式；「版本历史」同样加折叠开关（≤3 版默认展开） |
| 4 | 「我的论文」存证徽章被长句撑大 | 新增 `Timestamp::shortStatusLabel()`：列表/徽章显示「等待中 · 已获取 · 失败 · 无」，完整说明保留在 `title` 悬浮提示与论文页 |
| 5 | 四个介绍页只有中英 | 正文翻译为**全部 30 种语言**并入库（`database/pages/<locale>.json` + zh-CN/en 源文）；新增 `php bin/import-pages.php`，迁移也会自动导入，**只填空语种、绝不覆盖管理员的编辑** |
| 6 | 联系邮箱 / 配色 / Logo | 联系邮箱改为 `admin@example.org`；主色由绿松改为**学术蓝**（accent `#1d4ed8`、hover `#1e3a8a`、tint `#e9effc`、focus `#93c5fd`，白底对比度约 5.9:1）；用 `logo-designer` 技能产出「**盖章的 A**」标识（A 的横杠延伸为账册基线，末端一枚方形印章节点 = 论文 + 存在证明），导出 `logo-mark.svg`、`logo-mono.svg`、`favicon.svg`、`favicon.ico`、192/512 PWA 图标与 apple-touch-icon，并接入 `<link rel=icon>`、`site.webmanifest`、页头品牌位与 `theme-color` |
| 7 | 注册必须邮箱验证码 | 新表 `email_verifications`：只存 HMAC 哈希、10 分钟有效、单次使用、45 秒冷却、每小时最多 6 次、错 6 次作废；注册页有「发送验证码」按钮（AJAX）+ 60 秒倒计时与状态提示。**仅在邮件配置可用时才强制**，否则显示「本站尚无法发信，注册无需验证码」，避免 SMTP 故障导致全员无法注册；后台可用 `registration.verify_email` 随时开关 |
| 8 | AI 拒稿理由的语言 | 提示词同时给出该语言的**母语名与英文名**（如 `中文 / Chinese`），system prompt、JSON schema 与用户消息三处都明确要求 `reason` 用论文语言书写，仅在语言缺失时退回英文 |

> **本轮顺带修掉的一个隐蔽故障**：`Settings::all()` 在数据库不可用时静默返回内置默认值，于是「快照/导出设置」的工具会在没有 PDO 驱动的机器上导出一份默认值文件，还原时把真实配置（SMTP、AI、语言、版本开关）覆盖掉——这正是之前邮件与 AI 配置莫名变成 `127.0.0.1` 与 `openai` 的深层原因。现在 `Settings` 会记录自己是否真的读到了数据库（`Settings::loadedFromDatabase()`），`settings_snapshot.php` 在读不到表时**直接失败退出**而不是写出默认值；同时后台设置保存改为**只写本次表单真正提交过的键**，不再把没出现的字段清空。

**品牌资产**：概念与渲染稿在 `brand/`（含 `preview.html` 预览表），发布件在 `public/assets/img/`，
清单见 `public/site.webmanifest`。

**本轮迁移**：`php bin/migrate.php` 会建 `email_verifications`、补 `email_verifications.updated_at`、
导入 28 个语种的页面正文。

## 14. 共享主机部署（athenxiv.com 实例）

线上环境与理想布局差别很大，这里记录实际做过的事，便于复现与迁移。

**主机事实**（探测得到，非假设）：

| 项目 | 实测值 |
|---|---|
| Web 服务器 | **nginx 1.26.3** —— `.htaccess` **完全无效**，也没有 `try_files` 回退 |
| PHP | 8.2.28 FPM；`pdo_mysql` `curl` `mbstring` `openssl` `zip` `gd` `intl` 齐全（**无 `fileinfo`**） |
| 文档根 | FTP 根目录本身（`/www/wwwroot/gao.361588e21`），**FTP 被 chroot，应用无法放到 Web 根之外** |
| open_basedir | 仅该目录与 `/tmp`（主机 `.user.ini` 设定） |
| MySQL | 5.7.44，仅本机可连（3306 对外关闭） |
| 其他 | 目录列举 403；`.json/.sql/.ini/.md` 等扩展名被 nginx 拦截；`error_page 404` 指向根目录 `404.html` |

**采用的布局**：把 `public/*` 的内容复制到文档根（`index.php`、`assets/`、`vendor/`…），应用目录
（`app/ config/ database/ resources/ routes/`）并列放在同一层；`config/config.local.php` 把存储目录指向
一个**随机名目录**（`var-xxxxxxxxxx`，不可猜、不可列举），数据库口令与应用密钥只存在于该文件
（已 gitignore，永不入库）。

**URL 形式**：这台 nginx 无法把 `/paper/ATH-XXXX` 交给前端控制器，因此设置
`app.front_controller = 'index.php'`，所有链接形如 `/index.php/paper/ATH-XXXX`（PATH_INFO）。
在宝塔面板「网站 → 设置 → 伪静态」加入下面一行后，可把该配置改回 `''` 获得干净 URL
（同时 `/robots.txt`、`/sitemap.xml` 会恢复为动态生成）：

```nginx
location / { try_files $uri $uri/ /index.php?$query_string; }
```

**部署步骤**（`_deploy/` 是本地临时目录，含口令，已 gitignore）：

1. `php -d extension=zip _deploy/build-zip.php` 生成上传包（排除 `.git`、`tests/`、`brand/`、本地配置、SQLite schema）；
2. FTP 上传该包与一次性解压脚本 → 访问解压脚本 → 它自行删除；
3. 访问一次性安装脚本（随机 token）：它调用 `bin/install.php` + `bin/migrate.php`，写入生产设置并报告行数；
4. 删除安装脚本与 `bin/`（**bin/ 内的脚本可被 HTTP 直接执行，必须清掉**），并删除 `index.html`（否则会遮蔽 `index.php`）；
5. 上传静态 `robots.txt`、`sitemap.xml`（内容快照）、品牌化 `404.html` 与 `.user.ini`（`display_errors=0`、上传上限）。

**上线前安全审查修掉的真实缺陷**（均已由测试覆盖）：

* `database/schema.{mysql,sqlite}.sql` 长期失修：缺 `paper_versions`、`pages`、`email_verifications`
  与 10 个 `papers` 新列 → 全新安装会得到一个需要再跑一次 migrate 才能用的残缺库；
* `bin/install.php` 的 SQL 分割会**整段跳过带注释的表定义**（33 条语句只执行 7 条），`--fresh` 也漏删新表；
* `app/bootstrap.php` 被 `require` 第二次时返回 `null`，使 Web 安装器加载 `bin/` 脚本直接致命错误；
* 视图与路由文件被直接请求会打印带服务器路径的 Fatal error → 现在每个模板都带
  `defined('ATHENAEUM_BOOTSTRAPPED')` 守卫并返回 404；
* `HEAD` 请求被回 405；安全响应头只写在 `.htaccess`（在 nginx 上等于没有）→ 改由应用统一发送；
* MySQL 部署仍会创建 SQLite 目录。

### 14.1 OpenAlex 批量导入（公开许可的高被引论文）

`bin/openalex-import.php` 把开放许可的高被引论文成批导入本站，**可断点续跑**：

```bash
# 本地/服务器 CLI
php bin/openalex-import.php --api-key=… --per-field=26 --plan-cap=900 --scan-pages=34 --plan-only=1
php bin/openalex-import.php --api-key=… --budget-mb=340 --batch=12 --seconds=100
php bin/openalex-import.php --report=1          # 进度与失败原因
```

要点：

* **许可过滤**：只导入 public domain / CC0 / CC BY / CC BY-SA（`best_oa_location.license`），
  每篇都在 `papers.license` 与审核备注里标注原始许可与出处链接；
* **学科均衡**：按 OpenAlex 的 26 个学科分别取前 N 篇，再交错排序，避免整站变成医学库；
* **学科归类**：优先用 OpenAlex 的 subfield/field 名称匹配本站学科树，其次才用论文标题；
  匹配不到时用 field→slug 兜底表，仍失败则留空（可后续用 AI 归类）；
* **分区**：按被引量落入 tier-1/tier-2/preprints；
* **下载**：依次尝试 `best_oa_location.pdf_url`、`open_access.oa_url` 与全部 `locations[].pdf_url`，
  仓库域名（PMC/arXiv/Zenodo/机构库…）优先——出版社站点对爬虫常回 403；
  `--oa-status=green|diamond` 可只取仓库副本，成功率显著更高；
* **入库路径**：与正常投稿完全一致（`PaperService::create` → 哈希 → OpenTimestamps 存证 → 审核通过），
  因此 PDF、摘要、作者、DOI、附件与存证行为都不特殊；`Uploader` 的 `trusted_local` 通道
  只对「进程内自己抓取的文件」放行 `is_uploaded_file()` 检查，请求侧无法伪造；
* **服务器端运行**：生产主机没有 shell、3306 也不对外，因此导入由一次性 token 脚本
  （`oa-<token>.php` + `oa-lib-<token>.php`）在 PHP 内分批执行，状态写在
  `storage/openalex-import.json`；单批受 `max_execution_time` 与 `--seconds` 双重限制，
  中断后下一批自动续跑。**导入完成后请删除这两个脚本**（它们已带 token 校验与
  `OPENALEX_RUNNER` 守卫，直接访问返回 404）。

**归档论文与站内投稿的区分**：`papers.origin` 为 `archive` 的是批量导入的开放获取论文，
在前端与后台都另有标识：

| 行为 | 站内投稿 | 归档导入 |
|---|---|---|
| 标题后标识 | 无 | 权利状态徽章（公有领域 / CC0 显示 `Public Domain`，其余显示许可名如 `CC-BY`） |
| 日期 | 本站投稿/发布日期 | **原出版日期**、**版权届满日期**（仅在保护期确实已过时给出，否则只显示权利状态）、**加入本站日期** |
| 版本历史 | 有 | **不显示** |
| 时间戳历史 | 有 | **不显示**（存证照做，只是不展示历史） |
| 后台列表 | `站内投稿` 徽章 | `归档导入` 徽章 + 原出版日期 |

回填历史数据用 `_deploy/stage/backfill-origin.php`（按 DOI 向 OpenAlex 取
`publication_date` 与来源，分批写入；带 `?report=1` 与 `?sweep=` 两个诊断动作）。

> **许可现实**：`--licences` 可指定要抓的许可（默认 `cc-by|cc-by-sa|cc0|publicdomain`）。
> 首次抓取的 316 篇里有 **303 篇 CC-BY、13 篇 CC-BY-SA，没有一篇是 `publicdomain`/`cc0`**
> —— 也就是说它们属于「开放许可」而非「版权已过期」。若要专门补一批版权确实过期的
> 论文，用 `--licences=publicdomain|cc0` 再跑一遍（DOI 去重会自动跳过已有的）。



**上线后建议手动完成**：

1. 面板把 PHP 的 `display_errors` 设为 `Off`（宝塔用 `php_admin_value`，`.user.ini` 覆盖不了）；
2. `/admin/mail` 填入可用 SMTP（QQ 授权码需重新生成），`/admin/ai` 填 API Key 后选模式；
3. 首次登录后立即修改管理员密码；
4. 需要干净 URL 时加入上面的伪静态规则，并把 `config/config.local.php` 的 `app.front_controller` 改为 `''`。

## 15. 许可与致谢

* 本项目代码：供部署者自行使用与修改。
* 内置 PDF.js 4.10.38（Apache-2.0，见 `public/vendor/pdfjs/LICENSE` 与 `VENDOR.md`）。
* 内置 Parsedown 1.7.4（MIT，见 `app/Support/Parsedown.LICENSE` 与 `Parsedown.VENDOR.md`）。
* OpenTimestamps 日历为社区捐赠运营的公共服务，请勿滥用：本项目只在投稿时提交一次摘要，
  升级查询按 10 分钟级节流。
