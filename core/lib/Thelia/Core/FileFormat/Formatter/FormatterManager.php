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
use Thelia\Core\Translation\Translator;

/**
 * Class FormatterManager
 * @package Thelia\Core\FileFormat\Formatter
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FormatterManager
{

    protected $formatters = array();

    /**
     * @param $archiveCreator
     * @return $this
     */
    public function add(AbstractFormatter $formatter)
    {
        if (null !== $formatter) {
            $this->formatters[$formatter->getName()] = $formatter;
        }

        return $this;
    }

    /**
     * @param $name
     * @return $this
     * @throws \OutOfBoundsException
     */
    public function delete($name)
    {
        if (!array_key_exists($name, $this->formatters)) {
            throw new \OutOfBoundsException(
                Translator::getInstance()->trans(
                    "The formatter %name doesn't exist",
                    [
                        "%name" => $name
                    ]
                )
            );
        }

        unset($this->formatters[$name]);

        return $this;
    }

    /**
     * @return array[AbstractFormatter]
     */
    public function getAll()
    {
        return $this->formatters;
    }
}
