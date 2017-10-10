<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 2017/10/10
 * Time: 8:54
 */
declare(strict_types=1);


namespace inframe\core\cache;

use inframe\Component;

use inframe\throws\ConfigException;
use inframe\throws\PHPExtensionException;
use Memcached as M;

/**
 * Class Memcached 缓存的Memcache实现
 *
 * Memcached集群采取客户端加服务端的模式,服务端之间不存在数据同步,
 * 分布式是由客户端根据一定的分布式算法将数据存储到指定的Memcached服务端上(前提是客户端知道所有的服务端),
 * 所以Memcached集群实现的是伪分布式,这样确实可以提高一部分性能,但是却无法承担数据丢失风险
 *
 *
 * 使用下面的配置时,必须保证192.168.200.1和192.168.200.100的11211端口都开放,并且绑定IP不限定于127.0.0.1
 * [
 *  'key' => 'thisisprivatekeyofdefault',
 *  'servers' => [
 *      [
 *          'host' => '192.168.200.1',
 *          'port' => 11211,
 *          'weight' => 1,
 *      ],
 *      [
 *          'host' => '192.168.200.100',
 *          'port' => 11211,
 *          'weight' => 1,
 *      ],
 *  ],
 * ]
 *
 * 配置文件/etc/memcached.conf中使用如下的配置
 * ```conf
 * -l 127.0.0.1,192.168.200.1
 * ```
 * 表示远程可以通过127.0.0.1和192.168.200.1两个IP连接到该服务器,开始误以为是允许连接的IP地址 -.-#
 *
 *
 *
 *
 * @method array getStats() 获取服务器池的统计信息
 * @method Memcached getInstance(int $index = 0, array $config = []) static
 *
 * @package inframe\core\cache
 */
class Memcached extends Component
{
    /**
     * @var M
     */
    protected $handler = null;

    protected $_config = [
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

    /**
     * Memcached constructor.
     * @param array $config
     * @throws ConfigException 配置错误
     * @throws PHPExtensionException 缺少相应扩展时抛出
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->key = IN_DEBUG_MODE_ON ? '' : sha1($this->_config['key']);
        if (!extension_loaded('memcached')) throw new PHPExtensionException('memcached');
        $this->handler = new M();
        if (empty($servers = $this->_config['servers'])) {
            throw new ConfigException('require servers at least one');
        }
        $this->handler->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $this->_config['timeout']);

        foreach ($servers as $server) {
            $host = isset($server['host']) ? $server['host'] : '127.0.0.1';
            $port = isset($server['port']) ? $server['port'] : 11211;
            $weight = isset($server['weight']) ? $server['weight'] : 1;
            $this->handler->addServer($host, $port, $weight);
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
        return $this->handler->set($id, $data, $ttl);
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
        return $this->handler->touch($id, $ttl);
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
        $this->handler->quit();
    }

    /**
     * 魔术转移到handler上
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this->handler, $name], $arguments);
    }

}