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

假设请求的链接是: http://tuner.expample.com/work/start.html?name=tuner ，此请求的
被影射为控制器Work(对应文件controller/WorkController.php)的start方法请求。
uri 映射需要服务器做相关重写设置，请参照相关说明。

* pathinfo

假设请求的链接是: http://tuner.expample.com/index.php/work/start.html?name=tuner ，此请求的
被影射为控制器Work(对应文件controller/WorkController.php)的start方法（必须是public）请求。
此映射方法无需服务器做特殊设置。

影射中的多余字串将会传递给对应控制器的调用方法中。

例如请求 http://tuner.expample.com/work/start/beijing/monday.html , 将按如下调用：

```php
//WorkController中的
start('beijing', 'monday'); 
```

###控制器

组织业务逻辑，负责处理用户的请求和响应。系统通过路由分发将域名中的部分信息映射到路由器中。
控制器都放置的controller目录中。控制器一半需要继承框架中的Controller类。

###数据模型

数据模型主要用来封装对数据（主要是数据库）的相关操作。

#### 主要函数说明

```php
class Model {
	
	// 获取单条记录。
	public function getRow($where_array, $table = '')

	// 获取多条记录。
	public function getRows($where_array, $table = '')

	// 更新一条记录。
	public function updateOne($sets, $wheres, $table = '')

	
	// 更新多条记录。
	public function updateBatch($sets, $wheres, $table = '')

	// 插入一条数据。
	public function insertOne($sets, $table = '')

	// 删除一条记录。
	public function deleteOne($wheres, $table = '')

	// 删除多条记录。 
	public function deleteBatch($wheres, $table = '')

	// 获取单条记录。
	public function queryRow($sql)

	// 获取多条记录。
	public function queryRows($sql)

	// 获取查询中的第一个地段的值，通常用来获取数据库条数。
	public function queryFirst($sql)

	
	// 执行一条SQL语句。
	// $sql参数是执行的语句；$params 是传入的参数列表此时使用pdo绑定参数执行； $options是传入pdo 驱动的配置参数；
	// $force_new 为真时会使用新创建的连接执行，否则使用连接池中的已有连接。
	// 返回值是一个PDOStatement 对象。
	// 关于PDO的更多内容请查看[官方文档](http://php.net/manual/zh/book.pdo.php)。
	public function query($sql, $params = array(), $options = array(), $force_new = false)

	// 	获取数据库多条记录中某一列或几列的值列表。
	public function getValues($rows, $fields)

	// 格式化数据库多条记录，以$field字段位key。
	public function formatRows($rows, $field)

	// 过滤字符串，相当于内置的 mysql_real_escape_string 方法。
	public function escape($v)

	// 开启事务。
	public function begin()

	// 提交事务。
	public function commit()

	// 回滚事务。
	public function rollback()

}

```

上述所有函数中参数说明

* $where_array 

条件数组。 

```php
//支持的条件表达方式
$cond_array = array(
        'status' => 1,
        'id' => array(1, 2, 3),
        'status' => array('!=' => 1),
        'title' => array('like' => '%hello%'),
);
```

* $sets 

设置数组。key 是字段名，value 是对应字段值。

* $table 是数据库表名。

为空时使用模型默认的数据表名($model->table)。

定义自己的数据库模型

```php
class UserModel extends Model {
	
	public function __construct(){
		//配置数组
		$cfg = array(
			'master' => array(
				'host' => 'localhost',
				'user' => 'root',
				'password' => '221.179.190.191',
			),
			'slave' => array(
				'host' => 'localhost',
				'user' => 'root',
				'password' => '221.179.190.191',
			),
		);
		// tuner 是数据库名称
		$this->db = Db::mysql($cfg, 'tuner');
		// 默认表名
		$this->table = 'user';
		parent::__construct();
	}
}

``` 

#### 防止SQL 注入

Model 中query开头的都是支持原生查询的，使用这类方法需要使用 escape 函数过滤参数来
放置SQL注入。

###模版

基于 [twig](http://twig.sensiolabs.org) 模版引擎。

###配置

实现对项目的配置管理。

###类库

常用的方法集合。
