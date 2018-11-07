#!/usr/bin/env php
<?php
/**
 * DBF2ORA.PHP 
 * 
 * DBF 历史数据迁移脚本 
 * 
 * 出错记录保存在 <表名>.SQL 文件中 
 * 
 * @author zhan <zhanet@163.com>
 */

/**
 * 清空表和SQL记录文件
 */
function clearTable($tname) 
{
    //删除出错SQL记录文件
    // if (file_exists($tname.".sql")) unlink($tname.".sql");
    $fp = fopen($tname.".SQL", "w") or die("创建SQL记录文件出错！");
    fclose($fp);
    try {
        $sql = "TRUNCATE TABLE $tname;";
        //连接ORACLE数据库，需创建ODBC ORACLE连接：ORA11
        $dbh = new PDO('odbc:ORA11','gjcy','gjcygc', array(PDO_ATTR_PERSISTENT=>true));
        //$dbh->setAttribute(PDO_ATTR_ERRMODE, PDO_ERRMODE_EXCEPTION);
        $dbh->exec($sql); // or die(print_r($dbh->errorInfo(), true));
        //$dbh->exec("DELETE FROM $tname");
        $dbh = null;
    } catch (Exception $e) {
        echo "Failed: ".$e->getMessage();
    }
}

/**
 * 判断尾部是否半个汉字（不完善，但至少不会吃尾部'）
 * 
 * GB2312汉字由双特殊ASCII码组成一个汉字，前一个ASCII码高位必为1 
 */
function isGB($str) 
{
	$w = 0; //中文开始位置
	$slen = strlen($str);
	//取中文字开始位置
	for ($i=0; $i < $slen; $i++) {
		if (ord(substr($str,$i,1)) > 127) {
			$w = $i; break;
		}
	}
	//从中文字开始，跳过英文字符
	for ($i=$w; $i < $slen; $i=$i+2) {
		if (ord(substr($str,$i,1)) > 127) {
			//若为最后字符，则为半个汉字
			if ($i == $slen-1) return 1;
		} else {
			//普通ASCII，跳过
			$i = $i - 1;
		}
	}
	return 0;
}

/**
 * DBF 字段值转为 ORACLE 相关类型值
 * 
 * 参数：DBF文件，字段表，记录，字段名/序号，模式
 */
function getValue($dbf, &$fields, &$record, $f, $m) 
{
    $value = trim($record[$f]);
    if ($value == "") return "NULL";
    //echo $dbf." ".$f." ".$m; exit;
    //mb_internal_encoding("gb2312");
    $value = ltrim($value, "Yy");

    if ($m == "C") {
        $value = "'".$value."'";
    }
    else if ($m == "C1") {
        if (preg_match('/([\x00-\x1f]+)/', $value)) {
            //存在非法字符，清空
            //echo $value." \r\n";
            $value = "NULL";
        }
        else if (preg_match('/(队[1-9]\xC1)$/', $value)) {
            //补上后半个联字
            //echo $value." \r\n";
            $value = "'".$value.chr(170)."'";
        }
        else if (preg_match('/(\xC5|\xC6|\xC7|\xC8)$/', $value)) {
            //CYHA200909.DBF有很多字符串尾有半个汉字
            if (isGB($value)==0) {
                //此处仍有乱码
                //echo $value." \r\n";
                $value = "'".$value."'";
            } else {
                $value = "NULL";
            }
		}
		/*
        else if (preg_match('/(\x9E\xC7)$|(\x57\xC7)$|(\x6C\xC7)$|(\x81\xC7)$|(\xAB\xC7)$|(\x3E\xC7)$|(\x75\xC7)$/', $value)) {
            //echo $value." \r\n";
            $value = "NULL";
        }
        else if (preg_match('/(\x5B\xC6)$|(\x36\xC5)$|(\x36\xC6)$|(\x2B\xC6)$|(\xC8\xC8)$|(\xE6\xC8)$|(\xEE\xC8)$|(\xFF\xC8)$|(\x4F\xC8)$|(\xE9\xC8)$/', $value)) {
            //echo $value." \r\n";
            $value = "NULL";
        }
        */
        else {
            if (isGB($value)==0) {
                $value = "'".$value."'";
            } else {
                $value = "'".substr($value, 0, strlen($value)-1)."'";
                //$value = "NULL";
            }
        }
    }
    else if ($m == "D") {
        if (trim($record[$f])=='-  -') {
            $value = "NULL";
        } else {
            $value = "to_date('".trim($record[$f])."','YYYYMMDD')";
        }
    }
    else if ($m == "D1") {
        $value = "to_date('".trim($record[$f])."','YYYYMM')";
    }
    else if ($m == "D2") {
        //只有日，前面加上DBF年月
        $value = trim($record[$f]);
        if ($value=="0") {
            $value = "to_date('".substr($dbf,4,6).$value."1','YYYYMMDD')";
        } else if (strlen($value)==1 && preg_match('/^\d{1}$/', $value)) {
            $value = "to_date('".substr($dbf,4,6)."0".$value."','YYYYMMDD')";
        } else if (strlen($value)==2 && preg_match('/^(0[1-9]|1[0-9]|2[0-9]|3[0-1])$/', $value)) {
            $value = "to_date('".substr($dbf,4,6).$value."','YYYYMMDD')";
        } else $value = "NULL";
    }
	else if ($m == "D4") {
		//为早期DBF日期字段加上19 
		$value = trim($record[$f]);
        if (strlen($value)==8) {
			$value = "to_date('".$value."','YYYYMMDD')";
		} else if (strlen($value)==6 && preg_match('/^(8|9)\d{5}$/', $value)) {
			$value = "to_date('19".$value."','YYYYMMDD')";
		} else $value = "NULL";
	}
    return $value;
}

/**
 * 执行 DBF 数据迁移到 ORACLE 
 * 
 * @param string $csv     字段文件名 
 * @param string $dbf     DBF文件名 
 * @param string $tname   ORACLE表名 
 * @param string $dftype  DBF字段类型 
 * @param int    $start   起始记录序号 
 * @param int    $debug   调试模式 
 */
function dbf2ora($csv, $dbf, $tname, $dftype, $start=0, $debug=0)
{
	//读字段对应关系文件
    $fp = fopen($csv, "r") or die("打开字段定义文件出错：".$csv." !");
	if (!feof($fp)) {$tmp = fgets($fp);} //取第一行
	$fd = array();
	while (!feof($fp)) {
		$tmp = explode(",", fgets($fp));
		if (count($tmp) >= 3) {
			//读字段定义到数组中
			$fd[] = array_slice($tmp, 0, 3);
		} else if (count($tmp) < 3) {
			//不足出错
			//print_r($tmp);
			//die("字段对应关系错误：".$csv." !" );
		}
		//list($fn,$dfn,$m) = split(",", fgets($fp));
	}
	fclose($fp);

	//生成SQL字符串
	$sql0 = "INSERT INTO $tname(";
	foreach ($fd as $k) {
		//跳过空字段名
		if ( trim($k[0]) <> "" && trim($k[1]) <> "" && trim($k[2]) <> "" ) {
			$sql0 = $sql0.$k[0].",";
		}
	}
	$sql0 = trim($sql0,",").") VALUES (";
    //echo $sql0; exit;

	//打开DBF文件和SQL记录文件
    $dbp = @dbase_open($dbf, 0) or die("Error opening $dbf");
    $records = @dbase_numrecords($dbp);
    if ($records == 0) {
        //DBF没有记录
        dbase_close($dbp);
        return array(0, 0);
        //die("Error reading DBF's number of fields");
    }
    $fields = dbase_get_header_info($dbp);
    $fp = fopen($tname.".SQL", "a");

    try {
        //连接ORACLE数据库 需创建ODBC ORACLE连接：ORA11 
        $dbh = new PDO('odbc:ORA11','gjcy','gjcygc', array(PDO_ATTR_PERSISTENT=>true));
        $dbh->beginTransaction();

		$x = 0; //记录数
		$cc = 0; //出错记录数
        for($x = 1; $x <= $records; $x++) {
            //按字段名或按字段序号读字段值
            if ($dftype == "#") {
                //echo "按字段序号读取DBF记录...\r\n";
                $record = dbase_get_record($dbp, $x);
            } else {
                //echo "按字段名称读取DBF记录...\r\n";
                $record = dbase_get_record_with_names($dbp, $x);
            }
            $sql = $sql0;
            foreach ($fd as $k) {
                //跳过空字段名
                if ( trim($k[0]) <> "" && trim($k[1]) <> "" && trim($k[2]) <> "" ) {
                    $ftype = strtoupper(trim($k[2]));
                    //DBF字段位置若为*，则为计算字段
                    if (trim($k[1]) == "*") {
                        if ($ftype == "*") {
                            //字段类型为*取序号
                            $sql = $sql.($x+$start).",";
                        } else if ($ftype == "D3") {
                            //保存DBF文件的年月
                            $sql = $sql."to_date('".substr($dbf,4,6)."01','YYYYMMDD'),";
                        } else if ($ftype == "D3C") {
                            //保存DBF文件的年月
                            $sql = $sql."'".substr($dbf,4,6)."',";
                        }
                    } else if (trim($k[1]) == "=") {
                        //DBF字段位置若为=，则为缺省值
                        $sql = $sql.trim($k[2]).",";
                    } else {
                        $df = strtoupper(trim($k[1]));
                        $sql = $sql.getValue($dbf, $fields, $record, $df, $ftype).",";
                    }
                }
            }
            $sql = trim($sql, ",").")"; //去掉最后一个逗号，加SQL结束
            if ($debug == 1) {
                //调试模式：输出SQL和出错信息
                echo $sql ."\r\n"; $dbh->exec($sql) or die(print_r($dbh->errorInfo(), true));
            } else if (!$dbh->exec($sql)) {
                //执行模式：出错SQL写入文件
                fwrite($fp, $sql."\r\n");
                $cc++;
            }
        }

        $dbh->commit();
        $dbh = null;

    } catch(Exception $e) {
        $dbh->rollBack();
        echo "Failed: ".$e->getMessage();
    }

    fclose($fp);
    dbase_close($dbp);
    return array(($x-1), $cc);
}

// main()

//检查命令行参数
//print_r($argv);
if (count($argv) < 3) {
	echo "命令格式：PHP DBF2ORA.PHP <字段关系文件名> <年份> [清空] [调试] \r\n";
	exit;
}

$ffile = $argv[1];
$fp = fopen($ffile, "r") or die("打开字段定义文件出错：".$ffile." !");
if (!feof($fp)) {
	//分析首行
	$ff = explode(",", fgets($fp));
	if (count($ff) >= 3) {
		$dname = trim($ff[1]); //DBF前缀
		$tname = trim($ff[0]); //ORACLE表名
		$ftype = trim($ff[2]); //DBF字段类型
	} else die("\r\n字段对应关系文件错误：".$ffile." !\r\n");
} else die("\r\n字段对应关系不能为空：".$ffile." !\r\n");
//echo $dname.$ftype.$tname; exit;

//历史数据开始年份
$dbfYear = $argv[2];
if (preg_match("/^(19\d{2}|200\d{1})$/", $dbfYear)) {
	if ($dbfYear < "1980" || $dbfYear > "2009") {
		die("\r\n历史数据开始年份无效：".$dbfYear."\r\n");
	}
}
else die("\r\n历史数据开始年份错误：".$dbfYear."\r\n");

//是否清空表
if (isset($argv[3]) && $argv[3] == 1) {
	echo "\r\n清空 ORACLE 数据表：$tname \r\n";
	clearTable($tname); //清空目的表
	echo "\r\n";
}

//是否调试模式
$debug = (isset($argv[4]) && $argv[4] == 1) ? 1 : 0;

$xr = 0;
$ccr = 0;
$totalTime = 0;
for ($y = $dbfYear; $y <= 2009; $y++)
{
	for ($m = 1; $m <= 12; $m++)
	{
		$dbf = $dname.$y.str_pad($m,2,"0",STR_PAD_LEFT).".dbf";
		//echo $dname; exit;
        if (file_exists($dbf)) {
            $x = 0;
            $begin = microtime(TRUE);
            list($x,$cc) = dbf2ora($ffile, $dbf, $tname, $ftype, $xr, $debug);
            $xr = $xr + $x;
            $ccr = $ccr + $cc;
            $end = microtime(TRUE); 
            $time = round($end - $begin);
            $totalTime = $totalTime + $time;
            echo "迁移 ".$dbf." ".$x." 执行了 ".$time." 秒。\r\n";
        }
	}
}

echo "\r\n迁移记录：".($xr)." 笔。\r\n";
echo "累计时间：".round($totalTime / 60, 2)." 分钟。\r\n";
echo "错误记录：".($ccr)." 笔。\r\n记录文件：".$tname.".SQL\r\n";
