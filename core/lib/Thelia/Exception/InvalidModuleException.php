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
 * Class InvalidModuleException
 * @package Thelia\Exception
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class InvalidModuleException extends \RuntimeException
{
    protected $errors = [];

    public function __construct(array $errors = [])
    {
        parent::__construct();

        $this->errors = $errors;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    public function getErrorsAsString($separator = "\n")
    {
        $message = '';

        /** @var \Exception $error */
        foreach ($this->errors as $error) {
            $message .= $error->getMessage() . $separator;
        }

        return rtrim($message, $separator);
    }
}
