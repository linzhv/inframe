<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 09/10/2017
 * Time: 21:55
 */
declare(strict_types=1);


namespace inframe\database;

use inframe\throws\database\QueryException;
use inframe\throws\database\RecordNotFoundException;
use inframe\throws\DatabaseException;
use inframe\core\Log;

/**
 * Class Model
 *
 * @method bool beginTransaction()
 * @method bool commit() commit current transaction
 * @method bool rollback() rollback current transaction
 * @method bool inTransaction()  check if is in a transaction
 * @method int lastInsertId($name = null) get auto-inc id of last insert record
 *
 * @method string escape($field)
 * @method string compile(array $components)
 *
 * @method array|string getLastSql(bool $all = false) 获取上一次查询的sql语句
 * @method array|null getLastParams(bool $all = false) 获取上一次查询的输入参数
 *
 * @package inframe\database
 */
abstract class Model
{
    /**
     * 连接符号
     */
    CONST CONNECT_AND = ' AND ';
    CONST CONNECT_OR = ' OR ';
    CONST CONNECT_COMMA = ' , ';

    /**
     * 运算符
     */
    CONST OPERATOR_EQUAL = ' = ';
    CONST OPERATOR_NOTEQUAL = ' != ';
    CONST OPERATOR_LIKE = ' LIKE ';
    CONST OPERATOR_NOTLIKE = ' NOT LIKE ';
    CONST OPERATOR_IN = ' IN ';
    CONST OPERATOR_NOTIN = ' NOT IN ';


    const ENGINE_INNODB = 'InnoDB';
    const ENGINE_MYISAM = 'MyISAM';

    /**
     * @var int 默认的DAO index
     */
    protected $index = 0;

    /**
     * 数据访问对象
     * 每个模型都对应一个数据库访问对象
     * @var Dao
     */
    protected $_dao = null;

    /**
     * @var string 当前的数据库访问对象对应的数据表的名称
     */
    protected $tablename = null;

    /**
     * @var array all fields this table hold(should not include primary key and auto-update fields like timestamp which change on update )
     */
    protected $fields = null;
    /**
     * @var string|array 主键名称,如果是array则为复合主键
     */
    protected $pk = 'id';

    /**
     * 最近错误信息
     * 可以通过设置 $this->error 来设置错误信息
     * 访问错误信息可以通过 $this->getError();来获取错误信息，该方法获取后会清空error属性（即连续两次调用$this->getError()前次可以获取对应的错误信息，后一次一定获取的是null）
     * @var string
     */
    protected $error = '';

    /**
     * @var int 主键值
     */
    public $id = null;
    /**
     * @var array id对应的数据
     */
    protected $data = [];

    public function clear()
    {
        $this->id = null;
        $this->data = [];
        return $this;
    }

    /**
     * set table prefix
     * it's useful to set a base model to change the default prefix to fit you
     * requirement in your application
     * @return string
     */
    protected function tablePrefix()
    {
        return '';
    }

    /**
     * set $this->tablename in force
     * @return string
     */
    abstract protected function tableName();

    /**
     * 获取主键名称
     * @access public
     * @return array|string
     */
    final public function getPk()
    {
        return $this->pk;
    }

    /**
     * 表引擎
     * @return string
     */
    protected function tableEngine()
    {
        return self::ENGINE_INNODB;
    }

    /**
     * 获取表的名称
     * @return string
     */
    final public function getTable()
    {
        return $this->tablePrefix() . $this->tableName();
    }

    public function __toString()
    {
        return json_encode($this->data);
    }

    /**
     * 刷新数据
     * @return $this
     * @throws RecordNotFoundException id未指定时刷新将抛出异常
     */
    public function reload()
    {
        if ($this->id) {
            $this->data = $this->find($this->id);
        } else {
            throw new RecordNotFoundException('id not specialized');
        }
        return $this;
    }

    public function data()
    {
        return $this->data;
    }

    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * 保存数据
     * 当主键id被指定了之后（构造时指定了参数ID），save操作将会执行数据的修改操作；否则将添加数据
     * 如果需要验证信息，可以在调用parent::save()之前验证数据是否合法
     * @param bool $justInsert 是否是插入操作，为true时只进行插入操作
     * @return bool 返回是否成功
     * @throws DatabaseException
     */
    public function save($justInsert = false)
    {
        try {
            if (!$justInsert and $this->id) {
                unset($this->data[$this->pk]);
                $this->update($this->data, [$this->pk => $this->id]);
            } else {
                $this->{$this->pk} = null;
                unset($this->data[$this->pk]);
                $this->insert($this->data);
                $this->id = $this->lastInsertId();
            }
            $this->reload();
            return true;
        } catch (\Throwable $e) {
            throw new DatabaseException($e->getMessage());
        }
    }

    /**
     * 获取全部数据
     * @param bool $reload 是否刷新
     * @return array
     */
    public function all($reload = false)
    {
        $reload and $this->reload();
        return $this->data;
    }

    public function remove()
    {
        if (isset($this->id)) {
            $this->reload();
            Log::getLogger('database')->info([$this->data, $this->id]);
            return $this->delete([$this->pk => $this->id]) > 0;
        } else {
            return false;
        }
    }

    /**
     * 获取上一次调用的错误信息
     * 返回错误信息后会清空错误标志位
     * @return string
     */
    public function error()
    {
        //检查是否设置了error
        if (!$this->error) {
            $error = $this->getDao()->getError();
            $this->error = isset($error) ? $error : '';
        }
        //每次获取error之后清空操作
        $error = $this->error;
        $this->error = '';
        return $error;
    }

    /**
     * Model constructor.
     * 单参数为非null时就指定了该表的数据库和字段,来对制定的表进行操作
     * Model constructor.
     * @param int|string $pk
     * @param mixed $index 数据库配置的主键,通过设置该参数可以指定模型使用的是哪个数据库
     * @throws RecordNotFoundException 记录不存在
     */
    public function __construct($pk = 0, $index = null)
    {
        if (!$this->_dao) {
            if (gettype($index) === 'object') {
                //dao instance
                $this->_dao = $index;
            } else {
                isset($index) or $index = $this->index;
                $this->_dao = Dao::getInstance($index);
            }
        }

        if ($pk) {
            if (!($data = $this->find($pk))) throw new RecordNotFoundException("ID '{$pk}' not found ");
            $this->data = $data;
            $this->id = $this->data[$this->pk];
        }

        $tablename = $this->tableName();
        if (false !== strpos($tablename, '{{')) {
            $this->_replaceTablePrefix($tablename);
        } else {
            $tablename = $this->tablePrefix() . $this->tableName();
        }
        $this->reset([
            'table' => $tablename,
        ]);
    }

    /**
     * 获取DAO对象
     * @return Dao
     */
    public function getDao()
    {
        if (!$this->_dao) {
            $this->_dao = Dao::getInstance();
        }
        return $this->_dao;
    }

    /**
     * 执行EXEC类型的SQL并返回结果
     * @param string $sql 查询SQL
     * @param array|null $inputs 输入参数
     * @return false|int
     */
    public function exec($sql, array $inputs = null)
    {
        $res = $this->getDao()->exec($sql, $inputs);

        $this->reset();
        return $res;
    }

    /**
     * 执行返回结果集合的SQL并返回结果集合
     * @param string $sql 查询SQL
     * @param array|null $inputs 输入参数
     * @return array
     * @throws QueryException
     */
    public function query($sql, array $inputs = null)
    {
        $res = $this->getDao()->query($sql, $inputs);
        $this->reset();
        return $res;
    }


    /**
     * 调用不存在的方法时 转至 dao对象上调用
     * 需要注意的是，访问了禁止访问的方法时将返回false
     * @param string $name 方法名称
     * @param array $args 方法参数
     * @return false|mixed
     */
    public function __call($name, $args)
    {
        return call_user_func_array([$this->getDao(), $name], $args);
    }

    /**
     * @var array components of sql,ref. $this->reset()
     */
    private $_components = [];
    /**
     * input parameters for bind
     * categoory by field and where
     * @var array
     */
    private $_bindParam = [];

    /**
     * 获取模型实例
     * @param string|int $index
     * @return Model 返回模型实例
     */
    public static function getInstance($index = null)
    {
        static $instances = [];
        $clsnm = static::class;
        $key = $index . '-' . static::class;
        if (!isset($instances[$key])) {
            $instances[$key] = new $clsnm($index);
        }
        return $instances[$key];
    }

    /**
     * 重置CURD参数
     * @param array|null $originOption 初始化时使用的参数
     * @return Model
     */
    protected function reset(array $originOption = null)
    {
        static $origin = [
            //查询
            'distinct' => false,
            'fields' => ' * ',//操作的字段,最终将转化成字符串类型.(可以转换的格式为['fieldname'=>'value'])
            'table' => null,//操作的数据表名称
            /** @var array */
            'join' => null,
            'where' => null,//操作的where信息
            'group' => null,
            'order' => null,
            'having' => null,
            'limit' => null,
            'offset' => null,
        ];
        $originOption and $origin = array_merge($origin, $originOption);

        $this->_components = $origin;
        $this->table($this->getTable());
        $this->_bindParam = [];
        return $this;
    }


    /********************************************** linked-style method call - NODE **************************************************************************************************/
    /**
     * set distinct
     * @param bool $dist
     * @return Model
     */
    public function distinct($dist = true)
    {
        $this->_components['distinct'] = $dist;
        return $this;
    }

    /**
     * set fields for select/unselect or update/insert
     * the fields will conver to string before end this method
     * @param array|string $fields fields of select or unselect
     * @return Model
     */
    public function fields($fields)
    {
        if (true === $fields) {
            // 设置field用于查询
            $this->_components['fields'] = ' * ';
        } else {
            if (is_array($fields)) {
                // 设置field字段用于select查询和update/insert
                if (is_numeric(key($fields))) {
                    array_walk($fields, function (&$field) {
                        $field = $this->getDao()->escape(trim($field));
                    });
                    $this->_components['fields'] = implode(',', $fields);
                } else {
                    $keys = array_keys($fields);
                    array_walk($keys, function (&$field) {
                        $field = $this->getDao()->escape($field);
                    });

                    $this->_components['fields'] = implode(',', $keys);
                    $this->_bindParam['fields'] = array_values($fields);
                }
            } else {
                $this->_components['fields'] = $fields;
            }
        }
        return $this;
    }

    /**
     * 设置当前要操作的数据表
     * @param $tablename
     * @return Model
     */
    public function table($tablename)
    {
        $this->_replaceTablePrefix($tablename);
        $this->_components['table'] = $tablename;
        return $this;
    }

    /**
     * set alias for current table
     * @param string $alias
     * @return Model
     */
    public function alias($alias)
    {
        $this->_components['table'] = "{$this->_components['table']} {$alias}";
        return $this;
    }

    /**
     * YII style
     * replace tablename placeholder with tablename with prefix
     * It used for tablename format and join format
     *
     * where the table stand is "FROM" and "JOIN"
     *
     *  <code>
     *      //code below performs low
     *      preg_replace('/\{\{([\d\w_]+)\}\}/',"{$this->tablePrefix()}$1",$tablename);
     *  </code>
     * @param string $tablename table name without prefix
     * @return void
     */
    protected function _replaceTablePrefix(&$tablename)
    {
        if (strpos($tablename, '{{') !== false) {
            $tablename = str_replace(['{{', '}}'], [$this->tablePrefix(), ''], $tablename);
        }
    }

    /**
     * 只针对mysql有效
     * @param int $limit
     * @param int $offset
     * @return Model
     */
    public function limit($limit, $offset = 0)
    {
        $this->_components['limit'] = intval($limit);
        $this->_components['offset'] = intval($offset);
        return $this;
    }

    /**
     * @param string $group
     * @return Model
     */
    public function group($group)
    {
        $this->_components['group'] = "GROUP BY {$group} ";
        return $this;
    }

    /**
     * 设置当前要操作的数据的排列顺序
     * @param string $order
     * @return Model
     */
    public function order($order)
    {
        $this->_components['order'] = "ORDER BY {$order}";
        return $this;
    }

    /**
     * 在 SQL 中增加 HAVING 子句原因是，WHERE 关键字无法与合计函数一起使用。
     * @param string $having
     * @return Model
     */
    public function having($having)
    {
        $this->_components['having'] = "HAVING {$having}";
        return $this;
    }


    /**
     * 片段翻译(片段转化)
     * <note>
     *      片段匹配准则:
     *      $map == array(
     *           //第一种情况,连接符号一定是'='//
     *          'key' => $val,
     *          'key' => array($val,$operator,true),
     *
     *          //第二种情况，数组键，数组值//    -- 现在保留为复杂and和or连接 --
     *          //array('key','val','like|=',true),//参数4的值为true时表示对key进行[]转义
     *          //array(array(array(...),'and/or'),array(array(...),'and/or'),...) //此时数组内部的连接形式
     *
     *          //第三种情况，字符键，数组值//
     *          'assignSql' => array(':bindSQLSegment',value)//与第一种情况第二子目相区分的是参数一以':' 开头
     *      );
     * </note>
     * @param array $segments 片段数组
     * @param string $connect 表示是否使用and作为连接符，false时为,
     * @return array
     */
    public function parseSegments($segments, $connect = self::CONNECT_AND)
    {
        $sql = '';
        $bind = [];
        //元素连接
        foreach ($segments as $field => $segment) {
            if (is_numeric($field)) {
                //第二中情况,符合形式组成
                $result = $this->parseSegment($segment[0], $segment[1]);
                $sql .= " {$result[0]} {$connect}";
                $bind = array_merge($bind, $result[1]);
            } elseif (is_array($segment) and strpos($segment[0], ':') === 0) {
                //第三种情况,过于复杂而选择由用户自定义
                $sql .= " {$field} {$connect}";
                $bind[$segment[0]] = $segment[1];
            } else {
                //第一种情况
                $operator = self::OPERATOR_EQUAL;
                if (is_array($segment)) {
                    $operator = isset($segment[1]) ? $segment[1] : self::OPERATOR_EQUAL;
                    $segment = $segment[0];//value
                }
                $rst = $this->parseSegment($field, $segment, $operator);//第一种情况一定是'='的情况
                if (is_array($rst)) {
                    $sql .= " {$rst[0]} {$connect}";
                    $bind = array_merge($bind, $rst[1]);
                }
            }
        }
        return [
            substr($sql, 0, strlen($sql) - strlen($connect)),
            $bind,
        ];
    }

    /**
     * 综合字段绑定的方法
     * <code>
     *      $operator = '='
     *          $fieldName = :$fieldName
     *          :$fieldName => trim($fieldValue)
     *
     *      $operator = 'like'
     *          $fieldName = :$fieldName
     *          :$fieldName => dowithbinstr($fieldValue)
     *
     *      $operator = 'in|not_in'
     *          $fieldName in|not_in array(...explode(...,$fieldValue)...)
     * </code>
     * @param string $fieldName 字段名称
     * @param string|array $fieldValue 字段值
     * @param string $operator 操作符
     * @_param bool $escape 是否对字段名称进行转义,MSSQL中使用[],默认为false
     * @return array
     * @throws DatabaseException 使用错误的操作符时抛出
     */
    private function parseSegment($fieldName, $fieldValue, $operator = self::OPERATOR_EQUAL)
    {
        //该库开启的清空下
        if (false !== strpos($fieldName, '.')) {
            //field has assign to one table
            $arr = explode('.', $fieldName);
            $holder = ':' . array_pop($arr);
        } else {
            $holder = ":{$fieldName}";
        }

        if (strpos($fieldName, '.') === false) {
            $sql = $this->getDao()->escape($fieldName);
        } else {
            $sql = $fieldName;
        }

        $input = [];

        switch ($operator) {
            case self::OPERATOR_EQUAL:
            case self::OPERATOR_NOTEQUAL:
            case self::OPERATOR_LIKE:
            case self::OPERATOR_NOTLIKE:
                if ($fieldValue === null) {
                    //it will set value to null in database,not zero in convention
                    $sql .= " {$operator} NULL ";
                } else {
                    $sql .= " {$operator} {$holder} ";
                    $input[$holder] = $fieldValue;
                }
                break;
            case self::OPERATOR_IN:
            case self::OPERATOR_NOTIN:
                if (is_array($fieldValue)) $fieldValue = "'" . implode("','", $fieldValue) . "'";
                $sql .= " {$operator} ({$fieldValue}) ";
                break;
            default:
                throw new DatabaseException("lite:operator '$operator' is invalid");
        }
        return [$sql, $input];
    }

    /**
     * set where condituib
     * @param array|string $where
     * @return Model
     */
    public function where($where)
    {
        if (is_array($where)) {
            $where = $this->parseSegments($where, self::CONNECT_AND);
            $this->_bindParam['where'] = $where[1];
            $where = $where[0];
        }
        $this->_components['where'] = "WHERE {$where}";
        return $this;
    }

    const JOIN = 0;
    const INNER_JOIN = 1;
    const LEFT_OUTER_JOIN = 2;

    public function join($join, $type = null)
    {
        switch ($type) {
            case self::INNER_JOIN:
                $join = " INNER JOIN {$join} ";
                break;
            case self::LEFT_OUTER_JOIN:
                $join = " LEFT OUTER JOIN {$join} ";
                break;
            case self::JOIN:
                $join = " JOIN {$join} ";
            case null:
            default:
                //keep its origin pattern
        }
        $this->_replaceTablePrefix($join);
        if (empty($this->_components['join'])) {
            $this->_components['join'] = [$join];
        } else {
            $this->_components['join'][] = $join;
        }
        return $this;
    }

    /**
     * @param string $join statement without 'INNER JOIN'
     * @return Model
     */
    public function innerJoin($join)
    {
        return $this->join($join, self::INNER_JOIN);
    }

    /**
     * @param string $join statement without 'LEFT OUTER JOIN'
     * @return Model
     */
    public function leftOuterJoin($join)
    {
        return $this->join($join, self::LEFT_OUTER_JOIN);
    }
    /********************************************** link style method call - ENDNODE ***********************************/


    /**
     * insert an record to database
     * <code>
     *      $fields ==> array(
     *          'fieldName' => 'fieldValue',
     *      );
     * </code>
     *
     * format ：
     * ①INSERT INTO [tablename] VALUES (value1, value2 ,....)
     * ②INSERT INTO table_name (column1, column2,...) VALUES (value1, value2 ,....)
     *
     * @param array $data
     * @return int return the record id which inserted (useful for inc)
     */
    public function insert(array $data = null)
    {
        $tablename = $this->_getTable();
        null === $data and $data = $this->_bindParam['fields'];

        //validate
        if (!$data or !is_array($data)) {
            $this->error = 'insert deny';
            return false;
        }
        //fields test
        $keys = array_keys($data);
        $context = $this;
        array_walk($keys, function (&$field) use ($context) {
            $field = $context->_dao->escape($field);
        });//对字段进行转义
        $fields = implode(',', $keys);
        $holder = rtrim(str_repeat('?,', count($keys)), ',');
        return $this->exec("INSERT INTO {$tablename} ( {$fields} ) VALUES ( {$holder} );", array_values($data));
    }


    /**
     * delete record by where condition
     * eg. where must be set
     * @param array $where fields of where or string
     * @return int
     * @throws DatabaseException 未指定where时抛出异常
     */
    public function delete(array $where = null)
    {
        $tablename = $this->_getTable();

        null === $where and $where = $this->_components['where'];

        //parse where and input
        if (is_array($where)) {
            list($where, $inputs) = $this->parseSegments($where, self::CONNECT_AND);
        }

        if (empty($where)) {
            throw new DatabaseException('lite:where should not be empty');
        }

        return $this->exec("DELETE FROM {$tablename} WHERE {$where};", empty($inputs) ? null : $inputs);
    }

    /**
     * update record
     * note:it will return 0 if data to update is same to origin data
     * @param string|array $fields
     * @param string|array $where
     * @return int it will return the num of rows affected
     * @throws DatabaseException
     */
    public function update($fields = null, $where = null)
    {
        $tablename = $this->_getTable();
        //check
        if (!$fields and !($fields = $this->_components['fields'])) {
            $this->error = 'Fields should not be empty!';
            return false;
        }
        if (!$where and !($where = $this->_components['where'])) {
            $this->error = 'Where should not be empty!';
            return false;
        }

        //format
        if (is_array($fields)) {
            list($fields, $inputs) = $this->parseSegments($fields, self::CONNECT_COMMA);
        } else {
            $inputs = null;
        }

        if (is_array($where)) {
            list($where, $winput) = $this->parseSegments($where, self::CONNECT_AND);
            if (empty($inputs)) {
                $inputs = $winput;
            } else {
                $inputs = array_merge($inputs, $winput);
            }
        }
        $res = $this->exec("UPDATE {$tablename} SET {$fields} WHERE {$where};", $inputs);
        return $res;
    }

//---------------------------------------------------------------------------------------------------------------

    /**
     * 从数据库中获取指定条件的数据对象
     * @param array|null|string $options 如果是字符串是代表查询这张表中的所有数据并直接返回
     * @return array|false 返回数组或者false(发生了错误)
     */
    public function select($options = null)
    {
        // component for combine
        $components = [
            'distinct' => false,
            'fields' => ' * ',//操作的字段,最终将转化成字符串类型.(可以转换的格式为['fieldname'=>'value'])
            'table' => null,//操作的数据表名称
            'join' => null,
            'where' => null,//操作的where信息
            'group' => null,
            'order' => null,
            'having' => null,
            'limit' => null,
            'offset' => null,
        ];
        $this->_components and $components = array_merge($components, $this->_components);
        $options and $components = array_merge($components, $options);

        $sql = $this->getDao()->compile($components);
        //onlu where has condition bind
        $bind = empty($this->_bindParam['where']) ? null : $this->_bindParam['where'];
        $list = $this->query($sql, $bind);
        return $list;
    }

    /**
     * find a column from table of this table
     * 查询一条数据，依据逐渐，如果数据不存在时返回false
     * @param int|string|array|null $keys
     * @param bool $getall 是否获取全部数据
     * @return false|array it will return false if an error occur,empty array on target not exist ,infomation array if record exist
     */
    public function find($keys = null, $getall = false)
    {
        if (null === $keys) {
            $result = $this->select(null);
            if (false === $result) {
                return false;
            } elseif (!$result) {
                return [];
            } else {
                return array_shift($result);
            }
        } else {
            if (!is_array($keys)) {
                $keys = [
                    $this->pk => $keys,
                ];
            }
            $result = $this->where($keys)->select(null);
        }
        if (false === $result) return false;//发生错误时才但会false
        if ($getall) {
            return $result ? $result : [];
        } else {
            return empty($result[0]) ? [] : $result[0];
        }
    }

    /**
     * 获取查询选项中满足条件的记录数目
     * @return false|int 返回表中的数据的条数,发生了错误将不会返回数据
     */
    public function count()
    {
        $this->_components['fields'] = ' count(*) as c';
        $result = $this->select();
        return isset($result[0]['c']) ? intval($result[0]['c']) : false;
    }
//---------------------------------------------------------------------------------------------------------------

    /**
     * 获取数据表名称
     * @return string
     */
    private function _getTable()
    {
        if (empty($this->_components['table'])) {
            $tablename = $this->tablename;
        } else {
            $tablename = $this->_components['table'];
        }
        return $this->getDao()->escape($tablename);
    }


}