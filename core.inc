<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 06/10/2017
 * Time: 20:21
 */
declare(strict_types=1);

# 定义
namespace {

    const IN_VERSION = '0.8.0';
    # 外部可定义应用名称,默认为空
    defined('IN_APP_NAME') or define('IN_APP_NAME', '');
    # 定义debug模式是否开启,默认开启
    defined('IN_DEBUG_MODE_ON') or define('IN_DEBUG_MODE_ON', true);
    # 固定常量
    define('IN_NOW_MICRO', (float)(isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true)));
    define('IN_NOW', (int)(isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time()));
    define('IN_MEMORY', memory_get_usage());

    const IN_ONE_HOUR = 3600;
    const IN_ONE_DAY = 86400;

    # 布尔常量
    define('IN_IS_WIN', false !== stripos(PHP_OS, 'WIN'));
    const IN_IS_CLI = PHP_SAPI === 'cli';

    # 目录常量
    define('IN_PATH_BASE', dirname(__DIR__) . '/'); # 根目录
    const IN_PATH_FRAMEWORK = __DIR__ . '/';    # 框架目录
    const IN_PATH_APP = IN_PATH_BASE . IN_APP_NAME . '/'; # 应用目录
    const IN_PATH_DATA = IN_PATH_APP . 'data/'; # 数据目录
    const IN_PATH_VENDOR = IN_PATH_APP . 'vendor/'; # 第三方目录
    const IN_PATH_WWW = IN_PATH_APP . 'www/';# 应用模块目录
    const IN_PATH_CONFIG = IN_PATH_APP . 'config/'; #中配置目录
    const IN_PATH_RUNTIME = IN_PATH_APP . 'runtime/';# 运行时目录
    const IN_PATH_PUBLIC = IN_PATH_APP . 'public/'; # 公开目录,存放入口文件和其他资源文件
    const IN_PATH_UPLOAD = IN_PATH_APP . 'upload/'; # 上传文件

    # 语言
    const IN_LANG_ZH_CN = 'zh-CN';
    const IN_LANG_ZH_TW = 'zh-TW';
    const IN_LANG_EN_US = 'en-US';
    const IN_LANG_ZH = 'zh';
    const IN_LANG_EN = 'en';

    # 编码
    const IN_CHARSET_UTF8 = 'UTF-8';
    const IN_CHARSET_GBK = 'GBK';
    const IN_CHARSET_ASCII = 'ASCII';
    const IN_CHARSET_GB2312 = 'GB2312';
    const IN_CHARSET_LATIN1 = 'ISO-8859-1';# 欧洲部分国家使用,Latin1是ISO-8859-1的别名(西欧语言)

    # 数据类型
    const IN_TYPE_BOOL = 'boolean';
    const IN_TYPE_INT = 'integer';
    const IN_TYPE_FLOAT = 'double';//double ,  float
    const IN_TYPE_STR = 'string';
    const IN_TYPE_ARRAY = 'array';
    const IN_TYPE_OBJ = 'object';
    const IN_TYPE_RESOURCE = 'resource';
    const IN_TYPE_NULL = 'NULL';
    const IN_TYPE_UNKNOWN = 'unknown type';


    # storage 方案
    const IN_STORAGE_REDIS = 'redis';
    const IN_STORAGE_FILE = 'file';
    const IN_STORAGE_MEMCACHED = 'memcached';
    const IN_STORAGE_SQLITE = 'sqlite';

    if (!IN_IS_CLI) {
        # web模式
        error_reporting(IN_DEBUG_MODE_ON ? E_ALL : 0);
        define('IN_IS_AJAX', isset($_SERVER['HTTP_X_REQUESTED_WITH']) and $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        define('IN_REQUEST_METHOD', strtoupper($_SERVER['REQUEST_METHOD']));//'GET', 'HEAD'，'POST'，'PUT' ...
        define('IN_IS_POST', IN_REQUEST_METHOD === 'POST');
        $dir = dirname($_SERVER['SCRIPT_NAME']);
        define('IN_IS_HTTPS',
            (
                (
                    isset($_SERVER['HTTPS']) and
                    (
                        ($_SERVER['HTTPS'] == 'on') or ($_SERVER['HTTPS'] == '1')
                    )
                ) or
                $_SERVER['SERVER_PORT'] == 443
            )
            or
            (
                !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ||
                !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on'
            )
        );
        # httpd服务标识符 由服务器IP和端口组成,用于集群环境区别服务器
        define('IN_SERVER_ID', (isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '') . ':' . (isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : ''));
        # host路径
        define('IN_HOST_URL', (IN_IS_HTTPS ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']);
        # 公用路径,nginx作负载均衡时会变成特殊值,需要手动设定
        defined('IN_PUBLIC_URL') or define('IN_PUBLIC_URL', strlen($dir) === 1 ? IN_HOST_URL : IN_HOST_URL . (IN_IS_WIN ? str_replace('\\', '/', $dir) : $dir));//默认index
    }

}
# 核心类定义
namespace inframe {


    class InException extends \Exception
    {
        public function __construct($message = "")
        {
            parent::__construct($message);
        }
    }

    /**
     * Class RoutePacket 路由包
     *
     * 路由解析结果是一个路由包，也可以通过路由包创建URL
     *
     * @package inframe
     */
    final class RoutePacket
    {
        /**
         * 模块名称，多级模块用'／'分隔
         * @var string
         */
        private $modules;
        /**
         * 控制器名称
         * @var string
         */
        private $controller;
        /**
         * 操作名称
         * @var string
         */
        private $action;
        /**
         * 参数列表
         * @var array
         */
        private $params;

        /**
         * RoutePacket constructor.
         *
         * 路由包的属性在实例化后是只读的
         *
         * @param string $modules
         * @param string $controller
         * @param string $action
         * @param array $params
         */
        public function __construct(string $modules, string $controller, string $action, array $params = [])
        {
            $this->modules = $modules;
            $this->controller = $controller;
            $this->action = $action;
            $this->params = $params;
        }

        /**
         * 获取模块序列
         * @return string
         */
        public function getModules(): string
        {
            return $this->modules;
        }

        /**
         * 获取控制器序列
         * @return string
         */
        public function getController(): string
        {
            return $this->controller;
        }

        /**
         * @return string
         */
        public function getAction(): string
        {
            return $this->action;
        }

        /**
         * @return array
         */
        public function getParams(): array
        {
            return $this->params;
        }

    }

    /**
     * Interface RouteInterface 路由接口
     * @package inframe
     */
    interface RouteInterface
    {
        /**
         * 解析URL
         * @param string $url
         * @return RoutePacket
         */
        public function parse(string $url): RoutePacket;

        /**
         * 创建URL
         * @param RoutePacket $routePacket
         * @return string
         */
        public function build(RoutePacket $routePacket): string;

    }

    /**
     * Class Component 组件类
     *
     * 组件类的一个特征就是可以自动完成配置
     *  它的配置有三个来源：
     *      1.类静态配置(CSC)     : 每一个继承类可以将内置的配置写在 getStaticConfig 中，因为是写死到类实现代码里的，所以可以称之为"类静态配置"
     *      2.应用静态配置(ASC)   : 组件类可以针对每个应用设置不同的配置，这些配置统一写死到应用目录下的"config/app.php"，所以可以称为"应用静态配置"
     *      3.动态配置(DC)       : 组件在构造时可以传递配置数组，这些配置是程序运行时产生（可能是从文件／数据库中读取，可能是计算产生）而非事先写死到代码里，
     *                           所以可以称之为"动态配置"
     *  各类配置都有自己的优先级，优先级越高的配置会逐条按照配置项覆盖前者，它们的优先级如它们的序号一样：
     *      DC > ASC > CSC
     *
     * 组件的第二个特征是基于驱动的调用
     *  组件在实例化当前实例的时候都可以指定当前配置
     *
     *
     * @package inframe
     */
    abstract class Component
    {

        /**
         * 组件配置
         * @var array
         */
        protected $_config = [];

        /**
         * 上一次调用发生的错误
         * @var string
         */
        protected $_error = '';
        /**
         * 实例名称（作为同类不同实例之间的区别）
         * @var int|string
         */
        protected $_name = '';
        /**
         * 适配器对象
         * @var null|object|\PDO
         */
        protected $_adapter = null;
        /**
         * 适配器名称
         * @var null|string
         */
        protected $_adapterName = null;
        /**
         * 适配器构造参数
         * @var null|array
         */
        protected $_adapterParams = null;

        protected function __construct(array $config = [])
        {
        }

        /**
         * @return array
         */
        protected function getStaticConfig(): array
        {
            return [];
        }

        /**
         * 获取应用配置
         * @return array
         */
        protected function getApplicationConfig(): array
        {
            return [];
        }

        /**
         * 实例,对于构造参数的实例为同一个实例
         * @param int $index
         * @return Component 类实例
         */
        public static function getInstance(int $index = 0): Component
        {
            static $_instances = [];
            if (!isset($_instances[$key = md5(($clsnm = static::class) . '#' . $index)])) {
                $_instances[$key] = new $clsnm($index);
            }
            return $_instances[$key];
        }


        /**
         * 魔术调用转适配器
         * @param string $name 方法名称
         * @param array $arguments 参数名称
         * @return mixed|null 如果目标不存在适配器，则返回null
         */
        public function __call(string $name, array $arguments)
        {
            return $this->_adapter ? call_user_func_array([$this->_adapter, $name], $arguments) : null;
        }

        /**
         * 获取错误信息
         * @return string
         */
        public function getError(): string
        {
            return $this->_error;
        }

    }

}
# 核心对外接口
namespace {
    /**
     * Class InFrame
     *
     * InFrame类对外提供了框架内资源访问的入口
     *
     */
    final class InFrame
    {

        private $_config = [];

        public function __construct(array $config = [])
        {
            $config and $this->initialize($config);
        }

        /**
         * 初始化框架
         * @param array $config
         * @return InFrame
         */
        public function initialize(array $config): InFrame
        {
            $this->_config = array_merge($this->_config, $config);
            return $this;
        }

        /**
         * 注册脚本结束回调函数
         * @param callable $shutdownHandler
         * @return InFrame
         */
        public function onShutdown(callable $shutdownHandler): InFrame
        {

            return $this;
        }

        /**
         * 注册错误发生时回调函数
         * @param callable $errorHandler
         * @return InFrame
         */
        public function onError(callable $errorHandler): InFrame
        {
            return $this;
        }

        /**
         * 注册异常发生时的回调函数
         * @param callable $exceptionHandler
         * @return InFrame
         */
        public function onException(callable $exceptionHandler): InFrame
        {

            return $this;
        }

        /**
         * 开启应用
         * @param callable $router 路由解析回调，默认使用系统自带
         * @return void
         */
        public function start(callable $router = null): void
        {

        }


    }

}