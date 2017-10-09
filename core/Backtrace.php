<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 2017/10/9
 * Time: 10:15
 */

declare(strict_types=1);
namespace inframe\core;


final class Backtrace
{
    /**
     * 调用位置
     */
    const PLACE_BACKWORD = 0; //表示调用者自身的位置
    const PLACE_SELF = 1;// 表示调用调用者的位置
    const PLACE_FORWARD = 2;
    const PLACE_FURTHER_FORWARD = 3;
    /**
     * 信息组成
     */
    const ELEMENT_FUNCTION = 1;
    const ELEMENT_FILE = 2;
    const ELEMENT_LINE = 4;
    const ELEMENT_CLASS = 8;
    const ELEMENT_TYPE = 16;
    const ELEMENT_ARGS = 32;
    const ELEMENT_ALL = 0;

    /**
     * 返回调用当前方法(Backtrace::getPreviousMethod()所在的位置)的函数的前一个方法
     * @return string
     */
    public static function getPreviousMethod()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        if (isset($trace[self::PLACE_FORWARD])) {
            return isset($trace[self::PLACE_FORWARD]['function']) ? $trace[self::PLACE_FORWARD]['function'] : '';
        }
        return '';
    }

    /**
     * 获取调用者本身的位置
     * @param int $elements 为0是表示获取全部信息
     * @param int $place 位置属性
     * @return array|string
     */
    public static function backtrace($elements = self::ELEMENT_ALL, $place = self::PLACE_SELF)
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        if ($elements) {
            $result = [];
            $elements & self::ELEMENT_ARGS and $result[self::ELEMENT_ARGS] = isset($trace[$place]['args']) ? $trace[$place]['args'] : '';
            $elements & self::ELEMENT_CLASS and $result[self::ELEMENT_CLASS] = isset($trace[$place]['class']) ? $trace[$place]['class'] : '';
            $elements & self::ELEMENT_FILE and $result[self::ELEMENT_FILE] = isset($trace[$place]['file']) ? $trace[$place]['file'] : '';
            $elements & self::ELEMENT_FUNCTION and $result[self::ELEMENT_FUNCTION] = isset($trace[$place]['function']) ? $trace[$place]['function'] : '';
            $elements & self::ELEMENT_LINE and $result[self::ELEMENT_LINE] = isset($trace[$place]['line']) ? $trace[$place]['line'] : '';
            $elements & self::ELEMENT_TYPE and $result[self::ELEMENT_TYPE] = isset($trace[$place]['type']) ? $trace[$place]['type'] : '';
            1 === count($result) and $result = array_shift($result);//一个结果直接返回
        } else {
            $result = $trace[$place];
        }
        return $result;
    }
}