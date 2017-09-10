<?php

namespace RedisDriver\FnstRedisRouter;

class Hash extends base{
    protected $hashRing = 2147483648;
    protected $nodeNum = 1000;
    protected $arrNode = [];
    protected $arrConfig = [];
    protected static $self= null;
    
    public static function getInstance($arrConfig){
        if(empty(static::$self)){
            static::$self = new static();
            static::$self->init($arrConfig);
        }
        return static::$self;
    }
    

    public function init($arrConfig){
        $tmpArr=[];
        foreach($arrConfig as $v){
            $str = json_encode($v);
            $tmpKey = $this->createHashValue($str);
            $tmpArr[$tmpKey] = $v;
        }
        $this->arrConfig = array_values($tmpArr);
        $this->buildHashRing();
    }
    
    public function getConfig($key){
        $configKey = $this->findNode($key);
        return $this->arrConfig[$configKey];
    }

    protected function createHashValue($value){
        return crc32($value);
    }

    protected function createNode($realNode,$configKey){
        $node = $realNode;
        $step = (int)($this->hashRing / $this->nodeNum) * 2;
        if(isset($this->arrNode[$node])){
            $node += int($step/3);
        }
        for($i=1;$i<=($this->nodeNum - 1);$i++){
            if($node>$this->hashRing){
                $node = $node-$this->hashRing;
            }
            $this->arrNode[$node] = $configKey;
            $node += $step;
        }
    }

    protected function buildHashRing(){
        $arrConfig = $this->arrConfig;
        foreach($arrConfig as $k=>$v){
            $str = json_encode($v);
            $node = $this->createHashValue($str);
            $this->createNode($node,$k);
        }
        ksort($this->arrNode);
    }

    protected function findNode($str){
        $return = false;
        $findNum = $this->createHashValue($str);
        foreach($this->arrNode as $k=>$v){
            if($k>=$findNum){
                $return = $v;
            }
        }
        return $return;
    }
}