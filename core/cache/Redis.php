<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 2017/10/10
 * Time: 8:56
 */
declare(strict_types=1);


namespace inframe\core\cache;


use inframe\throws\database\ConnectException;
use inframe\throws\PHPExtensionException;
use Redis as R;
use inframe\Component;

/**
 * Class Redis
 * @method Redis getInstance(int $index = 0, array $params = []) static
 * @package inframe\core\cache
 */
class Redis extends Component
{
    /**
     * redis句柄
     * @var R
     */
    protected $handler = null;

    protected $_config = [
        'host' => '127.0.0.1',
        'key' => 'thisisprivatekeyofdefault',
        'password' => NULL,
        'port' => 6379,
        'timeout' => 7.0,
        'database' => 0
    ];
    protected $key = '';

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->key = sha1($this->_config['key']);
        $this->handler();
    }

    /**
     * 获取redis句柄
     * @return R
     * @throws PHPExtensionException
     * @throws ConnectException
     */
    public function handler()
    {
        if (!$this->handler) {
            if (!extension_loaded('redis')) throw new PHPExtensionException('redis');
            $this->handler = new R();
            $error = '';
            try {
                $host = $this->_config['host'];
                $port = $this->_config['port'];
                $timeout = $this->_config['timeout'];
                $password = $this->_config['password'];
                $database = $this->_config['database'];

                if (!$this->handler->connect($host, ($host[0] === '/' ? 0 : $port), $timeout))
                    $error = var_export([$host, $port, $timeout, 'connect failed!'], true);
                if ($password and !$this->handler->auth($password))
                    $error = var_export([$host, $port, $timeout, $password,
                        'authentication failed'], true);
                if ($database and !$this->handler->select($database))
                    $error = var_export([$host, $port, $timeout, $password,
                        $database, 'database select failed'], true);

            } catch (\RedisException $e) {
                $error = var_export([$host, $port, $timeout, $password, $database, $e->getMessage()], true);
            }
            if ($error) throw new ConnectException($error);
        }
        return $this->handler;
    }

    public function __destruct()
    {
        $this->handler and $this->handler->close();
    }


    /**
     * @param string $id
     * @param mixed $data
     * @param int $ttl 过期时间
     * @return bool
     */
    public function set($id, $data, $ttl = IN_ONE_HOUR)
    {
        if (is_resource($data) or !$id) return false;
        return $this->handler->setex(md5($this->key . $id), $ttl, serialize([$data]));
    }


    /**
     * @param string $id
     * @param mixed $replace
     * @return mixed
     */
    public function get($id, $replace = null)
    {
        $data = $this->handler->get(md5($this->key . $id));
        return empty($data) ? $replace : unserialize($data)[0]; # false 或者 []
    }


    /**
     * 判断缓存是否存在
     * @param string $id 缓存ID
     * @return bool
     */
    public function has($id)
    {
        return false !== $this->handler->get(md5($this->key . $id));
    }


    /**
     * 删除指定的键
     * @param string $id
     * @return bool
     */
    public function clean($id)
    {
        return $this->handler->delete(md5($this->key . $id)) === 1;
    }

    /**
     * @deprecated
     * @return bool
     */
    public function cleanAll()
    {
        return $this->handler->flushDB();
    }

}