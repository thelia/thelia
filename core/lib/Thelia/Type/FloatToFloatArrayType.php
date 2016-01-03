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

class FloatToFloatArrayType extends BaseType
{
    public function getType()
    {
        return 'Float key to float value array type';
    }

    public function isValid($value)
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $key => $val) {
            if (filter_var($key, FILTER_VALIDATE_FLOAT) === false || filter_var($val, FILTER_VALIDATE_FLOAT) === false) {
                return false;
            }
        }

        return true;
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
