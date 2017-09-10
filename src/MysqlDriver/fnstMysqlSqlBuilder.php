<?php
namespace MysqlDriver;

use \PDO;

class fnstMysqlSqlBuilder
{
    const POSITION_PREFIX = ':fn';
    protected $connection = null;
    protected $tableName = null;
    protected $uuidKeyField = null;
    //是否启用uuid
    protected $enableUuid = true;
    //insert后产生的uuid
    protected $uuid = null;
    //删除状态位
    public $delStatusCol = null;
    public $delStatusExistValue = null;//正常时的值
    public $delStatusDelValue = null;//删除时的值
    public $delOperatorCol = null;//执行删除用户的 user_uuid的列名称

    //将driver中定义的一些属性赋到这个对象上
    public function setDriverProperty($arrProperty){
        foreach($arrProperty as $k=>$v){
            $this->$k=$v;
        }
    }

    //生成uuid
    protected function createUuid()
    {
        mt_srand((double)microtime() * 10000);
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);
        $uuid = substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12);
        return $uuid;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;
    }


    protected function formatAllField($datas)
    {

    }

    public function getTable()
    {
        return $this->tableName;
    }

    //记录错误信息
    protected function recordErrMsg($sth)
    {
        $errorInfo = $sth->errorInfo();
        if ($errorInfo[1] != 0) {
            $strMsg = json_encode($errorInfo);
            throw new \Exception("db error,msg:$strMsg");
        }
    }

    //根据uuid查询一条记录
    public function queryOneByUuid($pk, $selected = [])
    {
        if (empty($this->enableUuid)) {
            $fun = __FUNCTION__;
            throw new \Exception("The uuidKeyField is empty ban of {$fun} function");
        }
        if ($selected == []) {
            $select = "*";
        } else {
            $tmp = [];
            //var_dump($selected);
            foreach ($selected as $v) {
                $tmp[] = "`{$v}`";
            }
            $select = implode(",", $tmp);
            $tmp = null;
        }
        $table = $this->getTable();
        $prepareValue = static::POSITION_PREFIX . "_PK";
        $sql = "select {$select} from $table where {$this->uuidKeyField} = {$prepareValue}";
        $params = [$prepareValue => $pk];
        $sth = $this->connection->prepare($sql);
        if($sth->execute($params)){
            $sth->setFetchMode(PDO::FETCH_ASSOC);
        }else{
            $msg = $sth->errorInfo();
            $strMsg = json_encode($msg);
            throw new \Exception("db query error,msg:$strMsg, sql:{$sql}  ");
            return false;
        }

        return $sth->fetch();
    }

    //根据条件查询全部符合要求的数据
    public function queryAllByAttributes($selectField, $attribute, $type = "and", $order = "", $limit = "")
    {
        $arrSelectField = '';
        foreach ($selectField as $v) {
            $arrSelectField[] = '`' . $v . '`';
        }
        $strSelectField = implode(',', $arrSelectField);
        $i = 0;
        $arrCondition = [];
        $arrPrepare = [];
        foreach ($attribute as $k => $v) {
            $arrCondition[] = "`{$k}`" . "=" . static::POSITION_PREFIX . $i;
            $arrPrepare[static::POSITION_PREFIX . $i] = $v;
            $i++;
        }
        $strConditon = implode(" " . $type . " ", $arrCondition);
        $sql = "select {$strSelectField} from {$this->tableName} where {$strConditon} ";
        if (!empty($order)) {
            $sql .= " order by " . $order;
        }
        if (!empty($limit)) {
            $sql .= " limit " . $limit;
        }
        $sth = $this->connection->prepare($sql);
        if($sth->execute($arrPrepare)){
            $sth->setFetchMode(PDO::FETCH_ASSOC);
            $r = $sth->fetchAll();
        }else{
            $msg = $sth->errorInfo();
            $strMsg = json_encode($msg);
            throw new \Exception("db query error,msg:$strMsg, sql:{$sql} ");
            return false;
        }
        return $r;
    }

    //根据条件查询全部符合要求的in数据
    //$attribute = ['name'=>['张三','李四']];
    public function queryAllByAttributesIn($selectField, $attribute)
    {

        $arrSelectField = '';
        foreach ($selectField as $v) {
            $arrSelectField[] = '`' . $v . '`';
        }
        $strSelectField = implode(',', $arrSelectField);
        $i = 0;
        $arrCondition = [];
        $arrPrepare = [];
        foreach ($attribute as $k => $v) {
            $arrIn = [];
            foreach ($v as $inValue) {
                $arrIn[] = static::POSITION_PREFIX . $i;
                $arrPrepare[static::POSITION_PREFIX . $i] = $inValue;
                $i++;
            }
            $strIn = implode(",", $arrIn);
            $arrCondition[] = "`{$k}`" . " in ( " . $strIn . " ) ";
        }
        $strConditon = implode(" and ", $arrCondition);
        $sql = "select {$strSelectField} from {$this->tableName} where {$strConditon} ";
        $sth = $this->connection->prepare($sql);
        if($sth->execute($arrPrepare)){
            $sth->setFetchMode(PDO::FETCH_ASSOC);
            $r = $sth->fetchAll();
        }else{
            $msg = $sth->errorInfo();
            $strMsg = json_encode($msg);
            throw new \Exception("db query error,msg:$strMsg, sql:{$sql} ");
        }
        return $r;
    }

    /**
 * 查询总数
 * @param $selectField
 * @param $attribute
 * @return mixed
 */
    public function queryCountByAttributesIn($attribute)
    {

        $i = 0;
        $arrCondition = [];
        $arrPrepare = [];
        foreach ($attribute as $k => $v) {
            $arrIn = [];
            foreach ($v as $inValue) {
                $arrIn[] = static::POSITION_PREFIX . $i;
                $arrPrepare[static::POSITION_PREFIX . $i] = $inValue;
                $i++;
            }
            $strIn = implode(",", $arrIn);
            $arrCondition[] = "`{$k}`" . " in ( " . $strIn . " ) ";
        }
        $strConditon = implode(",", $arrCondition);
        $sql = "select count(*) from {$this->tableName} where {$strConditon} ";
        $sth = $this->connection->prepare($sql);
        if($sth->execute($arrPrepare)){
            $sth->setFetchMode(PDO::FETCH_ASSOC);
            $r = $sth->fetch();
            $count = $r['count(*)'];
        }else{
            $msg = $sth->errorInfo();
            $strMsg = json_encode($msg);
            throw new \Exception("db query error,msg:$strMsg, sql:{$sql} ");
        }
        return $count;
    }

    /**
     * 查询总数
     * @param $selectField
     * @param $attribute
     * @return mixed
     */
    public function queryCountByAttributes($attribute,$type='and')
    {

        $i = 0;
        $arrCondition = [];
        $arrPrepare = [];
        foreach ($attribute as $k => $v) {
            $arrCondition[] = "`{$k}`" . "=" . static::POSITION_PREFIX . $i;
            $arrPrepare[static::POSITION_PREFIX . $i] = $v;
            $i++;
        }
        $strConditon = implode(" " . $type . " ", $arrCondition);
        $sql = "select count(*) from {$this->tableName} where {$strConditon} ";
        $sth = $this->connection->prepare($sql);
        if($sth->execute($arrPrepare)) {
            $sth->setFetchMode(PDO::FETCH_ASSOC);
            $r = $sth->fetch();
            $count = $r['count(*)'];
        }else{
            $msg = $sth->errorInfo();
            $strMsg = json_encode($msg);
            throw new \Exception("db query error,msg:$strMsg, sql:{$sql} ");
        }
        return $count;
    }

    //根据条件查询一条符合要求的数据
    public function queryOneByAttributes($selectField, $attribute, $type = "and")
    {
        $arrSelectField = [];
        foreach ($selectField as $v) {
            $arrSelectField[] = '`' . $v . '`';
        }

        $strSelectField = implode(',', $arrSelectField);
        $i = 0;
        $arrCondition = [];
        $arrPrepare = [];
        foreach ($attribute as $k => $v) {
            $arrCondition[] = " `{$k}`" . "=" . static::POSITION_PREFIX . $i . " ";
            $arrPrepare[static::POSITION_PREFIX . $i] = $v;
            $i++;
        }
        $strConditon = implode($type, $arrCondition);
        $sql = "select {$strSelectField} from `{$this->tableName}` where {$strConditon} ";
        $sth = $this->connection->prepare($sql);

        if($sth->execute($arrPrepare)) {
            $sth->setFetchMode(PDO::FETCH_ASSOC);
            $r = $sth->fetch();
        }else{
            $msg = $sth->errorInfo();
            $strMsg = json_encode($msg);
            throw new \Exception("db query error,msg:$strMsg, sql:{$sql} ");
        }
        return $r;
    }

    public function queryOneBySql($sql, $params = [])
    {
        $sth = $this->connection->prepare($sql);
        if($sth->execute($params)) {
            $sth->setFetchMode(PDO::FETCH_ASSOC);
            return $sth->fetch();
        }else{
            $msg = $sth->errorInfo();
            $strMsg = json_encode($msg);
            throw new \Exception("db query error,msg:$strMsg, sql:{$sql} ");
        }
    }


    public function queryAllBySql($sql, $params = [])
    {
        //echo "<p style='color:red'>in db</p>";//todo 测试用，正是时候删除
        $sth = $this->connection->prepare($sql);
        if($sth->execute($params)) {
            $sth->setFetchMode(PDO::FETCH_ASSOC);
            $r = $sth->fetchAll();
        }else{
            $msg = $sth->errorInfo();
            $strMsg = json_encode($msg);
            throw new \Exception("db query error,msg:$strMsg, sql:{$sql} ");
        }
        return $r;
    }

    //插入记录
    public function insert($attribute)
    {
        $arrKey = array_keys($attribute);
        $arrValue = array_values($attribute);
        $arrKey2 = [];
        $arrParpare = [];
        foreach ($arrKey as $v) {
            $arrKey2[] = "`{$v}`";
            $arrParpare[] = ":" . $v;
        }
        $strColum = implode(",", $arrKey2);
        $strParpare = implode(",", $arrParpare);
        if ($this->enableUuid) {
            $uuid = $this->createUuid();
            $sql = "insert into `{$this->tableName}`(`uuid`,{$strColum}) values('{$uuid}',{$strParpare})";
        } else {
            $sql = "insert into `{$this->tableName}`({$strColum}) values({$strParpare})";
        }
        $params = array_combine($arrParpare, $arrValue);
        $sth = $this->connection->prepare($sql);
        //var_dump($sql,$params);die;
        if ($sth->execute($params)) {
            if ($this->enableUuid) {
                $this->uuid = $uuid;
            }
            return $this->connection->lastInsertId();
        } else {
            $msg = $sth->errorInfo();
            $strMsg = json_encode($msg);
            $strAttributes = json_encode($attribute);
            throw new \Exception("db insert error,msg:$strMsg, sql:{$sql}  attributes:{$strAttributes}");
        }
    }

    //批量插入,insert into ...values(),(),()
    public function insertSome($arrAttributes)
    {
        $arrSqlValues = [];
        $i = 0;
        $arrKeys = array_keys($arrAttributes[0]);
        $arrNewKeys = [];
        foreach ($arrKeys as $v) {
            $arrNewKeys[] = "`{$v}`";
        }
        $strColum = implode(",", $arrNewKeys);
        $arrparams = [];
        $returnUuid = [];
        foreach ($arrAttributes as $attribute) {
            $arrParpare = [];
            foreach ($attribute as $k => $v) {
                $index = ":" . $k . $i;
                $arrParpare[] = $index;
                $arrparams[$index] = $v;
            }
            $strParpare = implode(",", $arrParpare);
            if ($this->enableUuid) {
                $uuid = $this->createUuid();
                $returnUuid[] = $uuid;
                $arrSqlValues[] = "('{$uuid}',{$strParpare})";
            } else {
                $arrSqlValues[] = "({$strParpare})";
            }
            $i++;
        }
        $strSqlValue = implode(",", $arrSqlValues);
        if ($this->enableUuid) {
            $uuid = $this->createUuid();
            $sql = "insert into `{$this->tableName}`(`uuid`,{$strColum}) values " . $strSqlValue;
        } else {
            $sql = "insert into `{$this->tableName}`({$strColum}) values" . $strSqlValue;
        }
        $sth = $this->connection->prepare($sql);
        $insertRe = $sth->execute($arrparams);
        if ($insertRe) {
            if ($this->enableUuid) {
                $this->uuid = $returnUuid;
            }
            return true;
        } else {
            $msg = $sth->errorInfo();
            $strMsg = json_encode($msg);
            $strAttributes = json_encode($attribute);
            throw new \Exception("db insert error,msg:$strMsg, sql:{$sql}  attributes:{$strAttributes}");
        }
    }

    //获取insert操作的uuid
    public function getInsertUuid()
    {
        return $this->uuid;
    }

    /**
     * 根据主键去修改
     * @param $pk
     * @param $attribute
     * @return mixed
     */
    public function updateByUuid($uuid, $attribute)
    {
        if (empty($this->enableUuid)) {
            $fun = __FUNCTION__;
            throw new \Exception("uuid not enable ,{$fun} not allowed");
        }
        $prepareValue = static::POSITION_PREFIX;
        $strCondition = " {$this->uuidKeyField} = $prepareValue";
        $conditionParams = [$prepareValue => $uuid];
        $updateRe = $this->update($attribute, $strCondition, $conditionParams);
        return $updateRe;
    }

    /**
     * 根据一批主键去修改
     * @param $pk
     * @param $attribute
     * @return mixed
     */
    public function updateByUuidIn(array $arrUuid, $attribute)
    {
        if (empty($this->enableUuid)) {
            $fun = __FUNCTION__;
            throw new \Exception("uuid not enable ,{$fun} not allowed");
        }
//        $prepareValue = static::POSITION_PREFIX;
//        $strCondition = " {$this->uuidKeyField} = $prepareValue";
//        $conditionParams = [$prepareValue => $uuid];
        $i= 0 ;
        $prefix = ":picUuid";
        $arrPre=[];
        $conditionParams = [];
        foreach($arrUuid as $v){
            $arrPre[] = $prefix.$i;
            $conditionParams[$prefix.$i] = $v;
            $i++;
        }
        $strCondition = implode(",",$arrPre);
        $strCondition = " {$this->uuidKeyField} in ($strCondition)";
        $updateRe = $this->update($attribute, $strCondition, $conditionParams);
        return $updateRe;
    }

    /**
     * 根据自定义的查询条件去修改
     * @param $attribute  k=>v k对应字段名，v对应要修改后的值
     * @param $strCondition  字符串类型的查询条件，也就是where子句
     * @param array $conditionParams sql防注入的参数
     * @return mixed
     */
    public function update($attribute, $strCondition, $conditionParams = [])
    {
        $arrUpdate = [];
        $params = [];
        $i=0;
        foreach ($attribute as $k => $v) {
            $arrUpdate[] = "`{$k}`=".static::POSITION_PREFIX.$i;
            $params[static::POSITION_PREFIX.$i] = $v;
            $i++;
        }
        $strUpdate = implode(",", $arrUpdate);
        $sql = "update `{$this->tableName}` set " . $strUpdate . " where {$strCondition}";
        if (!empty($conditionParams)) {
            $params = $params + $conditionParams;
        }
        $sth = $this->connection->prepare($sql);
        $sth->execute($params);
        $count = $sth->rowCount();
        if ($count > 0) {
            $return = $count;
        } else {
            $msg = $sth->errorInfo();
            $intCode = intval($msg[0]);
            if (!empty($intCode)) {
                $strMsg = json_encode($msg);
                $strParams = json_encode($conditionParams);
                throw new \Exception("db update error,msg:{$strMsg} condition:{$strCondition} params:{$strParams}");
            }
            $return = $count;
        }
        return $return;
    }

    //根据attribute更新
    public function updateByAttributes($attribute,$conditionAttributes){
        $tmpArr = [];
        $conditionParams = [];
        $i=0;
        foreach($conditionAttributes as $k=>$v){
            $tmpArr[] = "`{$k}` = :{$k}";
            $conditionParams[':'.$k] = $v;
            $i++;
        }
        $strCondition = implode(' and ',$tmpArr);
        return $this->update($attribute,$strCondition,$conditionParams);
    }

//    public function delete($strCondition, $conditionParams = [])
//    {
//        $sql = "delete from {$this->tableName} where {$strCondition}";
//        $sth = $this->connection->prepare($sql);
//        $sth->execute($conditionParams);
//        $count = $sth->rowCount();
//        if ($count > 0) {
//            $return = $count;
//        } else {
//            $strParams = json_encode($conditionParams);
//            throw new \Exception("db delete error,condition:{$strCondition} params:{$strParams}");
//        }
//        return $return;
//    }

    //尽量不使用真删除，使用状态位来记录状态
//    public function deleteByAttributes($attribute)
//    {
//        $arrKey = array_keys($attribute);
//        $arrValue = array_values($attribute);
//        $arrKey2 = [];
//        $arrParpare = [];
//        foreach ($arrKey as $v) {
//            $arrKey2[] = "`{$v}`";
//            $arrParpare[] = ":" . $v;
//        }
//        $arrWhere = array_combine($arrKey2,$arrParpare);
//        $arrWhere2 = [];
//        foreach($arrWhere as $k=>$v){
//            $arrWhere2[] = " {$k} = {$v}";
//        }
//        $arrPrepare = array_combine($arrParpare,$arrValue);
//        $strWhere = implode(" and ",$arrWhere2);
//        $sql = "delete from `{$this->tableName}` where ".$strWhere;
//        $sth = $this->connection->prepare($sql);
//        $sth->execute($arrPrepare);
//        $count = $sth->rowCount();
//        return $count;
//    }

    //【一般的删除都是软删除，只更新标志位】
    public function deleteByAttributes($conditionAttributes,$operatorUuid=null){
        $arrWhere = [];
        $arrPrepare = [];
        $i=0;
        foreach($conditionAttributes  as $k=>$v){
            $arrWhere[$k] = static::POSITION_PREFIX.$i;
            $arrPrepare[static::POSITION_PREFIX.$i] = $v;
            $i++;
        }
        $strSetValue = $this->delStatusCol." = ".$this->delStatusDelValue;
        if($operatorUuid!=null){//如果传了删除的人，则把删除人也加上
            $strSetValue .=" , ".$this->delOperatorCol." = :delOperatorUserUuid";
            $arrPrepare[":delOperatorUserUuid"] = $operatorUuid;
        }
        $arrWhere2 = [];
        foreach($arrWhere as $k=>$v){
            $arrWhere2[] = "`{$k}` = {$v}";
        }
        $strWhere = implode(" and ", $arrWhere2);
        $sql = "update `{$this->tableName}` set {$strSetValue} where ".$strWhere;
        $sth = $this->connection->prepare($sql);
        //var_dump($sql,$arrPrepare);die;
        $sth->execute($arrPrepare);
        $this->recordErrMsg($sth);
        $count = $sth->rowCount();
        return $count;
    }

    //【一般的删除都是软删除，只更新标志位】
    public function deleteByAttributesIn($attributes,$operatorUuid=null){
        $arrWhere = [];
        $arrPrepare = [];

        $strSetValue = $this->delStatusCol." = ".$this->delStatusDelValue;
        $arrWhere2 = [];

        $i=0;
        foreach($attributes as $k=>$v){
            $arrInValue = [];
            foreach($v as $k2=>$v2){
                $arrWhere[$i] = static::POSITION_PREFIX.$i;
                $arrInValue[] = static::POSITION_PREFIX.$i;
                $arrPrepare[static::POSITION_PREFIX.$i] = $v2;
                $i++;
            }

            $inValue = implode(",",$arrInValue);
            $inValue = '('.$inValue.')';
            $arrWhere2[] = "`{$k}` in {$inValue}";
        }
        $strWhere = implode(" and ", $arrWhere2);
        if($operatorUuid!=null){//如果传了删除的人，则把删除人也加上
            $strSetValue .=" , ".$this->delOperatorCol." = :delOperatorUserUuid";
            $arrPrepare[":delOperatorUserUuid"] = $operatorUuid;
        }
        $sql = "update `{$this->tableName}` set {$strSetValue} where ".$strWhere;
        $sth = $this->connection->prepare($sql);

        $sth->execute($arrPrepare);
        $count = $sth->rowCount();
        return $count;
    }

    //输入查询条件，获取count数量
    public function queryCount($strCondition, $params = [])
    {
        $countName = $this->tableName . '_count';
        $sql = 'select count(*) as ' . $countName . ' from ' . $this->tableName . ' where ' . $strCondition;
        $sth = $this->connection->prepare($sql);
        $sth->execute($params);
        $sth->setFetchMode(PDO::FETCH_ASSOC);
        $dbRe = $sth->fetch();
        return $dbRe[$countName];
    }
}