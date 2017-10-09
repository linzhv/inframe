<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 09/10/2017
 * Time: 21:50
 */
declare(strict_types=1);


namespace inframe\database\adapter;


class SQLServer extends DaoAdapter
{
    public function compile(array $components)
    {
        // TODO: Implement compile() method.
    }

    public function backup($table, $withdata = true)
    {
        // TODO: Implement backup() method.
    }

    public function escape($field)
    {
        return "[$field]";
    }

    public function getFields($tableName)
    {
        list($tableName) = explode(' ', $tableName);
        $result = $this->query("SELECT   column_name,   data_type,   column_default,   is_nullable
        FROM    information_schema.tables AS t
        JOIN    information_schema.columns AS c
        ON  t.table_catalog = c.table_catalog
        AND t.table_schema  = c.table_schema
        AND t.table_name    = c.table_name
        WHERE   t.table_name = '$tableName'");
        $info = array();
        if ($result)
            foreach ($result as $key => $val) {
                $info[$val['column_name']] = array(
                    'name' => $val['column_name'],
                    'type' => $val['data_type'],
                    'notnull' => (bool)($val['is_nullable'] === ''), // not null is empty, null is yes
                    'default' => $val['column_default'],
                    'primary' => false,
                    'autoinc' => false,
                );
            }
        return $info;
    }

    public function getTables($dbName = null)
    {
        $result = $this->query("SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_TYPE = 'BASE TABLE'
            ");
        $info = array();
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    }


    public function buildDSN(array $config)
    {
        $dsn = 'sqlsrv:Database=' . $config['database'] . ';Server=' . $config['hostname'];
        if (!empty($config['hostport'])) {
            $dsn .= ',' . $config['hostport'];
        }
        return $dsn;
    }

}