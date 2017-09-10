<?php
/**
 * 此类stringkey保存的都是json，函数内部会自动编码解码
 * 每个key都有限定的field,使用时候类似hashkey
 * 此种类型有批量查询方法和批量设置方法
 * 此种key的expire在查询的时候不会去增加,只会在设置的时候添加过期时间
 */
namespace RedisDriver\FnstRedisKM;
class JsonStringKey extends Base
{
    const KEY_TYPE = 'string';
    //对应数据库的唯一索引键名
    public static $uuid = "uuid";
    //字段内容，每个key都必须要有
    public static $field = [];

    //检测必传字段
    protected function needField($inputValue){
        if($inputValue===''){
            return true;
        }
        $keys = array_keys($inputValue);
        $diff = array_diff($keys,static::$field);
        if(!empty($diff)){
            throw new \Exception("input error input:{".json_encode($inputValue)."} field:{".json_encode(static::$field)."}");
        }
    }

    //查询一条内容
    public function query()
    {
        $redisRe = $this->redis->get($this->keyName);
        if($redisRe===false){
            $return = false;
        }elseif($redisRe === ""){
            $return = "";
        }else{
            $return = json_decode($redisRe,1);
        }
        return $return;
    }

    //设置一条内容
    public function set($attributes)
    {
        $this->checkMixValue($attributes);
        $this->needField($attributes);
        $value = json_encode($attributes);
        $r = $this->redis->set($this->keyName,$value);
        if($r){
            $this->redis->expire($this->keyName,static::$expireTime);
        }
        return $r;
    }


    protected function checkMixValue($mixValue){
        if(!is_array($mixValue) && $mixValue!==""){
            throw new \Exception("arrValue must array or empty string");
        }
    }

    //获取要查询的字段名Z
    public static function getSelectedField(){
        $return = static::$field;
        $return[] = static::$uuid;
        return $return;
    }
}