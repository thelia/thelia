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

class JsonType extends BaseType
{
    public function getType()
    {
        return 'Json type';
    }

    public function isValid($value)
    {
        json_decode($value, true);

        return (json_last_error() == JSON_ERROR_NONE);
    }

    public function getFormattedValue($value)
    {
        return $this->isValid($value) ? json_decode($value, true) : null;
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
