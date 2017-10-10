<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 2017/10/10
 * Time: 8:58
 */
declare(strict_types=1);


namespace inframe\core;

use Closure;
use inframe\Component;

/**
 * Class Cache
 *
 * @method mixed  set(string $id, mixed $data, int $ttl = IN_ONE_HOUR) 设置缓存
 * @method mixed  clean(string $id) 删除缓存
 * @method void  cleanAll() 删除全部缓存
 *
 * @method Cache getInstance(int $index = 0, array $params = []) static
 *
 * @package inframe\core
 */
class Cache extends Component
{
    protected $_config = [
        IN_ADAPTER_CLASS => [
            'redis' => 'inframe\\core\\cache\\Redis',
            'file' => 'inframe\\core\\cache\\File',
            'memcached' => 'inframe\\core\\cache\\Memcached',
        ],
        IN_ADAPTER_CONFIG => [],
    ];

    /**
     * 获取缓存
     * @param string $name
     * @param Closure|mixed $replace 如果是一个闭包，则值不存在时获取并设置缓存
     * @param int $expire Closure返回值的缓存期
     * @return mixed
     */
    public function get($name, $replace = null, $expire = IN_ONE_HOUR)
    {
        if ($replace instanceof Closure) {
            $value = $this->_adapter->get($name, null);
            if (null === $value) {
                $this->_adapter->set($name, $value = $replace(), $expire);
            }
        } else {
            $value = $this->_adapter->get($name, $replace);
        }
        return $value;
    }

}