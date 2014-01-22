<?php
use Thelia\TaxEngine\TaxEngine;
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

namespace Thelia\Tests\TaxEngine;

use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\TaxEngine\TaxEngine;


/**
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
class TaxEngineTest extends \PHPUnit_Framework_TestCase
{
    protected $request;

    public function setUp()
    {
        $this->request = new Request();

        $this->request->setSession(new Session(new MockArraySessionStorage()));
    }

    /**
      */
    public function testGetTaxTypeList()
    {
        $taxEngine = new TaxEngine($this->request);

        $list = $taxEngine->getTaxTypeList();

        $this->assertEquals($list[0], "Thelia\TaxEngine\TaxType\FeatureFixAmountTaxType");
        $this->assertEquals($list[1], "Thelia\TaxEngine\TaxType\FixAmountTaxType");
        $this->assertEquals($list[2], "Thelia\TaxEngine\TaxType\PricePercentTaxType");
    }
}