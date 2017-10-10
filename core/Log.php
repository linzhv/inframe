<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 07/10/2017
 * Time: 09:57
 */

declare(strict_types=1);
namespace inframe\core;

use inframe\Component;

/**
 * Class Log 日志类
 *
 * @method warn($message, bool $saverightnow = false) static
 * @method debug($message, bool $saverightnow = false) static
 * @method info($message, bool $saverightnow = false) static
 * @method fatal($message, bool $saverightnow = false) static
 *
 * @package inframe\core
 */
final class Log extends Component
{
    //5种正常级别
    const DEBUG = 0b1;//错误和调试
    const INFO = 0b10;
    const WARN = 0b100;
    const FATAL = 0b1000;//指出每个严重的错误事件将会导致应用程序的退出
    //特别的日志记录级别
    const ALL = 0b1111;//最低等级的，用于打开所有日志记录
    const OFF = 0;//最高等级的，用于关闭所有日志记录
    /**
     * @var array 日志信息
     */
    private static $records = [
        'default' => [],
    ];

    public static function getLogger(string $loggerName = 'default'): Log
    {
        static $_loggers = [];

        if (!isset($_loggers[$loggerName])) {
            $_loggers[$loggerName] = new self($loggerName);
            isset(self::$records[$loggerName]) or self::$records[$loggerName] = [];
        }
        return $_loggers[$loggerName];
    }


    public static function save()
    {
        $date = date('Ymd');

        if (IN_IS_CLI) {
            $title = '[IS_CLIENT]';
        } else {
            $now = date('Y-m-d H:i:s');
            $ip = Request::getIP();
            $title = "[{$now}] {$ip} {$_SERVER['REQUEST_URI']}";
        }

        foreach (self::$records as $name => & $logs) {
            if ($logs) {
                $message = implode("\n", $logs);
                is_dir($dirname = dirname($destination = IN_PATH_RUNTIME . "log/{$name}/{$date}.log"))
                or mkdir($dirname, 0777, true);
                IN_IS_CLI or $message = "{$title}\n{$message}";
                error_log($message . PHP_EOL, 3, $destination);
                $logs = [];
            }
        }
    }


//--------------------------- 实例方法 ------------------------------------------------//


    protected function __construct(string $name = 'default')
    {
        parent::__construct([], 0);
        $config = $this->getConfig();
        $this->level = $config['level'] ?? Log::ALL;
        $this->name = $name;
    }

    /**
     * 日志记录器名
     * @var string
     */
    protected $name = '';
    /**
     * @var  int
     */
    protected $level = 0;


    protected function getStaticConfig(): array
    {
        return [
            'format' => 'Ymd',
            // 允许记录的日志级别
            'level' => self::ALL,
        ];
    }

    /**
     * 返回或者设置level
     * @param int|null $newval 如果是null则表示设置level
     * @return int
     */
    public function level($newval = null)
    {
        if (null !== $newval) {
            $this->level = $newval;
        }
        return $this->level;
    }

    /**
     * 记录日志 并且会过滤未经设置的级别
     * @param string $message 日志信息
     * @param int $level 日志级别
     * @param boolean $saverightnow 是否立即保存，默认为否，命令行执行服务的情况下建议将参数二设置为true(脚本可能是ctrl+c强制结束)
     * @param null|string logger类型
     * @return void
     */
    public function record($message, $level = self::INFO, $saverightnow = false)
    {
        if ($level & $this->level) {
            //无论是静态调用还是实例化调用，都会得到index为2的位置
            if ($location = Backtrace::backtrace(Backtrace::ELEMENT_ALL, Backtrace::PLACE_FURTHER_FORWARD)) {
                $file = isset($location['file']) ? $location['file'] : '';
                $line = isset($location['line']) ? $location['line'] : '';
                $location = "{$file}<{$line}>";
            }

            $level = self::_level2str($level);
            $message = is_array($message) ? var_export($message, true) : (string)$message;
            $now = date('Y-m-d H:i:s');
            self::$records[$this->name][] = "[LV:{$level} NOW:{$now} LOC:{$location}]: \n{$message}\n";
            //命令行模式下立即保存
            $saverightnow and self::save();
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLog($getall = false)
    {
        return $getall ? self::$records : (isset(self::$records[$this->name]) ? self::$records[$this->name] : []);
    }

    /**
     * @param string $level 调用的方法名称
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $level, array $arguments)
    {
        return call_user_func_array(
            [self::getLogger(), 'record'],
            [
                $arguments[0],
                self::_str2level($level),
                empty($arguments[1]) ? false : $arguments[1]
            ]);
    }

    /**
     * @param string $name 调用的方法名称，即level
     * @param array $arguments 参数
     * @return void
     */
    public function __call(string $name, array $arguments)
    {
        $this->record($arguments[0], self::_str2level($name),
            isset($arguments[1]) ? $arguments[1] : false);
    }

    /**
     * level由string转int
     * @param string $str
     * @return int|mixed
     */
    private static function _str2level($str)
    {
        $str = strtolower($str);
        static $_map = [
            'debug' => self::DEBUG,
            'info' => self::INFO,
            'warn' => self::WARN,
            'fatal' => self::FATAL,
            'all' => self::ALL,
        ];
        return isset($_map[$str]) ? $_map[$str] : self::ALL;
    }

    /**
     * level由int转string
     * @param int $level
     * @return string
     */
    private static function _level2str($level)
    {
        switch ($level) {
            case self::DEBUG:
                return 'DEBUG';
                break;
            case self::INFO:
                return 'INFO';
                break;
            case self::WARN:
                return 'WARN';
                break;
            case self::FATAL;
                return 'FATAL';
                break;
            case self::ALL:
                return 'ALL';
                break;
            default:
                return 'UNKOWN LEVEL';
        }
    }
}

register_shutdown_function(function () {
    Log::save();
});