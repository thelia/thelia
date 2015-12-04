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

namespace Thelia\Core\FileFormat\Formatting\Serializer;

use Symfony\Component\DependencyInjection\SimpleXMLElement;
use Thelia\Core\FileFormat\Formatting\Exception\BadFormattedStringException;
use Thelia\Core\FileFormat\Formatting\AbstractSerializer;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\FileFormat\FormatType;

/**
 * Class XMLSerializer
 * @package Thelia\Core\FileFormat\Formatting\Serializer
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class XMLSerializer extends AbstractSerializer
{
    public $root = "data";
    public $rowName = "row";
    public $nodeName = "entry";

    /**
     * @return string
     *
     * This method must return a string, the name of the format.
     *
     * example:
     * return "XML";
     */
    public function getName()
    {
        return "XML";
    }

    /**
     * @return string
     *
     * This method must return a string, the extension of the file format, without the ".".
     * The string should be lowercase.
     *
     * example:
     * return "xml";
     */
    public function getExtension()
    {
        return "xml";
    }

    /**
     * @return string
     *
     * This method must return a string, the mime type of the file format.
     *
     * example:
     * return "application/json";
     */
    public function getMimeType()
    {
        return "application/xml";
    }

    /**
     * @param  FormatterData $data
     * @return mixed
     *
     * This method must use a FormatterData object and output
     * a formatted value.
     */
    public function encode(FormatterData $data)
    {
        $arrayData = $data->getData();

        $domDocument = new \DOMDocument("1.0");
        $container = $domDocument->appendChild(new \DOMElement($this->root));

        foreach ($arrayData as $key => $entry) {
            if (is_array($entry)) {
                $node = $container->appendChild(new \DOMElement($this->rowName));
                $this->recursiveBuild($entry, $node);
            } else {
                $node = new \DOMElement($this->nodeName);
                $container->appendChild($node);

                /** @var \DOMElement $lastChild */
                $lastChild = $container->lastChild;
                $lastChild->setAttribute("name", $key);
                $lastChild->setAttribute("value", $entry);
            }
        }

        $domDocument->preserveWhiteSpace = false;
        $domDocument->formatOutput = true;

        return $domDocument->saveXML();
    }

    protected function recursiveBuild(array $data, \DOMNode $node)
    {
        foreach ($data as $key => $entry) {
            if (is_array($entry)) {
                $newNode = $node->appendChild(new \DOMElement($key));
                $this->recursiveBuild($entry, $newNode);
            } else {
                $inputNode = new \DOMElement($this->nodeName);
                $node->appendChild($inputNode);

                /** @var \DOMElement $lastChild */
                $lastChild = $node->lastChild;
                $lastChild->setAttribute("name", $key);
                $lastChild->setAttribute("value", $entry);
            }
        }
    }

    /**
     * @param $rawData
     * @return FormatterData
     *
     * This must takes raw data as argument and outputs
     * a FormatterData object.
     */
    public function decode($rawData)
    {
        $raw = $this->rawDecode($rawData);

        return (new FormatterData())->setData($this->recursiveDecode($raw));
    }

    public function recursiveDecode(array &$data, array &$parent = null)
    {
        $row = [];

        foreach ($data as $name => &$child) {
            if (is_array($child)) {
                $data = $this->recursiveDecode($child, $data);
            }

            if ($name === "name" || $name === "value") {
                $row[$name] = $this->getValue($name, $data);
            }

            if (count($row) == 2) {
                reset($parent);

                if (is_int($key = key($parent))) {
                    $parent[$row["name"]] = $row["value"];
                    unset($parent[$key]);
                } else {
                    $data[$row["name"]] = $row["value"];
                }

                $row=[];
            }
        }

        return $parent === null ? $data : $parent;
    }

    public function getValue($name, array &$data)
    {
        $value = $data[$name];
        unset($data[$name]);

        return $value;
    }

    /**
     * @param $rawData
     * @return array
     * @throws \Thelia\Core\FileFormat\Formatting\Exception\BadFormattedStringException
     */
    public function rawDecode($rawData)
    {
        try {
            $xml = new SimpleXMLElement($rawData);
        } catch (\Exception $e) {
            $errorMessage = $this->translator->trans(
                "You tried to load a bad formatted XML"
            );

            $this->logger->error(
                $errorMessage .": ". $e->getMessage()
            );

            throw new BadFormattedStringException(
                $errorMessage
            );
        }

        $array = [];

        foreach ($xml->children() as $child) {
            $this->recursiveRawDecode($array, $child);
        }

        return $array;
    }

    protected function recursiveRawDecode(array &$data, \SimpleXMLElement $node)
    {
        if ($node->count()) {
            if (!array_key_exists($node->getName(), $data)) {
                $data[$node->getName()] = [];
            }
            $row = &$data[$node->getName()];

            foreach ($node->children() as $child) {
                $this->recursiveRawDecode($row, $child);
            }
        } else {
            $newRow = array();

            /** @var SimpleXMLElement $attribute */
            foreach ($node->attributes() as $attribute) {
                $newRow[$attribute->getName()] = $node->getAttributeAsPhp($attribute->getName());
            }

            if ($node->getName() === $this->nodeName) {
                $data[] = $newRow;
            } else {
                $data[$node->getName()] = $newRow;
            }
        }
    }

    public function getHandledType()
    {
        return FormatType::UNBOUNDED;
    }
}
