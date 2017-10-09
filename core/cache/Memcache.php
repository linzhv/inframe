<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 09/10/2017
 * Time: 22:06
 */
declare(strict_types=1);


namespace inframe\core\cache;


use inframe\Component;

class Memcache extends Component
{

    /**
     * @var M
     */
    private $handler = null;

    protected $_config = [
        'ext' => 0, # 选取扩展
        'key' => 'thisisprivatekeyofdefault',
        'servers' => [
            [
                'host' => '127.0.0.1',
                'port' => 11211,
                'weight' => 1,
            ]
        ],
        # 超时设置
        'timeout' => 1,
    ];
    protected $key = '';

    protected $isMemcached = true;

    /**
     * Memcached constructor.
     * @param array $config
     * @throws BadConfigException 配置错误
     * @throws ExtensionRequiredException 缺少相应扩展时抛出
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->key = IN_DEBUG_MODE_ON ? '' : sha1($this->_config['key']);
        if (!extension_loaded('memcache')) throw new ExtensionRequiredException('memcache');
        $this->handler = new M();
        if (empty($servers = $this->_config['servers'])) {
            throw new BadConfigException($this->_config, 'require servers at least one');
        }

        foreach ($servers as $server) {
            $host = isset($server['host']) ? $server['host'] : '127.0.0.1';
            $port = isset($server['port']) ? $server['port'] : 11211;
            $weight = isset($server['weight']) ? $server['weight'] : 1;
            // Third parameter is persistence and defaults to TRUE.
            $this->handler->addServer($host, $port, true, $weight, $this->_config['timeout']);
        }
    }

    /**
     * 设置缓存
     * @param string $id 缓存ID
     * @param mixed $data 缓存数据
     * @param int $ttl 缓存期,默认1小时
     * @return bool
     */
    public function set($id, $data, $ttl = IN_ONE_HOUR)
    {
        $id = $this->key . $id;
        $data = serialize($data);
        return $this->handler->set($id, $data, 0, $ttl);
    }

    /**
     * Set a new expiration on an item
     * @param string|int $id
     * @param int $ttl
     * @return bool
     */
    public function touch($id, $ttl = IN_ONE_HOUR)
    {
        $id = $this->key . $id;
        # memcache没有touch功能
        $data = $this->handler->get($id);
        return $this->handler->set($id, $data, 0, $ttl);
    }

    /**
     * 判断文件是否存在
     * @param string $id 缓存ID
     * @return bool
     */
    public function has($id)
    {
        return false !== $this->handler->get($this->key . $id);
    }

    /**
     * 获取缓存
     * @param string $id 缓存ID
     * @param mixed $replace 缓存不存在时默认返回的值,默认为null
     * @return mixed
     */
    public function get($id, $replace = null)
    {
        $data = $this->handler->get($this->key . $id);
        return false === $data ? $replace : unserialize($data);
    }

    /**
     * 清除缓存
     * @param string $id 缓存ID
     * @return bool
     */
    public function clean($id)
    {
        return $this->handler->delete($this->key . $id);
    }

    /**
     * 清空全部缓存
     * @deprecated
     * @return bool
     */
    public function cleanAll()
    {
        return $this->handler->flush();
    }


    /**
     * Class destructor
     * Closes the connection to Memcache(d) if present.
     * @return    void
     */
    public function __destruct()
    {
        $this->handler->close();
    }

    /**
     * 魔术转移到handler上
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        return call_user_func_array([$this->handler, $name], $arguments);
    }

}