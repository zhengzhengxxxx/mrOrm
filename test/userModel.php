<?php

class userModel extends BaseModel
{
	//插入一条user记录
	public function add($userName,$userAge,$addTime)
    {
        $cacheInput = [
            'cacheName'=>'userListCache',
            'param'=>$userAge
        ];
        //插入db
        $attributes = [
			'user_name'=>$userName,
			'user_age'=>$userAge,
			'add_time'=>$addTime
        ];
        $dbInput = [
			'dbName'=>'userDb',
            'attributes'=>$attributes
        ];
		//插入db和listCache
        $insertUuid = $this->addAllList($cacheInput,$dbInput);
        if ($insertUuid) {
			$return = $insertUuid;
			//插入hashCache
			$this->getCache("userHashCache", $insertUuid)->add($attributes);
        } else {
            $return = false;
        }
        return $return;
    }
}