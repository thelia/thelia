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
namespace Thelia\Type;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */

class IntListType extends BaseType
{
    public function getType()
    {
        return 'Int list type';
    }

    public function isValid($values)
    {
        foreach (explode(',', $values) as $value) {
            if(filter_var($value, FILTER_VALIDATE_INT) === false)

                return false;
        }

        return true;
    }

    public function getFormattedValue($values)
    {
        return $this->isValid($values) ? explode(',', $values) : null;
    }

    public function getFormType()
    {
        return 'text';
    }

    public function getFormOptions()
    {
        return array();
    }
}
