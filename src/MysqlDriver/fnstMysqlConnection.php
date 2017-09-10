<?php
//数据库连接类
namespace MysqlDriver;
use \PDO;
class fnstMysqlConnection{
    //数据库配置，第一次初始化时需要配置一下
    //protected $arrDbConfig = [];
    //连接池
    protected $arrConnection=[];
    //自己的单例
    protected static $self=null;
    
    //获取单例
    public static function getInstance(){
        if(static::$self==null){
            static::$self=new static();
        }
        return static::$self;
    }
    
    //初始化
//    public function init($arrDbConfig){
//        $this->arrDbConfig = $arrDbConfig;
//    }


    //获取一个连接
    public function getConnection($dbConfig){
        if(empty($dbConfig)){
            throw new \Exception("dbconfig is empty can`t connect ");
        }
        $json = json_encode($dbConfig);
        if(!isset($this->arrConnection[$json])){
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};port={$dbConfig['port']}";
            $user=$dbConfig['user'];
            $pw = $dbConfig['pw'];
            $connection = new PDO($dsn, $user, $pw);
            $this->arrConnection[$json] = $connection;
        }
        return $this->arrConnection[$json];
    }
    
}