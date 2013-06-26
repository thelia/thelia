<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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

namespace Thelia\Core\Template\TestLoop;

use Thelia\Tpex\Element\TestLoop\BaseTestLoop;

/**
 *
 * TestLoop equal, test if value and variable are equal
 *
 * example :
 *
 * <TEST_equal test="equal" variable="3" value="1">
 *      Result display here if variable and value are equal
 * </TEST_equal>
 *      Result display here if variable and value are not equal
 * <//TEST_equal>
 *
 * Class Equal
 * @package Thelia\Core\Template\TestLoop
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class Equal extends BaseTestLoop
{

    public function exec($variable, $value)
    {
        return $variable == $value;
    }
}
