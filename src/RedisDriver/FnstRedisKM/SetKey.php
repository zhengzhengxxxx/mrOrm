<?php
namespace RedisDriver\FnstRedisKM;
class SetKey extends Base implements Cache
{
    const KEY_TYPE = 'set';
    const EXPIRE_TIME = 10000;

    public function query($num=null)
    {
        $funcNum = func_num_args();
        if ($funcNum != 0) {
            throw new \Exception("cache function query param nums error");
        }

        $redisReturn = $this->redis->sRandMember($this->keyName);
        $return = $this->queryOut($redisReturn);
        return $return;
    }

    public function add($value = null)
    {
        if(empty($value)){
            throw new \Exception("set can`t set a empty value");
        }
        $funcNum = func_num_args();
        if ($funcNum != 1) {
            throw new \Exception("cache function query param nums error");
        }
        $funcArgs = func_get_args();
        $value = $funcArgs[0];
        $return = $this->redis->sAdd($this->keyName, $value);
        if($return){
            $this->setExpire($this->keyName);
        }
        return $return;
    }

    public function update($value=null)
    {
        return false;
    }
}