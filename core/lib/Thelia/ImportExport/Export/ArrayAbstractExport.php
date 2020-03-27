<?php


namespace Thelia\ImportExport\Export;


abstract class ArrayAbstractExport extends AbstractExport
{
    /**
     * @var array Data to export
     */
    private $data;

    public function current()
    {
        return current($this->data);
    }

    public function key()
    {
        return key($this->data);
    }

    public function next()
    {
        next($this->data);
    }

    public function rewind()
    {
        if ($this->data === null) {
            $data = $this->getData();

            if (\is_array($data)) {
                $this->data = $data;
                reset($this->data);

                return;
            }

            throw new \DomainException(
                'Data must be an array.'
            );
        }

        throw new \LogicException('Export data can\'t be rewinded');
    }

    public function valid()
    {
        return key($this->data) !== null;
    }
}