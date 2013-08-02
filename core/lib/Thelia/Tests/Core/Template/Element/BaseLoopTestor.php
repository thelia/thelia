<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Tests\Core\Template\Element;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Session\Session;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
abstract class BaseLoopTestor extends \PHPUnit_Framework_TestCase
{
    protected $request;
    protected $dispatcher;
    protected $securityContext;

    protected $instance;

    abstract public function getTestedClassName();
    abstract public function getTestedInstance();
    abstract public function getMandatoryArguments();

    protected function getMethod($name) {
        $class = new \ReflectionClass($this->getTestedClassName());
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    public function setUp()
    {
        $this->request = new Request();
        $this->request->setSession(new Session(new MockArraySessionStorage()));

        $this->dispatcher = new EventDispatcher();

        $this->securityContext = new SecurityContext($this->request);

        $this->instance = $this->getTestedInstance();
        $this->instance->initializeArgs($this->getMandatoryArguments());
    }

    public function testGetArgDefinitions()
    {
        $method = $this->getMethod('getArgDefinitions');

        $methodReturn = $method->invoke($this->instance);

        $this->assertInstanceOf('Thelia\Core\Template\Loop\Argument\ArgumentCollection', $methodReturn);
    }

    public function testExec()
    {
        $method = $this->getMethod('exec');

        $methodReturn = $method->invokeArgs($this->instance, array(null));

        $this->assertInstanceOf('\Thelia\Core\Template\Element\LoopResult', $methodReturn);
    }
}
