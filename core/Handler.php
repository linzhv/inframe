<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 07/10/2017
 * Time: 09:58
 */

declare(strict_types=1);

namespace inframe\core;

/**
 * Class Handler 错误／异常／终止处理器
 * @package inframe\core
 */
class Handler
{
    /**
     * 异常发生时
     * @return void
     */
    public function onException(): void
    {

    }

    /**
     * 错误发生时
     * @return void
     */
    public function onError(): void
    {

    }

    /**
     * 脚本终止时
     * @return void
     */
    public function onShutdown(): void
    {
    }

}