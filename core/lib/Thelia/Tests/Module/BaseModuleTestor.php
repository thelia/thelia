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

namespace Thelia\Tests\Module;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
abstract class BaseModuleTestor extends \PHPUnit_Framework_TestCase
{
    protected $instance;

    abstract public function getTestedClassName();
    abstract public function getTestedInstance();

    /*protected function getMethod($name)
    {
        $class = new \ReflectionClass($this->getTestedClassName());
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }*/

    public function setUp()
    {
        $this->instance = $this->getTestedInstance();
    }
}
