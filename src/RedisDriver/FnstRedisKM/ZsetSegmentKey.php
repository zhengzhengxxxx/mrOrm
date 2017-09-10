<?php
/**
 * 段类型的zset
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/1/23
 * Time: 13:29
 */
namespace RedisDriver\FnstRedisKM;

class ZsetSegmentKey extends Base
{
    public $segmentStart;
    public $segmentEnd;

    protected $grossTotalValue = null;
    protected $actualTotalValue = null;
    protected $targetValue = null;//目标的值，也就是count + 1
    protected $segmentKeyName = null;//每段的key的名称
    protected $lockKeyName = null;//上锁的key的名字
    protected $grossTotalKeyName = null;//总计的数目（包括已经删除的）
    protected $actualTotalKeyName = null;//实际的数目（不包括已经删除的)
    protected $segmentMapKeyName = null;//段的地图，标识出每个段中存放的真实元素数量,field为每个段其实的元素号，比如 0,1000,2000...
    protected $tmpAddHashFieldName = null;//当执行插入时，此字段保存要自增的map中的field
    protected $segmentMap = [];//此属性作为查询map的缓存使用
    public static $step = 10000;//每个集合的步长值，不建议修改

    const LOCK_TIME = 30;//加锁时间
    const CODE_NOCOUNT = -3;//没有countKey的错误代码

    //此常量为true时候，使用插入结果的uuid为cache的value
    const USE_VALUE_BY_INSERT_UUID = true;
    //此常量为true时候，使用插入结果的id为cache的value
    const USE_VALUE_BY_INSERT_ID = false;
    //作为自增列表的score的列
    const SCORE_FIELD = "addTime";
    //作为自增列表的value的列,一般是自己的uuid,@uuid是插入一条记录时，用他来表示uuid的
    const VALUE_FIELD = "uuid";

    //创造上锁key的名字
    protected function createLockKey()
    {
        $prefix = '[lock]';
        $this->lockKeyName = $prefix . $this->keyName;
    }

    //创造虚数key
    protected function createGrossTotal()
    {
        $prefix = '[grossTotal]';
        $this->grossTotalKeyName = $prefix . $this->keyName;
    }

    //创造实际总数 key
    protected function createActualTotal()
    {
        $prefix = '[actualTotal]';
        $this->actualTotalKeyName = $prefix . $this->keyName;
    }

    //创造段地图key
    protected function createSegmentMap()
    {
        $prefix = '[segmentMap]';
        $this->segmentMapKeyName = $prefix . $this->keyName;
    }

    ##########虚总数#############
    //设置虚总数的值
    public function setGrossTotal($count)
    {
        $this->grossTotalValue = $count;
        $return = $this->redis->set($this->grossTotalKeyName, $count);
        if ($return) {
            $this->setExpire($this->grossTotalKeyName);
        }
        return $return;
    }

    //检查c虚总数是否存在
    public function checkGrossKeyExists()
    {
        $r = $this->redis->exists($this->grossTotalKeyName);
        return $r;
    }

    //虚总数自增1
    public function incrGrossTotal()
    {
        $return = $this->redis->incr($this->grossTotalKeyName);
        if ($return) {
            $this->setExpire($this->grossTotalKeyName);
        }
        return $return;
    }

    //获取虚总数
    public function getGrossTotal()
    {
        $count = $this->redis->get($this->grossTotalKeyName);
        if ($count !== false) {
            $this->setExpire($this->grossTotalKeyName);
        }
        $this->grossTotalValue = $count;
        return $count;
    }

    #########################

    #######实总数#########
    //设置虚总数的值
    public function setActualTotal($count)
    {
        $this->actualTotalValue = $count;
        $return = $this->redis->set($this->actualTotalKeyName, $count);
        if ($return) {
            $this->setExpire($this->actualTotalKeyName);
        }
        return $return;
    }

    //检查虚总数是否存在
    public function checkActualKeyExists()
    {
        $r = $this->redis->exists($this->actualTotalKeyName);
        return $r;
    }

    //实总数自增1
    public function incrActualTotal()
    {
        $return = $this->redis->incr($this->actualTotalKeyName);
        if ($return) {
            $this->setExpire($this->actualTotalKeyName);
        }
        return $return;
    }

    //实总数-1
    public function decrActualTotal()
    {
        $return = $this->redis->decr($this->actualTotalKeyName);
        if ($return) {
            $this->setExpire($this->actualTotalKeyName);
        }
        return $return;
    }

    //获取实总数
    public function getActualTotal()
    {
        $count = $this->redis->get($this->actualTotalKeyName);
        if ($count !== false) {
            $this->setExpire($this->actualTotalKeyName);
        }
        $this->actualTotalValue = $count;
        return $count;
    }
    ################

    ###########segment map 相关###############
    //检查map的key是否存在
    public function checkMapKeyExists()
    {
        return $this->redis->exists($this->segmentMapKeyName);
    }

    //检查段map中，指定的段是否存在
    public function checkMapFieldExists()
    {
        $r = $this->redis->hExists($this->segmentMapKeyName, $this->segmentKeyName);
        if ($r) {
            $this->setExpire($this->segmentMapKeyName);
        }
        return $r;
    }

    //设置段map中某个段真实的值
    public function setMapField($value)
    {
        $r = $this->redis->hSet($this->segmentMapKeyName, $this->segmentKeyName, $value);
        if ($r) {
            $this->setExpire($this->segmentMapKeyName);
        }
        return $r;
    }

    //获取map中某个段真实总数的值
    public function getMapfieldValue()
    {
        $r = $this->redis->hGet($this->segmentMapKeyName, $this->segmentKeyName);
        if ($r) {
            $this->setExpire($this->segmentMapKeyName);
        }
        return $r;
    }

    //使field的值自增
    public function incrMapField()
    {
        $r = $this->redis->hIncrBy($this->segmentMapKeyName, $this->segmentStart, 1);
        if ($r) {
            $this->setExpire($this->segmentMapKeyName);
        }
        return $r;
    }

    //使field的值自减
    public function decrMapField()
    {
        $r = $this->redis->hIncrBy($this->segmentMapKeyName, $this->segmentStart, -1);
        if ($r) {
            $this->setExpire($this->segmentMapKeyName);
        }
        return $r;
    }

    //初始化map内容（用于没有缓存的时候，初始化生成全部缓存）
    //入参：一个索引数组数组，由0开始，每个元素代表一个段内的实总数（由低到高）
    public function initSegmentMap($arr)
    {
        $field = 0;
        $msetValue = [];
        foreach ($arr as $v) {
            $msetValue[$field] = $v;
            $field += static::$step;
        }
        $this->redis->del($this->segmentMapKeyName);
        $r = $this->redis->hMset($this->segmentMapKeyName, $msetValue);
        if ($r) {
            $this->setExpire($this->segmentMapKeyName, 2);
        }
        return $r;
    }


    //获取全部map
    public function getSegmentMap()
    {
        if (empty($this->segmentMap)) {
            $r = $this->redis->hGetAll($this->segmentMapKeyName);
            if ($r) {
                ksort($r, SORT_NUMERIC);
                $this->setExpire($this->segmentMapKeyName);
                $this->segmentMap = $r;
            }else{
                $this->segmentMap = [];
                $r = [];
            }
        } else {
            $r = $this->segmentMap;
        }
        return $r;
    }

    ############################
    public function buildKey($param = '', $arrSuffix = [])
    {
        $this->createKey($param);
        $this->createLockKey();//lock
        $this->createGrossTotal();//虚总数
        $this->createActualTotal();//实总数
        $this->createSegmentMap();//segemntkey
        $this->createNullKey();
    }

    //上锁
    public function lock()
    {
        return $this->redis->setnx($this->lockKeyName, 1);
    }

    //上锁时间设定
    public function lockTime()
    {
        $time = static::LOCK_TIME;
        return $this->redis->expire($this->lockKeyName, $time);
    }

    //解锁
    public function unLock()
    {
        return $this->redis->delete($this->lockKeyName);
    }

    //获取锁key的名字
    public function getLockName()
    {
        return $this->lockKeyName;
    }

    //判断key是否存在
    public function segmentKeyExists()
    {
        return $this->redis->exists($this->segmentKeyName);
    }

    //传入一个actual index设置这个段的名字
    public function setSegmentKeyNameByActual($index)
    {
        $map = $this->getSegmentMap();
        $target = $index+1;
        $total = 0;
        $prefixStart = null;
        foreach($map as $k=>$v){
            $total+=$v;
            if($target<=$total){
                $prefixStart = $k;
                break;
            }
        }
        if($prefixStart === null){
            throw new \Exception("segment map error no target {$target} key :{$this->segmentMapKeyName}");
        }
        $this->targetValue = $target;
        $this->segmentStart = $prefixStart;
        $prefixEnd = $prefixStart + (static::$step - 1);
        $prefix = $this->createSegmentPrefix($prefixStart, $prefixEnd);
        $this->segmentKeyName = $prefix . $this->keyName;
        return $this->segmentKeyName;
    }

    //将要插入一个元素时，设置段的名称
    public function setAddSegmentKeyName(){
        $map = $this->getSegmentMap();
        if($map){
            $tmp = array_slice($map,-1,1,true);
            $tmp = array_keys($tmp);
            $lastKey = $tmp[0];
            $prefixStart = $lastKey;
            if($map[$lastKey] == static::$step){
                $prefixStart += static::$step;
            }
        }else{
            $prefixStart = 0;
        }
        $this->segmentStart = $prefixStart;
        $prefixEnd = $prefixStart + (static::$step - 1);
        $prefix = $this->createSegmentPrefix($prefixStart, $prefixEnd);
        $this->segmentKeyName = $prefix . $this->keyName;
        return $this->segmentKeyName;
    }
    //传入起始和结束值，生成segment前缀
    protected function createSegmentPrefix($start, $end)
    {
        return "[segment:{$start}-{$end}]";
    }

    //获取段key的名字
    public function getSegmentKeyName($start, $end)
    {
        return $this->createSegmentPrefix($start, $end) . $this->keyName;
    }

    //判断此次插入的值是否为此段的第一条数据
    public function isFirst()
    {
        $mo = $this->targetValue % static::$step;
        $return = ($mo == 1) ? true : false;
        return $return;
    }

    //添加一批元素（一般用于被动生成一个key)
    public function addSome($arrArgs)
    {
        $args[] = $this->segmentKeyName;
        $finalArgs = array_merge($args, $arrArgs);
        $return = call_user_func_array([$this->redis, 'zAdd'], $finalArgs);
        if ($return) {
            $this->setExpire($this->segmentKeyName);
        }
        return $return;
    }

    public function add($score, $value)
    {
        $r = $this->redis->zAdd($this->segmentKeyName, $score, $value);
        if ($r) {
            $this->setExpire($this->segmentKeyName);
        }
        return $r;
    }

    public function incrTotal()
    {
        $this->redis->incr($this->grossTotalKeyName);
        $this->redis->incr($this->actualTotalKeyName);
    }

    ###################查询相关#######################
    //判断start和end是否在同一个段中
    public function inSameSegment($start, $end)
    {
        $map = $this->getSegmentMap();
        $startFlag = false;
        $endFlag = false;
        $count = 0;
        foreach ($map as $v) {
            $count += $v;
            if ($count >= $start + 1) {
                if ($startFlag === false) {
                    $startFlag = $count;
                }
            }
            if ($count >= $end + 1) {
                if ($endFlag === false) {
                    $endFlag = $count;
                }
            }
            if ($startFlag !== false && $endFlag !== false) {
                break;
            }
        }
        if ($startFlag === false || $endFlag === false) {
            $same = true;
        } else if ($startFlag == $endFlag) {
            $same = true;
        } else {
            $same = false;
        }
        return $same;
    }

    //按照正序查询
    public function query($start, $end, $order = "asc")
    {
        $map = $this->getSegmentMap();
        if ($map) {
            $mapTotal = 0;//先遍历一次，把map中的全部数累加
            foreach($map as $v){
                $mapTotal= $mapTotal+ $v;
            }
            if($start>$mapTotal){
                return [];
            }

            $total = 0;
            if ($start == 0) {
                $segmentStart = 0;
            } else {
                $targetValue = $start + 1;
                foreach ($map as $k => $v) {
                    if ($targetValue <= ($total + $v)) {
                        break;
                    } else {
                        $total += $v;
                    }
                }
                $segmentStart = $start - $total;
            }
            if($end>$mapTotal){//如果end大于总数，则把end变成总数
                $end = $mapTotal-1;
            }
            $total = 0;
            if ($end == -1) {
                $segmentEnd = -1;
            }else {
                $targetValue = $end + 1;
                foreach ($map as $k => $v) {
                    if ($targetValue <= ($total + $v)) {
                        break;
                    } else {
                        $total += $v;
                    }
                }
                $segmentEnd = $end - $total;
            }
            $return = $this->redis->zRange($this->segmentKeyName, $segmentStart, $segmentEnd);
            if ($return) {
                $this->setExpire($this->segmentKeyName);
                if ($order == 'desc') {
                    $return = array_reverse($return);
                }
            }
        } else {
            $return = [];
        }
        return $return;
    }

    ##########删除相关###############
    public function incrListRem($e){
        $return = $this->redis->zRem($this->segmentKeyName,$e);
        return $return;
    }
}
