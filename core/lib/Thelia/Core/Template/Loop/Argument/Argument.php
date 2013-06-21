<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Core\Template\Loop\Argument;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class Argument
{
    protected $name;
    protected $type;
    protected $mandatory;
    protected $default;

    public function __construct($name, \Thelia\Type\TypeCollection $type, $mandatory = false, $default = null)
    {
        $this->name         = $name;
        $this->type         = $type;
        $this->mandatory    = $mandatory ? true : false;
        $this->default      = $default;
    }
}
