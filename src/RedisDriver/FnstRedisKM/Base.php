<?php
//redis key manager
namespace RedisDriver\FnstRedisKM;

interface Cache
{
    //抽象方法，最终使用者都是通过此函数来读取缓存
    public function query();

    //更新方法
    public function add();

    //修改方法
    public function update();
}

class Base
{
    //key的类型,子类继承要重写他
    protected static $keyType = NULL;
    //key的名称模板,最终实现不可为NULL
    protected static $keyNameTemplate = NULL;
    /**
     * key的后缀
     * 如果不为空，那么key在构建的时候也要写上每个suffix
     * 不允许出现KEY_SUFFIX中有值，而构建的key没有suffix的情况(为了统一考虑)
     */
    protected static $keySuffix = NULL;
    //key的过期时间,0表示不过期，以秒为单位
    protected static $expireTime = 0;
    //redis使用的配置组，子类不可为0
    protected static $configGroup = null;

    //以$keyIsNull作为key的时候的值
    const NULL_KEY_VALUE = 1;
    //正常返回（有key,没有nullKey）
    const CODE_SUCC = 0;
    //有缓存信息，但是没有数据（没有key,有nullKey）
    const CODE_NULL = -1;
    //没有缓存信息（没有key,没有nullKey）
    const CODE_NOCACHE = -2;

    //key由模板生成的真实名称
    protected $keyName = null;
    //key不存在时，缓存的key名,需要初始化时赋值
    protected $nullKeyName = null;
    //参数，一个关联数组，key为模板中的占位符，值为实际的变量
    protected $params = [];
    //自己的实例
    protected static $self = null;
    //redis实例
    protected $redis;
    //后缀，最后写入值得时候会拼接
    protected $strSuffix = '';

    /**
     * 获取单例
     * @return null|static
     */
    public static function getInstance()
    {
        if (empty(static::$self)) {
            $className = get_called_class();
            static::$self = new $className();
        }
        return static::$self;
    }

    //返回配置组信息
    public static function getConfigGroup()
    {
        return static::$configGroup;
    }


    //清除keyName,nullKeyName等等属性
    protected function cleanProperty()
    {
        $this->keyName = null;
        $this->nullKeyName = null;
    }

    //在创建key之前执行的东西
    protected function beforeCreateKey($params, $arrSuffix)
    {

    }

    //获取返回格式
    protected function queryOutStd($code, $data = null)
    {
        return ['code' => $code, 'data' => $data];
    }

    protected function querySuccOut($data)
    {
        $return = $this->queryOutStd(static::CODE_SUCC, $data);
        return $return;
    }

    //获取没有数据的返回
    protected function queryErrOut()
    {
        $nullKey = $this->nullKeyExists();
        if ($nullKey) {
            $return = $this->queryOutStd(static::CODE_NULL);
        } else {
            $return = $this->queryOutStd(static::CODE_NOCACHE);//这种情况是key不存在，nullkey也不存在
        }
        return $return;
    }

    //返回结果
    protected function queryOut($data)
    {
        if (!empty($data) && ($data!=[])) {
            $this->setExpire($this->keyName);//设置过期时间
            $return = $this->querySuccOut($data);
        } else {
            $exists = $this->keyNameExists();
            if ($exists != static::CODE_NOCACHE) {//这里是为了兼容zset情况,zset可能因为传入的边界不对，查询不到数据，返回空数组，但是如果key不存在也是返回空数组
                $return = $this->querySuccOut($data);
            } else {
                $return = $this->queryErrOut();
            }
        }
        return $return;
    }

    //在创建key之后执行的东西
    protected function afterCreateKey($params, $arrSuffix)
    {
        //创建后缀
        $this->createSuffix($arrSuffix);
        //创建nullKey
        $this->createNullKey();
        //合并后缀
        $this->combineSuffix();
    }

    //将后缀合并到key名上
    final protected function combineSuffix()
    {
        $this->keyName = $this->keyName . $this->strSuffix;
        $this->nullKeyName = $this->nullKeyName . $this->strSuffix;
    }

    //创建后缀
    final protected function createSuffix($inputSuffix)
    {
        if (!empty(static::$keySuffix)) {
            if (empty($inputSuffix)) {
                throw new \Exception("The suffix input is empty ");
            }
            foreach (static::$keySuffix as $suffKey) {
                //验证suffix的完整性
                if (!isset($inputSuffix[$suffKey])) {
                    throw new \Exception("The input suffix lack:{$suffKey}");
                }
                $suffix = "_[" . $suffKey . ":" . $inputSuffix[$suffKey] . "]";
                $this->strSuffix .= $suffix;
            }

        }
    }

    /**
     * 传入数据，获得redisKey
     * @params $arrInput 输入的查询数组
     * @param $arrQuery 缓存的key,也是数据库对应的字段名
     * @throws \Exception
     */
    final protected function createKey($param)
    {
        $hasParam = strstr(static::$keyNameTemplate, '{#}');
        if($hasParam){
            if(empty($param)){
                throw new \Exception("key Template error: The " . static::$keyNameTemplate . " you must input param");
            }else{
                $this->keyName = str_replace("{#}", $param, static::$keyNameTemplate);
            }
        }else{
            if($param){
                throw new \Exception("key Template error: The " . static::$keyNameTemplate . " not have {#}");
            }else{
                $this->keyName = static::$keyNameTemplate;
            }
        }
        return $this->keyName;
    }

    /**
     * 新建key的时候都要调用这个方法来清除nullKey,
     * 此功能一般用于新插入记录时候或生成缓存时调用
     */
    final public function cleanNullKey()
    {
        return $this->redis->delete($this->nullKeyName);
    }

    //设置nullKey,这只null key后，会删除原来的key
    public function setNullKey()
    {
        $return = $this->redis->set($this->nullKeyName, static::NULL_KEY_VALUE);
        $this->setExpire($this->nullKeyName);
        $this->redis->delete($this->keyName);
        return $return;
    }

    //查询nullKey是否存在
    protected function nullKeyExists()
    {
        $data = $this->redis->get($this->nullKeyName);
        if ($data) {
            $this->setExpire($this->nullKeyName);
            $return = true;
        } else {
            $return = false;
        }
        return $return;
    }

    //判断当前key是否存在
    //返回1代表key存在，返回-1代表nullKey存在，返回false代表什么都不存在
    public function keyNameExists()
    {
        $keyExists = $this->redis->exists($this->keyName);
        if ($keyExists) {
            return static::CODE_SUCC;
        }
        $nullKeyExists = $this->redis->exists($this->nullKeyName);
        if ($nullKeyExists) {
            return static::CODE_NULL;
        }
        return static::CODE_NOCACHE;
    }

    //设置过期时间,
    //主要用于2点：
    //1.第一次生成缓存时设置过期时间
    //2.以后每次用户访问这个key的时候设置过期时间
    protected function setExpire($key, $scale = 10)
    {
        if ($scale < 1) {
            throw new \Exception("The scale can`t < 1 !!");
        }
        if (static::$expireTime > 0) {
            $step = ceil((static::$expireTime) / $scale);
            $ttl = $this->redis->ttl($key);
            //ttl小于key的过期时间时，才会加时间
            if ($ttl < static::$expireTime) {
                $tmpExpire = $step + $ttl;
                $expire = ($tmpExpire < static::$expireTime) ? $tmpExpire : static::$expireTime;
                $this->redis->expire($key, $expire);
            }
        }
    }

    //此key每次被查询时，调用的设置过期时间函数
    public function queryingSetExpire($key)
    {
        $this->setExpire($key);
    }

    //此key第一次被写入时调用的设置过期时间函数
    public function creatingSetExpire($key)
    {
        $this->setExpire($key);
    }

    //设置redis连接
    final protected function setRedisConnection($connection)
    {
        $this->redis = $connection;
    }

    //创建一个key,尚未写入数据
    /**
     * 生成key,nullkey,suffix三种
     * @param $params
     * @param array $arrSuffix
     * @throws \Exception
     */
    public function buildKey($param = '', $arrSuffix = [])
    {
        $this->cleanProperty();
        $this->beforeCreateKey($param, $arrSuffix);
        $this->createKey($param, $arrSuffix);
        $this->afterCreateKey($param, $arrSuffix);
        return $this->keyName;
    }

    //创建出nullkey,不存在的标志
    final protected function createNullKey()
    {
        $this->nullKeyName = "[null]_" . $this->keyName;
    }

    //获取key的名字
    public function getKey()
    {
        return $this->keyName;
    }


    //删除key，并且设置一个nullkey
    public function del()
    {
        $return = $this->redis->delete($this->keyName);
        if ($return) {
            $this->setNullKey();
        }
        return $return;
    }

    //删除key
    public function delKey()
    {
        return $this->redis->delete($this->keyName);
    }

    //将redisOperator设置为自己的属性  
    public function setRedisOperator($redisOperator)
    {
        $this->redis = $redisOperator;
    }

    //获取keyName
    public function getKeyName()
    {
        return $this->keyName;
    }

    //清理掉全部的key
    public function clearKey(){
        $this->redis->delete($this->keyName);
        $this->redis->delete($this->nullKeyName);
    }
}