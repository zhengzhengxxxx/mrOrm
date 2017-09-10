<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/1/23
 * Time: 13:29
 */
namespace RedisDriver\FnstRedisKM;

class ZsetAllKey extends Base
{
    const KEY_TYPE = 'zset';
    const COMMAND = 'zRange';
    //此常量为true时候，使用插入结果的uuid为cache的value
    const USE_VALUE_BY_INSERT_UUID = true;
    //此常量为true时候，使用插入结果的id为cache的value
    const USE_VALUE_BY_INSERT_ID = false;
    //score字段名
    const SCORE_FIELD = "addTime";
    //value字段名
    const VALUE_FIELD = "uuid";
    //score来自的表，null代表就是默认
    const SCORE_TABLE = null;
    //value来自的表，null代表就是默认
    const VALUE_TABLE = null;

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

    //将 一个数组中的全部内容写入zset,key=value，value=key
    final public function addSome($arrData)
    {
        $redisKey = $this->keyName;

        //设置缓存数据
        $redis = $this->redis;
        $fun_array = [];
        $fun_array[] = $redisKey;
        foreach ($arrData as $k => $v) {
            $fun_array[] = $v;
            $fun_array[] = $k;
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
    public function query($start = 0, $end = -1, $withscore = null,$command=null)
    {
        $funcNum = func_num_args();
        if ($funcNum < 0 || $funcNum > 4) {
            throw new \Exception("cache function query param nums error");
        }
        $funcArgs = func_get_args();
        $start = isset($funcArgs[0]) ? $funcArgs[0] : 0;
        $end = isset($funcArgs[1]) ? $funcArgs[1] : 100;
        if($command==null){
            $command = static::COMMAND;
        }
        $withscore = isset($funcArgs[2]) ? $funcArgs[2] : null;
        if (!in_array($command, ['zRange', 'zRevRange', 'zRangeByScore', 'zRevRangeByScore'])) {
            throw new \Exception("not alllow this command:" . $command);
        }
        if(in_array($command,['zRangeByScore', 'zRevRangeByScore'])){
            $option = ['withscores'=>$withscore];
        }else{
            $option = $withscore;
        }
        $redisReturn = $this->redis->$command($this->keyName, $start, $end, $option);
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
            }
        }
        return $return;
    }

    //获取count数，返回-1代表
    public function getCount()
    {
        $exists = $this->keyNameExists();
        if($exists == static::CODE_SUCC){
            $count = $this->redis->zCard($this->keyName);
            $return = ['count'=>$count,'code'=>static::CODE_SUCC];
        }elseif($exists == static::CODE_NULL){
            $return = ['count'=>0,'code'=>static::CODE_NULL];
        }else{
            $return = ['count'=>null,'code'=>static::CODE_NOCACHE];
        }
        return $return;
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

    //删除分数之间的
    public function zRemRangeByScore($minScore,$maxScore){
        $exists = $this->keyNameExists();
        $return = false;
        if ($exists == static::CODE_SUCC) {
            //var_dump($minScore,$maxScore);die;
            $return = $this->redis->zRemRangeByScore($this->keyName, $minScore,$maxScore);
            $this->redis->decrBy($this->countKeyName,$return);
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
