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
namespace Thelia\Type;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */

class IntToCombinedIntsList implements TypeInterface
{
    public function getType()
    {
        return 'Int to combined ints list type';
    }

    public function isValid($values)
    {
        foreach(explode(',', $values) as $intToCombinedInts) {
            $parts = explode(':', $intToCombinedInts);
            if(count($parts) != 2)
                return false;
            if(filter_var($parts[0], FILTER_VALIDATE_INT) === false)
                return false;
            if(!preg_match('#([0-9]+)|\*|&\*]+#', $parts[1]))
                return false;
        }

        return true;
    }

    public function getFormattedValue($values)
    {
        return $this->isValid($values) ? explode(',', $values) : null;
    }
}
