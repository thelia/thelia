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

namespace Thelia\Exception;

/**
 * Class HookException
 * @package Thelia\Exception
 * @author  Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class HookException extends \RuntimeException
{
    /**
     * @param int $type
     * @param string $code
     * @param string $message
     */
    public function __construct($type, $code, $message)
    {
        parent::__construct('HookCode : ' . $code . ' : ' . $message);
    }
}
