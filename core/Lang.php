<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 07/10/2017
 * Time: 10:01
 */

declare(strict_types=1);

namespace inframe\core;

use inframe\Component;

/**
 * Class Lang 语言类
 *
 * 语言类处理国际化（Internationalization 即 I18N）的问题，解决的是应用程序无需做大的改变就能够适应不同的语言和地区的需要
 *
 *
 *
 * @package inframe\core
 */
final class Lang extends Component
{
    /**
     * @param string $lang 语言包名称，缺失时默认从请求的头部中获取
     * @return Lang
     */
    public static function load(string $lang = ''): Lang
    {

    }

    /**
     * 获取语言项
     * @param string $name
     * @param string $value
     * @return string
     */
    public function get(string $name, string $value = ''): string
    {
    }

    /**
     * 临时设置语言项目
     * @param string $name
     * @param string $value
     * @return string
     */
    public function set(string $name, string $value): string
    {

    }

}