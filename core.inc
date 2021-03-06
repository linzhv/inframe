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

    const IN_VERSION = '0.7.1';
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

    # 驱动类
    const IN_ADAPTER_CLASS = 'IN_ADAPTER_CLASS';
    const IN_ADAPTER_CONFIG = 'IN_ADAPTER_CONFIG';

    # 数据库
    const IN_DB_HOST = 'host';
    const IN_DB_PORT = 'port';
    const IN_DB_USER = 'user';
    const IN_DB_PASSWD = 'passwd';
    const IN_DB_NAME = 'dbname';
    const IN_DB_CHARSET = 'charset';
    const IN_DB_DSN = 'dsn';//默认先检查差DSN是否正确,直接写dsn而不设置其他的参数可以提高效率，也可以避免潜在的bug

    # 请求参数来源
    const IN_FROM_REQUEST = 0;
    const IN_FROM_GET = 1;
    const IN_FROM_POST = 2;
    const IN_FROM_INPUT = 3;

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

    if (IN_DEBUG_MODE_ON) {
        require __DIR__ . '/inc/dvlp.php';
    } else {
        function dump(...$params)
        {
        }

        function dumpout(...$params)
        {
        }

        class _inframe_dvlp
        {
            public static function status($tag)
            {
            }

            public static function openTrace()
            {
            }

            public static function closeTrace()
            {
            }

            public static function import($tag, array $status)
            {
            }

            public static function trace($message = null)
            {
            }
        }
    }

}
# 核心类定义
namespace inframe {

    use InFrame;
    use Exception;
    use inframe\core\Response;
    use ReflectionMethod;
    use inframe\throws\route\ActionNotFoundException;
    use inframe\throws\ParametersInvalidException;

    final class Developer extends \_inframe_dvlp
    {
    }

    /**
     * Class Kits 工具集合
     * @package inframe
     */
    final class Kits
    {
        /**
         * 获取pathinfo信息
         *
         * 1.http://localhost/litera/public/
         * 'REQUEST_URI' => '/litera/public/',
         * 'SCRIPT_NAME' => '/litera/public/index.php',
         *
         * @return string
         */
        public static function getPathInfo(): string
        {
            $url = null;

            if (isset($_SERVER['PATH_INFO'])) {
                //如果设置了PATH_INFO则直接获取之
                $url = $_SERVER['PATH_INFO'];
            } else {
                $scriptlen = strlen($_SERVER['SCRIPT_NAME']);

                # 去除参数部分
                if (strpos($_SERVER['REQUEST_URI'], '?') !== false) {
                    $request_url = strstr($_SERVER['REQUEST_URI'], '?', true);
                } else {
                    $request_url = $_SERVER['REQUEST_URI'];
                }

                if (strlen($request_url) > $scriptlen) {
                    $pos = strpos($request_url, $_SERVER['SCRIPT_NAME']);
                    if (false !== $pos) {
                        //在不支持PATH_INFO...或者PATH_INFO不存在的情况下(URL省略将被认定为普通模式)
                        //REQUEST_URI获取原生的URL地址进行解析(返回脚本名称后面的部分)
                        if (0 === $pos) {//PATHINFO模式
                            $url = substr($request_url, $scriptlen);
                        } else {
                            //重写模式
                            $url = $request_url;
                        }
                    } else {
                        $url = $request_url;
                    }
                } else {
                    if (strpos($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_URI']) === 0) {
                        # 符合情况1的URL
                    } else {
                        # "/icode/r26" 和 '/index.php' 长度一致
                        $url = $request_url;
                    }
                }
            }
            return isset($url) ? $url : '/';
        }


        /**
         * 执行方法
         * 注:只能通过class属性得到名称，所以猜测ReflectionMethod反射的原理是用过加载进内存中的类的二进制代码来获取参数信息的，而构造中参数以可以是实力对象，也是想依赖这个对象得到类名称
         * @param ReflectionMethod $method 方法反射对象
         * @param array $arguments 方法参数数组
         * @return int 返回的int值表示缓存时间
         * @throws Exception
         */
        public static function runMethod(ReflectionMethod $method, array $arguments): int
        {
            if (!$method->isPublic() or $method->isStatic()) {
                throw new ActionNotFoundException($method->name);
            }
            $clsnm = $method->class;
            $instance = new $clsnm();

            //方法的参数检测
            if ($method->getNumberOfParameters()) {//有参数
                $args = [];
                /** @var \ReflectionParameter[] $methodParams */
                $methodParams = $method->getParameters();
                if ($methodParams) {
                    //参数组织
                    foreach ($methodParams as $param) {
                        $paramName = $param->getName();
                        if (isset($arguments[$paramName])) {
                            $args[] = $arguments[$paramName];
                        } elseif ($param->isDefaultValueAvailable()) {
                            $args[] = $param->getDefaultValue();
                        } else {
                            Response::cleanOutput();
                            die("'$paramName' missing");
                        }
                    }
                }
                //执行方法
                $result = $method->invokeArgs($instance, $args);
            } else {//无参数的方法调用
                $result = $method->invoke($instance);
            }
            return $result ?? 0;
        }

        /**
         * 将参数二的配置合并到参数一种，如果存在参数一数组不存在的配置项，跳过其设置
         * (不会覆盖)
         * @param array $dest 被覆盖者
         * @param array $sourse 覆盖者
         * @return array
         */
        public static function merge(array $dest, array $sourse): array
        {
            foreach ($sourse as $key => $val) {
                if (isset($dest[$key]) and is_array($val)) {
                    $dest[$key] = self::merge($dest[$key], $val);
                } else {
                    if (is_int($key)) {
                        if (!in_array($val, $dest)) $dest[] = $val;
                    } else {
                        $dest[$key] = $val;
                    }
                }
            }
            return $dest;
        }

        /**
         * 过滤特殊字符串
         * @param string $str
         * @return string
         */
        public static function filter(string $str): string
        {
            return htmlentities(strip_tags($str), ENT_QUOTES, 'utf-8');
        }
    }

    /**
     * Class InException 异常
     * @package inframe
     */
    class InException extends Exception
    {
        public function __construct($message = '')
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
         * @param string $default
         * @return string
         */
        public function getModules(string $default = ''): string
        {
            return $this->modules ? $this->modules : $default;
        }

        /**
         * 获取控制器序列
         * @param string $default
         * @return string
         */
        public function getController(string $default = ''): string
        {
            return $this->controller ? $this->controller : $default;
        }

        /**
         * @param string $default
         * @return string
         */
        public function getAction(string $default = ''): string
        {
            return strtolower($this->action ? $this->action : $default);
        }

        /**
         * @param array $default
         * @return array
         */
        public function getParams(array $default = []): array
        {
            return $this->params ? $this->params : $default;
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
        protected $_index = 0;
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

        protected function __construct(array $config = [], int $index = 0)
        {
            $_config = $this->getStaticConfig();
            $_config = Kits::merge($_config, $this->getApplicationConfig());
            $this->_config = $config ? Kits::merge($_config, $config) : $_config;
            $this->_index = $index;


            if (!empty($this->_config[IN_ADAPTER_CLASS]) /* 说明该类使用适配器(驱动器)驱动 */ and
                $adaptername = $this->_adapterName =
                    isset($this->_config[IN_ADAPTER_CLASS][$index]) ?
                        $this->_config[IN_ADAPTER_CLASS][$index] : reset($this->_config[IN_ADAPTER_CLASS])
            ) {
                if (!$adaptername) throw new ParametersInvalidException($index);
                # 配置中不存在'IN_ADAPTER_CLASS'，适配器为null
                $this->_adapterParams = isset($this->_config[IN_ADAPTER_CONFIG][$index]) ?
                    $this->_config[IN_ADAPTER_CONFIG][$index] : reset($this->_config[IN_ADAPTER_CONFIG]);
                # debug代码
//                if (stripos(static::class, 'dao') !== false) {
//                    dumpout($_config, $this->_config, $name, $adaptername, $this->_adapterParams);
//                }
                $this->_adapter = new $adaptername($this->_adapterParams === false ? [] : $this->_adapterParams);
            }
        }

        /**
         * 获取配置
         * @return array
         */
        protected function getConfig(): array
        {
            return $this->_config;
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
            $clsnm = str_replace('\\', '.', static::class);
            return (array)InFrame::getInstance()->getConfig($clsnm, []);
        }

        /**
         * 实例,对于构造参数的实例为同一个实例
         * @param int $index
         * @param array $config
         * @return Component 类实例
         */
        public static function getInstance(int $index = 0, array $config = [])
        {
            /** @var Component[] $_instances */
            static $_instances = [];
            if (!isset($_instances[$key = md5(($clsnm = static::class) . '#' . $index)])) {
                $_instances[$key] = new $clsnm($config, $index);
            }
            return $_instances[$key];
        }


        /**
         * 魔术调用转适配器
         * @param string $name 方法名称
         * @param array $arguments 参数名称
         * @return mixed 如果目标不存在适配器，则返回null
         */
        public function __call(string $name, array $arguments)
        {
            return $this->_adapter ? call_user_func_array([$this->_adapter, $name], $arguments) : null;
        }

        /**
         * 静态魔术调用转默认实例
         * @param string $name 方法名称
         * @param array $arguments 参数名称
         * @return mixed
         */
        public static function __callStatic(string $name, array $arguments)
        {
            return call_user_func_array([self::getInstance(), $name], $arguments);
        }

        /**
         * 设置异常信息
         * @param string $error
         * @return void
         */
        protected function setError(string $error)
        {
            $this->_error = $error;
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

    use inframe\core\Handler;
    use inframe\core\Router;
    use inframe\Developer;
    use inframe\Kits;
    use inframe\RouteInterface;
    use inframe\RoutePacket;
    use inframe\throws\route\ActionNotFoundException;
    use inframe\throws\route\ModulesNotFoundException;
    use inframe\throws\route\ControllerNotFoundException;

    /**
     * Class InFrame
     *
     * InFrame类对外提供了框架内资源访问的入口
     *
     */
    final class InFrame
    {
        /**
         * 应用配置
         * @var array
         */
        private $_config = [
            # 时区
            'timezone_zone' => 'Asia/Shanghai',
            # trace显示
            'show_trace' => true,

            # session 处理
            'session_customized' => false,//是否开启session自定义
            'session_handler' => 'redis',
            'session_save_path' => 'tcp://127.0.0.1:6379',
            'session_expire' => IN_ONE_HOUR,

            # 默认模块,控制器,方法
            'default_modules' => '',
            'default_controller' => 'index',
            'default_action' => 'index',

            # 是否开启CDN
            'cdn_on' => false,
            'cdn_path' => '',

            # 管理员邮箱,可以是多个
            'admin' => '784855684@qq.com',

            # 异常和错误处理 设置为null时表示使用系统默认，设置为false时表示不进行设置
            'error_handler' => null,
            'exception_handler' => null,

        ];
        /**
         * 错误回调
         * @var callable
         */
        private $_errorHandler;
        /**
         * 异常回调
         * @var callable
         */
        private $_exceptionHandler;
        /**
         * 脚本结束回调
         * @var callable
         */
        private $_shutdownHandler;

        /**
         * 获取InFrame实例
         * @return InFrame
         */
        public static function getInstance(): InFrame
        {
            static $_instance = null;
            if (null === $_instance) {
                $_instance = new self();
            }
            return $_instance;
        }

        private function __construct()
        {
        }

        /**
         * 初始化框架
         * @param array $config
         * @return InFrame
         */
        public function initialize(array $config): InFrame
        {
            $this->_config = array_merge($this->_config, $config);

            # 时区
            date_default_timezone_set($this->_config['timezone_zone']) or die('timezone set failed!');
            # 设置自动加载
            spl_autoload_register([$this, 'loadClass'], false, true) or die('autoload register failed!');
            # 会话
            if ($this->_config['session_customized']) {
                false === ini_set('session.save_handler', $this->_config['session_handler']) and die('set session.save_handler failed');
                false === ini_set('session.save_path', $this->_config['session_save_path']) and die('set session.save_path failed');
                false === ini_set('session.gc_maxlifetime', (string)$this->_config['session_expire']) and die('set session.gc_maxlifetime failed');
                false === ini_set('session.cache_expire', (string)$this->_config['session_expire']) and die('set session.cache_expire failed');
            }
            if (!IN_IS_CLI) {
                # web模式
                if ($this->_config['cdn_on'] and $this->_config['cdn_path']) {
                    define('IN_CDN', $this->_config['cdn_path']);
                } else {
                    # 源站
                    define('IN_CDN', IN_PUBLIC_URL);
                }
                # 脚本结束回掉
                register_shutdown_function(function () {/* 脚本结束时缓存区域的内容将会自动输出，所以不能把输出控制语句放到这里 */
                    if (!IN_IS_AJAX and $this->_config['show_trace']) Developer::trace(null); # ajax或者明确关闭trace的情况下不输出
                });
                # 处理未捕获错误
                if (false !== $this->_config['error_handler']) {
                    set_error_handler($this->_config['error_handler'] ?? function (int $code, string $message, string $file, int $line) {
                            Handler::handleThrowable($message, "Error {$code}", $file, $line, debug_backtrace());
                        });
                }
                # 处理未捕获异常
                if (false !== $this->_config['exception_handler']) {
                    set_exception_handler($this->_config['exception_handler'] ?? function (Throwable $e) {
                            Handler::handleThrowable($e->getMessage(), get_class($e), $e->getFile(), (int)$e->getLine(), $e->getTrace());
                        });
                }
            }
            return $this;
        }

        /**
         * 注册脚本结束回调函数
         * @param callable $shutdownHandler
         * @return InFrame
         */
        public function onShutdown(callable $shutdownHandler): InFrame
        {
            $this->_shutdownHandler = $shutdownHandler;
            return $this;
        }

        /**
         * 注册错误发生时回调函数
         * @param callable $errorHandler
         * @return InFrame
         */
        public function onError(callable $errorHandler): InFrame
        {
            $this->_errorHandler = $errorHandler;
            return $this;
        }

        /**
         * 注册异常发生时的回调函数
         * @param callable $exceptionHandler
         * @return InFrame
         */
        public function onException(callable $exceptionHandler): InFrame
        {
            $this->_exceptionHandler = $exceptionHandler;
            return $this;
        }

        /**
         * 类的加载规则
         *
         * //根命名空间
         * 'root_namespace' => [
         *      'path'   => '',//加载路径
         *      'ext'    => '',//类的后缀名
         * ],
         * 或者
         * 'root_namespace' => '[path]',//这种情况下后缀名默认为 .php
         *
         * 例子:
         * //laravel
         * 'Illuminate' => '/lite/vendor/laravel/',
         * //@see https://github.com/symfony/symfony
         * 'Symfony' => '/lite/vendor/',
         *
         * 如果类以‘ABS:’开头，则后面的路径为绝对地址,否则为相对项目根目录(naz存放的目录)的地址
         *
         * 如果自动加载的类无命名空间，需要将类放到根目录下的 classes 文件夹下
         * @var array
         */
        private $_rules = [
            'Workerman' => 'inframe/plugin/',
            'Symfony' => 'inframe/plugin/',
        ];

        /**
         * 根据类加载
         * @param string $clsnm
         * @return bool 返回类是否加载成功
         */
        public function loadClass(string $clsnm): bool
        {
            $pos = strpos($clsnm, '\\');

            if ($pos === false) {
                # 不存在命名空间
                $relapath = $clsnm;
                $namespace = false;
            } else {
                $relapath = str_replace('\\', '/', $clsnm);
                $namespace = substr($clsnm, 0, $pos);# 命名空间的第一级
            }

            if (false !== $namespace) {

                if (isset($this->_rules[$namespace])) {
                    //has namespace and has rule specialized
                    $rule = $this->_rules[$namespace];
                    if (is_string($rule)) {
                        $path = $rule;
                        $ext = 'php';
                    } else {/* array */
                        $path = isset($rule['path']) ? $rule['path'] : '';
                        $ext = isset($rule['ext']) ? $rule['ext'] : 'php';//兼容
                    }
                    if (strpos($path, '@') === 0) {
                        # @开头的都是绝对路径
                        $path = ltrim($path, '@');
                    } else {
                        $path = IN_PATH_BASE . '/' . $path;
                    }
                    $path = "{$path}/{$relapath}.{$ext}";
                } else {
                    $path = IN_PATH_BASE . "{$relapath}.php";
                }
            } else {
                # 直接在根路径下的寻找
                $path = IN_PATH_BASE . "{$relapath}.php";
            }

            if (is_file($path)) {
                require($path);
                return true;
            } else {
                return false;
            }
        }

        /**
         * 开启应用
         * @param RouteInterface $router 路由解析回调，默认使用系统自带
         * @return void
         * @throws ActionNotFoundException
         * @throws ControllerNotFoundException
         * @throws ModulesNotFoundException
         */
        public function start(RouteInterface $router = null)
        {

            if (IN_IS_CLI) return;
            Developer::status('engine_start');
            # 解析路由
            $router or $router = Router::getInstance();
            /** @var RoutePacket $routePacket */
            $routePacket = $router->parse(Kits::getPathInfo());

            # 定义调度常量( 由于方法不区分大小写 => 操作的名称统一转小写 )
            define('IN_REQUEST_MODULES', $routePacket->getModules($this->_config['default_modules']));
            define('IN_REQUEST_CONTROLLER', $routePacket->getController($this->_config['default_controller']));
            define('IN_REQUEST_ACTION', $routePacket->getAction($this->_config['default_action']));
            define('IN_REQUEST_PATH', IN_REQUEST_MODULES . '/' . IN_REQUEST_CONTROLLER . '/' . IN_REQUEST_ACTION);

            Developer::status('dispatch_begin');

            # 执行调度
            $modulepath = IN_PATH_APP . 'app/' . IN_REQUEST_MODULES;//linux 不识别
            if (!is_dir($modulepath)) throw new ModulesNotFoundException($modulepath);
            if (!class_exists($className = IN_APP_NAME . (IN_REQUEST_MODULES ? '\\app\\' . IN_REQUEST_MODULES : '\\app') . '\\controller\\' . IN_REQUEST_ACTION, true)) throw new ControllerNotFoundException($className);
            try {
                $method = new ReflectionMethod($className, IN_REQUEST_ACTION);
            } catch (ReflectionException $e) {
                try {
                    $method = new ReflectionMethod($className, '_empty');
                } catch (ReflectionException $e) {
                    throw new ActionNotFoundException(IN_REQUEST_ACTION);
                }
            }

            Kits::runMethod($method, $_REQUEST);
        }

        /**
         * 获取配置
         * @param string $name
         * @param mixed $replacement 配置项不存在时默认返回的
         * @return mixed|null
         */
        public function getConfig(string $name = '', $replacement = null)
        {
            return $this->_config[$name] ?? $replacement;
        }

    }

}