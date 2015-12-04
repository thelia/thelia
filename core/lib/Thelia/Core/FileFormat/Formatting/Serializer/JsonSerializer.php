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
 * Class JsonSerializer
 * @package Thelia\Core\FileFormat\Formatting\Serializer
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class JsonSerializer extends AbstractSerializer
{
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
        return "Json";
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
        return "json";
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
        return "application/json";
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
        return json_encode($data->getData());
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
        return (new FormatterData())->setData(
            json_decode($rawData, true)
        );
    }

    public function getHandledType()
    {
        return FormatType::UNBOUNDED;
    }
}
