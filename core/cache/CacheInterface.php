<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 09/10/2017
 * Time: 22:05
 */
declare(strict_types=1);


namespace inframe\core\cache;

/**
 * Interface CacheInterface 缓存接口
 * @package inframe\core\cache
 */
interface CacheInterface
{
    /**
     * 设置缓存
     * @param string $id 缓存键
     * @param mixed $data 缓存的值
     * @param int $ttl 缓存时间,秒计
     * @return mixed
     */
    public function set($id, $data, $ttl = IN_ONE_HOUR);

    /**
     * 获取缓存
     * @param string $id 缓存键
     * @param mixed $replace 缓存值不存在时的替代值,默认为null
     * @return mixed
     */
    public function get($id, $replace = null);

    /**
     * 删除缓存
     * @param string $id 缓存键
     * @return mixed 返回删除的缓存值
     */
    public function clean($id);

    /**
     * 删除全部缓存
     * @return void
     */
    public function cleanAll();

    /**
     * 获取上一次错误
     * @return string
     */
    public function getLastError();
}