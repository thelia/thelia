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

namespace Thelia\Core\Serializer\Serializer;

use Thelia\Core\Serializer\SerializerInterface;

/**
 * Class CSVSerializer
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class CSVSerializer implements SerializerInterface
{
    /**
     * @var string CSV delimiter char
     */
    const DELIMITER = ',';

    /**
     * @var string CSV enclosure char
     */
    const ENCLOSURE = '"';

    public function getId()
    {
        return 'thelia.csv';
    }

    public function getName()
    {
        return 'CSV';
    }

    public function getExtension()
    {
        return 'csv';
    }

    public function getMimeType()
    {
        return 'text/csv';
    }

    public function wrapOpening()
    {
        return null;
    }

    public function serialize($data)
    {
        $fd = fopen('php://memory', 'r+b');
        fputcsv($fd, $data, static::DELIMITER, static::ENCLOSURE);
        rewind($fd);
        $csvRow = stream_get_contents($fd);
        fclose($fd);

        return $csvRow;
    }

    public function separator()
    {
        return null;
    }

    public function wrapClosing()
    {
        return null;
    }

    public function unserialize()
    {
        // TODO: Implement unserialize() method.
    }
}
