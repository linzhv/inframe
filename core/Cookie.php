<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 2017/10/10
 * Time: 8:59
 */
declare(strict_types=1);


namespace inframe\core;


class Cookie
{
    /**
     * @var array
     */
    private static $config = [
        // cookie 名称前缀
        'prefix' => '',
        // cookie 保存时间
        'expire' => 0,
        // cookie 保存路径
        'path' => '/',
        // cookie 有效域名
        'domain' => '',
        //  cookie 启用安全传输
        'secure' => false,
        // httponly设置
        'httponly' => '',
    ];

    /**
     * 是否完成初始化
     * @var bool
     */
    private static $_inited = false;

    private static function initializationize(array $config = null)
    {
        $config and self::$config = array_merge(self::$config, $config);
        empty(self::$config['httponly']) or ini_set('session.cookie_httponly', '1');
        self::$_inited = true;
    }


    /**
     * 判断Cookie数据
     * @param string $name cookie名称
     * @return bool
     */
    public static function has($name)
    {
        self::$_inited or self::initializationize();
        return isset($_COOKIE[$name]);
    }

    /**
     * 设置或者获取cookie作用域（前缀）
     * @param string $prefix
     * @return string
     */
    public static function prefix($prefix = null)
    {
        self::$_inited or self::initializationize();
        if (null === $prefix) {
            return self::$config['prefix'];
        } else {
            return self::$config['prefix'] = $prefix;
        }
    }

    /**
     * Cookie 设置、获取、删除
     * @param string $name cookie名称
     * @param string|int $value cookie值
     * @param int|array $option 配置参数,如果是int表示cookie有效期,如果是array,则表示setcookie参数数组
     *
     * @return void
     */
    public static function set($name, $value = '', $option = null)
    {
        self::$_inited or self::initializationize();
        // 参数设置(会覆盖黙认设置)
        if (isset($option)) {
            if (is_numeric($option)) {
                $option = ['expire' => $option];
            }
            self::$config = array_merge(self::$config, array_change_key_case($option));
        }
        // 设置cookie
        if (is_array($value)) {
            array_walk($value, function (&$val) {
                empty($val) or $val = urlencode($val);
            });
            $value = 'lite:' . json_encode($value);
        }
        $expire = !empty(self::$config['expire']) ? IN_NOW + intval(self::$config['expire']) : 0;
        setcookie($name, $value, $expire,
            (string)self::$config['path'],
            (string)self::$config['domain'],
            (bool)self::$config['secure'],
            (bool)self::$config['httponly']);
        $_COOKIE[$name] = $value;
    }

    /**
     * Cookie获取
     * @param string $name cookie名称
     * @return string|null cookie不存在时返回null
     */
    public static function get($name)
    {
        self::$_inited or self::initializationize();
        if (isset($_COOKIE[$name])) {
            $value = $_COOKIE[$name];
            if (0 === strpos($value, 'lite:')) {
                $value = substr($value, 6);
                $value = json_decode($value, true);
                array_walk($value, function (&$val) {
                    empty($val) or $val = urldecode($val);
                });
            }
            return $value;
        } else {
            return null;
        }
    }

    /**
     * Cookie删除
     * @param string $name cookie名称
     * @return void
     */
    public static function delete($name)
    {
        self::$_inited or self::initializationize();
        setcookie($name, '', IN_NOW - 3600,
            (string)self::$config['path'],
            (string)self::$config['domain'],
            (bool)self::$config['secure'],
            (bool)self::$config['httponly']);
        // 删除指定cookie
        unset($_COOKIE[$name]);
    }

    /**
     * Cookie清空
     * @return void
     */
    public static function clear()
    {
        self::$_inited or self::initializationize();
        // 清除指定前缀的所有cookie
        if ($_COOKIE) {
            // 如果前缀为空字符串将不作处理直接返回
            foreach ($_COOKIE as $key => $val) {
                setcookie(
                    $key,
                    '',
                    $_SERVER['REQUEST_TIME'] - 3600,
                    (string)self::$config['path'],
                    (string)self::$config['domain'],
                    (bool)self::$config['secure'],
                    (bool)self::$config['httponly']
                );
                unset($_COOKIE[$key]);
            }
        }
    }
}
