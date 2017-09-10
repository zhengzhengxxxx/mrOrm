<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/2/26
 * Time: 12:42
 */
//这种DB都配合cache的全部列表类型使用
namespace MysqlDriver;
class fnstMysqlDriverIncrList extends \MysqlDriver\fnstMysqlDriver{
    //自增列表依据的uuid
    const INCRLIST_UUID = NULL;

    //查询实总数
    public function queryActualTotal($incrListUuid){
        $prePare = ":incrListUuid";
        $params = [$prePare=>$incrListUuid];
        $strCondition = static::INCRLIST_UUID." = {$prePare} and `".static::DEL_STATUS_COL."` = ".static::DEL_STATUS_EXIST_VALUE." ";
        return $this->queryCount($strCondition,$params);
    }

    //查询虚总数
    public function queryGrossTotal($incrListUuid){
        $prePare = ":incrListUuid";
        $params = [$prePare=>$incrListUuid];
        $strCondition = static::INCRLIST_UUID." = {$prePare}";
        return $this->queryCount($strCondition,$params);
    }

    //查询列表元素
    public function queryListElement($incrListUuid,$start,$limit,$scoreField,$valueField){
        $prePare = ":incrListUuid";
        $params = [$prePare=>$incrListUuid];
        $sql = "select `".$valueField."`,`".$scoreField."`,`".static::DEL_STATUS_COL."`  from `".static::TABLE_NAME."` where `".static::INCRLIST_UUID."`=".$prePare."  ORDER BY ".$scoreField." asc,".static::PRIMARY_KEY." asc limit {$start},{$limit}";
        $r = $this->queryAllBySql($sql,$params);
        //var_dump($sql,$params);die;
        $return = [];
        if($r){
            foreach($r as $v){
                if($v[static::DEL_STATUS_COL] == static::DEL_STATUS_EXIST_VALUE){
                    $return[$v[$valueField]] = $v[$scoreField];
                }
            }
        }
        return $return;
    }

    //查询实元素数量
    public function queryListElementTotal($incrListUuid,$start,$limit,$scoreField){
        $prePare = ":incrListUuid";
        $params = [$prePare=>$incrListUuid];
        $sql = "select `".static::DEL_STATUS_COL."`  from `".static::TABLE_NAME."` where `".static::INCRLIST_UUID."`=".$prePare."  ORDER BY ".$scoreField." asc,".static::PRIMARY_KEY." asc limit {$start},{$limit}";
        $r = $this->queryAllBySql($sql,$params);
        $return = 0;
        if($r){
            foreach($r as $v){
                if($v[static::DEL_STATUS_COL] == static::DEL_STATUS_EXIST_VALUE){
                    $return++;
                }
            }
        }
        return $return;
    }

    //给一个uuid,查询在他以前共有多少元素
    //$incrlistUuid为在关系表中依据的自增条件
    public function queryBeforeTotal($uuid,$scoreField,$valueField,$incrlistUuid){
        $selected = [static::PRIMARY_KEY,$scoreField];
        $attributes = [$valueField=>$uuid];
        $r = $this->queryOneByAttributes($selected,$attributes);
        if($r){
            $id = $r[static::PRIMARY_KEY];
            $score = $r[$scoreField];
            $prePareId = ":id";
            $prePareScore = ":score";
            $params = [$prePareId=>$id,$prePareScore=>$score,":incrListUuid"=>$incrlistUuid];
            $countCondition = static::INCRLIST_UUID." = :incrListUuid  and ".static::PRIMARY_KEY." <= {$prePareId} and ".$scoreField." <= {$prePareScore} and ".static::DEL_STATUS_COL." = ".static::DEL_STATUS_EXIST_VALUE;
            $return = $this->queryCount($countCondition,$params);
        }else{
            $return = false;
        }
        return $return;
    }

    //将状态位置为删除
//    public function setDelStatus($input,$valueField,$delOperatorUuid=null){
//        $attribute = [static::DEL_STATUS_COL=>static::DEL_STATUS_DEL_VALUE];
//        if($delOperatorUuid!=null){
//            $attribute[static::DEL_OPERATOR_UUID_COL]=$delOperatorUuid;
//        }
//        $strCondition = $valueField." = :input";
//        $conditionParams = [':input'=>$input];
//        return $this->update($attribute,$strCondition,$conditionParams);
//    }

    //将DB中的状态位置为失效
    public function setDelStatus($arrRemKv,$delOperatorUuid=null){
        $attribute = [static::DEL_STATUS_COL=>static::DEL_STATUS_DEL_VALUE];
        if($delOperatorUuid!=null){
            $attribute[static::DEL_OPERATOR_UUID_COL]=$delOperatorUuid;
        }
        return $this->updateByAttributes($attribute,$arrRemKv);
    }

    //通过uuid查询他的incrListuuid
    public function queryIncrListUuidByUuid($uuid,$valueField){
        $selected = [static::INCRLIST_UUID];
        $attributes = [$valueField=>$uuid];
        $dbRe = $this->queryOneByAttributes($selected,$attributes);
        if($dbRe){
            $return = $dbRe[static::INCRLIST_UUID];
        }else{
            $return = false;
        }
        return $return;
    }
}