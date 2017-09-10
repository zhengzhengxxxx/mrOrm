<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/9
 * Time: 22:51
 */
namespace RedisDriver;
class FnstOperator
{
    const WRITE_TYPE = 1;
    const READ_TYPE = 2;

    const CONFIG_TIMEOUT = 5;
    const CONFIG_PREFIX = '';
    const CONFIG_PW = '';
    const CONFIG_DATABASE = 0;

    protected $slef = null;
    protected static $arrRedisConnection = [];
    protected $wrConfig = [];//一组读或写的配置
    protected $arrWriteFun = ['set','mset', 'push', 'lPush', 'keys', 'lPop', 'rPop', 'sAdd', 'sRem', 'sRandMember','sPop', 'hMset', 'hDel', 'delete','del', 'hSet', 'expire', 'set','setnx','incr', 'decr', 'zAdd','zRem','zRemRangeByScore', 'incrBy', 'decrBy', 'hIncrBy','ttl','expireAt',"brPop"];
    protected $arrReadFun = ['hExists', 'get','mget', 'lRange', 'sMembers', 'hGet', 'hMGet', 'hGetAll', 'sIsMember', 'exists', 'zRange', 'zCard', 'zRevRange','zRangeByScore','zRevRangeByScore', 'zRank', 'hKeys', 'hLen', 'sCard','zRank'];

    public function __construct($wrConfig)
    {
        $this->wrConfig = $wrConfig;
    }


    //获取redis连接
    protected function getRedisConection($config)
    {
        $json = json_encode($config);
        if(!isset(static::$arrRedisConnection[$json])){
            $redis = new \Redis();
            $ip = $config['host'];
            $port = $config['port'];
            $timeOut = isset($config['timeout']) ? $config['timeout'] : static::CONFIG_TIMEOUT;
            $prefix = isset($config['prefix']) ? $config['prefix'] : static::CONFIG_PREFIX;
            $pwd = isset($config['pw']) ? $config['pw'] : static::CONFIG_PW;
            $databases = isset($config['database']) ? $config['database'] : static::CONFIG_DATABASE;
            $mixRet = $redis->connect($ip, $port, $timeOut);

            if (!$mixRet) {
                throw new \Exception("Redis server can not connect!");
            }
            if ($pwd) {
                $redis->auth($pwd);
            }
            if ($prefix) {
                $redis->setOption(\Redis::OPT_PREFIX, $prefix);
            }
            if ($databases) {
                $redis->select($databases);
            }
            static::$arrRedisConnection[$json] = $redis;
        }
        return static::$arrRedisConnection[$json];
    }

    //执行redis方法时候通过__call调用真正的redis方法
    public function __call($fun, $args)
    {
        //根据函数名判断用读还是写配置
        $type = $this->readOrWrite($fun);
        //根据配置去拿连接
        $config = $this->getConfig($type);
        $redis = $this->getRedisConection($config);
        //var_dump($fun, $args);die;
        //拿到连接后执行函数并返回结果
        return call_user_func_array([$redis,$fun],$args);
    }

    //根据读或写拿到最终配置
    protected function getConfig($type)
    {
        if ($type == static::WRITE_TYPE) {
            return $this->wrConfig['write'];
        }
        if ($type == static::READ_TYPE) {
            return $this->wrConfig['read'];
        }
        throw new \Exception("FnstOperator type not read or write");
    }

    //通过函数名称判断用读还是写
    protected function readOrWrite($funName)
    {
        if (in_array($funName, $this->arrWriteFun)) {
            return static::WRITE_TYPE;
        }
        if (in_array($funName, $this->arrReadFun)) {
            return static::READ_TYPE;
        }
        throw new \Exception("FnstOperator unsupport fun: {$funName}");
    }

}