# 会員情報変更通知プラグイン

EC-CUBE 4.0 用プラグインで、会員情報が変更された際に管理者と会員本人にメール通知を送信します。

## 機能

- 会員情報（氏名、メールアドレス、電話番号、住所など）の変更を自動検知
- 変更内容を管理者に通知するメールを自動送信
- 変更内容を会員本人に通知するメールを自動送信
- 管理画面から通知設定を簡単にカスタマイズ可能
- 監視対象フィールドのカスタマイズ対応

## 動作環境

- EC-CUBE 4.0.x

## インストール方法

### 1. プラグインファイルの配置

プラグインを EC-CUBE のプラグインディレクトリに配置します。

```bash
cp -r CustomerChangeNotify /path/to/ec-cube/app/Plugin/
```

### 2. プラグインのインストール

EC-CUBE の管理画面、または以下のコマンドでプラグインをインストールします。

```bash
# コマンドラインでインストール
bin/console eccube:plugin:install --code=CustomerChangeNotify

# プラグインを有効化
bin/console eccube:plugin:enable --code=CustomerChangeNotify
```

または、EC-CUBE 管理画面の「オーナーズストア」→「プラグイン」→「プラグイン一覧」から「会員情報変更通知プラグイン」をインストール・有効化してください。

### 3. メールテンプレートの確認

インストール後、以下のメールテンプレートが自動登録されます。

- 会員情報変更通知（管理者向け）
- 会員情報変更通知（会員向け）

管理画面の「コンテンツ管理」→「メールテンプレート設定」から、メールの件名や本文をカスタマイズできます。

## 設定方法

### 管理画面での設定

1. EC-CUBE 管理画面にログイン
2. 「設定」→「会員情報変更通知設定」を開く
3. 以下の項目を設定：
   - **管理者通知先メールアドレス**: 変更通知を受け取るメールアドレス（空欄の場合は店舗設定のメールアドレスが使用されます）
   - **管理者向けメール件名**: 管理者に送信されるメールの件名
   - **会員向けメール件名**: 会員に送信されるメールの件名

### 設定ファイルでの設定（上級者向け）

`app/Plugin/CustomerChangeNotify/config.yml` を直接編集することも可能です。

```yaml
service:
    customer_change_notify.admin_to: admin@example.com
    customer_change_notify.admin_subject: 会員情報変更通知（管理者向け）
    customer_change_notify.member_subject: 会員情報が変更されました
```

設定変更後はキャッシュをクリアしてください。

```bash
bin/console cache:clear --no-warmup
```

## 使用方法

プラグインを有効化すると、自動的に以下の動作を行います。

### 1. 変更の自動検知

会員が以下の情報を変更すると、自動的に検知されます。

- 姓・名
- セイ・メイ（カナ）
- メールアドレス
- 電話番号（市外局番・市内局番・加入者番号）
- 郵便番号
- 住所1・住所2

### 2. メール送信

変更が検知されると、以下の 2 通のメールが自動送信されます。

- **管理者向けメール**: 誰がどの項目をどのように変更したかを記載
- **会員向けメール**: 本人に対して変更内容の確認通知

### 3. 変更差分の表示

メールには、変更前と変更後の値が明確に表示されます。

例：
```
【変更内容】
- メールアドレス: old@example.com → new@example.com
- 電話番号（市外局番）: 03 → 06
- 住所1: 東京都渋谷区 → 大阪府大阪市
```

## 監視対象フィールドのカスタマイズ

デフォルトでは以下のフィールドが監視対象です。

- `name01`, `name02` (姓・名)
- `kana01`, `kana02` (セイ・メイ)
- `email` (メールアドレス)
- `tel01`, `tel02`, `tel03` (電話番号)
- `zip01`, `zip02` (郵便番号)
- `addr01`, `addr02` (住所)

監視対象フィールドを変更する場合は、`Resource/config/services.yaml` を編集してください。

```yaml
Plugin\CustomerChangeNotify\Service\DiffBuilder:
  class: Plugin\CustomerChangeNotify\Service\DiffBuilder
  arguments:
    - ['name01', 'name02', 'email']  # ← 監視したいフィールドを指定
```

## トラブルシューティング

### メールが送信されない

1. **EC-CUBE のメール設定を確認**
   - 管理画面の「設定」→「店舗設定」→「メール設定」でメールサーバーの設定が正しいか確認してください。

2. **ログを確認**
   - `var/log/site.log` にエラーログが出力されていないか確認してください。

3. **キャッシュをクリア**
   ```bash
   bin/console cache:clear --no-warmup
   ```

### 特定のフィールドの変更が通知されない

`Resource/config/services.yaml` の `DiffBuilder` の `arguments` に、該当フィールド名が含まれているか確認してください。

### メールテンプレートが表示されない

プラグインの再インストールを試してください。

```bash
bin/console eccube:plugin:uninstall --code=CustomerChangeNotify
bin/console eccube:plugin:install --code=CustomerChangeNotify
bin/console eccube:plugin:enable --code=CustomerChangeNotify
```

## アンインストール方法

```bash
# プラグインを無効化
bin/console eccube:plugin:disable --code=CustomerChangeNotify

# プラグインをアンインストール
bin/console eccube:plugin:uninstall --code=CustomerChangeNotify
```

アンインストールすると、メールテンプレートも自動削除されます。

## 開発者向け情報

### ディレクトリ構成

```
CustomerChangeNotify/
├── Plugin.php                   # プラグインのエントリーポイント
├── config.yml                   # プラグイン設定
├── composer.json                # Composer 設定
├── Event/
│   └── CustomerChangeSubscriber.php  # Doctrine イベント監視
├── Service/
│   ├── NotificationService.php  # メール通知サービス
│   └── DiffBuilder.php          # 差分検出サービス
├── Controller/
│   └── Admin/
│       └── ConfigController.php # 管理画面設定コントローラー
├── Form/
│   └── Type/
│       └── ConfigType.php       # 設定フォーム
├── Entity/
│   └── Config.php               # 設定エンティティ
├── Resource/
│   ├── config/
│   │   └── services.yaml        # サービス定義
│   └── template/
│       ├── admin/
│       │   └── config.twig      # 管理画面テンプレート
│       └── Mail/
│           ├── customer_change_admin_mail.twig   # 管理者向けメール
│           └── customer_change_member_mail.twig  # 会員向けメール
└── tests/
    └── Service/
        ├── DiffBuilderTest.php  # ユニットテスト
        ├── NotificationServiceTest.php
        └── ...
```

### テストの実行

```bash
vendor/bin/phpunit
```

### 技術的な詳細

詳細なアーキテクチャやコード構造については、`CLAUDE.md` をご覧ください。

## ライセンス

Apache License 2.0

詳細は [LICENSE](LICENSE) ファイルをご確認ください。

## サポート

問題が発生した場合や機能要望がある場合は、GitHub の Issue からお知らせください。

---

**バージョン**: 1.0.0
**対応 EC-CUBE バージョン**: 4.0.x
