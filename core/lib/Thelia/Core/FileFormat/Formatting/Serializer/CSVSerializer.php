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

use Thelia\Core\FileFormat\Formatting\AbstractSerializer;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\FileFormat\FormatType;

/**
 * Class CSVSerializer
 * @package Thelia\Core\FileFormat\Formatting\Serializer
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CSVSerializer extends AbstractSerializer
{
    public $delimiter = ";";
    public $lineReturn = "\n";
    public $stringDelimiter = "\"";

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
        return "CSV";
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
        return "csv";
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
        return "text/csv";
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
        $string = "";

        /**
         * Get the first row and delimiters lengths
         */
        $firstRow = $data->getRow();
        $delimiterLength = strlen($this->delimiter);
        $lineReturnLength = strlen($this->lineReturn);

        if ($firstRow === false) {
            return "";
        }

        /**
         * check if $this->order doesn't have non-existing rows
         */
        $this->checkOrders($firstRow);

        $rawKeys = array_keys($firstRow);
        $keys = [];

        foreach ($rawKeys as $key) {
            $keys[$key] = $key;
        }

        $values = $data->getData();
        array_unshift($values, $keys);

        while (null !== $row = array_shift($values)) {
            /**
             * First put the sorted ones
             */
            foreach ($this->order as $order) {
                $string .= $this->formatField($row[$order]);
                unset($row[$order]);
            }

            /**
             * Then place the fields,
             * order by name
             */
            ksort($row);

            foreach ($keys as $key) {
                if (array_key_exists($key, $row)) {
                    $string .= $this->formatField($row[$key]);
                }
            }

            $string = substr($string, 0, -$delimiterLength) . $this->lineReturn;
        }

        return substr($string, 0, -$lineReturnLength);
    }

    protected function formatField($value)
    {
        if ($value === null) {
            $value = "";
        } elseif (!is_scalar($value)) {
            $value = serialize($value);
        }

        $value = str_replace($this->stringDelimiter, "\\" . $this->stringDelimiter, $value);

        return $this->stringDelimiter . $value . $this->stringDelimiter . $this->delimiter;
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
        $rawData = str_replace("\r" . $this->lineReturn, $this->lineReturn, $rawData);
        $rawData = str_replace("\r", $this->lineReturn, $rawData);
        $raw = explode($this->lineReturn, $rawData);

        $result = array();

        if (count($raw) > 0) {
            $keys = str_getcsv($raw[0], $this->delimiter, $this->stringDelimiter);
            unset($raw[0]);
            foreach ($raw as $line) {
                $result[] = array_combine($keys, str_getcsv($line, $this->delimiter, $this->stringDelimiter));
            }
        }

        return (new FormatterData())->setData($result);
    }

    /**
     * @return string
     *
     * return a string that defines the handled format type.
     *
     * Thelia types are defined in \Thelia\Core\FileFormat\FormatType
     *
     * examples:
     *   return FormatType::TABLE;
     *   return FormatType::UNBOUNDED;
     */
    public function getHandledType()
    {
        return FormatType::TABLE;
    }
}
