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

abstract class PropelCollectionAbstractExport extends AbstractExport
{
    /**
     * @var \Propel\Runtime\Util\PropelModelPager Data to export
     */
    private $data;

    /**
     * @return array|false|mixed|string
     *
     * @throws \Exception
     */
    public function current()
    {
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
        $this->data->getIterator()->next();
        if (!$this->valid() && !$this->data->isLastPage()) {
            $this->data = $this->data->getQuery()->paginate($this->data->getNextPage(), 1000);
            $this->data->getIterator()->rewind();
        }
    }

    /**
     * @throws \Exception
     */
    public function rewind(): void
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

    /**
     * @throws \Exception
     */
    public function valid(): bool
    {
        return $this->data->getIterator()->valid();
    }
}
