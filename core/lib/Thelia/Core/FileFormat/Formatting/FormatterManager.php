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
        foreach ($this->formatters as $formatter) {
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

    /**
     * @return array
     *
     * Return the extensions handled by archive builders
     */
    public function getExtensions($withDot = false)
    {
        $extensions = [];

        /** @var AbstractFormatter $formatter */
        foreach ($this->formatters as $formatter) {
            $extensionName = $withDot ? ".": "";
            $extensionName .= $formatter->getExtension();
            $extensions[$formatter->getName()] = $extensionName;
        }

        return $extensions;
    }

    public function getExtensionsByTypes($types, $withDot = false)
    {
        $extensions = [];

        /** @var AbstractFormatter $formatter */
        foreach ($this->getFormattersByTypes($types) as $formatter) {
            $extensionName = $withDot ? ".": "";
            $extensionName .= $formatter->getExtension();
            $extensions[$formatter->getName()] = $extensionName;
        }

        return $extensions;
    }

    /**
     * @param $extension
     * @return bool|AbstractFormatter
     */
    public function getFormatterByExtension($extension)
    {
        if ($extension[0] === ".") {
            $extension = substr($extension, 1);
        }

        $extensions = $this->getExtensions();

        if (!in_array($extension, $extensions)) {
            return false;
        } else {
            $flip = array_flip($extensions);
            $formatterName = $flip[$extension];

            return $this->formatters[$formatterName];
        }
    }

    public function getFormattersByTypes($types)
    {
        if (!is_array($types)) {
            $types = [$types];
        }

        $selectedFormatters = [];

        /** @var AbstractFormatter $formatter */
        foreach ($this->formatters as $formatter) {
            $handledType = $formatter->getHandledType();

            if (in_array($handledType, $types)) {
                $selectedFormatters[$formatter->getName()] = $formatter;
            }
        }

        return $selectedFormatters;
    }

    public function getMimeTypesByTypes($types)
    {
        if (!is_array($types)) {
            $types = [$types];
        }

        $mimeTypes = [];

        /** @var AbstractFormatter $formatter */
        foreach ($this->getFormattersByTypes($types) as $formatter) {
            $mimeTypes[$formatter->getName()] = $formatter->getMimeType();
        }

        return $mimeTypes;
    }
}
