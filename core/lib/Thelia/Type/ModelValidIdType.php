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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Exception\TypeException;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class ModelValidIdType extends BaseType
{
    protected $expectedModelActiveRecordQuery = null;

    /**
     * @param $expectedModelActiveRecord
     * @throws TypeException
     */
    public function __construct($expectedModelActiveRecord)
    {
        $class = '\\Thelia\\Model\\' . $expectedModelActiveRecord . 'Query';

        if (!(class_exists($class) || !new $class instanceof ModelCriteria)) {
            throw new TypeException('MODEL NOT FOUND', TypeException::MODEL_NOT_FOUND);
        }

        $this->expectedModelActiveRecordQuery = $class;
    }

    public function getType()
    {
        return 'Model valid Id type';
    }

    public function isValid($value)
    {
        $queryClass = $this->expectedModelActiveRecordQuery;

        return null !== $queryClass::create()->findPk($value);
    }

    public function getFormattedValue($value)
    {
        $queryClass = $this->expectedModelActiveRecordQuery;

        return $this->isValid($value) ? $queryClass::create()->findPk($value) : null;
    }

    public function getFormType()
    {
        return 'choice';
    }

    public function getFormOptions()
    {
        $queryClass = $this->expectedModelActiveRecordQuery;

        $choices = array();
        foreach ($queryClass::create()->find() as $item) {
            $choices[$item->getId()] = method_exists($item, "getTitle") ? $item->getTitle() : $item->getId();
        }

        return array(
            "choices" => $choices,
        );
    }
}
