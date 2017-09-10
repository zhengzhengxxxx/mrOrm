<?php
//配置文件，自己根据环境定义
$dbConfig = [
	'db1'=>['host'=>'127.0.0.1','port'=>'3306','dbname'=>'test','user'=>'root','pw'=>'']
	];

$cacheConfig = [
	'redis1'=>[
		[
            'write' => ['host' => '127.0.0.1', 'port' => '6379', 'pw' => '', 'database' => 0, 'timeout' => 3],
            'read' => ['host' => '127.0.0.1', 'port' => '6379', 'pw' => '', 'database' => 0, 'timeout' => 3],
        ]
	]
];


//建表sql
/*
CREATE TABLE `mr_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_age` int(11) NOT NULL,
  `add_time` int(11) NOT NULL,
  `del_status` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
*/

//加载各种文件
require("./load.php");



$userModel = new userModel();
$userModel->setDbConf($dbConfig);
$userModel->setRedisConf($cacheConfig);
//查询列表
$age = "45";
$queryRe = $userModel->queryList($age);
if($queryRe){
	echo "列表中存在的元素是：<br/>";
	foreach($queryRe as $v){
		echo $v."<br/>";
	}
}else{
	echo "列表中尚未存在元素<br/>";
}
echo "<br/>";
//执行插入操作
$userName = "niko";
$userAge = "45";
$addTime = time();
$insertRe = $userModel->add($userName,$userAge,$addTime);
if($insertRe){
	echo "成功插入数据，uuid:{$insertRe}";
}else{
	echo "插入数据失败";
}



