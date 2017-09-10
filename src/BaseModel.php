<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/1
 * Time: 13:46
 */
use RedisDriver\FnstRedisRouter\Average;
use RedisDriver\FnstOperator;

class BaseModel
{
    public static $dbName = null;
    public static $cacheName = null;
	
    protected static $self = [];
	
	protected $dbConf = [];
	protected $redisConf = [];
	protected $route = null;

    public static function getInstance()
    {
        $className = get_called_class();
        if (empty(static::$self[$className])) {
            static::$self[$className] = new static();
        }
        return static::$self[$className];
    }
	
	//设置db配置
	public function setDbConf($dbConf){
		$this->dbConf = $dbConf;
	}
	//设置redis配置
	public function setRedisConf($redisConf){
		$this->redisConf = $redisConf;
	}

    //获取一个db对象
    public static function getDbObj($dbName){
        $className = $dbName;

        return new $className;
    }

    //获取一个redis的对象名
    public function getCacheName($cacheName)
    {
        return $cacheName . "Cache";
    }

    //获取一个配置好的redisOperator对象
    public function getCache($cacheName, $param = '', $suffix = [])
    {
        //生成一个缓存对象，并构建一个key
        $cacheObj = new $cacheName;
        $key = $cacheObj->buildKey($param, $suffix);
        //选出配置
        $configGroupInfo = $cacheObj::getConfigGroup();
        $redisConfig = $this->redisConf;
        $configGroup = $redisConfig[$configGroupInfo];

        $router = new Average($configGroup);
        $config = $router->getConfig($key);
        //将配置赋给执行者
        $operator = new FnstOperator($config);
        //将执行者赋给缓存对象，并返回这个缓存对象
        $cacheObj->setRedisOperator($operator);
        return $cacheObj;
    }


    /**
     * 获取一个db的对象
     * @param $dbName
     * @return \MysqlDriver\fnstMysqlSqlBuilder
     */
    public function getDb($dbName)
    {
        $dbObj = static::getDbObj($dbName);
        $connectionName = $dbObj::CONNECTION_NAME;
        $dbConfig = $this->dbConf;
        $config = $dbConfig[$connectionName];
        $connectionObj = \MysqlDriver\fnstMysqlConnection::getInstance();
        $connection = $connectionObj->getConnection($config);
        $builder = new \MysqlDriver\fnstMysqlSqlBuilder;
        $arrProperty=[
            'connection'=>$dbObj::CONNECTION_NAME,
            'tableName'=>$dbObj::TABLE_NAME,
            'uuidKeyField'=>$dbObj::UUID_KEY,
            'enableUuid'=>$dbObj::ENABLE_UUID,
            'delStatusCol'=>$dbObj::DEL_STATUS_COL,
            'delStatusExistValue'=>$dbObj::DEL_STATUS_EXIST_VALUE,
            'delStatusDelValue'=>$dbObj::DEL_STATUS_DEL_VALUE,
            'delOperatorCol'=>$dbObj::DEL_OPERATOR_UUID_COL,
        ];
        $builder->setDriverProperty($arrProperty);
        $builder->setConnection($connection);
        $dbObj->setBuilder($builder);
        return $dbObj;
    }


    ###########自增列表相关操作--开始###################
    //被动生成缓存，结合queryList使用
    protected function queryIncrListDb($cache, $db, $uuid)
    {
        $dbStart = $cache->segmentStart;
        $dbLimit = $cache::$step;
        $scoreField = $cache::SCORE_FIELD;
        $valueField = $cache::VALUE_FIELD;
        $dbRe = $db->queryListElement($uuid, $dbStart, $dbLimit,$scoreField,$valueField);//这里返回的k为value，v为score
        if (!$dbRe) {
            $return = false;
        } else {
            $args = [];
            foreach ($dbRe as $k => $v) {
                $args[] = $v;
                $args[] = $k;
            }
            $return = $cache->addSome($args);
        }
        return $return;
    }

    //生成一个mapField
    protected function setMapField($uuid, $cache, $db)
    {
        $dbStart = $cache->segmentStart;
        $dbLimit = $cache::$step;
        $scoreField = $cache::SCORE_FIELD;
        $count = $db->queryListElementTotal($uuid, $dbStart, $dbLimit,$scoreField);
        $cache->setMapField($count);
        return $count;
    }

    //生成全部mapField
    protected function setMapFieldAll($uuid, $cache, $db, $grossTotal)
    {
        $dbStart = 0;
        $dbLimit = $cache::$step;
        $cacheValue = [];
        //num为一共要循环多少次
        $num = intval($grossTotal / $cache::$step);
        if ($grossTotal % $cache::$step != 0) {
            $num++;
        }
        $scoreField = $cache::SCORE_FIELD;
        if($num==0){
            $return = false;
        }else{
            for ($i = 1; $i <= $num; $i++) {
                $count = $db->queryListElementTotal($uuid, $dbStart, $dbLimit,$scoreField);
                $cacheValue[] = $count;
                $dbStart += $cache::$step;
            }
            $return = $cache->initSegmentMap($cacheValue);
        }
        return $return;
    }

    //获取segmentMap（被动缓存）
    protected function getSegmentMap($uuid, $cache, $dbName, $grossTotal)
    {
        $mapKeyExists = $cache->checkMapKeyExists();
        if ($mapKeyExists == false) {
            $db = $this->getDb($dbName);
            $this->setMapFieldAll($uuid, $cache, $db, $grossTotal);
        }
        return $cache->getSegmentMap();
    }

    //获取虚总数(被动生成)
    protected function getGrossTotal($cache, $dbName, $uuid)
    {
        $countKeyExists = $cache->checkGrossKeyExists();
        if (!$countKeyExists) {
            $db = $this->getDb($dbName);
            $grossTotal = $db->queryGrossTotal($uuid);
            $cache->setGrossTotal($grossTotal);
        } else {
            $grossTotal = $cache->getGrossTotal();
        }
        return $grossTotal;
    }

    //获取实总数（被动生成）
    protected function getActualTotal($cache, $dbName, $uuid)
    {
        $countKeyExists = $cache->checkActualKeyExists();
        if (!$countKeyExists) {
            $db = $this->getDb($dbName);
            //实总数的sql比虚总数的多了一个状态位的判断
            $actualTotal = $db->queryActualTotal($uuid);
            $cache->setActualTotal($actualTotal);
        } else {
            $actualTotal = $cache->getActualTotal();
        }
        return $actualTotal;
    }

    //对于自增列表，每次使用之前都调用此函数进行初始化一下
    protected function initIncrList($cache, $dbName, $incrListUuid)
    {
        //检测插入前的虚总数
        $grossTotal = $this->getGrossTotal($cache, $dbName, $incrListUuid);
        //检测插入前的实总数
        $actualTotal = $this->getActualTotal($cache, $dbName, $incrListUuid);
        //检查map
        $map = $this->getSegmentMap($incrListUuid, $cache, $dbName, $grossTotal);
        return ['actualTotal' => $actualTotal, 'grossTotal' => $grossTotal, 'map' => $map];
    }

    //

    /**
     * 查询列表
     * @param $start
     * @param $end
     * @param $order
     * @param $cacheInput
     * @param $dbInput
     * @return array
     * @throws Exception
     */
    public function queryIncrList($cacheInput,$dbInput,$start, $end, $order)
    {
        $cacheName = isset($cacheInput['cacheName'])?$cacheInput['cacheName']:static::$cacheName;
        $cacheParam = $cacheInput['param'];
        $cacheSuffix = isset($cacheInput['suffix'])?$cacheInput['suffix']:[];

        $dbName = isset($dbInput['dbName']) ? $dbInput['dbName'] : static::$dbName;
        $incrListUuid = isset($dbInput['incrListUuid'])?$dbInput['incrListUuid']:$cacheInput['param'];

        $cache = $this->getCache($cacheName, $cacheParam, $cacheSuffix);

        $initRe = $this->initIncrList($cache, $dbName, $incrListUuid);
        //检测插入前的虚总数
        $grossTotal = $initRe['grossTotal'];
        //检测插入前的实总数
        $actualTotal = $initRe['actualTotal'];
        //检查map
        $map = $initRe['map'];
        //start与end的差距不可以超过一个步长值
        if (abs($start - $end) >= $cache::$step) {
            throw new \Exception("start({$start}) - end({$end}) > step(" . $cache::$step . ")");
        }
        if ($actualTotal == 0) {
            $return = ['count' => '0', 'data' => []];
        } elseif ($start > ($actualTotal - 1)) {
            $return = ['count' => '0', 'data' => []];
        } else {

            $same = $cache->inSameSegment($start, $end);
            if ($same) {
                $cache->setSegmentKeyNameByActual($start);
                //检查segment
                $segmentKeyExists = $cache->segmentKeyExists();
                if ($segmentKeyExists) {
                    $data = $cache->query($start, $end, $order);
                } else {
                    $db = $this->getDb($dbName);
                    $this->queryIncrListDb($cache, $db, $incrListUuid);
                    $data = $cache->query($start, $end,$order);
                }
            } else {//start和end落在了不同段内
                //进行低位计算
                $cache->setSegmentKeyNameByActual($start);
                $segmentKeyExists = $cache->segmentKeyExists();
                if ($segmentKeyExists) {
                    $dataL = $cache->query($start, -1, $order);
                } else {
                    $db = $this->getDb($dbName);
                    $this->queryIncrListDb($cache, $db, $incrListUuid);
                    $dataL = $cache->query($start, -1, $order);
                }
                //进行高位计算
                $cache->setSegmentKeyNameByActual($end);
                $segmentKeyExists = $cache->segmentKeyExists();
                if ($segmentKeyExists) {
                    $dataH = $cache->query(0, $end, $order);
                } else {
                    $db = $this->getDb($dbName);
                    $this->queryIncrListDb($cache, $db, $incrListUuid);
                    $dataH = $cache->query(0, $end, $order);
                }
                if ($order == "asc") {
                    $data = array_merge($dataL, $dataH);
                } else {
                    $data = array_merge($dataH, $dataL);
                }
            }
            $return = ['count' => $actualTotal, 'data' => $data];
        }
        return $return;
    }

    //查询自增列表的总数
    public function queryIncrListCount($cacheInput,$dbInput){
        $cacheName = isset($cacheInput['cacheName'])?$cacheInput['cacheName']:static::$cacheName;
        $cacheParam = $cacheInput['param'];
        $cacheSuffix = isset($cacheInput['suffix'])?$cacheInput['suffix']:[];

        $dbName = isset($dbInput['dbName']) ? $dbInput['dbName'] : static::$dbName;
        $incrListUuid = isset($dbInput['incrListUuid'])?$dbInput['incrListUuid']:$cacheInput['param'];

        $cache = $this->getCache($cacheName, $cacheParam, $cacheSuffix);

        $initRe = $this->initIncrList($cache, $dbName, $incrListUuid);

        //检测插入前的实总数
        $actualTotal = $initRe['actualTotal'];
        return $actualTotal;
    }

    //加锁逻辑
    protected function incrListLock($cache)
    {
        for ($i = 1; $i <= 20; $i++) {
            if ($cache->lock()) {
                $cache->lockTime();
                break;
            } else {
                usleep(100000);//0.1秒
            }
        }
        //$cache->unlock();//todo  上线时去掉
        if ($i >= 20) {
            $lockName = $cache->getLockName();
            $logger = logger::getInstance();
            $logger->error("The list is locked : {$lockName}");
            throw new \Exception(ERRCODE_SYS_BUSY, FNST_EXCEPTION_CODE);
        }
    }

    /**
     * 向递增列表添加元素（比如按照发布时间）
     * @param $cacheInput
     * @param $dbInput
     * @return 成功返回uuid,失败返回false
     * @throws Exception
     */
    public function addIncrList($cacheInput, $dbInput)
    {
        $cacheName = $cacheInput['cacheName'];
        $cacheParam = $cacheInput['param'];
        $cacheSuffix = isset($cacheInput['suffix']) ? $cacheInput['suffix'] : [];

        $dbName = isset($dbInput['dbName']) ? $dbInput['dbName'] : static::$dbName;
        $dbAttributes = $dbInput['attributes'];
        $incrListUuid = isset($dbInput['incrListUuid'])?$dbInput['incrListUuid']:$cacheInput['param'];

        $cache = $this->getCache($cacheName, $cacheParam, $cacheSuffix);
        $this->incrListLock($cache);//加锁
        $db = $this->getDb($dbName);
        $score = $dbInput['attributes'][$cache::SCORE_FIELD];//根据db的SCORE_FILED常量，得出哪个字段是缓存的score

        //初始化一些元素
        $this->initIncrList($cache, $dbName, $incrListUuid);
        $dbRe = $db->insert($dbAttributes);
        if ($dbRe) {
            if($cache::USE_VALUE_BY_INSERT_UUID==true){
                $value = $db->getInsertUuid();
            }elseif($cache::USE_VALUE_BY_INSERT_UUID==true){
                $value = $dbRe;
            }else{
                $value = $dbInput['attributes'][$cache::VALUE_FIELD];//根据db的VALUE_FILED常量，得出哪个字段是缓存的value
            }
            $return = $value;
            $cache->setAddSegmentKeyName();
            //判断是否为首次插入
            if ($cache->isFirst()) {
                $addRe = $cache->add($score, $value);
            } else {
                $segmentKeyExists = $cache->segmentKeyExists();
                if ($segmentKeyExists) {
                    $addRe = $cache->add($score, $value);
                } else {
                    $addRe = $this->queryIncrListDb($cache, $db, $incrListUuid);
                }
            }
            if ($addRe) {
                //虚总数+1
                $cache->incrGrossTotal();
                //实总数+1
                $cache->incrActualTotal();
                //map+1
                $cache->incrMapField();
            }
        } else {
            $return = false;
        }

        $cache->unLock();
        return $return;
    }

    //移除一个自增列表中的元素
    public function remIncrList($cacheInput, $dbInput,$remUuid,$releation=false)
    {
        //初始化参数
        $cacheName = $cacheInput['cacheName'];
        $cacheParam = $cacheInput['param'];
        $cacheSuffix = isset($cacheInput['suffix']) ? $cacheInput['suffix'] : [];
        $dbName = isset($dbInput['dbName']) ? $dbInput['dbName'] : static::$dbName;
        $delOperatorUuid = isset($dbInput['delUser_uuid'])?$dbInput['delUser_uuid']:null;
        //初始化db并得到incrListUuid
        $db = $this->getDb($dbName);
        //根据uuid,查询出incrListUuid
        //初始化cache并加锁
        $cache = $this->getCache($cacheName, $cacheParam, $cacheSuffix);
        $valueField = $cache::VALUE_FIELD;
        $incrListUuid = $db->queryIncrListUuidByUuid($remUuid,$valueField);
        $this->initIncrList($cache, $dbName, $incrListUuid);
        $this->incrListLock($cache);//加锁
        if ($incrListUuid) {
            //先查询出要删除元素在db中的实数排名
            $scoreField = $cache::SCORE_FIELD;
            $valueField = $cache::VALUE_FIELD;
            $actualBeforeTotal = $db->queryBeforeTotal($remUuid,$scoreField,$valueField,$cacheParam);
            $arrRemKv = [$valueField=>$remUuid];//需要删除的对应数组
            if($releation){//这种情况是做那种关系表，比如社团粉丝表，由两个字段：group_uuid和user_uuid来确定一条数据
                $arrRemKv[$db::INCRLIST_UUID] = $cacheParam;
            }
            $delRe = $db->setDelStatus($arrRemKv,$delOperatorUuid);
            if ($delRe) {
                $return = true;
                $index = $actualBeforeTotal - 1;
                //设置段名称
                $cache->setSegmentKeyNameByActual($index);
                //对所在段执行zrem
                $cache->incrListRem($remUuid);
                //实总数-1
                $cache->decrActualTotal();
                //map-1
                $cache->decrMapField();
            } else {
                $return = false;
            }
        } else {
            $return = false;
        }
        $cache->unlock();
        return $return;
    }
    ############自增列表相关操作--结束##############3

    ##############全部类型列表相关操作--开始###################3
    //重建缓存逻辑
    public function initCache($cacheInputParams,$dbInputParams){
        $cacheName = isset($cacheInputParams['cacheName'])?$cacheInputParams['cacheName']:static::$cacheName;
        $cacheParam = $cacheInputParams['param'];
        $suffix = isset($cacheInputParams['suffix'])?$cacheInputParams['suffix']:[];

        $cache = $this->getCache($cacheName, $cacheParam, $suffix);
        $scoreField = $cache::SCORE_FIELD;
        $valueField = $cache::VALUE_FIELD;

        $dbName = isset($dbInputParams['dbName']) ? $dbInputParams['dbName'] : static::$dbName;

        $dbInputParams['allListUuidField']=$cacheParam;

        $scoreParams = [
            'field'=>$scoreField,
            'table'=>$cache::SCORE_TABLE
        ];
        $valueParams = [
            'field'=>$valueField,
            'table'=>$cache::VALUE_TABLE
        ];
        //var_dump($scoreParams,$valueParams);
        $dbRe = $this->getDb($dbName)->queryAllList($scoreParams,$valueParams,$dbInputParams);
        //先把key清理掉
        $cache->clearKey();
        if ($dbRe) {
            $cacheAddParams = [];
            foreach ($dbRe as $v) {
                $cacheAddParams[$v[$valueField]] = $v[$scoreField];
            }
            $cache->addSome($cacheAddParams);
            $return = true;
        }else{
            $cache->setNullKey();
            $return = false;
        }
        return $return;
    }

    //查询全部列表内容
    public function queryAllList($cacheInputParams,$dbInputParams,$start=0,$end=-1,$withScores = null)
    {
        //初始redis的参数
        $cacheName = isset($cacheInputParams['cacheName'])?$cacheInputParams['cacheName']:static::$cacheName;
        $cacheParam = $cacheInputParams['param'];
        $suffix = isset($cacheInputParams['suffix'])?$cacheInputParams['suffix']:[];
        $command = isset($cacheInputParams['command'])?$cacheInputParams['command']:null;
        $cache = $this->getCache($cacheName, $cacheParam, $suffix);
        $cacheRe = $cache->query($start,$end,$withScores,$command);
//var_dump($cacheName, $cacheParam, $suffix);
        if ($cacheRe['code'] == $cache::CODE_SUCC) {
            $data = $cacheRe['data'];
            $countData = $cache->getCount();
            $count = $countData['count'];
            $return = ['data' => $data, 'count' => $count];
        } elseif ($cacheRe['code'] == $cache::CODE_NULL) {
            $return = ['data' => [], 'count' => 0];
        } else {//没有缓存的情况
            $initRe = $this->initCache($cacheInputParams,$dbInputParams);
            if($initRe){
                $cacheRe = $cache->query($start,$end,$withScores,$command);
                $data = $cacheRe['data'];
                $countData = $cache->getCount();
                $count = $countData['count'];
                $return = ['data' => $data, 'count' => $count];
            }else{
                $return = ['data' => [], 'count' => 0];
            }
        }
        //var_dump($return);die;
        return $return;
    }

    //查询全部类型的列表总数
    public function queryAllListCount($cacheInputParams,$dbInputParams){
        $cacheName = isset($cacheInputParams['cacheName'])?$cacheInputParams['cacheName']:static::$cacheName;
        $cacheParam = $cacheInputParams['param'];
        $suffix = isset($cacheInputParams['suffix'])?$cacheInputParams['suffix']:[];
        $cache = $this->getCache($cacheName, $cacheParam, $suffix);
        $countInfo = $cache->getCount();
        $code = $countInfo['code'];
        if($code == $cache::CODE_SUCC){
            $return = $countInfo['count'];
        }else{
            $this->initCache($cacheInputParams,$dbInputParams);
            $countInfo = $cache->getCount();
            $return = $countInfo['count'];
        }
        return $return;
    }

    //对全部列表进行添加
    public function addAllList($cacheInput,$dbInput)
    {
        $cacheName = $cacheInput['cacheName'];
        $cacheParam = $cacheInput['param'];
        $cacheSuffix = isset($cacheInput['suffix']) ? $cacheInput['suffix'] : [];

        $dbName = isset($dbInput['dbName']) ? $dbInput['dbName'] : static::$dbName;
        $dbAttributes = $dbInput['attributes'];

        $db = $this->getDb($dbName);
        $dbRe = $db->insert($dbAttributes);
        if ($dbRe) {
            $cache = $this->getCache($cacheName, $cacheParam, $cacheSuffix);
            $keyExists = $cache->keyNameExists();
            if($cache::USE_VALUE_BY_INSERT_UUID==true){
                $value = $db->getInsertUuid();
            }elseif($cache::USE_VALUE_BY_INSERT_UUID==true){
                $value = $dbRe;
            }else{
                $value = $dbInput['attributes'] [$cache::VALUE_FIELD];//根据db的VALUE_FILED常量，得出哪个字段是缓存的value
            }


            if ($keyExists != $cache::CODE_NOCACHE) {
                $score = $dbInput['attributes'][$cache::SCORE_FIELD];
                $r = $cache->add($score, $value);
            }
            $return = $value;
        } else {
            $return = false;
        }
        return $return;
    }

    //删除全部类型列表其中的一个元素
    //此函数一般用于删除关系表中的数据（比如用户禁言表，attributes其中有两个元素，一个是group_uuid,一个是user_uuid）
    public function delAllListElement($cacheInput,$dbInput)
    {
        $cacheName = $cacheInput['cacheName'];
        $cacheParam = $cacheInput['param'];
        $cacheSuffix = isset($cacheInput['suffix']) ? $cacheInput['suffix'] : [];

        $dbName = isset($dbInput['dbName']) ? $dbInput['dbName'] : static::$dbName;
        $dbAttributes = $dbInput['attributes'];
        $operatorUuid = isset($dbInput['delUser_uuid'])?$dbInput['delUser_uuid']:null;

        $db = $this->getDb($dbName);
        $dbRe = $db->deleteByAttributes($dbAttributes,$operatorUuid);
        if ($dbRe) {
            $cache = $this->getCache($cacheName, $cacheParam, $cacheSuffix);
            $value = $dbInput['attributes'][$cache::VALUE_FIELD];//根据db的VALUE_FILED常量，得出哪个字段是缓存的value
            $cache->zRem($value);
            $return = $dbRe;
        } else {
            $return = false;
        }
        return $return;
    }
    ###############全部类型列表相关操作--结束#####################3
    //查询一条哈希记录
    public function queryHashOneByUuid($dbInput, $cacheInput,$queryField=[])
    {
        $dbName = isset($dbInput['dbName']) ? $dbInput['dbName'] : static::$dbName;
        $uuid = isset($dbInput['uuid'])?$dbInput['uuid']:$cacheInput['param'];

        $cacheName = $cacheInput['cacheName'];
        $suffix = isset($cacheInput['suffix'])?$cacheInput['suffix']:[];
        $cacheParam = $cacheInput['param'];

        $cacheObj = $this->getCache($cacheName, $cacheParam, $suffix);
        $hashField = $cacheObj->getHashField();
        $queryRe = $cacheObj->query($queryField);

        if ($queryRe['code'] == $cacheObj::CODE_SUCC) {
            $return = $queryRe['data'];
        } else {
            if ($queryRe['code'] == $cacheObj::CODE_NULL) {
                $return = [];
            } elseif ($queryRe['code'] == $cacheObj::CODE_NOCACHE || $queryRe['code'] == $cacheObj::CODE_FIELDCHANGE) {
                $db = $this->getDb($dbName);
                $dbQueryRe = $db->queryOneByUuid($uuid, []);
                if ($dbQueryRe) {
                    //添加缓存的逻辑
                    $cacheValue = [];
                    foreach ($dbQueryRe as $k => $v) {
                        if (in_array($k, $hashField)) {
                            $cacheValue[$k] = $v;
                        }
                    }
                    $cacheObj->initAdd($cacheValue);
                    $queryRe = $cacheObj->query($queryField);
                    if($queryRe['code'] == $cacheObj::CODE_NOCACHE || $queryRe['code'] == $cacheObj::CODE_FIELDCHANGE){
                        //这里加一个异常，如果字段变更后，重新初始化cache并查询，仍然有问题，则抛出异常
                        throw new \Exception("hash cache field error");
                    }else{
                        $return = $queryRe['data'];
                    }
                } else {
                    $cacheObj->setNullKey();
                    $return = [];
                }
            } else {
                throw new \Exception("err cache return code");
            }
        }
        return $return;
    }

    //查询多条哈希记录
    public function queryHashSome($list, $dbParams, array $cacheParams,$selected = [])
    {
        $unCacheList = [];
        $returnData = [];

        $dbName = isset($dbParams['dbName']) ? $dbParams['dbName'] : static::$dbName;

        $cacheName = $cacheParams['cacheName'];
        $suffix = isset($cacheParams['suffix'])?$cacheParams['suffix']:[];
        $cacheSelectField = $selected;

        if (!is_array($cacheSelectField)) {
            throw new \Exception("The cacheParams must be array");
        }
        //先进行遍历，把所有已经缓存的数据先拿出来
        foreach ($list as $v) {
            $orderList[]=$v;
            $cache = $this->getCache($cacheName, $v, $suffix);
            $cacheReturn = $cache->query($cacheSelectField);
            if ($cacheReturn['code'] == $cache::CODE_SUCC) {//如果是正常返回，装入返回数组
                $returnData[$v] = $cacheReturn['data'];
            } elseif ($cacheReturn['code'] == $cache::CODE_NULL) {//如果是正常返回为空
                $returnData[$v] = [];
            } elseif ($cacheReturn['code'] == $cache::CODE_NOCACHE) {//如果是没有被缓存到，装入返回待查询db数组
                $unCacheList[] = $v;
            } elseif ($cacheReturn['code'] == $cache::CODE_FIELDCHANGE) {
                $unCacheList[] = $v;
            } else {
                throw new \Exception("unknow code:{$cacheReturn['code']}");
            }
        }
        //如果有未缓存的数据，执行查库操作
        if (!empty($unCacheList)) {
            $db = $this->getDb($dbName);
            $uuid = $db::UUID_KEY;
            $db = $db->getBuilder();
            $i = 0;
            $arrPosition = [];
            $arrPrepareValue = [];
            //处理一下sql
            foreach ($unCacheList as $v) {
                $arrPosition[] = $db::POSITION_PREFIX . $i;
                $arrPrepareValue[] = $v;
                $i++;
            }
            $strPosition = implode(",", $arrPosition);
            $arrPrepare = array_combine($arrPosition, $arrPrepareValue);
            $cacheClassName = $this->getCacheName($cacheName);
            $arrHashField = $cacheClassName::getHashField();
            $arrSelectField = [];
            foreach ($arrHashField as $v) {
                $arrSelectField[] = "`{$v}`";
            }
            $strSelectField = implode(",", $arrSelectField);
            $sql = "select `{$uuid}`,{$strSelectField} from {$db->getTable()} where `{$uuid}` in ($strPosition) ";
            $dbRe = $db->queryAllBySql($sql, $arrPrepare);
            $dbRe2 = [];
            foreach ($dbRe as $v) {
                $dbRe2[$v[$uuid]] = $v;
            }
            //将查出来的数据写入缓存，放到返回的数据中
            foreach ($unCacheList as $v) {
                $cache = $this->getCache($cacheName, $v, $suffix);
                if (isset($dbRe2[$v])) {//如果要查询的数据在db结果中，写入缓存
                    $cache->delKey();//先把key删除
                    $cache->formatAdd($dbRe2[$v]);//插入的数据
                    $returnData[$v] = $cache->formatHashField($dbRe2[$v], $cacheSelectField);
                } else {//如果要查询的数据不再db查询结果中，给这个key缓存一个空
                    $cache->setNullKey();
                    $returnData[$v] = [];
                }
            }
        }
        //格式化顺序，使得与输入顺序一致
        $formatOrderReturnData = [];
        foreach($list as $v){
            $formatOrderReturnData[$v] = $returnData[$v];
        }
        return $formatOrderReturnData;
    }

    //添加一条哈希记录
    public function addHash($dbInput,$cacheInput){}

    //更新一条哈希记录
    public function updateHash($uuid,$input){
        $attributes = $input['attributes'];
        $cacheName = $input['cacheName'];
        $dbRe = $this->getDb(static::$dbName)->updateByUuid($uuid,$attributes);
        if($dbRe){
            $this->getCache($cacheName,$uuid)->update($attributes);
            $return = true;
        }else{
            $return = false;
        }
        return $return;
    }


    //将变量中的@都替换为实际的值
    protected function replaceAt($value, $map)
    {
        $return = $value;
        if (isset($map[$value])) {
            $return = $map[$value];
        }
        return $return;
    }



}