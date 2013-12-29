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
