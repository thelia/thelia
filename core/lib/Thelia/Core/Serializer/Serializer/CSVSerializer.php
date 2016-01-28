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

use Thelia\Core\Serializer\AbstractSerializer;

/**
 * Class CSVSerializer
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class CSVSerializer extends AbstractSerializer
{
    /**
     * @var string CSV delimiter char
     */
    protected $delimiter = ',';

    /**
     * @var string CSV enclosure char
     */
    protected $enclosure = '"';

    /**
     * @var null|array Headers
     */
    private $headers;

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

    /**
     * Set delimiter char
     *
     * @param string $delimiter Delimiter char
     *
     * @return $this Return $this, allow chaining
     */
    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * Set enclosure char
     *
     * @param string $enclosure Enclosure char
     *
     * @return $this Return $this, allow chaining
     */
    public function setEnclosure($enclosure)
    {
        $this->enclosure = $enclosure;

        return $this;
    }

    public function prepareFile(\SplFileObject $fileObject)
    {
        $this->headers = null;
    }

    public function serialize($data)
    {
        if ($this->headers === null) {
            $this->headers = array_keys($data);
        }

        $fd = fopen('php://memory', 'w+b');
        fputcsv($fd, $data, $this->delimiter, $this->enclosure);
        rewind($fd);
        $csvRow = stream_get_contents($fd);
        fclose($fd);

        return $csvRow;
    }

    public function finalizeFile(\SplFileObject $fileObject)
    {
        if ($this->headers !== null) {
            // Create tmp file with header
            $fd = fopen('php://temp', 'w+b');
            fputcsv($fd, $this->headers);

            // Copy file content into tmp file
            $fileObject->rewind();
            fwrite($fd, $fileObject->fread($fileObject->getSize()));

            // Overwrite file content with tmp content
            rewind($fd);
            $fileObject->rewind();
            $fileObject->fwrite(stream_get_contents($fd));
            clearstatcache(true, $fileObject->getPathname());

            fclose($fd);
        }

        // Remove last line feed
        $fileObject->ftruncate($fileObject->getSize() - 1);
    }

    public function unserialize(\SplFileObject $fileObject)
    {
        $data = [];

        foreach ($fileObject as $index => $row) {
            if (empty($row)) {
                continue;
            }

            if ($index === 0) {
                $this->headers = str_getcsv($row, $this->delimiter, $this->enclosure);
                continue;
            }

            $data[] = array_combine(
                $this->headers,
                str_getcsv($row, $this->delimiter, $this->enclosure)
            );
        }

        return $data;
    }
}
