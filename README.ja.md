[English](README.md) · [简体中文](README.zh-CN.md) · **日本語**

# AthenXiv

**OpenTimestamps** による証明、ブラウザ上での PDF 閲覧、30 言語のインターフェースを備えた、分野を問わないオープンな研究論文アーカイブです。

公開サイト：<https://athenxiv.com/>

AthenXiv は、学位・職位・所属・スポンサーのいずれも必要とせず、どなたからの投稿も受け付けます。アップロードされたファイルはハッシュ化され、OpenTimestamps によるタイムスタンプが付与されるため、読者はそのファイルが特定の時点で存在していたことを検証できます。ほかで公開されたオープンアクセスの研究成果も保存し、その旨を明示したうえで、元の公開日とライセンスを表示します。

## 含まれるもの

* **依存関係ゼロ。** Composer も npm ビルドも不要です。素の PHP 8.1+ と小さな MVC コア、保存には PDO、CSS/JS はフレームワークに頼らずディスクからそのまま配信します。
* **データベースは 2 種類。** MySQL（本番）と SQLite（開発・テスト）が 1 つのスキーマ層を共有します。SQL は一度書いたものを、ドライバーごとに書き換えます。
* **OpenTimestamps。** アップロードには `.ots` 証跡が付き、カレンダーの結果は集約され、証跡は保留中から確認済みへ自動的に昇格します。
* **PDF.js ビューアー**はアプリケーション経由で配信されるため、`.mjs` を知らないホストでも正しく表示されます。
* **30 言語**に対応し、`Accept-Language` のネゴシエーションとページごとの hreflang、さらに管理者が編集できるコンテンツページを備えています。
* **Google Scholar 対応**：Highwire の `citation_*` メタデータ、`Content-Type: application/pdf` を返す `/paper/{uid}.pdf` という URL、そしてリクエストのたびにデータベースから生成されるサイトマップ。
* **モデレーションのワークフロー**：任意の AI 事前レビュー、未審査のリビジョンを読者に見せないバージョン履歴、アクセスログを参照できないホスト向けのクローラー訪問記録を備えています。

## 必要条件

* PHP 8.1 以降（`pdo`、`pdo_mysql` または `pdo_sqlite`、`mbstring`、`json`、`curl`、`openssl` が必要です）
* MySQL 5.7+ または SQLite 3
* Web サーバー。本アプリケーションは 2 つの配置方法に対応しています。ドキュメントルートを `public/` に向ける方法と、URL リライトが使えないホスト向けに、プロジェクト全体をウェブルートに置いてフロントコントローラー（`/index.php/...`）を使う方法です。

## インストール

```bash
git clone https://github.com/AthenXiv/athenxiv.git
cd athenxiv

# 1. 設定：サンプルをコピーして編集してください
cp config/config.example.php config/config.local.php

# 2. データベース（動作を確認するだけなら SQLite で十分です）
php bin/install.php --driver=sqlite \
    --email=you@example.com --password='change-me-please' --nickname=Keeper
php bin/migrate.php

# 3. 起動します
php -S 127.0.0.1:8000 -t public public/router.php
```

次に <http://127.0.0.1:8000/> を開いてください。管理者アカウントは手順 2 で作成したものです。

本番のホストでは、ドキュメントルートを `public/` に向けてください。それができない場合（共用ホスティングでは通常できません）は、`public/` の中身をウェブルートにコピーし、設定で `app.front_controller` を `index.php` に設定してください。すべての URL に `/index.php` の接頭辞が付くようになります。

## テスト

```bash
php tests/lang_audit.php                        # コードで使われるすべてのキーが en.php に存在します
php tests/v2_test.php                           # サービス、モデル、メール、バージョン、OTS コーデック
php tests/e2e_smoke.py   http://127.0.0.1:8000  # 公開ページと一連の流れ
php tests/e2e_admin.py   http://127.0.0.1:8000  # 管理者コンソール
php tests/e2e_i18n.py    http://127.0.0.1:8000  # 30 言語
php tests/scholar_check.py https://athenxiv.com # Google Scholar 準拠（公開サイトに対して実行）
```

Python のスイートは起動中のインスタンスに対して実行し、PHP のスイートは単体で、使い捨ての SQLite データベースに対して実行します。一部のスイートは `tests/fixtures/` にある偽の外部サービス（AI エンドポイント、カレンダー、SMTP）を必要とします。これらのスイートが使う認証情報（`*-password-2026`、デモ用の管理者）は、テスト自身が作成するデータベースのものであり、設定値ではなくフィクスチャです。

## ディレクトリ構成

```
app/          コア（ルーター、リクエスト、レスポンス、i18n、設定）、モデル、サービス、コントローラー
bin/          CLI：インストール、マイグレーション、インポート、翻訳の適用、OTS アップグレード
config/       設定テンプレートとローカルの上書きファイル（コミットされません）
database/     スキーマ、シードデータ、コンテンツページの翻訳ソース
public/       ウェブルート：フロントコントローラー、アセット、同梱の PDF.js
resources/    ビュー（素の PHP テンプレート）と 30 の言語ファイル
routes/       ルートテーブル
tests/        簡易ユニットテストとエンドツーエンドのスイート、およびフィクスチャ
```

## コントリビューション

バグ報告と pull request を歓迎します。pull request を作成する前に、上記のスイートを実行し、言語ファイルを完全な状態に保ってください。テンプレートで使われている文字列が `resources/lang/en.php` に存在しない場合、`tests/lang_audit.php` は失敗します。

## ライセンス

MIT — 詳細は [LICENSE](LICENSE) をご覧ください。公開サイトで公開されている論文その他のコンテンツは、本ライセンスの対象**ではありません**。各著作物は自らの権利とライセンスを保持しており、サイトはそれを論文ページに表示します。
