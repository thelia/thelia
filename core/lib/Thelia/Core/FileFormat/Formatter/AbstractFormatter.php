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

namespace Thelia\Core\FileFormat\Formatter;
use Thelia\Core\FileFormat\FormatInterface;

/**
 * Class AbstractFormatter
 * @package Thelia\Core\FileFormat\Formatter
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class AbstractFormatter implements FormatInterface
{
    /**
     * @param array $data
     * @return mixed
     *
     * Encodes an array to the desired format.
     * $data array only contains array and scalar data.
     */
    abstract public function encode(array $data);

    /**
     * @param $data
     * @return array
     * @throws \Thelia\Core\FileFormat\Formatter\Exception\BadFormattedStringException
     *
     * this method must do exactly the opposite of encode and return
     * an array composed of array and scalar data.
     */
    abstract public function decode($data);
} 