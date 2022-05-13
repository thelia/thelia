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

namespace Thelia\ImportExport\Export;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\TableMap;
use SplFileObject;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;

/**
 * Class AbstractExport.
 *
 * @deprecated since 2.4, please use a specific AbstractExport (like JsonFileAbstractExport).
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
abstract class AbstractExport implements \Iterator
{
    /**
     * @var string Default file name
     */
    public const FILE_NAME = 'export';

    /**
     * @var bool Export images with data
     */
    public const EXPORT_IMAGE = false;

    /**
     * @var bool Export documents with data
     */
    public const EXPORT_DOCUMENT = false;

    /**
     * @var bool Use range date
     */
    public const USE_RANGE_DATE = false;

    /**
     * @var SplFileObject|\Propel\Runtime\Util\PropelModelPager Data to export
     */
    private $data;

    /**
     * @var bool True if data is array, false otherwise
     */
    private $dataIsArray;

    /**
     *  @var bool True if data is a path to a JSON file, false otherwise
     */
    private $dataIsJSONFile;

    /**
     * @var \Thelia\Model\Lang A language model
     */
    protected $language;

    /**
     * @var array|null List of fields in order in which they must be exported and there alias name
     */
    protected $orderAndAliases;

    /**
     * @var array|null Keep untranslated
     */
    private $originalOrderAndAliases;

    /**
     * @var bool Whether to export images or not
     */
    protected $exportImages = false;

    /**
     * @var array Images paths list
     */
    protected $imagesPaths = [];

    /**
     * @var bool Whether to export documents or not
     */
    protected $exportDocuments = false;

    /**
     * @var array Documents paths list
     */
    protected $documentsPaths = [];

    /**
     * @var array|null Export date range
     */
    protected $rangeDate;

    /**
     * @return array|false|mixed|string
     *
     * @throws \Exception
     */
    public function current()
    {
        if ($this->dataIsJSONFile) {
            /** @var resource $file */
            $result = json_decode($this->data->current(), true);

            if ($result !== null) {
                return $result;
            }

            return [];
        }

        if ($this->dataIsArray) {
            return current($this->data);
        }

        $data = $this->data->getIterator()->current()->toArray(TableMap::TYPE_COLNAME, true, [], true);

        foreach ($this->data->getQuery()->getWith() as $withKey => $with) {
            $data = array_merge($data, $data[$withKey]);
            unset($data[$withKey]);
        }

        return $data;
    }

    /**
     * @return bool|float|int|string|null
     *
     * @throws \Exception
     */
    public function key()
    {
        if ($this->dataIsJSONFile) {
            return $this->data->key();
        }

        if ($this->dataIsArray) {
            return key($this->data);
        }

        if ($this->data->getIterator()->key() !== null) {
            return $this->data->getIterator()->key() + ($this->data->getPage() - 1) * 1000;
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function next(): void
    {
        if ($this->dataIsJSONFile) {
            $this->data->next();
        } elseif ($this->dataIsArray) {
            next($this->data);
        } else {
            $this->data->getIterator()->next();
            if (!$this->valid() && !$this->data->isLastPage()) {
                $this->data = $this->data->getQuery()->paginate($this->data->getNextPage(), 1000);
                $this->data->getIterator()->rewind();
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function rewind(): void
    {
        // Since it's first method call on traversable, we get raw data here
        // but we do not permit to go back

        if ($this->data === null) {
            $data = $this->getData();

            // Check if $data is a path to a json file
            if (\is_string($data)
                && '.json' === substr($data, -5)
                && file_exists($data)
            ) {
                $this->data = new \SplFileObject($data, 'r');
                $this->data->setFlags(SPLFileObject::READ_AHEAD);
                $this->dataIsJSONFile = true;

                $this->data->rewind();

                return;
            }

            if (\is_array($data)) {
                $this->data = $data;
                $this->dataIsArray = true;
                reset($this->data);

                return;
            }

            if ($data instanceof ModelCriteria) {
                $this->data = $data->setFormatter(ModelCriteria::FORMAT_ON_DEMAND)->keepQuery(false)->paginate(1, 1000);
                $this->data->getIterator()->rewind();

                return;
            }

            throw new \DomainException(
                'Data must an array, an instance of \\Propel\\Runtime\\ActiveQuery\\ModelCriteria or a JSON file ending with.json'
            );
        }

        throw new \LogicException('Export data can\'t be rewinded');
    }

    /**
     * @throws \Exception
     */
    public function valid(): bool
    {
        if ($this->dataIsJSONFile) {
            return $this->data->valid();
        }

        if ($this->dataIsArray) {
            return key($this->data) !== null;
        }

        return $this->data->getIterator()->valid();
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

        if ($this->originalOrderAndAliases === null) {
            $this->originalOrderAndAliases = $this->orderAndAliases;
        }

        if ($this->language !== null && $this->orderAndAliases !== null) {
            $previousLocale = Translator::getInstance()->getLocale();

            Translator::getInstance()->setLocale($this->language->getLocale());
            foreach ($this->orderAndAliases as &$alias) {
                $alias = Translator::getInstance()->trans($alias);
            }

            Translator::getInstance()->setLocale($previousLocale);
        }

        return $this;
    }

    /**
     * Check if export is empty.
     *
     * @return bool true if export is empty, else false
     */
    public function isEmpty()
    {
        return empty($this->data);
    }

    /**
     * Whether images has to be exported as data.
     *
     * @return bool
     */
    public function hasImages()
    {
        return static::EXPORT_IMAGE;
    }

    /**
     * Get export images.
     *
     * @return bool Whether to export images or not
     */
    public function isExportImages()
    {
        return $this->exportImages;
    }

    /**
     * Set export images.
     *
     * @param bool $exportImages Whether to export images or not
     *
     * @return $this Return $this, allow chaining
     */
    public function setExportImages($exportImages)
    {
        $this->exportImages = $exportImages;

        return $this;
    }

    /**
     * Get images paths.
     *
     * @return array|null Images paths list
     */
    public function getImagesPaths()
    {
        return $this->imagesPaths;
    }

    /**
     * Set images paths.
     *
     * @param array $imagesPaths Images paths list
     *
     * @return $this Return $this, allow chaining
     */
    public function setImagesPaths(array $imagesPaths)
    {
        $this->imagesPaths = $imagesPaths;

        return $this;
    }

    /**
     * Whether documents has to be exported as data.
     *
     * @return bool
     */
    public function hasDocuments()
    {
        return static::EXPORT_DOCUMENT;
    }

    /**
     * Get export documents.
     *
     * @return bool Whether to export documents or not
     */
    public function isExportDocuments()
    {
        return $this->exportDocuments;
    }

    /**
     * Set export documents.
     *
     * @param bool $exportDocuments Whether to export documents or not
     *
     * @return $this Return $this, allow chaining
     */
    public function setExportDocuments($exportDocuments)
    {
        $this->exportDocuments = $exportDocuments;

        return $this;
    }

    /**
     * Get documents paths.
     *
     * @return array|null Documents paths list
     */
    public function getDocumentsPaths()
    {
        return $this->documentsPaths;
    }

    /**
     * Set documents paths.
     *
     * @param array $documentsPaths Documents paths list
     *
     * @return $this Return $this, allow chaining
     */
    public function setDocumentsPaths(array $documentsPaths)
    {
        $this->documentsPaths = $documentsPaths;

        return $this;
    }

    /**
     * Get range date.
     *
     * @return array|null Array with date range
     */
    public function getRangeDate()
    {
        return $this->rangeDate;
    }

    /**
     * Set range date.
     *
     * @param array|null $rangeDate Array with date range
     *
     * @return $this Return $this, allow chaining
     */
    public function setRangeDate(array $rangeDate = null)
    {
        $this->rangeDate = $rangeDate;

        return $this;
    }

    /**
     * Whether export bounded with date.
     *
     * @return bool
     */
    public function useRangeDate()
    {
        return static::USE_RANGE_DATE;
    }

    /**
     * Get file name.
     *
     * @return string Export file name
     */
    public function getFileName()
    {
        return static::FILE_NAME;
    }

    /**
     * Apply order and aliases on data.
     *
     * @param array $data Raw data
     *
     * @return array Ordered and aliased data
     */
    public function applyOrderAndAliases(array $data)
    {
        if ($this->orderAndAliases === null) {
            return $data;
        }

        $processedData = [];

        foreach ($this->orderAndAliases as $key => $value) {
            if (\is_int($key)) {
                $fieldName = $value;
                $fieldAlias = $value;
            } else {
                $fieldName = $key;
                $fieldAlias = $value;
            }

            if ($this->dataIsJSONFile) {
                $fieldName = substr($fieldName, strripos($fieldName, '.'));
                $fieldName = str_replace('.', '', $fieldName);
                $fieldName = strtolower($fieldName);
            }

            $processedData[$fieldAlias] = null;
            if (\array_key_exists($fieldName, $data)) {
                $processedData[$fieldAlias] = $data[$fieldName];
            }
        }

        return $processedData;
    }

    /**
     * Process data before serialization.
     *
     * @param array $data Data before serialization
     *
     * @return array Processed data before serialization
     */
    public function beforeSerialize(array $data)
    {
        foreach ($data as $idx => &$value) {
            if ($value instanceof \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }

    /**
     * Process data after serialization.
     *
     * @param string $data Data after serialization
     *
     * @return string Processed after before serialization
     */
    public function afterSerialize($data)
    {
        return $data;
    }

    /**
     * Get data to export.
     *
     * @return string|array|\Propel\Runtime\ActiveQuery\ModelCriteria Data to export
     */
    abstract protected function getData();
}
