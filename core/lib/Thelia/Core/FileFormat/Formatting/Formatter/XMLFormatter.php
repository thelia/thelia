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

namespace Thelia\Core\FileFormat\Formatting\Formatter;
use Thelia\Core\FileFormat\Formatter\Exception\BadFormattedStringException;
use Thelia\Core\FileFormat\Formatting\AbstractFormatter;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\ImportExport\Export\ExportType;

/**
 * Class XMLFormatter
 * @package Thelia\Core\FileFormat\Formatting\Formatter
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class XMLFormatter extends AbstractFormatter
{
    public $root = "data";
    public $nodeName = "node";
    public $rowName = "row";

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

        foreach ($arrayData as $key=>$entry) {
            if (is_array($entry)) {
                $node = $container->appendChild(new \DOMElement($this->nodeName));
                $this->recursiveBuild($entry, $node);
            } else {
                $node = new \DOMElement($this->nodeName);
                $container->appendChild($node);

                /** @var \DOMElement $lastChild */
                $lastChild = $container->lastChild;
                $lastChild->setAttribute("name",$key);
                $lastChild->setAttribute("value", $entry);
            }
        }

        $domDocument->preserveWhiteSpace = false;
        $domDocument->formatOutput = true;

        return $domDocument->saveXML();
    }

    protected function recursiveBuild(array $data, \DOMNode $node)
    {
        foreach ($data as $key=>$entry) {
            if (is_array($entry)) {
                $newNode = $node->appendChild(new \DOMElement($key));
                $this->recursiveBuild($entry, $newNode);
            } else {
                $inputNode = new \DOMElement($this->rowName);
                $node->appendChild($inputNode);

                /** @var \DOMElement $lastChild */
                $lastChild = $node->lastChild;
                $lastChild->setAttribute("name",$key);
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
        try {
            $xml = new \SimpleXMLElement($rawData);
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

        $array = json_decode(json_encode($xml),true);

        if (isset($array[$this->nodeName])) {
            $array += $array[$this->nodeName];
            unset($array[$this->nodeName]);
        }

        $data = new FormatterData($this->getAliases());
        return $data->setData($array);
    }

    public function getExportType()
    {
        return ExportType::EXPORT_UNBOUNDED;
    }
} 