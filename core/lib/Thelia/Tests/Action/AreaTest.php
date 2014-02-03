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

namespace Thelia\Tests\Action;

use Thelia\Action\Area;
use Thelia\Core\Event\Area\AreaCreateEvent;


/**
 * Class AreaTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AreaTest extends \PHPUnit_Framework_TestCase
{

    public function testCreate()
    {
        $event = new AreaCreateEvent();
        $event
            ->setAreaName('foo')
            ->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $areaAction = new Area();
        $areaAction->create($event);

        $createdArea = $event->getArea();

        $this->assertInstanceOf('Thelia\Model\Area', $createdArea);
        $this->assertFalse($createdArea->isNew());
        $this->assertTrue($event->hasArea());

        $this->assertEquals('foo', $createdArea->getName());
    }

} 