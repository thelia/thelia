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

namespace Thelia\Domain\DataTransfer\Export;

use Propel\Runtime\Connection\StatementInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Exception\DataTransferNoDataFoundException;
use Thelia\Domain\DataTransfer\Service\ExportCachePurger;

/**
 * Class JsonFileAbstractExport.
 *
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
abstract class JsonFileAbstractExport extends AbstractExport
{
    /** @var \SplFileObject Data to export */
    private ?\SplFileObject $data = null;

    public function current(): mixed
    {
        $dataCurrent = $this->data->current();
        if (empty($dataCurrent)) {
            return [];
        }
        $result = json_decode($this->data->current(), true, 512, \JSON_THROW_ON_ERROR);

        return $result ?? [];
    }

    public function key(): mixed
    {
        return $this->data->key();
    }

    public function next(): void
    {
        $this->data->next();
    }

    public function rewind(): void
    {
        if (!$this->data instanceof \SplFileObject) {
            $data = $this->getData();

            // Check if $data is a path to a json file
            if (\is_string($data)
                && str_ends_with($data, '.json')
                && file_exists($data)
            ) {
                $this->data = self::openAndForget($data);

                return;
            }

            throw new \DomainException('Data should be a JSON file, ending with .json');
        }

        throw new \LogicException("Export data can't be rewinded");
    }

    public function valid(): bool
    {
        return $this->data->valid();
    }

    /**
     * Apply order and aliases on data.
     *
     * @param array $data Raw data
     *
     * @return array Ordered and aliased data
     */
    public function applyOrderAndAliases(array $data): array
    {
        if (null === $this->orderAndAliases || [] === $this->orderAndAliases) {
            return $data;
        }

        $processedData = [];

        foreach ($this->orderAndAliases as $key => $value) {
            $fieldName = \is_int($key) ? $value : $key;

            $fieldAlias = $value;

            $processedData[$fieldAlias] = null;

            if (\array_key_exists($fieldName, $data)) {
                $processedData[$fieldAlias] = $data[$fieldName];
            }
        }

        return $processedData;
    }

    protected function getDataJsonCache(StatementInterface $statement, string $exportName): string
    {
        if (0 === $statement->rowCount()) {
            throw new DataTransferNoDataFoundException(Translator::getInstance()->trans('No data found for your export.'));
        }

        // A name of its own: two workers may run the same export at once.
        $filename = ExportCachePurger::directory().DS.$exportName.'-'.bin2hex(random_bytes(8)).'.json';
        (new Filesystem())->mkdir(\dirname($filename));

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            file_put_contents($filename, json_encode($row, \JSON_THROW_ON_ERROR)."\r\n", \FILE_APPEND);
        }

        return $filename;
    }

    /**
     * Opens the rows an export read, then removes the file: the open file is read to its
     * end all the same, and the customer data it holds outlives no export, even one whose
     * worker dies half way.
     */
    public static function openAndForget(string $path): \SplFileObject
    {
        $file = new \SplFileObject($path, 'r');
        $file->setFlags(\SplFileObject::READ_AHEAD);
        $file->rewind();

        try {
            (new Filesystem())->remove($path);
        } catch (\Throwable) {
            // A file that cannot go yet (an open file on Windows) is the purge's to sweep.
        }

        return $file;
    }
}
