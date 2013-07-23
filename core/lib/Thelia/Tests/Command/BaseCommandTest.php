<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 11/07/13
 * Time: 10:34
 * To change this template use File | Settings | File Templates.
 */

namespace Thelia\Tests\Command;


abstract class BaseCommandTest extends \PHPUnit_Framework_TestCase {
    public function getKernel()
    {
        $kernel = $this->getMock("Symfony\Component\HttpKernel\KernelInterface");

        return $kernel;
    }
}