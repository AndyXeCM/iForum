# iForum

iForum 是一个普通 PHP 空间可部署的通用论坛模板，不包含任何机构定制内容。

## 功能

- PHP 8.1+、PDO MySQL/MariaDB，无 Composer 依赖
- 浏览器安装器：上传 zip 后打开 `install.php`，填写数据库信息即可建表
- 基础论坛：分类、主题、回复、注册、登录、管理员设置
- JSON API：`api.php` 可供 iOS 客户端调用
- 打包脚本：一键生成上传用 zip

## 普通主机部署

1. 直接下载仓库里的 `packages/iforum-php-template.zip`，或运行 `bash scripts/make-zip.sh` 重新生成 `dist/iforum-php-template.zip`。
2. 把 zip 上传到网站目录并解压。
3. 浏览器打开 `https://你的域名/install.php`。
4. 填写 MySQL/MariaDB 数据库、表前缀和管理员信息。
5. 安装完成后进入 `index.php`。

安装器会写入 `app/config.php` 和 `app/installed.lock`。这两个文件不会进入 Git，也不会进入打包 zip。

## 本地开发

```bash
php -S 127.0.0.1:8080
```

然后打开 [http://127.0.0.1:8080/install.php](http://127.0.0.1:8080/install.php)。

## API

常用接口：

- `GET api.php?action=site`
- `GET api.php?action=categories`
- `GET api.php?action=threads`
- `GET api.php?action=thread&id=1`
- `POST api.php?action=login`
- `POST api.php?action=register`
- `POST api.php?action=thread`
- `POST api.php?action=reply`

登录后 API 返回 Bearer token，iOS 客户端会把它放入 `Authorization: Bearer <token>`。

## 服务器要求

- PHP 8.1+
- `pdo_mysql`
- MySQL 5.7+ 或 MariaDB 10.4+
- `app/` 目录安装时需要可写
