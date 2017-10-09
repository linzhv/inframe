<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 07/10/2017
 * Time: 17:20
 */
declare(strict_types=1);


namespace inframe\database;

use PDO;
use PDOStatement;
use PDOException;
use inframe\Component;
use inframe\throws\DatabaseException;
use inframe\throws\database\QueryException;
use inframe\throws\database\ExecuteException;


/**
 * Class Dao 数据库访问对象(Database Access Object)
 *
 * 出现错误时抛出DatabaseException异常
 *
 * @method bool beginTransaction()
 *
 * @method string escape($field)
 * @method string compile(array $components)
 *
 * @method bool commit() commit current transaction
 * @method bool rollback() rollback current transaction
 * @method bool inTransaction()  check if is in a transaction
 * @method bool backup($table, bool $withdata = true)
 * @method int lastInsertId($name = null) get auto-inc id of last insert record
 *
 * @package inframe\database
 */
class Dao extends Component
{
    /**
     * @var PDOStatement current PDOStatement object
     */
    protected $_statement = null;

    /**
     * 上一次执行的SQL语句
     * @var array
     */
    protected static $_lastSql = [];
    /**
     * 返回上一次查询的SQL输入参数
     * @var array
     */
    protected static $_lastParams = [];

    /**
     * 链接数据库并获得DAO对象
     * @param string $adapter 驱动(适配器)名称
     * @param array $params 驱动(适配器)参数
     * @return Dao|Component
     */
    public static function connect($adapter, array $params)
    {
        return parent::getInstance(0, [
            IN_ADAPTER_CLASS => [
                0 => $adapter,
            ],
            IN_ADAPTER_CONFIG => [
                0 => $params,
            ],
        ]);
    }

    /**
     * 获取pdo访问对象
     * @return PDO
     */
    public function getPdo()
    {
        return $this->_adapter;
    }

    protected function getStaticConfig(): array
    {
        return [
            IN_ADAPTER_CLASS => [
                'wshore\\core\\database\\MySQL',
            ],
            IN_ADAPTER_CONFIG => [
                [
                    IN_DB_NAME => 'ischema',
                    IN_DB_USER => 'root',
                    IN_DB_PASSWD => '123456',
                    IN_DB_HOST => '127.0.0.1',
                    IN_DB_PORT => 3306,
                    IN_DB_CHARSET => 'UTF8',
                    IN_DB_DSN => null,//默认先检查差DSN是否正确,直接写dsn而不设置其他的参数可以提高效率，也可以避免潜在的bug
                ],
            ],
            ];
    }

    /********************************* 基本的查询功能(发生了错误可以查询返回值是否是false,getError可以获取错误的详细信息(每次调用这些功能前都会清空之前的错误)) ***************************************************************************************/
    /**
     * 简单地查询一段SQL，并且将解析出所有的结果集合
     *
     * @param string $sql 查询的SQL
     * @param array $params 输入参数
     *                          如果输入参数未设置或者为null（显示声明），则直接查询
     *                          如果输入参数为非空数组，则使用PDOStatement对象查询
     * @param bool $returnStatement 是否直接返回statement
     * @return array|\PDOStatement 返回array类型表述查询结果，返回false表示查询出错，可能是数据表不存在等数据库返回的错误信息
     * @throws QueryException
     */
    public function query($sql, array $params = null, $returnStatement = false)
    {
        self::$_lastSql[] = $sql;
        self::$_lastParams[] = $params;
        try {
            if (empty($params)) {
                if ($statement = $this->_adapter->query($sql)) {//query成功时返回PDOStatement对象,否则返回false
                    return $returnStatement ? $statement : $statement->fetchAll(PDO::FETCH_ASSOC);//成功返回
                }
                throw new QueryException(self::fetchPdoError($this->_adapter));
            } else {
                $statement = $this->_adapter->prepare($sql);//可能returnfalse或者抛出错误
                if (!$statement) {
                    throw new QueryException(self::fetchPdoError($this->_adapter));
                } else {
                    if ($statement->execute($params)) {/*execute不会抛出异常*/
                        return $returnStatement ? $statement : $statement->fetchAll(PDO::FETCH_ASSOC);
                    }
                    throw new QueryException(self::fetchPdoStatementError($statement));
                }
            }
        } catch (PDOException $e) {
            throw new QueryException(var_export([
                'error' => $e->getMessage(),
                'sql' => $sql,
                'params' => $params,
            ], true));
        }
    }

    /**
     * 简单地执行Insert、Delete、Update操作
     * @param string $sql 待查询的SQL语句，如果未设置输入参数则需要保证SQL已经被转义
     * @param array|null $params 输入参数,具体参考query方法的参数二
     * @return int 返回受到影响的行数，但是可能不会太可靠，需要用===判断返回值是0还是false
     *                   返回false表示了错误，可以用getError获取错误信息
     * @throws ExecuteException
     */
    public function exec($sql, array $params = null)
    {
        self::$_lastSql[] = $sql;
        self::$_lastParams[] = $params;
        try {
            if (!$params) {
                //调用PDO的查询功能
                if (false !== ($rst = $this->_adapter->exec($sql))) {
                    return (int)$rst;
                }
                throw new ExecuteException(self::fetchPdoError($this->_adapter));
            } else { //调用PDOStatement的查询功能
                $statement = $this->_adapter->prepare($sql);
                if (false === $statement) {
                    throw new ExecuteException(self::fetchPdoError($this->_adapter));
                } else {
                    if (false !== $statement->execute($params)) {
                        return $statement->rowCount();
                    }
                    throw new ExecuteException(self::fetchPdoStatementError($statement));
                }
            }
        } catch (PDOException $e) {
            throw new ExecuteException(var_export([
                'error' => $e->getMessage(),
                'sql' => $sql,
                'params' => $params,
            ], true));
        }
    }

    /********************************* 高级查询功能(支持链式调用相应的错误的处理必须是异常处理,与无法通过getError获取这些错误的详细信息,但可以通过$e->getMessage()获取详细信息) ***************************************************************************************/
    /**
     * 准备一段SQL
     * @param string $sql 查询的SQL，当参数二指定的ID存在，只有在参数一布尔值不为false时，会进行真正地prepare
     * @param array $option prepare方法参数二
     * @return Dao
     * @throws DatabaseException
     */
    public function prepare($sql, array $option = [])
    {
        try {
            $this->_statement = $this->_adapter->prepare($sql, $option);//prepare失败抛出异常后赋值过程结束,$this->_statement可能依旧指向之前的SQLStatement对象（可能不为null）
            $this->_statement or $error = self::fetchPdoError($this->_adapter);
        } catch (PDOException $e) {
            throw new DatabaseException($e->getMessage());
        }
        return $this;
    }

    /**
     * 执行查询功能，返回的结果是bool表示是否执行成功
     * @param array|null $input_parameters
     *                  一个元素个数和将被执行的 SQL 语句中绑定的参数一样多的数组。所有的值作为 PDO::PARAM_STR 对待。
     *                  不能绑定多个值到一个单独的参数,如果在 input_parameters 中存在比 PDO::prepare() 预处理的SQL 指定的多的键名，
     *                  则此语句将会失败并发出一个错误。(这个错误在php 5.2.0版本之前是默认忽略的)
     * @return int 返回受影响的行数
     * @throws DatabaseException
     */
    public function execute(array $input_parameters = null)
    {
        //出错时设置错误信息，注：PDOStatement::execute返回bool类型的结果 参数数目不正确时候会抛出异常"Invalid parameter number"
        if ($this->_statement->execute($input_parameters)) {
            return $this->_statement->rowCount();
        } else {
            throw new DatabaseException(self::fetchPdoStatementError($this->_statement));
        }
    }

    /**
     * 返回一个包含结果集中所有剩余行的数组
     * 此数组的每一行要么是一个列值的数组，要么是属性对应每个列名的一个对象
     * @param int|null $fetch_style
     *          想要返回一个包含结果集中单独一列所有值的数组，需要指定 PDO::FETCH_COLUMN ，
     *          通过指定 column-index 参数获取想要的列。
     *          想要获取结果集中单独一列的唯一值，需要将 PDO::FETCH_COLUMN 和 PDO::FETCH_UNIQUE 按位或。
     *          想要返回一个根据指定列把值分组后的关联数组，需要将 PDO::FETCH_COLUMN 和 PDO::FETCH_GROUP 按位或
     * @param int $fetch_argument
     *                  参数一为PDO::FETCH_COLUMN时，返回指定以0开始索引的列（组合形式如上）
     *                  参数一为PDO::FETCH_CLASS时，返回指定类的实例，映射每行的列到类中对应的属性名
     *                  参数一为PDO::FETCH_FUNC时，将每行的列作为参数传递给指定的函数，并返回调用函数后的结果
     * @param array $constructor_args 参数二为PDO::FETCH_CLASS时，类的构造参数
     * @return array
     */
    public function fetchAll($fetch_style = null, $fetch_argument = null, $constructor_args = null)
    {
        $param = [];
        isset($fetch_style) and $param[0] = $fetch_style;
        isset($fetch_argument) and $param[1] = $fetch_argument;
        isset($constructor_args) and $param[2] = $constructor_args;
        return call_user_func_array(array($this->_statement, 'fetchAll'), $param);
    }

    /**
     * 从结果集中获取下一行
     * @param int $fetch_style
     *              \PDO::FETCH_ASSOC 关联数组
     *              \PDO::FETCH_BOUND 使用PDOStatement::bindColumn()方法时绑定变量
     *              \PDO::FETCH_CLASS 放回该类的新实例，映射结果集中的列名到类中对应的属性名
     *              \PDO::FETCH_OBJ   返回一个属性名对应结果集列名的匿名对象
     * @param int $cursor_orientation 默认使用\PDO::FETCH_ORI_NEXT，还可以是PDO::CURSOR_SCROLL，PDO::FETCH_ORI_ABS，PDO::FETCH_ORI_REL
     * @param int $cursor_offset
     *              参数二设置为PDO::FETCH_ORI_ABS(absolute)时，此值指定结果集中想要获取行的绝对行号
     *              参数二设置为PDO::FETCH_ORI_REL(relative) 时 此值指定想要获取行相对于调用 PDOStatement::fetch() 前游标的位置
     * @return array 此函数（方法）成功时返回的值依赖于提取类型。
     * @throws DatabaseException
     */
    public function fetch($fetch_style = PDO::FETCH_ASSOC, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        $res = $this->_statement->fetch($fetch_style, $cursor_orientation, $cursor_offset);
        if (false === $res) {
            throw new DatabaseException(self::fetchPdoStatementError($this->_statement));
        }
        return $res;
    }

    /**
     * 返回上一个由对应的 PDOStatement 对象执行DELETE、 INSERT、或 UPDATE 语句受影响的行数
     * 如果上一条由相关 PDOStatement 执行的 SQL 语句是一条 SELECT 语句，有些数据可能返回由此语句返回的行数
     * 但这种方式不能保证对所有数据有效，且对于可移植的应用不应依赖于此方式
     * @return int
     */
    public function rowCount()
    {
        return $this->_statement->rowCount();
    }


    /**
     * @param bool $all
     * @return array|string
     */
    final public static function getLastSql($all = false)
    {
        if ($all) {
            return self::$_lastSql;
        } else {
            $last = end(self::$_lastSql);
            return false === $last ? '' : $last;
        }
    }

    /**
     * @param bool $all
     * @return array|null
     */
    final public static function getLastParams($all = false)
    {
        if ($all) {
            return self::$_lastParams;
        } else {
            $last = end(self::$_lastParams);
            return false === $last ? [] : $last;
        }
    }


    /**
     * 获取PDO对象上发生的错误
     * [
     *      0   => SQLSTATE error code (a five characters alphanumeric identifier defined in the ANSI SQL standard).
     *      1   => Driver-specific error code.
     *      2   => Driver-specific error message.
     * ]
     * If the SQLSTATE error code is not set or there is no driver-specific error,
     * the elements following element 0 will be set to NULL .
     * @param \PDO $pdo PDO对象或者继承类的实例
     * @return null|string null表示未发生错误,string表示序列化的错误信息
     */
    public static function fetchPdoError(PDO $pdo)
    {
        $pdoError = $pdo->errorInfo();
        return null !== $pdoError[0] ? "PDO Error:{$pdoError[0]} >>> [{$pdoError[1]}]:[{$pdoError[2]}]" : null;// PDO错误未被设置或者错误未发生,0位的值为null
    }

    /**
     * 获取PDOStatemnent对象上查询时发生的错误
     * 错误代号参照ANSI CODE ps: https://docs.oracle.com/cd/F49540_01/DOC/server.815/a58231/appd.htm
     * @param \PDOStatement $statement 发生了错误的PDOStatement对象
     * @return string|null 错误未发生时返回null
     */
    public static function fetchPdoStatementError(PDOStatement $statement)
    {
        $stmtError = $statement->errorInfo();
        return !empty($stmtError[1]) ? "Error Code:[{$stmtError[0]}]::[{$stmtError[1]}]:[{$stmtError[2]}]" : null;//代号为0时表示错误未发生
    }

}