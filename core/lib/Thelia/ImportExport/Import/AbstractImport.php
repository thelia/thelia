<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\ImportExport\Import;

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
    /**
     * @var array
     */
    private $data;

    /**
     * @var \Symfony\Component\HttpFoundation\File\File
     */
    protected $file;

    /**
     * @var \Thelia\Model\Lang A language model
     */
    protected $language;

    /**
     * @var array Mandatory columns
     */
    protected $mandatoryColumns = [];

    /**
     * @var int Imported row count
     */
    protected $importedRows = 0;

    public function current()
    {
        return current($this->data);
    }

    public function key()
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
        return key($this->data) !== null;
    }

    /**
     * Get data.
     *
     * @return array Parsed data
     */
    public function getData()
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
     *
     * @return \Symfony\Component\HttpFoundation\File\File
     */
    public function getFile()
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
     * @return \Thelia\Model\Lang A language model
     */
    public function getLang()
    {
        return $this->language;
    }

    /**
     * Set language.
     *
     * @param \Thelia\Model\Lang|null $language A language model
     *
     * @return $this Return $this, allow chaining
     */
    public function setLang(Lang $language = null)
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

        if (\count($diff) > 0) {
            throw new \UnexpectedValueException(
                Translator::getInstance()->trans(
                    'The following columns are missing: %columns',
                    [
                        '%columns' => implode(', ', $diff),
                    ]
                )
            );
        }
    }

    /**
     * Get imported rows.
     *
     * @return int Imported rows count
     */
    public function getImportedRows()
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
    public function setImportedRows($importedRows)
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
    abstract public function importData(array $data);
}
