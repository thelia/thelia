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

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Exception\TypeException;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class ModelType extends BaseType
{
    protected $expectedModelActiveRecord = null;

    /**
     * @param $expectedModelActiveRecord
     * @throws TypeException
     */
    public function __construct($expectedModelActiveRecord)
    {
        $class = '\\Thelia\\Model\\' . $expectedModelActiveRecord;

        if (!(class_exists($class) && new $class instanceof ActiveRecordInterface)) {
            throw new TypeException('MODEL NOT FOUND', TypeException::MODEL_NOT_FOUND);
        }

        $this->expectedModelActiveRecord = $class;
    }

    public function getType()
    {
        return 'Model type';
    }

    public function isValid($value)
    {
        return $value instanceof $this->expectedModelActiveRecord;
    }

    public function getFormattedValue($value)
    {
        return $this->isValid($value) ? $value : null;
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
