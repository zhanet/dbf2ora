# dbf2ora

DBF历史数据迁移脚本。


## 系统环境 

Windows + XAMPP + Oracle 


## 准备运行环境

### 安装 XAMPP

下载 Xampp 安装程序，执行安装程序，并将 Xampp 安装到 D:\ 。

设置系统环境变量：将 `D:\XAMPP\PHP` 加入 PATH 。

### 配置 PHP.INI

使用文本编辑器打开 `D:\XAMPP\PHP\PHP.INI` 文件，找到 
```
;extension=php_dbase.dll
```
删除前面的;分号，保存文件退出。

### 配置 ODBC

打开：控制面板 -> 管理工具 -> 数据源(ODBC) 

添加：`Oracle in OraHome90` 点击 “完成”，输入：
```
Data Source Name : ORA11 
TNS Service Name : ORA11 
```
按 [OK] 按钮结束。


## 编制字段关系对照表

使用 .csv 文件定义 Oracle 数据表与对应 DBF 表字段映射关系与转换方式。

### 文件命名

```
表名.csv     (.csv 文件是,逗号分隔的文本文件)
```
如：cyhw.csv 定义 cyhw.dbf 字段转换。

### 文件内容

每字段一行，每行各项内容用,逗号分隔，如下所示：
```
Oracle表字段名, DBF列名, 字段类型和转换方式 
```

### 字段类型和转换方式
```
N    数值型（原样输出）
C    字符串（使用单引号）
C1   处理乱码或半个汉字（使用单引号）
D    日期型（使用 to_date 日期格式：YYYYMMDD）
D1   日期型（使用 to_date 日期格式：YYYYMM 只有年月）
D2   日期型（前面自动加当前年月，使用 to_date 日期格式：YYYYMM）
D3   日期型（使用to_date保存DBF年月）
D3C  日期型（DBF年月）
```

### 重要提示
```
首行第一列：表名 
首行第二列：DBF文件前缀 
首行第三列：DBF字段类型：为 # 表示按字段序号 

ID自增字段DBF列名和类型都为 * 
```

## 执行迁移脚本

### 迁移脚本用法

命令格式：
```
PHP DBF2ORA.PHP <字段定义文件> <开始年份> [清空] [调试] 
```

其中：
```
清空选项：1/0 清空/不清空 
调试选项：1 调试模式 
```

例如：
```
PHP DBF2ORA.PHP cyhw.csv 2009 1 1 
```

### 编制批命令

为简化多表操作，可以将一组迁移写成批命令，例如：
```
@php dbf2ora.php cyhw.csv 2009 1 %1
@php dbf2ora.php cyha.csv 2009 1 %1
...
```

### 执行数据迁移

1. 将字段定义 .csv 文件复制到DBF历史数据文件目录中；
2. 复制 dbf2ora.php 到DBF历史数据文件目录中；
3. 双击 cmdhere.reg 导入注册表；
4. 右击目录选择 CMD...
5. 执行迁移命令。


#### TODO

* 支持更多数据库。
* 支持生成SQL脚本文件。
* 支持字段定义自动生成、GUI工具 ...
