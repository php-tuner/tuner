#Tuner 用户指南

Tunner是一套易用的应用程序开发框架，目的是帮助PHP开发者快速开发出稳定、高效的应用程序。它提供了必要的类库、模版和常用函数，在清晰的运行逻辑下拥有极高的运行效率。
通过最少的约束和必要的功能，让开发者能够轻松愉快地完成既定目标。

##安装及运行
假设网站的根目录是 /var/www，我们将会创建一个 /varw/www/htdocs/hello_world的示例项目。

* 下载安装包解压到 /var/www/tuner 目录。

* 执行如下命令 

```bash  
php /var/www/tuner/index.php project/new path=/var/www/htdocs/hello_world
```

执行结果将会在 /var/www/htdocs/hello_world 目录产生如下文件和目录。

```
├── config      // 配置文件目录
├── controller  // 前端控制器目录
├── model       // 数据模型目录
├── view        // 模版视图目录
├── public      // 开放资源目录
└── index.php   // 入口文件
```

* 服务器配置

Nginx 服务器

```nginx
server {
        server_name domain.tld;

        root /var/www/htdocs/hello_world;
        index index.html index.php;

		# 实现单一入口访问
        location / {
                try_files $uri $uri/ /index.php;
        }

        location ~* \.php$ {
                fastcgi_pass 127.0.0.1:9000;
                include fastcgi.conf;
        }
}
```

Apache 服务器主要配置

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]
```

测试环境也可以直接用PHP内置服务器进行开发，只需要运行如下命令即可：
 
```bash
cd /var/www/htdocs/hello_world
php -S 127.0.0.1:8090
```
然后在浏览器中打开 http://127.0.0.1:8090/index.php ，注意此时是用pathinfo进行路由分发的。

##教程

教程中使用 tuner.example.com 作为示例域名。

###路由器

路由器实现从请求到对应控制器的映射。目前支持一下映射方法。

* uri

假设请求的链接是: http://tuner.expample.com/work/start.html?name=tuner，此请求的
被影射为控制器Work(对应文件controller/WorkController.php)的start方法请求。
uri 映射需要服务器做相关重写设置，请参照相关说明。

* pathinfo

假设请求的链接是: http://tuner.expample.com/index.php/work/start.html?name=tuner，此请求的
被影射为控制器Work(对应文件controller/WorkController.php)的start方法（必须是public）请求。
此映射方法无需服务器做特殊设置。

影射中的多余字串将会传递给对应控制器的调用方法中。

例如请求http://tuner.expample.com/work/start/beijing/monday.html, 将按如下调用：

```
//WorkController中的
start('beijing', 'monday'); 
```

###控制器

组织业务逻辑，负责处理用户的请求和响应。系统通过路由分发将域名中的部分信息映射到路由器中。
控制器都放置的controller目录中。控制器一半需要继承框架中的Controller类。

###数据模型

数据模型主要用来封装对数据（主要是数据库）的相关操作。

###模版

基于 [twig](http://twig.sensiolabs.org) 模版引擎。

###配置

实现对项目的配置管理。

###类库

常用的方法集合。
