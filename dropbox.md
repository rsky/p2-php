---
title: Dropbox連携
layout: default
---
# Dropbox連携
Dropboxに画像をアップロードし、そのURLを投稿するには以下の作業が必要です。

要望と作者のやる気、あるいはpull request次第ではDropboxを活用する機能が増えるかもしれません。

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
4. アプリ情報ページの *General information* の項にある **App key** と **App secret** をメモしておく。

## 2. アクセストークンを取得する


## Dropbox連携を解除する

*conf/dropbox.json* を削除してください。
