# iForum 1.0 部署说明

iForum 的目标是让现代论坛像传统 PHP 博客程序一样安装：上传、解压、浏览器配置、完成。

## Typecho 式安装

`iForum` 适合普通 PHP 虚拟主机：

1. 上传 `packages/iforum-php-template.zip`
2. 在主机面板解压到网站根目录
3. 访问 `/install.php`
4. 填写数据库地址、数据库名、用户名、密码、表前缀
5. 填写管理员账号
6. 点击安装

如果安装器提示无法写入配置文件，请给 `app/` 目录临时写权限，安装完成后可恢复更严格权限。

## 安全建议

- 安装完成后确认 `app/installed.lock` 存在。
- 不要把 `app/config.php` 上传到公共 Git 仓库。
- 生产环境建议开启 HTTPS。
- 数据库用户只授予当前库权限。
- 修改默认管理员密码并保存好。

## 重新打包

```bash
bash scripts/make-zip.sh iforum-php-template.zip
```

脚本会排除运行时配置、安装锁、上传目录和旧安装包。

## iOS API 地址

iOS 模板默认把 API 地址写在 `ForumAPI.swift` 的 `APISettings.baseURLString`：

```swift
static let baseURLString = "https://example.com/api.php"
```

部署 Web 后，把它改成你的域名，例如：

```swift
static let baseURLString = "https://forum.example.com/api.php"
```
