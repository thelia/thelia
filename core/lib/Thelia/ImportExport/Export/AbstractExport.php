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

    public function current()
    {
        if ($this->dataIsArray) {
            return current($this->data);
        }

        return $this->data->getIterator()->current()->toArray();
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

            throw new \Exception('TODO ' . __FILE__);
        }

        throw new \Exception('TODO ' . __FILE__);
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
     * @param \Thelia\Model\Lang $language A language model
     *
     * @return $this Return $this, allow chaining
     */
    public function setLang(Lang $language)
    {
        $this->language = $language;

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
