<?php
/**
 * 【此类型基本已经被弃用】
 */
namespace RedisDriver\FnstRedisKM;

class ZsetKey extends Base implements Cache
{
    const KEY_TYPE = 'zset';
    const COMMAND = 'zRevRange';

    protected $countKeyName = null;

    protected function cleanProperty()
    {
        parent::cleanProperty();
        $this->countKeyName = null;
    }

    protected function afterCreateKey($param, $arrSuffix)
    {
        parent::afterCreateKey($param, $arrSuffix);
        $this->countKeyName = "[count]_" . $this->keyName;
    }

    //zset的时候 删除nullkey 还要删除counkey
    public function setNullKey()
    {
        parent::setNullKey();
        $this->redis->delete($this->countKeyName);
    }

    //将 一个数组中的全部内容写入zset,key=score，value=value
    final public function setData($arrData)
    {
        $redisKey = $this->keyName;

        //设置缓存数据
        $redis = $this->redis;
        $fun_array = [];
        $fun_array[] = $redisKey;
        foreach ($arrData as $k => $v) {
            $fun_array[] = $k;
            $fun_array[] = $v;
        }
        call_user_func_array([$redis, 'zAdd'], $fun_array);
        $this->setExpire($redisKey);
    }

    /**
     *
     * @param int $start
     * @param int $end
     * @param null $command
     * @param null $withscore
     * @return bool
     * @throws \Exception
     * @return 正常情况下返回数组，
     *          当nullkey存在时，返回-1，
     *          当nullkey,普通key都不存在时，返回-2
     */
    public function query($start = 0, $end = 100, $command = null, $withscore = null)
    {
        $funcNum = func_num_args();
        if ($funcNum < 0 || $funcNum > 4) {
            throw new \Exception("cache function query param nums error");
        }
        $funcArgs = func_get_args();
        $start = isset($funcArgs[0]) ? $funcArgs[0] : 0;
        $end = isset($funcArgs[1]) ? $funcArgs[1] : 100;
        $command = isset($funcArgs[2]) ? $funcArgs[2] : static::COMMAND;
        $withscore = isset($funcArgs[3]) ? $funcArgs[3] : null;
        if (!in_array($command, ['zRange', 'zRevRange', 'zRangeByScore', 'zRevrangeByScore'])) {
            throw new \Exception("not alllow this command:" . $command);
        }
        $redisReturn = $this->redis->$command($this->keyName, $start, $end, $withscore);
        $return = $this->queryOut($redisReturn);
        return $return;
    }

    /**
     * 接受一个数组,key为score,value为值
     */
    public function add($score = null, $value = null)
    {
        $exists = $this->keyNameExists();
        $return = false;
        if ($exists != static::CODE_NOCACHE) {
            $funcNum = func_num_args();
            if ($funcNum < 1 || $funcNum > 2) {
                throw new \Exception("cache function query param nums error");
            }
            $funcArgs = func_get_args();
            $score = $funcArgs[0];
            $value = $funcArgs[1];
            $addRe = $this->redis->zAdd($this->keyName, $score, $value);
            if ($addRe) {
                $return = true;
                $this->setExpire($this->keyName);
                $this->redis->incr($this->countKeyName);
                $this->setExpire($this->countKeyName);
                if ($exists == static::CODE_NULL) {
                    $this->cleanNullKey();
                }
            }
        }
        return $return;
    }

    /**
     * [未测试]
     * 接受一个数组,key为score,value为值
     * @param $arrData
     */
    public function addSome($arrKv)
    {

    }

    //获取count数
    public function getCount()
    {
        $count = $this->redis->get($this->countKeyName);
        if ($count !== false) {
            $this->setExpire($this->countKeyName);
        }
        return $count;
    }

    //设置count键，此功能主要用于count键丢失时，补count键
    public function setCount($count)
    {
        $r = $this->redis->set($this->countKeyName, $count);
        if ($r) {
            $this->setExpire($this->countKeyName);
        }
        return $r;
    }

    //返回集合数量
    public function zCard()
    {
        return $this->redis->zcard($this->keyName);
    }

    /**
     * @param string $value
     * @param string $score
     */
    public function update($score = '', $value = '')
    {
        $exists = $this->keyNameExists();
        $return = false;
        if ($exists == static::CODE_SUCC) {
            $rank = $this->redis->zRank($this->keyName, $value);
            if ($rank !== false) {//在集合中存在，才去更新
                $return = $this->redis->zAdd($this->keyName, $score, $value);
            }
        }
        return $return;
    }


    /**[未测试]
     * 移除列表中的一个元素
     * @param string $value
     * @return bool
     */
    public function zRem($value)
    {
        $exists = $this->keyNameExists();
        $return = false;
        if ($exists == static::CODE_SUCC) {
            $return = $this->redis->zRem($this->keyName, $value);
            $this->redis->decr($this->countKeyName);
        }
        return $return;
    }

    /**[未测试]
     * 移除列表中的多个元素
     * @param array $mixValue
     * @return bool|mixed
     */
    public function zRemSome($arrValue)
    {
        $exists = $this->keyNameExists();
        $return = false;
        if ($exists == static::CODE_SUCC) {
            $redis = $this->redis;
            $funArray = [];
            $funArray[] = $this->keyName;
            $funArray = array_merge($funArray, $arrValue);
            $return = call_user_func_array([$redis, 'zRem'], $funArray);
        }
        return $return;
    }

    //获取元素的排名
    public function zRank($element)
    {
        return $this->redis->zRank($this->keyName,$element);
    }
}
