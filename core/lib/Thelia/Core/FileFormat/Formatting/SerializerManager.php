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
 * @author Benjamin Perche <bperche@openstudio.fr>
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class SerializerManager
{
    /**
     * @var array List of handled serializers
     */
    protected $serializers = [];

    /**
     * Add
     *
     * @param AbstractSerializer $formatter
     *
     * @return $this Return $this, allow chaining
     */
    public function add(AbstractSerializer $formatter)
    {
        if (null !== $formatter) {
            $this->serializers[$formatter->getName()] = $formatter;
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
        if (!array_key_exists($name, $this->serializers)) {
            $this->throwOutOfBounds($name);
        }

        unset($this->serializers[$name]);

        return $this;
    }

    public function get($name)
    {
        if (!array_key_exists($name, $this->serializers)) {
            $this->throwOutOfBounds($name);
        }

        return $this->serializers[$name];
    }

    /**
     * @return array[AbstractSerializer]
     */
    public function getAll()
    {
        return $this->serializers;
    }

    /**
     * @return array
     */
    public function getNames()
    {
        $names = [];

        /** @var AbstractSerializer $formatter */
        foreach ($this->serializers as $formatter) {
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

        /** @var AbstractSerializer $formatter */
        foreach ($this->serializers as $formatter) {
            $extensionName = $withDot ? ".": "";
            $extensionName .= $formatter->getExtension();
            $extensions[$formatter->getName()] = $extensionName;
        }

        return $extensions;
    }

    public function getExtensionsByTypes($types, $withDot = false)
    {
        $extensions = [];

        /** @var AbstractSerializer $formatter */
        foreach ($this->getFormattersByTypes($types) as $formatter) {
            $extensionName = $withDot ? ".": "";
            $extensionName .= $formatter->getExtension();
            $extensions[$formatter->getName()] = $extensionName;
        }

        return $extensions;
    }

    /**
     * @param $extension
     * @return bool|AbstractSerializer
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

            return $this->serializers[$formatterName];
        }
    }

    public function getFormattersByTypes($types)
    {
        if (!is_array($types)) {
            $types = [$types];
        }

        $selectedFormatters = [];

        /** @var AbstractSerializer $formatter */
        foreach ($this->serializers as $formatter) {
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

        /** @var AbstractSerializer $formatter */
        foreach ($this->getFormattersByTypes($types) as $formatter) {
            $mimeTypes[$formatter->getName()] = $formatter->getMimeType();
        }

        return $mimeTypes;
    }
}
