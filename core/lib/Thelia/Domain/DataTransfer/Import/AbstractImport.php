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

namespace Thelia\Domain\DataTransfer\Import;

use Symfony\Component\HttpFoundation\File\File;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;

/**
 * Class AbstractImport.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
abstract class AbstractImport implements \Iterator
{
    private ?array $data = null;
    protected File $file;
    protected Lang $language;
    protected array $mandatoryColumns = [];
    protected int $importedRows = 0;

    #[\ReturnTypeWillChange]
    public function current(): mixed
    {
        return current($this->data);
    }

    #[\ReturnTypeWillChange]
    public function key(): int|string|null
    {
        return key($this->data);
    }

    public function next(): void
    {
        next($this->data);
    }

    public function rewind(): void
    {
        reset($this->data);
    }

    public function valid(): bool
    {
        return null !== key($this->data);
    }

    /**
     * Get data.
     *
     * @return array Parsed data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set data.
     *
     * @param array $data Parsed data
     *
     * @return $this Return $this, allow chaining
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get file.
     */
    public function getFile(): File
    {
        return $this->file;
    }

    /**
     * Set file.
     *
     * @return $this Return $this, allow chaining
     */
    public function setFile(File $file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Get language.
     *
     * @return Lang A language model
     */
    public function getLang(): Lang
    {
        return $this->language;
    }

    /**
     * Set language.
     *
     * @param Lang|null $language A language model
     *
     * @return $this Return $this, allow chaining
     */
    public function setLang(?Lang $language = null)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Check mandatory columns.
     *
     * @param array $data Data
     *
     * @return bool Data contains mandatory columns or not
     */
    public function checkMandatoryColumns(array $data): void
    {
        $diff = array_diff($this->mandatoryColumns, array_keys($data));

        if ([] !== $diff) {
            throw new \UnexpectedValueException(Translator::getInstance()->trans('The following columns are missing: %columns', ['%columns' => implode(', ', $diff)]));
        }
    }

    /**
     * Get imported rows.
     *
     * @return int Imported rows count
     */
    public function getImportedRows(): int
    {
        return $this->importedRows;
    }

    /**
     * Set imported rows.
     *
     * @param int $importedRows Imported rows count
     *
     * @return $this Return $this, allow chaining
     */
    public function setImportedRows(int $importedRows)
    {
        $this->importedRows = $importedRows;

        return $this;
    }

    /**
     * Import data.
     *
     * @param array $data Data to import
     *
     * @return string|null String with error, null otherwise
     */
    abstract public function importData(array $data): ?string;
}
