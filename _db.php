<?php
/**********************************************************
*   MSSQL DBClass                                         *
*   Created By: Charles Watkins                           *
*   2011-02-21                                            *
*   2019-04-06 UPDATED FOR PDO                            *
**********************************************************/

date_default_timezone_set('America/Los_Angeles');

 if(!defined('_DATABASE_IP')) {
    define('_DATABASE_IP'                 ,'');
    define('_DATABASE_USER'               ,'');
    define('_DATABASE_PASSWORD'           ,'');
 }

class log{
    static function error($data1="No Error",$data2=false,$title=false,$function=false){
        echo "<!-- \r";
        if($function)   echo "Function:".$function;
        echo "\r\n";
        if($title)      echo "Title:".$title;
        echo "\r\n";
        print_r($data1);
        echo "\r\n";
        print_r($data2);
        echo "-->";
        error_log("");
    }

    static function write($title="No Error",$data=false,$class=false,$function=false){
        $m=array();
        if($function)   $m[]="Function:".$function;
        if($class)      $m[]="Class:".$class;
        if($title)      $m[]="Title:".$title;
        error_log("[".join(' ',$m)."] ".$data);
    }
        
    
}


if(False==function_exists('mssql_select_db')){
    $GLOBALS['db_mode']='PDO';
    log::error("DB","USING PDO Driver for DB");
} else {
    $GLOBALS['db_mode']='mssql';
    log::error("DB","USING MSSQL Driver for DB");
}

class db{

    static function connect(){
        if(db::isConnected() && db::isValidConnection()) {
            return;
        }

        if($GLOBALS['db_mode']=='PDO'){
            try{
                $GLOBALS['db']=new PDO("dblib:host="._DATABASE_IP.";dbname="._DATABASE_NAME, _DATABASE_USER, _DATABASE_PASSWORD);
            } catch(PDOException $e) {
                $msg=$e->getMessage();
                log::error("DB","DB Class ".$msg);
            }
        } else {
            $GLOBALS['db']=mssql_connect(_DATABASE_IP, _DATABASE_USER,_DATABASE_PASSWORD);
        }
        $GLOBALS['dbQueries']=array();

        if($GLOBALS['db']) return true;
        //log::error("Connect failed"._DATABASE_IP.":". _DATABASE_SA_USER.":"._DATABASE_SA_PASSWORD,mssql_get_last_message(),__class__,__function__);
        //log::error("Connect failed",mssql_get_last_message(),__class__,__function__);
        return false;
    }

    static function close(){
        try{
            if($GLOBALS['db']) {
                if($GLOBALS['db_mode']=='PDO'){
                    try{
                        $con=$GLOBALS['db'];
                        $con=NULL;
                        $GLOBALS['db']=NULL;
                    } catch(PDOException $e) {
                        $msg=$e->getMessage();
                        log::error("DB","DB Class ".$msg);
                    }
                } else {
                    mssql_close($GLOBALS['db']);
                }
                return true;
            }
        } catch (Exception $e) {
            log::error("Closing DB Failed.",$e,__class__,__function__);
        }
        return false;
    }

    static function select($db_name){
        if(!db::isConnected() || !db::isValidConnection()) {
            //log::error("DB Not CONNECTED","",__class__,__function__);
            db::connect();
        }
        $GLOBALS['dbName']=$db_name;
        if($GLOBALS['db_mode']=='PDO'){
            try{
                $con=$GLOBALS['db'];
                $con->query("USE $db_name");
            } catch(PDOException $e) {
                $msg=$e->getMessage();
                log::error("DB","DB Class ".$msg);
            }
        } else {
            mssql_select_db($db_name);
        }
    }

    

    /********************************************
    * fetch: $res, can be a mssql result OR a Query
    * string; if it is a string, it will be execues as a
    * string and then fetched. if no res is passed the
    * previous $res is pulled.
    *
    * function, will pass the function to be called if
    * any with the paramitters of the fetch
    ********************************************/
    static function fetch($res=false,$function=false){
        if($res) {
            if(is_string($res)) {
            $query=$res;
            $res=db::query($query);
           }
        } else {
            $res=$GLOBALS['dbQueryResult'];
        }
        if($res && db::querySuccess($res)) {
            if($GLOBALS['db_mode']=='PDO'){
                try{
                    $row=$res->fetch(PDO::FETCH_ASSOC);
                } catch(PDOException $e) {
                    $msg=$e->getMessage();
                    log::error("DB","DB Class ".$msg);
                }
            } else {
                $row=mssql_fetch_object($res);
            }
            $GLOBALS['dbQueryData'] =$row;
            $GLOBALS['dbQueryFields']=array_keys((array)$row);
            if($function) return $function($row);
            return $row;
        }
        return false;
    }

    static function fetchArr($res=false,$function=false){
        if($res) {
            if(is_string($res)) {
            $query=$res;
            $res=db::query($query);
           }
        } else {
            $res=$GLOBALS['dbQueryResult'];
        }
        if($res && db::querySuccess($res)) {
            if($GLOBALS['db_mode']=='PDO'){
                try{
                    $row=$res->fetch(PDO::FETCH_NUM);
                } catch(PDOException $e) {
                    $msg=$e->getMessage();
                    log::error("DB","DB Class ".$msg);
                }
            } else {
                $row=mssql_fetch_assoc($res);
            }
            $GLOBALS['dbQueryData'] =$row;
            $GLOBALS['dbQueryFields']=array_keys((array)$row);
            if($function) return $function($row);
            return $row;
        }
        return false;
    }

    static function showFieldSample($strip=false){
        if($strip) {
            $data=$GLOBALS['dbQueryData'];
            foreach($data as $key=>$item) {
                $tokens=explode("_",$key);
                $len=count($tokens);
                if($len==0)$data2[]=$key;
                else $data2[]=$tokens[$len-1];
            }
        } else $data2=$GLOBALS['dbQueryData'];

        log::error("Show Fields",$data2);
    }

    static function showDataSample($strip=true){
     if($strip) {
            $data=$GLOBALS['dbQueryData'];
            foreach($data as $key=>$item) {
                $tokens=explode("_",$key);
                $len=count($tokens);
                if($len==0)$data2[$key]=$data;
                else $data2[$tokens[$len-1]]=$data;
            }
        } else $data2=$GLOBALS['dbQueryData'];
        log::error("Show Fields",$data2);
    }


    /********************************************
    * fetchALL: returns all ropws for an object...
    * $res, can be a mssql result OR a Query
    * string; if it is a string, it will be execues as a
    * string and then fetched. if no res is passed the
    * previous $res is pulled.
    *
    * function, will pass the function to be called if
    * any with the paramitters of the fetch
    ********************************************/
    static function fetchAll($res=false,$function=false){
        log::error("DB","Fetching");
        if($res && is_string($res)) {
            $res=db::query($res);
        } else {
            //print_r($res);
            if(!isset($GLOBALS['dbQueryResult'])) {
                log::error("No previous query found","Resource bad");
                return false;
            }
            $res=$GLOBALS['dbQueryResult'];
        }

        if(db::querySuccess($res)) {
            $rows=array();
            if($GLOBALS['db_mode']=='PDO'){
                try{
                    log::error("DB","Fetching PDO");
                    $rows=$res->fetchAll();
                } catch(PDOException $e) {
                    $msg=$e->getMessage();
                    log::error("DB","DB Class ".$msg);
                }
            } else {
                while(true==($row=mssql_fetch_object($res))){
                    $rows[]=$row;
                }
            }
            $row=current($rows);
            $GLOBALS['dbQueryData'] =$row;
            $GLOBALS['dbQueryFields']=array_keys((array)$row);

            if($function) return $function($rows);
            return $rows;
        }
        return false;
    }

    static function ms_escape_string($data) {

        if ( !isset($data) || $data===0 ) return '';
        if ( is_numeric($data) ) return $data;

        $non_displayables = array(
            '/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
            '/%1[0-9a-f]/',             // url encoded 16-31
            '/[\x00-\x08]/',            // 00-08
            '/\x0b/',                   // 11
            '/\x0c/',                   // 12
            '/[\x0e-\x1f]/'             // 14-31
        );
        foreach ( $non_displayables as $regex )
            $data = @preg_replace( $regex, '', $data );
        $data = str_replace("'", "''", $data );
        return $data;
    }

    static function insert($table,$dataArray){
        foreach($dataArray as $field=>$data){
            $columns[]=$field;
            if($data=="getdate()")  $values []=$data;
            else                    $values []="'".db::ms_escape_string($data)."'";
        }
        if(count($columns)!=count($values)) {
            log::error("Field count: ".count($columns),"Value Count:".count($values),__class__,__function__);
            return false;
        }

        $query="INSERT INTO {$table} (".join(",",$columns).") VALUES (".join(',',$values).")";
        db::query($query);

        return db::fetch("SELECT @@IDENTITY as id");

    }



    /********************************************
    * rows: returns the number of reows form  query
    ********************************************/
    static function rows($res=false){
        if(!$res) $res=$GLOBALS['dbQueryResult'];
        if($res) {
            if($GLOBALS['db_mode']=='PDO'){
                try{
                    log::error("DB","DB_CLASS Function not supported yet");
                } catch(PDOException $e) {
                    $msg=$e->getMessage();
                    log::error("DB","DB Class ".$msg);
                }
            } else {
                return mssql_num_rows($res);
            }
        }
        return false;
    }

    static function assoc($res=false){
        if(!$res) $res=$GLOBALS['dbQueryResult'];
        if($res) {
            if($GLOBALS['db_mode']=='PDO'){
                try{
                    log::error("DB","DB_CLASS Function not supported yet");
                } catch(PDOException $e) {
                    $msg=$e->getMessage();
                    log::error("DB","DB Class ".$msg);
                }
            } else {
                return mssql_fetch_assoc($res);
            }
        }
        return false;
    }

    static function _array($res=false){
        if(!$res) $res=$GLOBALS['dbQueryResult'];
        if($res) {
            if($GLOBALS['db_mode']=='PDO'){
                try{
                    log::error("DB","DB_CLASS Function not supported yet");
                } catch(PDOException $e) {
                    $msg=$e->getMessage();
                    log::error("DB","DB Class ".$msg);
                }
            } else {
                return mssql_fetch_array($res);
            }
        }
        return false;
    }

    static function isConnected(){
        if(!isset($GLOBALS['db'])) {
          //log::error("DB Not Connected","",__class__,__function__);
          return false;
        }
        return true;
    }

    static function isValidConnection(){
        if(!$GLOBALS['db']) {
          //log::error("DB Not Connected","",__class__,__function__);
          return false;
        }
        return true;
    }


    static function query($query_string){
        if(!db::isConnected() || !db::isValidConnection()) {
          log::write("db not connected",$query_string,__class__,__function__);
          return;
        }

        $GLOBALS['dbQueries'][]=$query_string;
        if(!is_string($query_string)) {
         log::error("Not a String: Query",$query_string,__class__,__function__);
         log::write("Not a String: Query",$query_string,__class__,__function__);
         $GLOBALS['dbQueryResult']=$res;
         return;
        }

        //Test for update without a where.....
        $test=strtoupper($query_string);
        if ( !(strrpos($test,"UPDATE ")===false) && strrpos($test,"WHERE ") === false) { // note: three equal signs
            if((strrpos($test,"BKSYMSTR")===false)) {
                 log::error("DB","[query error] ".$query_string);
                 log::error("Update with no where...Not a String: Query",$query_string,__class__,__function__);
        //         return;
            }
        }
        if($GLOBALS['db_mode']=='PDO'){
            try{
                log::error("DB","PDO Query: ".$query_string);
                $res=$GLOBALS['db']->query($query_string);
                #$res->execute();
            } catch(PDOException $e) {
                $msg=$e->getMessage();
                log::error("DB","DB Class ".$msg);
            }
            if(!$res) {
                $conn=$GLOBALS['db'];
                #print_r($conn->errorInfo());
                log::error("Query Failed",$query_string." ",__class__,__function__);
            }
    
        } else {
            $res=mssql_query($query_string);
            if(!$res) {
                log::error("Query Failed",$query_string." ".mssql_get_last_message(),__class__,__function__);
                log::write("[Query Failed]",$query_string." ".mssql_get_last_message(),__class__,__function__);
            }
        }

        $GLOBALS['dbQueryResult']=$res;
        return $res;
    }

    static function querySuccess($res=false){
        if(!$res) {
            if(!isset($GLOBALS['dbQueryResult'])) {
                 //$query=end($GLOBALS['dbQueries']);
               //log::error("Query Failed",$query,__class__,__function__);
              // var_dump(debug_backtrace());
              return false;
            }

            $res=$GLOBALS['dbQueryResult'];
        }
        if($res) return true;
        return false;
    }

    static function queryFail($res=false){
        if(!$res) $res=$GLOBALS['dbQueryResult'];
        if(!$res) return true;
        return false;
    }

    static function name() {
        if(isset($GLOBALS['dbName']))        return $GLOBALS['dbName'];
        else                                 log::error("DB name not set.","",__class__,__function__);
        return false;
    }

    static function showQueries(){
        log::error("Queries",$GLOBALS['dbQueries'],__class__,__function__);
    }
}
