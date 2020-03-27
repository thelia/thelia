<?php


namespace Thelia\ImportExport\Export;


use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;

abstract class PropelCollectionAbstractExport extends AbstractExport
{
    /**
     * @var \Propel\Runtime\Util\PropelModelPager Data to export
     */
    private $data;

    public function current()
    {
        $data = $this->data->getIterator()->current()->toArray(TableMap::TYPE_COLNAME, true, [], true);

        foreach ($this->data->getQuery()->getWith() as $withKey => $with) {
            $data = array_merge($data, $data[$withKey]);
            unset($data[$withKey]);
        }

        return $data;
    }

    public function key()
    {
        if ($this->data->getIterator()->key() !== null) {
            return $this->data->getIterator()->key() + ($this->data->getPage() - 1) * 1000;
        }

        return null;
    }

    public function next()
    {
        $this->data->getIterator()->next();
        if (!$this->valid() && !$this->data->isLastPage()) {
            $this->data = $this->data->getQuery()->paginate($this->data->getNextPage(), 1000);
            $this->data->getIterator()->rewind();
        }
    }

    public function rewind()
    {
        if ($this->data === null) {
            $data = $this->getData();

            if ($data instanceof ModelCriteria) {
                $this->data = $data->setFormatter(ModelCriteria::FORMAT_ON_DEMAND)->keepQuery(false)->paginate(1, 1000);
                $this->data->getIterator()->rewind();

                return;
            }

            throw new \DomainException(
                'Data must be an instance of \\Propel\\Runtime\\ActiveQuery\\ModelCriteria'
            );
        }

        throw new \LogicException('Export data can\'t be rewinded');
    }

    public function valid()
    {
        return $this->data->getIterator()->valid();
    }
}