#Tuner 用户指南

Tunner是一套易用的应用程序开发框架，目的是帮助PHP开发者快速开发出稳定、高效的应用程序。它提供了必要的类库、模版和常用函数，在清晰的运行逻辑下拥有极高的运行效率。
通过最少的约束和必要的功能，让开发者能够轻松愉快地完成既定目标。

##安装及运行
假设网站的根目录是 /var/www，我们将会创建一个 /varw/www/htdocs/hello_world的示例项目。

1. 下载安装包解压到 /var/www/tuner 目录。

2. 执行如下命令 

```bash  
php /var/www/tuner/index.php project/new path=/var/www/htdocs/hello_world
```

执行结果将会在 /var/www/htdocs/hello_world 目录产生如下文件和目录。

```
├── config      // 配置文件目录
├── controller  // 前端控制器目录
├── index.php   // 入口文件
├── model       // 数据模型目录
├── public      // 开放资源目录
└── view        // 模版视图目录
```
##教程

###路由器

###模型

###模版

###配置

###类库
