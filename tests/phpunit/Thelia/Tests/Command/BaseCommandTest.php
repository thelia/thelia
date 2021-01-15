<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * base class for testing command line command
 *
 * Class BaseCommandTest
 * @package Thelia\Tests\Command
 * @author Manuel Raynaud <manu@raynaud.io>
 */
abstract class BaseCommandTest extends TestCase
{
    /**
     * @return KernelInterface
     */
    public function getKernel()
    {
        $kernel = $this->createMock("Symfony\Component\HttpKernel\KernelInterface");

        return $kernel;
    }
}
