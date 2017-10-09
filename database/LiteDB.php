<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 09/10/2017
 * Time: 21:51
 */
declare(strict_types=1);


namespace inframe\database;

use inframe\throws\storage\FileWriteException;
use SQLiteException;
use PDO;
/**
 *
 * Class LiteDB SQLite数据库文件
 * ps:
 * sqlite所有字段实际上都是字符串(包括int)
 * @package inframe\core\database
 * @package inframe\database
 */
class LiteDB
{
    protected static $sqlite3 = '';

    protected $dbname = '';

    protected $dbfile = '';

    /**
     * LiteDB constructor.
     * @param string $dbname 数据库名称,如果出现'/'则表示绝对路径
     * @param bool $createifnotexist
     * @throws FileWriteFailedException 极少出现这样的清空,为缓存无法写入时出现
     * @throws SqliteException
     */
    public function __construct($dbname, $createifnotexist = false)
    {
        if (!self::$sqlite3) {

            $sqlite = realpath(IN_IS_WIN ? __DIR__ . '/../../bin/sqlite3.exe' : __DIR__ . '/../../bin/sqlite3');
            if (!is_file($sqlite)) {
                throw new SqliteException("engine lost");
            } elseif (!is_executable($sqlite)) {
                if (!chmod($sqlite, 0700)) {
                    throw new SqliteException("engine unexecutable");
                }
            } else {
                self::$sqlite3 = $sqlite;
            }
        }
        $this->dbname = $dbname;
        $this->dbfile = strpos($dbname, '/') !== false ? $dbname : IN_PATH_DATA . 'dblite/' . $dbname . '.db';
        if (!is_file($this->dbfile)) {
            if ($createifnotexist) {
                $this->createTable('stagein');
            } else {
                throw new SqliteException("sqlite [$dbname] not found");
            }
        }
    }

    /**
     * 获取数据库文件路径
     * @return string
     */
    public function getDatabasePath()
    {
        return $this->dbfile;
    }

    /**
     * @param string $tableName
     * @param array $fields 键为字段名称,值为属性,如VARCHAR, NOT NULL等
     * @throws FileWriteException
     */
    public function createTable($tableName, array $fields = ['VAL' => 'VARCHAR'])
    {
        $_fields = '';
        foreach ($fields as $name => $type) {
            $_fields .= "{$name} {$type} ,";
        }
        $_fields = trim($_fields, ',');
        # 数据库文件不存在是创建
        $kvtable = "CREATE TABLE {$tableName} ( ID INT NOT NULL PRIMARY KEY , {$_fields} );";
        //临时SQL文件，用于保存SQL 作为创建的参数
        $sqlfile_temp = IN_PATH_RUNTIME . 'temp/table.create.' . $tableName . microtime(true) . '.sql';

        is_dir($sql_dir = dirname($this->dbfile)) or mkdir($sql_dir, 0700, true);
        is_dir($sqlfile_temp_dir = dirname($sqlfile_temp)) or mkdir($sqlfile_temp_dir, 0700, true);

        if (file_put_contents($sqlfile_temp, $kvtable)) {
            exec(self::$sqlite3 . " {$this->dbfile} < {$sqlfile_temp}");
        } else {
            throw new FileWriteException($sqlfile_temp);
        }
    }

    /**
     * 获取数据库表列表
     * ```php
     *  [
     *      'stagein'   => [
     *          'type'  => 'table',
     *          'name'  => 'stagein',
     *          'tbl_name'  => 'stagein',
     *          'rootpage'  => '2',
     *          'sql'  => 'CREATE TABLE stagein ( ID INT NOT NULL PRIMARY KEY , VAL VARCHAR  )',
     *      ],
     * ];
     * ```
     * ps:
     * - 对于表来说，type 字段永远是 ‘table’，name 字段永远是表的名字
     * - 对于索引，type 等于 ‘index’, name 则是索引的名字，tbl_name 是该索引所属的表的名字
     *
     * @return array
     * @throws SqliteException
     */
    public function getTables()
    {
        # ps:每次需要重新建立连接,否则抛出异常 "database schema has changed"
        $connection = $this->getConnection();
        $list = $connection->query('SELECT * FROM sqlite_master WHERE type = \'table\';');
        if ($list) {
            $list = $list->fetchAll(PDO::FETCH_ASSOC);
        } else {
            throw new SqliteException(var_export($connection->errorInfo(), true));
        }
        $tmp = [];
        foreach ($list as $item) {
            $tmp[$item['name']] = $item;
        }
        return $tmp;
    }

    /**
     * @param $sql
     * @return array
     */
    public function query($sql)
    {
        return $this->getConnection()->query($sql)->fetchAll();
    }

    /**
     * @return PDO
     */
    public function getConnection()
    {
        static $connection = null;
        if (!$connection) {
            $connection = new PDO('sqlite:' . $this->dbfile);
        }
        return $connection;
    }

    public function hasTable($name)
    {
        $tables = $this->getTables();
        return isset($tables[$name]);
    }

    /**
     * @param string $clsnm 类名称
     * @return LiteModel
     */
    public function createLiteModel($clsnm)
    {
        /** @var LiteModel $instance */
        $instance = new $clsnm($this->dbfile);
        return $instance;
    }

}