<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 09/10/2017
 * Time: 21:47
 */
declare(strict_types=1);


namespace inframe\database\adapter;

use PDO;
use inframe\throws\database\QueryException;

class MySQL extends DaoAdapter
{

    protected $config = [
        IN_DB_NAME => '',//选择的数据库
        IN_DB_USER => '',
        IN_DB_PASSWD => '',
        IN_DB_HOST => '127.0.0.1',
        IN_DB_PORT => '3306',
        IN_DB_CHARSET => 'UTF8',
        IN_DB_DSN => null,//默认先检查差DSN是否正确,直接写dsn而不设置其他的参数可以提高效率，也可以避免潜在的bug
    ];

    public function escape($field)
    {
        return (strpos($field, '`') !== false) ? $field : "`{$field}`";
    }

    /**
     * 根据配置创建DSN
     * @param array $config 数据库连接配置
     * @return string
     */
    public function buildDSN(array $config)
    {
        $dsn = "mysql:host={$config[IN_DB_HOST]}";
        if (isset($config[IN_DB_NAME])) {
            $dsn .= ";dbname={$config[IN_DB_NAME]}";
        }
        if (!empty($config[IN_DB_PORT])) {
            $dsn .= ';port=' . $config[IN_DB_PORT];
        }
        if (!empty($config['socket'])) {
            $dsn .= ';unix_socket=' . $config['socket'];
        }
        if (!empty($config[IN_DB_CHARSET])) {
            //为兼容各版本PHP,用两种方式设置编码
            $dsn .= ';charset=' . $config[IN_DB_CHARSET];//$this->options[\PDO::MYSQL_ATTR_INIT_COMMAND]    =   'SET NAMES '.$config['charset'];
        }
        return $dsn;
    }


    /**
     * 取得数据表的字段信息
     * @access public
     * @param string $tableName 数据表名称
     * @return array
     */
    public function getFields($tableName)
    {
        list($tableName) = explode(' ', $tableName);
        if (strpos($tableName, '.')) {
            list($dbName, $tableName) = explode('.', $tableName);
            $sql = 'SHOW COLUMNS FROM `' . $dbName . '`.`' . $tableName . '`';
        } else {
            $sql = 'SHOW COLUMNS FROM `' . $tableName . '`';
        }

        $result = $this->query($sql);
        $info = array();
        if ($result) {
            foreach ($result as $key => $val) {
                if (\PDO::CASE_LOWER != $this->getAttribute(\PDO::ATTR_CASE)) {
                    $val = array_change_key_case($val, CASE_LOWER);
                }
                $info[$val['field']] = array(
                    'name' => $val['field'],
                    'type' => $val['type'],
                    'notnull' => (bool)($val['null'] === ''), // not null is empty, null is yes
                    'default' => $val['default'],
                    'primary' => (strtolower($val['key']) == 'pri'),
                    'autoinc' => (strtolower($val['extra']) == 'auto_increment'),
                );
            }
        }
        return $info;
    }

    /**
     * @param string $dbname
     * @return int
     */
    public function createSchema(string $dbname)
    {
        return $this->exec('CREATE SCHEMA `' . $dbname . '` DEFAULT CHARACTER SET utf8 ;');
    }

    /**
     * 取得数据库的表信息
     * @access public
     * @param string $dbName
     * @return array
     */
    public function getTables($dbName = null)
    {
        $sql = empty($dbName) ? 'SHOW TABLES ;' : "SHOW TABLES FROM {$dbName};";
        $result = $this->query($sql);
        $info = array();
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    }

    /**
     * SELECT %DISTINCT% %FIELD% FROM %TABLE% %FORCE% %JOIN% %WHERE% %GROUP% %HAVING% %ORDER% %LIMIT% %UNION% %LOCK% %COMMENT%;
     *
     * Avalable SQL:
     * SELECT DISTINCT
     *  a.aid,COUNT(is_show) as c
     * from
     *  blg_article a
     * INNER JOIN blg_article_pic ap on ap.aid = a.aid
     * INNER JOIN blg_article_tag bat on bat.aid = a.aid
     * WHERE a.author = 'bjy' and a.aid = 17
     * GROUP BY a.aid
     * HAVING COUNT(is_show) > 0
     * ORDER BY a.aid
     * LIMIT 0,1
     *
     * @param array $components
     * @return mixed
     */
    public function compile(array $components)
    {
        //------------------------- join ------------------------------------------------//
        if (!empty($components['join']) and is_array($components['join'])) {
            $j = '';
            foreach ($components['join'] as $join) {
                $j .= "\n{$join}\n";
            }
            $components['join'] = $j;
        }

        //------------------------- limit ------------------------------------------------//
        if (isset($components['limit'])) {
            $l = '';
            if (empty($components['offset'])) {
                $l .= " LIMIT {$components['limit']} ";
            } else {
                $l .= " LIMIT {$components['offset']},{$components['limit']} ";//                    $sql .= ' LIMIT '.$this->_options['offset'].' , '.$this->_options['limit'];
            }
            $components['limit'] = $l;
        }

        return str_replace(
            array('%TABLE%', '%DISTINCT%', '%FIELD%', '%JOIN%', '%WHERE%', '%GROUP%', '%HAVING%', '%ORDER%', '%LIMIT%',
//                '%UNION%', '%LOCK%', '%COMMENT%', '%FORCE%'
            ),
            array(
                $components['table'],
                !empty($components['distinct']) ? 'DISTINCT' : '',
                !empty($components['fields']) ? $components['fields'] : ' * ',
                !empty($components['join']) ? $components['join'] : '',
                !empty($components['where']) ? $components['where'] : '',
                !empty($components['group']) ? $components['group'] : '',
                !empty($components['having']) ? $components['having'] : '',
                !empty($components['order']) ? $components['order'] : '',
                !empty($components['limit']) ? $components['limit'] : '',
//                !empty($components['union']) ? $components['union'] : '',
//                isset($components['lock']) ? $components['lock'] : '',
//                !empty($components['comment']) ? $components['comment'] : '',
//                !empty($components['force']) ? $components['force'] : '',
            ), 'SELECT %DISTINCT% %FIELD% FROM %TABLE% %JOIN% %WHERE% %GROUP% %HAVING% %ORDER% %LIMIT%;');
    }

    /**
     * 备份表
     * @param string $table 表名
     * @param bool $withdata 同时备份数据
     * @return bool
     * @throws QueryException
     */
    public function backup($table, $withdata = true)
    {

        $struct_sql = "\n";
        $datatime = date('Y-m-d H:i:s');
        //备份表结构
        $result = $this->query("SHOW CREATE TABLE `{$table}`")->fetchAll();

        $create_sql = isset($result[0]['Create Table']) ? $result[0]['Create Table'] : '';
        if (!$create_sql) {
            throw new QueryException((string)$this->errorInfo());
        }
        $create_sql = preg_replace('/AUTO_INCREMENT=\d+/', '', $create_sql, 1);

        $struct_sql .= "-- -----------------------------\n";
        $struct_sql .= "-- Table structure for `{$table}` at {$datatime} \n";
        $struct_sql .= "-- -----------------------------\nSET FOREIGN_KEY_CHECKS=0;\n";
        $struct_sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $struct_sql .= trim($create_sql) . ";\n\n";

        if (!$this->_write($table, $struct_sql)) {
            return false;
        }

        if ($withdata) {
            //数据总数
            $result = $this->query("SELECT COUNT(*) AS c FROM `{$table}`")->fetch();
            //备份表数据
            if (isset($result['c']) ? $result['c'] : 0) {
                //写入数据注释
                $data_sql = "-- -----------------------------\n";
                $data_sql .= "-- Records of table `{$table}`\n";
                $data_sql .= "-- -----------------------------\n";

                //备份数据记录
                $stmt = $this->query("SELECT * FROM `{$table}`;");
                $c = 0;
                while ($row = $stmt->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT)) {
                    $fields = '';
                    foreach ($row as &$item) {
                        if ($item === null) {
                            $fields .= 'NULL,';
                        } else {
                            $fields .= '\'' . str_replace(["\r", "\n", '\''], ['\r', '\n', '"'], addslashes($item)) . '\',';
                        }
                    }
                    $fields = rtrim($fields, ',');
                    $data_sql .= "INSERT INTO `{$table}` VALUES ( {$fields} ); \n";

                    if ($c++ > 1000) {
                        if (!$this->_write($table, $data_sql, true)) {
                            return false;
                        }
                        $data_sql = '';
                        $c = 0;
                    }
                }
                $stmt->closeCursor();
                unset($stmt);
                $this->_write($table, $data_sql, true);
            }
        }
        return true;
    }
}