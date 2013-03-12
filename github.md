---
title: 開発版を追いかける
layout: default
---
# 開発版を追いかける

## 初期化

### 本体をGitHubからcloneする

    git clone git://github.com/rsky/p2-php.git
    cd p2-php

### 依存ライブラリをダウンロード

    curl -O http://getcomposer.org/composer.phar
    php -d detect_unicode=0 composer.phar install

### Webサーバが書き込めるようにディレクトリのアクセス権をセット

(CGI/suEXECIやCLI/Built-in web serverでは不要)

    chmod 0777 data/* rep2/ic


## 更新

    php scripts/p2cmd.php update --alldeps

これは下記コマンドを個別に実行するのと等価です。

    git pull
    php -d detect_unicode=0 composer.phar selfupdate
    php -d detect_unicode=0 composer.phar update
