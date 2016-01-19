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

namespace Thelia\ImportExport\Export;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\TableMap;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;

/**
 * Class AbstractExport
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
abstract class AbstractExport implements \Iterator
{
    /**
     * @var string Default file name
     */
    const FILE_NAME = 'export';

    /**
     * @var boolean Export images with data
     */
    const EXPORT_IMAGE = false;

    /**
     * @var boolean Export documents with data
     */
    const EXPORT_DOCUMENT = false;

    /**
     * @var array|\Propel\Runtime\Util\PropelModelPager Data to export
     */
    private $data;

    /**
     * @var boolean True if data is array, false otherwise
     */
    private $dataIsArray;

    /**
     * @var \Thelia\Model\Lang A language model
     */
    protected $language;

    /**
     * @var null|array List of fields in order in which they must be exported and there alias name
     */
    protected $orderAndAliases;

    /**
     * @var null|array Keep untranslated $orderAndAliases
     */
    private $originalOrderAndAliases;

    /**
     * @var boolean Whether to export images or not
     */
    protected $exportImages = false;

    /**
     * @var array Images paths list
     */
    protected $imagesPaths = [];

    /**
     * @var boolean Whether to export documents or not
     */
    protected $exportDocuments = false;

    /**
     * @var array Documents paths list
     */
    protected $documentsPaths = [];

    public function current()
    {
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

    public function key()
    {
        if ($this->dataIsArray) {
            return key($this->data);
        }

        if ($this->data->getIterator()->key() !== null) {
            return $this->data->getIterator()->key() + ($this->data->getPage() - 1) * 1000;
        }

        return null;
    }

    public function next()
    {
        if ($this->dataIsArray) {
            next($this->data);
        } else {
            $this->data->getIterator()->next();
            if (!$this->valid() && !$this->data->isLastPage()) {
                $this->data = $this->data->getQuery()->paginate($this->data->getNextPage(), 1000);
                $this->data->getIterator()->rewind();
            }
        }
    }

    public function rewind()
    {
        // Since it's first method call on traversable, we get raw data here
        // but we do not permit to go back

        if ($this->data === null) {
            $data = $this->getData();

            if (is_array($data)) {
                $this->data = $data;
                $this->dataIsArray = true;

                return;
            }

            if ($data instanceof ModelCriteria) {
                $this->data = $data->setFormatter(ModelCriteria::FORMAT_ON_DEMAND)->keepQuery(false)->paginate(1, 1000);
                $this->data->getIterator()->rewind();

                return;
            }

            throw new \DomainException(
                'Data must an array or an instance of \\Propel\\Runtime\\ActiveQuery\\ModelCriteria'
            );
        }

        throw new \LogicException('Export data can\'t be rewinded');
    }

    public function valid()
    {
        if ($this->dataIsArray) {
            return key($this->data) !== null;
        }

        return $this->data->getIterator()->valid();
    }


    /**
     * Get language
     *
     * @return \Thelia\Model\Lang A language model
     */
    public function getLang()
    {
        return $this->language;
    }

    /**
     * Set language
     *
     * @param null|\Thelia\Model\Lang $language A language model
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
     * Whether images has to be exported as data
     *
     * @return bool
     */
    public function hasImages()
    {
        return static::EXPORT_IMAGE;
    }

    /**
     * Get export images
     *
     * @return boolean Whether to export images or not
     */
    public function isExportImages()
    {
        return $this->exportImages;
    }

    /**
     * Set export images
     *
     * @param boolean $exportImages Whether to export images or not
     *
     * @return $this Return $this, allow chaining
     */
    public function setExportImages($exportImages)
    {
        $this->exportImages = $exportImages;

        return $this;
    }

    /**
     * Get images paths
     *
     * @return null|array Images paths list
     */
    public function getImagesPaths()
    {
        return $this->imagesPaths;
    }

    /**
     * Set images paths
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
     * Whether documents has to be exported as data
     *
     * @return bool
     */
    public function hasDocuments()
    {
        return static::EXPORT_DOCUMENT;
    }

    /**
     * Get export documents
     *
     * @return boolean Whether to export documents or not
     */
    public function isExportDocuments()
    {
        return $this->exportDocuments;
    }

    /**
     * Set export documents
     *
     * @param boolean $exportDocuments Whether to export documents or not
     *
     * @return $this Return $this, allow chaining
     */
    public function setExportDocuments($exportDocuments)
    {
        $this->exportDocuments = $exportDocuments;

        return $this;
    }

    /**
     * Get documents paths
     *
     * @return null|array Documents paths list
     */
    public function getDocumentsPaths()
    {
        return $this->documentsPaths;
    }

    /**
     * Set documents paths
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
     * Get file name
     *
     * @return string Export file name
     */
    public function getFileName()
    {
        return static::FILE_NAME;
    }

    /**
     * Apply order and aliases on data
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
            if (is_integer($key)) {
                $fieldName = $value;
                $fieldAlias = $value;
            } else {
                $fieldName = $key;
                $fieldAlias = $value;
            }

            $processedData[$fieldAlias] = null;
            if (array_key_exists($fieldName, $data)) {
                $processedData[$fieldAlias] = $data[$fieldName];
            }
        }

        return $processedData;
    }

    /**
     * Process data before serialization
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
            } elseif (is_array($value)) {
                unset($data[$idx]);
            }
        }

        return $data;
    }

    /**
     * Process data after serialization
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
     * Get data to export
     *
     * @return array|\Propel\Runtime\ActiveQuery\ModelCriteria Data to export
     */
    abstract protected function getData();
}
