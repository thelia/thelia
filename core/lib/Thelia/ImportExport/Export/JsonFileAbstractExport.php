<?php


namespace Thelia\ImportExport\Export;


use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\TableMap;
use SplFileObject;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;

abstract class JsonFileAbstractExport extends AbstractExport
{
    /**
     * @var SplFileObject Data to export
     */
    private $data;

    public function current()
    {
        /** @var resource $file */
        $result = json_decode($this->data->current(), true);

        if($result != null){
            return $result;
        }

        return [];
    }

    public function key()
    {
        return $this->data->key();
    }

    public function next()
    {
        $this->data->next();
    }

    public function rewind()
    {
        if ($this->data === null) {
            $data = $this->getData();

            // Check if $data is a path to a json file
            if (\is_string($data)
                && ".json" === substr($data, -5)
                && file_exists($data))
            {
                $this->data = new \SplFileObject($data, 'r');
                $this->data->setFlags(SPLFileObject::READ_AHEAD);

                $this->data->rewind();

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
        return $this->data->valid();
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
            if (\is_integer($key)) {
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
}