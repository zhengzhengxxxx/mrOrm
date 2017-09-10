<?php
namespace RedisDriver\FnstRedisKM;
class HashKey extends Base implements Cache
{
    protected static $keyType = 'hash';
    protected static $hashField = [];//此key允许哪些field,为了统一性的考虑，这些key最好与db中对应的字段相同,否则无法使用model中的被动函数
    const CODE_FIELDCHANGE = -3;

    //返回hashField字段
    public static function getHashField()
    {
        return static::$hashField;
    }


    /**
     * @param $mixHashKey array|string
     * @return mixed
     * @throws \Exception
     */
    public function query($mixHashKey = [])
    {
        $redisReturn = [];
        $funcNum = func_num_args();
        if ($funcNum > 1) {
            throw new \Exception("cache function query param nums error");
        }
        if($funcNum == 0){
            $mixHashKey = [];
        }
        if (!is_array($mixHashKey)) {
            throw new \Exception("hashKey must be a array");
        }
        if (is_array($mixHashKey)) {
            if ($mixHashKey == []) {
                $redisReturn = $this->redis->hGetAll($this->keyName);
                if ($redisReturn) {
                    $this->setExpire($this->keyName);
                }
            } else {
                $diff = array_diff($mixHashKey, static::$hashField);
                if (!empty($diff)) {
                    //var_dump($this->keyName);die;  //todo 上线时去掉
                    throw new \Exception("The query keys not in class hashField query:" . json_encode($mixHashKey) . " class:" . json_encode(static::$hashField));
                }
                //hmget因为指定某key的时候，会返回一个有key名的数组，这样的话，如果key不存在，也会返回一个不为空的数组
                //所以要加上判断，如果全部key都是false,证明这个key不存在

                $redisRe = $this->redis->hMGet($this->keyName, $mixHashKey);
                $countTrue = 0;
                foreach ($redisRe as $v) {
                    if ($v !== false) {
                        $countTrue++;
                    }
                }
                if ($countTrue === count($redisRe)) {
                    $missingFieldFlag = false;
                    $redisReturn = $redisRe;
                    $this->setExpire($this->keyName);
                } else {
                    $missingFieldFlag = true;
                    $redisReturn = [];
                }
            }
        }
        //在这里加上自动更新field的逻辑
        if($redisReturn){
            if($mixHashKey == []){
                $key = array_keys($redisReturn);
                $arrField = static::$hashField;
                sort($key);
                sort($arrField);
                $same = ($key==$arrField);
                if($same){
                    $return = $this->queryOut($redisReturn);
                }else{
                    $return = ['code'=>static::CODE_FIELDCHANGE,'data'=>[]];
                }
            }elseif($missingFieldFlag == true){
                $return = ['code'=>static::CODE_FIELDCHANGE,'data'=>[]];
            }else{
                $return = $this->queryOut($redisReturn);
            }
        }else{
            $return = $this->queryOut($redisReturn);
        }
        return $return;
    }

    /**
     * @param null $arrKV ['field'=>'value',] 一个或多个
     * @return mixed
     * @throws \Exception
     */
    public function add($arrKV = [])
    {
        $funcNum = func_num_args();
        if ($funcNum != 1) {
            throw new \Exception("cache function query param nums error");
        }
        if (!is_array($arrKV)) {
            throw new \Exception("hashKey must be a array");
        }
        //插入前校验是否是本类允许的field
        $inputKeys = array_keys($arrKV);
        $diff = array_diff($inputKeys, static::$hashField);
        if (!empty($diff)) {
            throw new \Exception("The add keys not in class hashField add:" . json_encode($inputKeys) . " class:" . json_encode(static::$hashField));
        }
        //检查本key的hashField在插入的K中全部存在
        $diff = array_diff(static::$hashField,$inputKeys);
        if (!empty($diff)) {
            throw new \Exception("The class hashField not in add keys add:" . json_encode($inputKeys) . " class:" . json_encode(static::$hashField));
        }
        $return = $this->redis->hMset($this->keyName, $arrKV);
        if ($return) {
            $this->setExpire($this->keyName);
        } else {
            $logger = \logger::getInstance();
            $logger->error("hmset error key:{$this->keyName}");
        }
        return $return;
    }

    //初始化添加
    public function initAdd($arrKV = []){
        $this->delKey();
        return $this->add($arrKV);
    }

    /**
     * 对key进行修改，传入的是需要修改的字段的k=>v,如果key不存在的话不执行修改
     * @param array $arrKV
     * @return bool
     * @throws \Exception
     */
    public function update($arrKV = [])
    {
        $funcNum = func_num_args();
        if ($funcNum != 1) {
            throw new \Exception("cache function update param nums error");
        }
        if (!is_array($arrKV)) {
            throw new \Exception("hashKey must be a array");
        }
        //插入前校验是否是本类允许的field
        $inputKeys = array_keys($arrKV);
        $diff = array_diff($inputKeys, static::$hashField);
        if (!empty($diff)) {
            throw new \Exception("The add keys not in class hashField add:" . json_encode($inputKeys) . " class:" . json_encode(static::$hashField));
        }
        $exist = $this->keyNameExists();
        if ($exist == static::CODE_SUCC) {
            $return = $this->redis->hMset($this->keyName, $arrKV);
        } else {
            $return = false;
        }
        return $return;
    }

    //1.格式化数据，去掉不属于本hashField的字段
    //2.第二个参数如果不为空，在1的前提下只保留第二个参数的这些字段
    public function formatHashField($inputArr, $storeField = [])
    {
        $classField = static::$hashField;
        if (!empty($storeField)) {
            $arrStoreField = array_intersect($storeField, $classField);
        } else {
            $arrStoreField = $classField;
        }

        foreach ($inputArr as $k => $v) {
            if (!in_array($k, $arrStoreField)) {
                unset($inputArr[$k]);
            }
        }
        return $inputArr;
    }

    //先格式化数据然后再插入
    public function formatAdd($inputArr)
    {
        $inputData = $this->formatHashField($inputArr);
        return $this->add($inputData);
    }
}