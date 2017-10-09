<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 09/10/2017
 * Time: 21:40
 */
declare(strict_types=1);


namespace inframe\database\adapter;

use inframe\core\File;
use inframe\throws\storage\FileWriteException;
use PDO;
use PDOException;
use inframe\throws\database\ConnectException;

/**
 * Class DaoAdapter DAO适配类
 * @package inframe\database\adapter
 */
abstract class DaoAdapter extends PDO
{
    /**
     * 配置
     * @var array
     */
    protected $config = [];
    /**
     * select结构
     * @var string
     */
    protected static $select = 'SELECT %DISTINCT% %FIELD% FROM %TABLE% %FORCE% %JOIN% %WHERE% %GROUP% %HAVING% %ORDER% %LIMIT% %UNION% %LOCK% %COMMENT%';

    /**
     * DaoAdapter constructor.
     * @param array $config
     * @throws ConnectException
     */
    public function __construct(array $config = [])
    {
        $config and $this->config = array_merge($this->config, $config);
        $dsn = empty($config[IN_DB_DSN]) ? $this->buildDSN($config) : $config[IN_DB_DSN];
        try {
            parent::__construct($dsn, $config[IN_DB_USER], $config[IN_DB_PASSWD], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,//默认异常模式
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,//结果集返回形式
            ]);
        } catch (PDOException $e) {
            throw new ConnectException($e->getMessage());
        }
    }

    protected function _write($table, $sql, $append = false)
    {
        $file = IN_PATH_DATA . 'backup/' . $table . '/' . IN_NOW . '.sql';
        File::makeParentDir($file);
        if (false === ($res = file_put_contents($file, $sql, $append ? FILE_APPEND : 0))) {
            throw new FileWriteException($file);
        }
        return $res > 0;
    }

    /**
     * compile component to executable sql statement
     * @param array $components
     * @return string
     */
    abstract public function compile(array $components);

    /**
     *  transfer word(may be keywork) if not transferred
     * @param string $field
     * @return string
     */
    abstract public function escape($field);

    /**
     * 取得数据表的字段信息
     * @access public
     * @param string $tableName 数据表名称
     * @return array
     */
    abstract public function getFields($tableName);

    /**
     * 取得数据库的表信息
     * @access public
     * @param string $dbName
     * @return array
     */
    abstract public function getTables($dbName = null);

    /**
     * 备份表和数据
     * @param string $table
     * @param bool $withdata
     * @return bool
     */
    abstract public function backup($table, $withdata = true);

    /**
     * 根据配置创建DSN
     * @param array $config 数据库连接配置
     * @return string
     */
    abstract public function buildDSN(array $config);
}