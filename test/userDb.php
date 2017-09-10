<?php

//用户对应的db表
class UserDb extends \MysqlDriver\fnstMysqlDriverAllList{
    //连接名，子类不可为空
    const CONNECTION_NAME = "db1";
    //表名
    const TABLE_NAME = "mr_user";
	//删除字段状态位
    const DEL_STATUS_COL = "del_status";
}