<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 10/10/2017
 * Time: 20:49
 */
declare(strict_types=1);


namespace inframe\library;


use inframe\database\Dao;
use inframe\InException;

class DatatableX
{

    private $draw = '';
    private $order = '';
    private $search = '';
    private $start = '';
    private $length = '';
    /**
     * @var Dao
     */
    private $dao = null;

    /**
     * DatatablesX constructor.
     * @param Dao $dao
     */
    public function __construct(Dao $dao)
    {
        $this->draw = $this->getRequest('draw', '', true);
        $this->order = $this->getRequest('order', '', false);
        $this->search = $this->getRequest('search', '', true);
        $this->start = $this->getRequest('start', 0);
        $this->length = $this->getRequest('length', 10);
        $this->dao = $dao;
    }

    private function getRequest($name, $replace = '', $throw = false)
    {
        if (!isset($_REQUEST[$name])) {
            if ($throw) {
                throw new InException("require '{$name}'!");
            } else {
                return $replace;
            }
        }
        return $_REQUEST[$name];
    }

    /**
     * @var string 查询字段
     */
    private $queryFields = '*';

    /**
     * @param array|string
     * @return $this
     */
    public function setQueryFields($fields)
    {
        $this->queryFields = is_array($fields) ? implode(',', $fields) : $fields;
        return $this;
    }

    /**
     * @var array 搜寻字段
     */
    private $searchFields = [];

    /**
     * 设置搜寻字段
     * @param string|array $fields
     * @return $this
     */
    public function setSearchFields($fields)
    {
        $this->searchFields = is_string($fields) ? explode(',', $fields) : $fields;
        return $this;
    }

    /**
     * @var string 查询SQL
     */
    private $querySQL = '';
    private $countSQL = null;
    private $whereSQL = '';
    private $defaultOrder = '';

    /**
     * @param string $sql
     * @return $this
     */
    public function setQuerySQL(string $sql)
    {
        $this->querySQL = $sql;
        return $this;
    }

    /**
     * 用于提高count时的效率
     * @param string $sql
     * @return $this
     */
    public function setCountSQL(string $sql)
    {
        $this->countSQL = $sql;
        return $this;
    }

    /**
     * @param string $where
     * @return $this
     */
    public function setWhereSQL(string $where)
    {
        $this->whereSQL = $where;
        return $this;
    }

    /**
     * @var array 排序的顺序应该与显示列的顺序相同
     */
    private $orderMap = [];

    /**
     * 设置排序
     * @param string|array $fields
     * @return $this
     */
    public function setOrderFields($fields)
    {
        $this->orderMap = is_string($fields) ? explode(',', $fields) : $fields;
        return $this;
    }

    /**
     * 设置默认的排序
     * @param string $order
     * @return $this
     */
    public function setDefaultOrder(string $order)
    {
        $this->defaultOrder = $order;
        return $this;
    }


    public function fetch(): array
    {
        $order = &$this->order;
        /** @var array $search */
        $search = &$this->search;
        $start = &$this->start;
        $length = &$this->length;

        $order_column = $order[0]['column'] ?? null;//那一列排序，从0开始
        $order_dir = $order[0]['dir'] ?? null;//ase desc 升序或者降序

        //拼接排序sql
        if (isset($order_column)) {
            $i = intval($order_column);
            //按照列排序
            $orderSql = isset($this->orderMap[$i]) ? " ORDER BY {$this->orderMap[$i]} {$order_dir}" : '';
        } else {
            $orderSql = $this->defaultOrder ? ' ORDER BY ' . $this->defaultOrder : '';
        }

        $error = 0;

        //分页
        $limitSql = '';
        $limitFlag = isset($_GET['start']) && $length != -1;
        if ($limitFlag) {
            $limitSql = ' LIMIT ' . intval($start) . ', ' . intval($length);
        }

        // 过滤特殊字符，仅仅允许空格、字符、数字、双引号
        $search = $search['value'] ?? '';
        $search and $search = preg_replace('/[\"\']/', '%', $search);

        $whereSql = ' WHERE 1 ';
        if ($this->whereSQL) {
            $whereSql = ' WHERE ' . $this->whereSQL;
        }

        if ($search and $this->searchFields) {
            $_whereSql = '';
            foreach ($this->searchFields as $field) {
                $_whereSql .= " {$field} like '%{$search}%' or";
            }
            $_whereSql = rtrim($_whereSql, 'ro');
            $whereSql .= " and ( $_whereSql )";
        }

        //定义查询数据总记录数sql
        $sumSql = str_replace('{{fields}}', ' count(*) as s ', $this->countSQL ?? $this->querySQL);
        $sumSql .= $whereSql;
        $result = $this->dao->query($sumSql);
        if (empty($result)) {
            $recordsTotal = 0;
            $error = 1;
        } else {
            $recordsTotal = intval($result[0]['s']);
        }

        //query data
        $totalResultSql = str_replace('{{fields}}', $this->queryFields, $this->querySQL);
        $totalResultSql .= " {$whereSql} {$orderSql} {$limitSql}; ";

        $infos = $this->dao->query($totalResultSql);
        if (false === $infos) {
            $error = 1;
            $infos = [];
        }

        return [
            'error' => $error,
            "draw" => intval($this->draw),
            "recordsTotal" => intval($recordsTotal),
            "recordsFiltered" => intval($recordsTotal),
            "data" => $infos
        ];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->fetch());
    }

}