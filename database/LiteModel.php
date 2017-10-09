<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 09/10/2017
 * Time: 21:53
 */
declare(strict_types=1);


namespace inframe\database;

use inframe\throws\database\SqliteException;
use PDO;

/**
 * Class LiteModel SQLite表模型
 * @package inframe\database
 */
abstract class LiteModel extends PDO
{

    /**
     * 表名
     * @var string
     */
    protected $tablename = '';
    /**
     * 主健名
     * @var string
     */
    protected $pk = 'ID';
    /**
     * 字段列表
     * @var array
     */
    protected $fields = [];
    /**
     * 记录数据
     * @var array
     */
    protected $data = [];

    /**
     * LiteModel constructor.
     * @param string $dbfile sqlite数据库路径
     */
    public function __construct($dbfile)
    {
        parent::__construct('sqlite:' . $dbfile);
        $this->tablename = $this->getTableName();
        $this->pk = $this->getPrimaryKey();
        $this->fields = $this->getPrimaryKey();
    }

    /**
     * 获取表名称
     * @return string
     */
    abstract public function getTableName();

    /**
     * 返回表字段
     * @return array
     */
    abstract public function getFields();

    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        return 'ID';
    }

    /**
     * @param string $name
     * @return mixed|string
     */
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : '';
    }

    /**
     * @param string $name
     * @param string|int $value
     * @throws SqliteException
     */
    public function __set($name, $value)
    {
        if (key_exists($name, $this->data)) {
            $this->data[$name] = $value;
        } else {
            throw new SqliteException("field '{$name}' not exist");
        }
    }

    /**
     * 查询数据
     * @param array|string $where where字句,默认选择全部
     * @return array 返回查询结果
     */
    public function select($where = '')
    {
        if (is_array($where) and $where) $where = $this->_parseWhere($where);
        if (!$where) $where = '1';# 为空时选择全部
        $sql = "select * from {$this->tablename} where {$where} ;";
        return $this->query($sql);
    }

    /**
     * 查询所有相关记录
     * @param string|int $pk 主键值
     * @return array
     */
    public function find($pk)
    {
        return $this->select(" {$this->pk} = $pk ");
    }

    /**
     * 添加数据
     * @param array|null $fields
     * @return int|false 添加失败时返回false，否则返回添加的记录数目
     * @throws SqliteException
     */
    public function create(array $fields = null)
    {
        null === $fields and $fields = $this->data;
        if (!$fields) throw new SqliteException('empty data!');
        $holder = "(" . implode(",", array_keys($fields)) . ")";
        $fields = "('" . implode("','", $fields) . "')";
        $sql = "INSERT INTO {$this->tablename} $holder VALUES $fields ;";
        return $this->exec($sql);
    }

    /**
     * @param string|int $pkey
     * @return int
     */
    public function delete($pkey = null)
    {
        if (!$pkey) {
            $where = " {$this->pk} = '{$pkey}' ";
        } else {
            $where = $this->_parseWhere($this->data);
        }
        $sql = "DELETE FROM {$this->tablename} WHERE {$where} LIMIT 1;";
        return $this->exec($sql);
    }

    private function _parseWhere(array $fields)
    {
        $where = '';
        foreach ($fields as $field => $value) {
            $where .= " {$field} = '{$value}' and";
        }
        return substr($where, 0, strlen($where) - 3);
    }

    private function _parseFields(array $fields)
    {
        $sql = '';
        foreach ($fields as $key => $val) {
            $sql .= " $key = '$val',";
        }
        return rtrim($sql, ',');
    }

    /**
     * 修改数据
     * @param array $fields 修改的字段
     * @param array|int|string $where
     * @return int
     */
    public function update(array $fields, $where)
    {
        $sql = "update {$this->tablename} set" . $this->_parseFields($fields);
        if (is_array($where)) {
            $sql .= ' where ' . $this->_parseWhere($where);
        } else {
            $sql .= " where {$this->pk} = '$where';";
        }

        return $this->exec($sql);
    }

//---------------------------------------------------------------------------------------//

    /**
     * 查询
     * @param string $statement
     * @param int $mode
     * @param null $arg3
     * @param array $ctorargs
     * @return array
     * @throws SqliteException
     */
    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = array())
    {
        $statement = parent::query($statement);
        if (false === $statement) {
            throw new SqliteException(var_export($this->errorInfo(), true));
        }
        return $statement->fetchAll();
    }

    /**
     * 执行
     * @param string $statement
     * @return int
     * @throws SqliteException
     */
    public function exec($statement)
    {
        $res = parent::exec($statement);
        if (false === $res) {
            throw new SqliteException(var_export($this->errorInfo(), true));
        }
        return $res;
    }

    public function getError()
    {
        $info = $this->errorInfo();
        return var_export($info, true);
    }
}