<?php

namespace RedisDriver\FnstRedisRouter;

class Average extends Base{
    const MAX_SIDE = 2147483648;
    protected $averageConfig = [];
    protected $step = null;

    public function __construct($arrConfig)
    {
        $this->init($arrConfig);
    }

    protected function init($arrConfig){
        $this->setStep($arrConfig);
        $this->averageConfig($arrConfig);
    }
    
    //这是步长
    protected function setStep($arrConfig){
        $count = count($arrConfig);
        $step = (static::MAX_SIDE)/$count;
        $this->step = (int)$step;
    }

    protected function averageConfig($arrConfig){
        $start = 0;
        foreach($arrConfig as $v){
            $this->averageConfig[$start] = $v;
            $start += $this->step;
        }
    }

    public function getConfig($key){
        $crc32 = (int)((sprintf("%u",crc32($key)))/2);
        $return = $this->averageConfig[0];
        krsort($this->averageConfig);
        foreach($this->averageConfig as $k=>$v){
            if($crc32>$k){
                $return=$v;
                break;
            }
        }
        return $return;
    }
}