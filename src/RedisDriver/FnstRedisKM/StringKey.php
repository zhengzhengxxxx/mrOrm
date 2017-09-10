<?php
namespace RedisDriver\FnstRedisKM;
class StringKey extends Base implements Cache
{
    const KEY_TYPE = 'string';
    const EXPIRE_TIME = 10000;

    public function setCache($value = null)
    {
        $funcNum = func_num_args();
        if ($funcNum != 1) {
            throw new \Exception("cache function query param nums error");
        }
        $funcArgs = func_get_args();
        $value = $funcArgs[0];
        $return = $this->redis->set($this->keyName, $value);
        if ($return) {
            $this->setExpire($this->keyName);
        }
        return $return;
    }

    public function query()
    {
        $funcNum = func_num_args();
        if ($funcNum != 0) {
            throw new \Exception("cache function query param nums error");
        }
        $redisReturn = $this->redis->get($this->keyName);
        $return = $this->queryOut($redisReturn);
        return $return;
    }

    public function add($value = null)
    {
        $value = (string)$value;
        if(empty($value) && $value!=="0"){
            throw new \Exception("string can`t set a empty value");
        }
        $funcNum = func_num_args();
        if ($funcNum != 1) {
            throw new \Exception("cache function query param nums error");
        }
        $funcArgs = func_get_args();
        $value = $funcArgs[0];
        $return = $this->redis->set($this->keyName, $value);
        if($return){
            $this->setExpire($this->keyName);
        }
        return $return;
    }

    public function update($value=null)
    {
        if(empty($value)){
            throw new \Exception("string can`t set a empty value");
        }
        $funcNum = func_num_args();
        if ($funcNum != 1) {
            throw new \Exception("cache function query param nums error");
        }
        $funcArgs = func_get_args();
        $value = $funcArgs[0];
        $exists = $this->redis->exists($this->keyName);
        if($exists){
            $return = $this->redis->set($this->keyName, $value);
            if($return){
                $this->setExpire($this->keyName);
            }
        }
        return $return;
    }

    //自增
    public function incr(){
        $return = $this->redis->incr($this->keyName);
        return $return;
    }
}