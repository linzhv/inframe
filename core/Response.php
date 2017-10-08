<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 07/10/2017
 * Time: 17:25
 */
declare(strict_types=1);


namespace inframe\core;

use inframe\helper\XMLer;
use inframe\throws\ParametersInvalidException;


final class Response
{
    const TYPE_JSONP = 4;
    const TYPE_JSON = 0;
    const TYPE_XML = 1;
    const TYPE_HTML = 2;
    const TYPE_PLAIN = 3;


    /**
     * 发送http协议代号
     * @param int $code
     * @param string $message
     */
    public static function sendHttpStatus($code, $message = '')
    {
        static $_status = [
            // Informational 1xx
            100 => 'Continue',
            101 => 'Switching Protocols',

            // Success 2xx
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',

            // Redirection 3xx
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',  // 1.1
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            // 306 is deprecated but reserved
            307 => 'Temporary Redirect',

            // Client Error 4xx
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Resource Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',

            // Server Error 5xx
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            509 => 'Bandwidth Limit Exceeded',
        ];
        if (!$message) {
            $message = isset($_status[$code]) ? $_status[$code] : '';
        }
        self::cleanOutput();
        header("HTTP/1.1 {$code} {$message}");
    }

    /**
     * 向浏览器客户端发送不缓存命令
     * @param bool $clean
     * @return void
     */
    public static function sendNocache($clean = true)
    {
        $clean and self::cleanOutput();
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
    }


    /**
     * 获取输出内容
     * @param bool $clean
     * @return string
     */
    public static function getOutput($clean = true)
    {
        if (ob_get_level()) {
            $content = ob_get_contents();
            $clean and ob_end_clean();
            return $content;
        } else {
            return '';
        }
    }

    /**
     * flush the cache to client
     * @return void
     */
    public static function flushOutput()
    {
        ob_get_level() and ob_end_flush();
    }

    /**
     * 清空输出缓存
     * @param bool $end 结束该level的的ob缓存
     * @return void
     */
    public static function cleanOutput($end = true)
    {
        ob_get_level() and ($end ? ob_end_clean() : ob_clean());
    }


    /**
     * 设置Access-Control-Allow-Origin来实现跨域
     *
     * 如果直接使用ajax访问，会有以下错误：
     * XMLHttpRequest cannot load http://server.runoob.com/server.php. No 'Access-Control-Allow-Origin'
     * header is present on the requested resource.Origin 'http://client.runoob.com' is therefore not
     * allowed access.
     *
     * 允许单个域名访问   header('Access-Control-Allow-Origin:http://client.runoob.com');
     * 允许多个域名访问
     *  $origin = isset($_SERVER['HTTP_ORIGIN'])? $_SERVER['HTTP_ORIGIN'] : '';
     *  $allow_origin = array(
     *      'http://client1.runoob.com',
     *      'http://client2.runoob.com',
     *  );
     *  if(in_array($origin, $allow_origin))  if(in_array($origin, $allow_origin)){
     *
     * 允许所有域名访问
     * header('Access-Control-Allow-Origin:*');
     *
     * @param array $allow 允许的域名，默认全部
     * @return void
     */
    public static function allow(array $allow = [])
    {
        self::cleanOutput();
        if ($allow) {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            in_array($origin, $allow) and header('Access-Control-Allow-Origin:' . $origin);
        } else {
            header('Access-Control-Allow-Origin:*');
        }
    }


    /**
     * 输出结果，默认输出json
     * @param string|array $data 输出内容
     * @param int $format 输出格式
     * @param int $options 输出配置
     * @return void
     * @throws ParametersInvalidException 返回格式出现错误
     */
    public static function export($data, $format = self::TYPE_JSON, $options = 0)
    {
//        IN_IS_AJAX or die('request should be ajax');
        self::cleanOutput();
        switch ($format) {
            case self::TYPE_JSON :// 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                die(json_encode($data, $options));
                break;
            case self::TYPE_PLAIN:
                header('Content-type:text/plain;charset=utf-8');
                die($data);
                break;
            case self::TYPE_JSONP:
                die((isset($_GET['callback']) ? $_GET['callback'] : 'callback') . '(' . json_encode($data) . ')');
                break;
            default:
                throw new ParametersInvalidException();
        }
    }


    /**
     * 重定向
     * @param string $url 重定向地址
     * @param int $time
     * @param string $message
     * @return void
     */
    public static function redirect($url, $time = 0, $message = '')
    {
        if (strpos($url, 'http') !== 0) {
            $url = IN_PUBLIC_URL . str_replace(["\n", "\r"], ' ', $url);
        }
        $message or $message = "{$time}秒后自动跳转到'{$url}'！";

        if (headers_sent()) {//检查头部是否已经发送
            exit("<meta http-equiv='Refresh' content='{$time};URL={$url}'>{$message}");
        } else {
            if (0 === $time) {
                header('Location: ' . $url);
            } else {
                header("refresh:{$time};url={$url}");
                echo $message;
            }
        }
        die;
    }

    /**
     * 返回成功的消息
     * @param string $message 成功返回信息
     * @param array $data 返回数据
     * @param int $code 错误代号,默认是0,表示无错误发生
     * @return void
     */
    public static function success($message = '', array $data = [], $code = 0)
    {
        self::export([
            'status' => 1,
            'message' => $message,
            'data' => $data,
            'code' => $code,
        ]);
    }

    /**
     * 返回错误的消息
     * @param string $message 错误返回信息
     * @param array $data 返回数据
     * @param int $code 错误代号,默认是0,表示无错误发生
     * @return void
     */
    public static function failure($message = '', array $data = [], $code = 0)
    {
        self::export([
            'status' => 0,
            'message' => $message,
            'data' => $data,
            'code' => $code,
        ]);
    }

}