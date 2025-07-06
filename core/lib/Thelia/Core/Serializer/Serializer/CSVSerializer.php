<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Core\Serializer\Serializer;

use SplFileObject;
use Thelia\Core\Serializer\AbstractSerializer;

/**
 * Class CSVSerializer.
 *
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
     * @var array|null Headers
     */
    private $headers;

    public function getId(): string
    {
        return 'thelia.csv';
    }

    public function getName(): string
    {
        return 'CSV';
    }

    public function getExtension(): string
    {
        return 'csv';
    }

    public function getMimeType(): string
    {
        return 'text/csv';
    }

    /**
     * Set delimiter char.
     *
     * @param string $delimiter Delimiter char
     *
     * @return $this Return $this, allow chaining
     */
    public function setDelimiter($delimiter): self
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * Set enclosure char.
     *
     * @param string $enclosure Enclosure char
     *
     * @return $this Return $this, allow chaining
     */
    public function setEnclosure($enclosure): self
    {
        $this->enclosure = $enclosure;

        return $this;
    }

    public function prepareFile(SplFileObject $fileObject): void
    {
        $this->headers = null;
    }

    public function serialize($data): string|false
    {
        if ($this->headers === null) {
            $this->headers = array_keys($data);
        }

        foreach ($data as &$value) {
            if (\is_array($value)) {
                $value = \gettype($value);
            }
        }

        $fd = fopen('php://memory', 'w+');
        fputcsv($fd, $data, $this->delimiter, $this->enclosure);
        rewind($fd);
        $csvRow = stream_get_contents($fd);
        fclose($fd);

        return $csvRow;
    }

    public function finalizeFile(SplFileObject $fileObject): void
    {
        if ($this->headers !== null) {
            // Create tmp file with header
            $fd = fopen('php://temp', 'w+');
            fputcsv($fd, $this->headers, $this->delimiter, $this->enclosure);

            // Copy file content into tmp file
            $fileObject->rewind();
            fwrite($fd, file_get_contents($fileObject->getPathname()));

            // Overwrite file content with tmp content
            rewind($fd);
            $fileObject->rewind();
            $fileObject->fwrite(stream_get_contents($fd));
            clearstatcache(true, $fileObject->getPathname());

            fclose($fd);
        }

        // Remove last line feed
        $fileObject->ftruncate($fileObject->getSize() - 1);

        clearstatcache(true, $fileObject->getPathname());
    }

    /**
     * @return list<array>
     */
    public function unserialize(SplFileObject $fileObject): array
    {
        $data = [];

        $index = 0;
        while (false !== $row = $fileObject->fgetcsv($this->delimiter, $this->enclosure)) {
            ++$index;
            if (empty($row)) {
                continue;
            }

            if ($index === 1) {
                $this->headers = $row;
                continue;
            }

            if (\count($row) !== \count($this->headers)) {
                continue;
            }

            $data[] = array_combine(
                $this->headers,
                $row
            );
        }

        return $data;
    }
}
