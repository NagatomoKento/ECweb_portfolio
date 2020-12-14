<?php

namespace ECweb\lib;

class PDODatabase
{
  private $dbh = null;
  private $db_host = '';
  private $db_user = '';
  private $db_pass = '';
  private $db_name = '';
  private $db_type = '';
  
  private $order = '';
  private $limit = '';
  private $offset = '';
  private $groupby = '';

  public function __construct($db_host, $db_user, $db_pass, $db_name, $db_type)
  {
    $this->dbh = $this->connectDB($db_host, $db_user, $db_pass, $db_name, $db_type);
    $this->db_host = $db_host;
    $this->db_user = $db_user;
    $this->db_pass = $db_pass;
    $this->db_name = $db_name;

    $this->order = '';
    $this->limit = '';
    $this->offset = '';
    $this->groupby = '';
  }

  private function connectDB($db_host, $db_user, $db_pass, $db_name, $db_type)
  {
    try {
      switch ($db_type) {
        case 'mysql':
          $dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name;
          $dbh = new \PDO($dsn, $db_user, $db_pass);
          $dbh->query('SET NAMES utf8');
        break;

        case 'pgsql':
          $dsn = 'pgsql:dbname=' . $db_name . 'host=' . $db_host . 'port=5432';
          $dbh = new \PDO($dsn, $db_user, $db_pass);
        break;
      }
    } catch (\PDOException $e) 
     {
      var_dump($e->getMessage());
      exit();
    }
    return $dbh;
  }

  public function setQuery($query = '', $arrVal = [])
  {
    $stmt = $this->dbh->prepare($query);
    $stmt->execute($arrVal);
  }

  public function select($table, $column = '', $where = '', $arrVal = [])
  {
    $sql = $this->getSql('select', $table, $where, $column);
    $this->sqlLogInfo($sql, $arrVal);
    $stmt = $this->dbh->prepare($sql);
    $res = $stmt->execute($arrVal);
    
    if ($res === false) {
      $this->catchError($stmt->errorInfo());
    }
    
    $data = [];
    while($result = $stmt->fetch(\PDO::FETCH_ASSOC)){
      array_push($data, $result);
    }
    return $data;
  }

  public function cartcheck($table, $column = '', $where = '', $arrVal = [])
  {
    $sql = $this->getSql('select', $table, $where, $column);
    $stmt = $this->dbh->prepare($sql);
    $res = $stmt->execute($arrVal);
    $data = [];
    while($result = $stmt->fetch(\PDO::FETCH_ASSOC)){
      array_push($data, $result);
    }
    return $data;
  }

  public function count($table, $where = '', $arrVal= [])
  {
    $sql = $this->getSql('count', $table, $where);
    $this->sqlLogInfo($sql, $arrVal);

    $stmt = $this->dbh->prepare($sql);
    $res = $stmt->execute($arrVal);

    if ($res === false) {
      $this->catchError($stmt->errorInfo());
    }
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    return intval($result['NUM']);
  }


  // public function setOrder($order = ''){
  //   if($strOrder !== ''){
  //     $this->order = ' ORDER BY ' . $strOrder;
  //   }
  // }

  // public function setLimitOff($limit = '', $offset = '')
  // {
  //   if($limit !== ""){
  //     $this->limit = " LIMIT " . $limit;
  //   }
  //   if($offset !== ""){
  //     $this->offset = " OFFSET " . $offset;
  //   }
  // }

  // public function setGroupBy($groupby)
  // {
  //   if($groupby !== ""){
  //     $this->groupby = ' GROUP BY ' . $groupby;
  //   }
  // }

  private function getSql($type, $table, $where = '', $column = '')
  {
    switch($type){
      case 'select':
        $columnKey = ($column !== '')? $column : "*";
      break;

      case 'count':
        $columnKey = 'COUNT(*) AS NUM ';
      break;

      default:
    break;
    }

    $whereSQL = ($where !== '')? ' WHERE '.$where : '';

    // $other = $this->groupby . "  " . $this->order . "  " . $this->limit . "  " . $this->offset;
    
    $sql = " SELECT " . $columnKey . " FROM " . $table . $whereSQL; //  . $other;

    // $sql= 'select' castomer_no FROM session WHERE ession_key = ?' 
    return $sql;
  }


  public function insert($table, $insData = [])
  {
    $insDataKey = [];
    $insDataVal = [];
    $preCnt = [];
    $columns = '';
    $prest = '';

    foreach($insData as $col => $val){
      $insDataKey[] = $col;
      $insDataVal[] = $val;
      $preCnt[] = '?';
    }

    $columns = implode(",", $insDataKey);
    $preSt = implode(",", $preCnt);

    $sql = " INSERT INTO "
          . $table
          . " ("
          . $columns
          . ") VALUES ("
          . $preSt
          . ") ";


    $this->sqlLogInfo($sql, $insDataVal);
    $stmt = $this->dbh->prepare($sql);
    $res = $stmt->execute($insDataVal);

    if($res === false){
      $this->catchError($stmt->errorInfo());
    }
    return $res;
  }

  public function update($table, $insData=[], $where, $arrWhereVal = [])
  {
    $arrPreSt = [];
    foreach ($insData as $col => $val) {
        $arrPreSt[] = $col . " =? ";
    }
    $preSt = implode(',', $arrPreSt);

    $sql = " UPDATE "
         . $table
         . " SET "
         . $preSt
         . " WHERE "
         . $where;
         
    $updateData = array_merge(array_values($insData), $arrWhereVal);
    $this->sqlLogInfo($sql, $updateData);
    
    $stmt = $this->dbh->prepare($sql);
    $res = $stmt->execute($updateData);

    if($res === false){
      $this->catchError($stmt->errorInfo());
    }
    return $res;
  }


  public function getLastId()
  {
    return $this->dbh->lastInsertId();
  }

  private function catchError($errArr = [])
  {
    // 配列の２番目にエラーメッセージが格納されている
    $errMsg = (!empty($errArr[2]))?  $errArr[2] : "";
    die("SQLエラーが発生しました。" . $errArr[2]);  // exitと同じ
  }

  private function makeLogFile()
  {
    $logDir = dirname(__DIR__) . "/logs";
    if(!file_exists($logDir)){  //ファイル存在を探す !:ない
      mkdir($logDir, 777);  //makedirektry permission:777
    }
    $logPath = $logDir . '/shopping.log';
    if(!file_exists($logPath)){
      touch($logPath);  //shopping.logファイルの作成
    }
    return $logPath;
  }


  private function sqlLogInfo($str, $arrVal = [])
  {
    $logPath = $this->makeLogFile();

    $logData = sprintf("[SQL_LOG:%s]:%s [%s]\n " , date('Y-m-d H:i:s'), $str, implode(",", $arrVal));

    error_log($logData, 3, $logPath);
    // 第1引数：エラーログの登録する文字
    // 第2引数：3 任意のファイルにログを書き込む 
    // 第3引数：パス
  }
}