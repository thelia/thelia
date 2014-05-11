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

namespace Thelia\Core\Template\Element\Exception;

class LoopException extends \RuntimeException
{
    const UNKNOWN_EXCEPTION = 0;

    const NOT_TIMESTAMPED = 100;
    const NOT_VERSIONED = 101;

    const MULTIPLE_SEARCH_INTERFACE = 400;
    const SEARCH_INTERFACE_NOT_FOUND = 404;

    public function __construct($message, $code = null, $arguments = array(), $previous = null)
    {
        if (is_array($arguments)) {
            $this->arguments = $arguments;
        }
        if ($code === null) {
            $code = self::UNKNOWN_EXCEPTION;
        }
        parent::__construct($message, $code, $previous);
    }
}
