<?php


//数据库连接类
namespace MysqlDriver;
class fnstMysqlDriver{
    //连接名，子类不可为空
    const CONNECTION_NAME = null;
    //表名
    const TABLE_NAME = null;
    //uuid键名
    const UUID_KEY = "uuid";
    //是否启用uuid
    const ENABLE_UUID = true;
    //主键名
    const PRIMARY_KEY = "id";
    //删除字段状态位
    const DEL_STATUS_COL = null;
    //删除状态正常时的值
    const DEL_STATUS_EXIST_VALUE = '1';
    //删除状态位删除时的值
    const DEL_STATUS_DEL_VALUE = '0';
    //哪个字段代表执行删除的用户的uuid
    const DEL_OPERATOR_UUID_COL = "delUser_uuid";

    //设置已经配置好的sqlBuilder
    protected $builder = null;

    public function setBuilder($builder){
        $this->builder = $builder;
    }

    public function getBuilder(){
        return $this->builder;
    }

    //调用不存在的方法，去builder中找
    public function __call($method,$arg){
        return call_user_func_array([$this->builder,$method],$arg);
    }
}