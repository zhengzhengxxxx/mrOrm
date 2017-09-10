<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/2/26
 * Time: 12:42
 */
//这种DB都配合cache的全部列表类型使用
namespace MysqlDriver;
class fnstMysqlDriverAllList extends \MysqlDriver\fnstMysqlDriver{
    //全部类型列表依据的uuid字段名
    const ALLLIST_UUID =null;

    /**
     * @param $dbInput
     * [
     *  "allListUuidField"=>全部类型列表依据的uuid字段名,(选填)
     *  "where"=>字符串类型，查询条件(allListUuidField也是查询条件,与where一起生效)(选填)
     *  "limit"=>sql中取的条数,(选填)
     *  "params"=>where条件的参数,(选填)
     *  "join"=>关联条件
     * ]
     * @param null $join
     * @return mixed
     */
    public function queryAllList($scoreParams,$valueParams,$dbInput){
        //cache相关
        $scoreField = $scoreParams['field'];
        $scoreTable = $scoreParams['table'];
        $valueField = $valueParams['field'];
        $valueTable = $valueParams['table'];
        //db相关
        $allListUuidField = $dbInput['allListUuidField'];
        $where = isset($dbInput['where'])?$dbInput['where']:"";
        $limit = isset($dbInput['limit'])?$dbInput['limit']:"";
        $whereParams = isset($dbInput['params'])?$dbInput['params']:[];
        $join = isset($dbInput['join'])?$dbInput['join']:[];
        $params = $whereParams;

        $table = static::TABLE_NAME;
        if(!empty(static::ALLLIST_UUID)){
            $allListStr = static::ALLLIST_UUID."=:allListUuid ";
        }else{
            $allListStr = '';
        }

        if($join==[]){
            $arrWhere = [];
            if($allListStr){
                $arrWhere[] = $allListStr;
            }
            $arrWhere[] = static::DEL_STATUS_COL." =  ".static::DEL_STATUS_EXIST_VALUE;
            $strCondition = implode(" and ",$arrWhere);
            $sql = "select `{$scoreField}`,`{$valueField}` from {$table}
                    where ".$strCondition;
            if($where){
                $sql .=" and ".$where;
            }
            $sql .= " ORDER BY {$scoreField} asc";
            if($limit){
                $sql .=" limit ".$limit;
            }
        }else{
            $arrJoin = [];
            $arrWhere = [];

            $arrWhere[] = $table.".".$allListStr;
            $arrWhere[] = $table.".".static::DEL_STATUS_COL."=".static::DEL_STATUS_EXIST_VALUE;
            foreach($join as $v){
                $dbObj = \BaseModel::getDbObj($v['dbName']);
                $joinTable = $dbObj::TABLE_NAME;
                $fromCol = $v['fromCol'];
                $joinCol = $v['joinCol'];
                $arrJoin[] = "INNER JOIN `{$joinTable}` on `{$table}`.`{$fromCol}` = `{$joinTable}`.`{$joinCol}`";
                $arrWhere[] = $joinTable.".".$dbObj::DEL_STATUS_COL.'='.$dbObj::DEL_STATUS_EXIST_VALUE;
            }

            $strJoin = implode(" ",$arrJoin);
            $strCondition = implode(" and ",$arrWhere);
            if($scoreTable===null){
                $strScoreField = "{$table}.{$scoreField}";
            }else{
                $strScoreField = "{$scoreTable}.{$scoreField}";
            }
            if($valueTable===null){
                $strValueField = "{$table}.{$valueField}";
            }else{
                $strValueField = "{$valueTable}.{$valueField}";
            }
            $sql = "select {$strScoreField},{$strValueField} from `{$table}` {$strJoin} where {$strCondition}";
            if($where){
                $sql .=" and ".$where;
            }
            if($scoreTable===null){
                $sql .=" ORDER BY {$table}.{$scoreField} asc";
            }else{
                $sql .=" ORDER BY {$scoreTable}.{$scoreField} asc";
            }

            if($limit){
                $sql .=" limit ".$limit;
            }
        }
       if(!empty(static::ALLLIST_UUID)){
            $params[':allListUuid']=$allListUuidField;
        }
        //var_dump($sql,$params);
        $dbRe = $this->queryAllBySql($sql, $params);
        //var_dump($dbRe);
        return $dbRe;
    }
}