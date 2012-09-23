---
title: インストールと設定
layout: default
---
# インストールと設定

## インストール

1. All-in-oneパッケージをダウンロード。  
   (以下の作業はすべてこれを展開してできた**rep2ex-*yymmddhhmm***ディレクトリで行う)

2. **rep2**ディレクトリをWebサーバの公開ディレクトリにする。

3. Webサーバが書き込めるようにディレクトリのアクセス権をセット。  
   (WindowsやPHP 5.4のビルトインウェブサーバなどでは、必要ない場合もあります)  
   <pre>chmod 0777 data/* rep2/ic</pre>

4. 動作環境チェック  
   以下のコマンドを実行して、全ての項目で `OK` が出たなら大丈夫です。  
   何かエラーが出たらがんばって環境を整えてください。
   <pre>php scripts/p2cmd.php check</pre>


## Built-in web serverで使ってみる (PHP 5.4+)

PHP 5.4の新機能、[ビルトインウェブサーバー](http://docs.php.net/manual/ja/features.commandline.webserver.php)で簡単に試せます。

ルートディレクトリで以下のようにすると、Webサーバーの設定をしなくても `http://localhost:8080/` でrep2を使えます。**(Windowsでも!)**

    php -S localhost:8080 -t rep2 router.php


## 画像を自動で保存したい

スレに貼られている画像を自動で保存する機能、**ImageCache2**があります。

対応しているデータベースはMySQL (`mysql`, `mysqli`), PostgreSQL (`pgsql`), SQLite2 (`sqlite`)です。SQLite3 (`sqlite3`)およびPDOはサポートしていません。

see also [doc/ImageCache2/README.txt](https://github.com/rsky/p2-php/blob/master/doc/ImageCache2/README.txt), [doc/ImageCache2/INSTALL.txt](https://github.com/rsky/p2-php/blob/master/doc/ImageCache2/INSTALL.txt)

### 準備

1. SQLite以外のデータベースを使う場合はデータベースサーバーを立ち上げておく。

2. conf/conf_admin_ex.inc.phpでImageCache2を有効にする。
  <pre>$_conf['expack.ic2.enabled'] = 3;</pre>

3. conf/conf_ic2.inc.phpで[DSN](http://pear.php.net/manual/ja/package.database.db.intro-dsn.php)を設定する。
  <pre>$_conf['expack.ic2.general.dsn'] = 'mysql://username:password@localhost:3306/database';</pre>

4. setupスクリプトを実行する。
  <pre>php scripts/ic2.php setup</pre>

### 注意

* PHP 5.4ではSQLite2がサポートされなくなったので、ImageCache2を使いたいときはMySQLかPostgreSQLが必要です。
* ホストに`localhost`を指定して接続できないときは、代わりに`127.0.0.1`にしてみてください。


## 設定を変えたい

細かい挙動の変更は `メニュー > 設定管理 > ユーザー設定編集` から行えます。

Webブラウザから変更できない項目は [conf/conf_admin.inc.php](https://github.com/rsky/p2-php/blob/master/conf/conf_admin.inc.php) (基本), [conf/conf_admin_ex.inc.php](https://github.com/rsky/p2-php/blob/master/conf/conf_admin_ex.inc.php) (拡張パック), [conf/conf_ic2.inc.php](https://github.com/rsky/p2-php/blob/master/conf/conf_ic2.inc.php) (ImageCache2) を直接編集します。

どういうことができるか書き起こすのが面倒なので設定ファイルのコメントを見てください。
