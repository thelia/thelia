<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Type;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */

class EnumListType extends BaseType
{
    protected $values = array();

    public function __construct($values = array())
    {
        if (is_array($values)) {
            $this->values = $values;
        }
    }

    public function addValue($value)
    {
        if (!in_array($value, $this->values)) {
            $this->values[] = $value;
        }
    }

    /**
     * @param array|\Traversable $values
     * @since 2.3.0
     */
    public function addValues($values)
    {
        if (!is_array($values) && !$values instanceof \Traversable) {
            throw new \InvalidArgumentException('$values must be an array or an instance of \Traversable');
        }

        foreach ($values as $value) {
            $this->addValue($value);
        }
    }

    public function getType()
    {
        return 'Enum list type';
    }

    public function isValid($values)
    {
        foreach (explode(',', $values) as $value) {
            if (!$this->isSingleValueValid($value)) {
                return false;
            }
        }

        return true;
    }

    public function getFormattedValue($values)
    {
        return $this->isValid($values) ? explode(',', $values) : null;
    }

    public function isSingleValueValid($value)
    {
        return in_array($value, $this->values);
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
