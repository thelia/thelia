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
use Thelia\Core\FileFormat\Formatting\AbstractFormatter;
use Thelia\Core\FileFormat\Formatting\Exception\BadFormattedStringException;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\FileFormat\FormatType;
use Thelia\Core\Translation\Translator;

/**
 * Class CSVFormatter
 * @package Thelia\Core\FileFormat\Formatting\Formatter
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CSVFormatter extends AbstractFormatter
{
    public $delimiter = ";";
    public $lineReturn = "\r\n";
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

        $firstRow = $data->getRow();
        $delimiterLength = strlen($this->delimiter);
        $lineReturnLength = strlen($this->lineReturn);


        if (false !== $firstRow) {
            $rawKeys = array_keys($firstRow);
            $keys = [];

            foreach ($rawKeys as $key) {
                $keys[$key] = $key;
            }

            $values = $data->getData();
            array_unshift($values, $keys);

            while (null !== $row = array_shift($values)) {
                foreach ($keys as $key) {
                    if (!is_scalar($row[$key])) {
                        $row[$key] = serialize($row[$key]);
                    }

                    $string .= $this->stringDelimiter . addslashes($row[$key]) . $this->stringDelimiter . $this->delimiter;
                }

                $string = substr($string,0, -$delimiterLength) . $this->lineReturn;
            }

        }

        return substr($string,0, -$lineReturnLength);
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
        $raw = explode($this->lineReturn, $rawData);
        $decoded = [];

        if (count($raw) > 0) {
            $keys = explode($this->delimiter, array_shift($raw));

            foreach ($keys as &$key) {
                $key = trim($key, $this->stringDelimiter);
            }

            $columns = count ($keys);

            while (null !== $row = array_shift($raw)) {
                $newRow = [];
                $row = explode($this->delimiter, $row);

                for ($i = 0; $i < $columns; ++$i) {
                    $value = trim($row[$i], $this->stringDelimiter);

                    if (false !== $unserialized = @unserialize($row[$i])) {
                        $value = $unserialized;
                    }

                    $newRow[$keys[$i]] = $value;
                }

                $decoded[] = $newRow;

            }
        }

        return (new FormatterData())->setData($decoded);
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