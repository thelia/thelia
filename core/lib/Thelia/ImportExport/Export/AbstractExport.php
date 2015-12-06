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

/**
 * Class AbstractExport
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
abstract class AbstractExport implements \Iterator
{
    /**
     * @var array|\Propel\Runtime\Util\PropelModelPager Data to export
     */
    private $data;

    /**
     * @var boolean True if data is array, false otherwise
     */
    private $dataIsArray;

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
     * @return array|\Propel\Runtime\ActiveQuery\ModelCriteria
     */
    abstract protected function getData();
}
