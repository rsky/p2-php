---
title: Dropbox連携
layout: default
---
# Dropbox連携

rep2機能拡張パックの[Dropbox](https://www.dropbox.com/)連携機能では、Dropboxに画像をアップロードし、そのURLを投稿することができます。

要望と作者のやる気、あるいはpull request次第ではDropboxを活用する機能が増えるかもしれません。

このページにはDropbox連携を有効にする方法を記載しています。

## 1. Dropboxアプリケーションを作成する

1. Dropboxにアカウントを作成する。（なければ）
2. Dropboxにデベロッパー登録する。（まだなら）
	1. Webブラウザで [Dropbox Developers](https://www.dropbox.com/developers) を開く。
	2. **My Apps** をクリック。  (登録していなければここで規約への同意を求められる)
3. [My Apps](https://www.dropbox.com/developers/apps) にある **Create an app** から新しいアプリケーションを作成する。
	* *Access type* は **API** を選んでください。
	* *App name* には他のアプリケーションで使われている名前は使えません。
	* *Description* も必須なので適当に入力してください。
	* *Access* を **App Folder** にしておくと、そのアプリ用のフォルダ以外にAPI経由でアクセスできないので安全です。
	* 公開用URLにアプリ名を含めたくないなら、**Full Dropbox** を選んでください。
4. アプリ情報ページの *General information* の項にある **App key** と **App secret** をメモしておく。

## 2. アクセストークンを取得する

コンソールで以下のようにします。

	cd /path/to/p2-php
	php scripts/p2cmd.php dropbox-auth --key="メモしたApp key" --secret="メモしたApp secret"

作成したDropboxアプリが *Full Dropbox* アクセスの場合は `--full-access` オプションも付けてください。

	php scripts/p2cmd.php dropbox-auth --key="メモしたApp key" --secret="メモしたApp secret" --full-access

ここでガイダンスが表示されるので、コンソールでは何もせずに *Go to the following URL:* で示されたURL (*https://www.dropbox.com/1/oauth/authorize?…*) をブラウザで開き、**Allow** をクリックしてください。

**成功しました!** と書いてある画面に遷移したら成功です。

コンソールに戻って**Enterキー**を押し、**Authorization complete.** と表示されれば完了です。

## Dropbox連携を解除する

*conf/dropbox.json* を削除してください。
