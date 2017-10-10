<?php
/**
 * Repository: git@github.com:lichtung/inframe.git
 * User: 784855684@qq.com
 * Date: 2017/10/10
 * Time: 13:55
 */
declare(strict_types=1);


namespace test;


use PHPUnit\Framework\TestCase;

class OverflowTest extends TestCase
{

    public function test1()
    {
        $this->assertTrue(3 > 1);
    }

}