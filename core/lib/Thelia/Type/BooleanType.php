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

class BooleanType implements TypeInterface
{
    protected $trueValuesArray = array(
        '1',
        'true',
        'yes',
        'y',
    );
    protected $falseValuesArray = array(
        '0',
        'false',
        'no',
        'n',
    );

    public function getType()
    {
        return 'Boolean type';
    }

    public function isValid($value)
    {
        return in_array($value, $this->trueValuesArray) || in_array($value, $this->falseValuesArray);
    }

    public function getFormattedValue($value)
    {
        return $this->isValid($value) ? ( in_array($value, $this->trueValuesArray) ) : null;
    }
}
