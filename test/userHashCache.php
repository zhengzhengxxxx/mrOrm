<?php

class userHashCache extends \RedisDriver\FnstRedisKM\HashKey{
    protected static $keyNameTemplate = "user_info:{#}";
    protected static $configGroup = "redis1";
    protected static $expireTime = 86400;
    protected static $hashField = ['user_name','user_age','add_time'];
}