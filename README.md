# Discord Post Notify

WordPress で次のことが起きたときに、Discord の Webhook へ通知を送るプラグインです。

- 新しい投稿を公開したとき
- Contact Form 7 のフォームが送信されたとき
- コメントが投稿されたとき（スパム・ゴミ箱は除く）

## 特長

- 用途ごと（投稿・フォーム・コメント）に送り先の Webhook URL を分けられます。空欄の用途はデフォルトの URL に送ります
- 1行に1つずつ書けば、複数のサーバー・チャンネルへ同時に送れます
- フォームやコメントの本文に `@everyone` / `@here` / `<@ユーザーID>` が書かれていても、Discord でメンション通知は飛びません
- Discord の文字数上限に合わせて、長い本文は切り詰めます

## インストール

1. [Releases](https://github.com/donnma777/discord-post-notify/releases) から Source code (zip) をダウンロード
2. WordPress の管理画面「プラグイン → 新規追加 → プラグインのアップロード」で zip を選び、有効化
3. 「設定 → Discord通知」で Webhook URL を入れて保存し、テスト送信で確認

Webhook URL は、Discord のチャンネル設定 →「連携サービス」→「ウェブフック」から発行できます。

### wp-config.php で指定する（任意）

`wp-config.php` に次を書くと、管理画面の設定より優先して、全用途がこの URL に送られます。

```php
define( 'DISCORD_WEBHOOK_URL', 'https://discord.com/api/webhooks/xxxx/yyyy' );
```

## 動作環境

- WordPress 6.0 以上
- PHP 7.4 以上
- フォーム通知を使う場合は Contact Form 7

## 注意

- Webhook URL を知っている人は誰でもそのチャンネルに投稿できます。URL は人に見せないでください
- 管理画面は日本語のみです
- 個人で作ったものです。サポートや不具合対応のお約束はできません

## ライセンス

GPLv2 or later
