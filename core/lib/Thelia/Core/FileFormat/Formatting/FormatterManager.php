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

namespace Thelia\Core\FileFormat\Formatting;
use Thelia\Core\Translation\Translator;

/**
 * Class FormatterManager
 * @package Thelia\Core\FileFormat\Formatting
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
            $this->throwOutOfBounds($name);
        }

        unset($this->formatters[$name]);

        return $this;
    }

    public function get($name)
    {
        if (!array_key_exists($name, $this->formatters)) {
            $this->throwOutOfBounds($name);
        }

        return $this->formatters[$name];
    }

    /**
     * @return array[AbstractFormatter]
     */
    public function getAll()
    {
        return $this->formatters;
    }

    /**
     * @return array
     */
    public function getNames()
    {
        $names = [];

        /** @var AbstractFormatter $formatter */
        foreach($this->formatters as $formatter) {
            $names[] = $formatter->getName();
        }

        return $names;
    }

    /**
     * @param $name
     * @throws \OutOfBoundsException
     */
    protected function throwOutOfBounds($name)
    {
        throw new \OutOfBoundsException(
            Translator::getInstance()->trans(
                "The formatter \"%name\" doesn't exist",
                [
                    "%name" => $name
                ]
            )
        );
    }
}
