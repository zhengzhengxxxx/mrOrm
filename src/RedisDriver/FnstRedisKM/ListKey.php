<?php
namespace RedisDriver\FnstRedisKM;
class ListKey extends Base
{
    const KEY_TYPE = 'list';
    const EXPIRE_TIME = 0;

    //进队列
    public function push($info){
        $return = $this->redis->lPush($this->keyName, $info);
        return $return;
    }

    //出队列
    public function pop(){
        $return = $this->redis->rPop($this->keyName);
        return $return;
    }

    //阻塞式出队列
    public function bPop(){
        ini_set("default_socket_timeout",-1);
        $return = $this->redis->brPop($this->keyName,0);
        return $return[1];
    }
}