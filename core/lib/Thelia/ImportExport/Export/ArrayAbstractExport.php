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
namespace Thelia\ImportExport\Export;

use DomainException;
use LogicException;

abstract class ArrayAbstractExport extends AbstractExport
{
    /**
     * @var array Data to export
     */
    private ?array $data = null;

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
        if ($this->data === null) {
            $data = $this->getData();

            if (\is_array($data)) {
                $this->data = $data;
                reset($this->data);

                return;
            }

            throw new DomainException(
                'Data must be an array.'
            );
        }

        throw new LogicException("Export data can't be rewinded");
    }

    public function valid(): bool
    {
        return key($this->data) !== null;
    }
}
