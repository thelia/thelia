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

namespace Thelia\Tests\Unit\Api\Bridge\Propel\Service\Fixture;

use Thelia\Model\Product;

/**
 * A product model whose extra setters remember how they were called, standing for the
 * columns and virtual fields a model may expose.
 */
class RecordingProduct extends Product
{
    public const TABLE_MAP = RecordingProductTableMap::class;

    /** @var array<string, list<list<mixed>>> */
    public array $calls = [];

    public function setTitle(?string $v = null): static
    {
        $this->calls[__FUNCTION__][] = [$this->getLocale(), $v];

        return $this;
    }

    public function setNote(?string $note): static
    {
        $this->calls[__FUNCTION__][] = \func_get_args();

        return $this;
    }

    public function setAmount(string $amount): static
    {
        $this->calls[__FUNCTION__][] = \func_get_args();

        return $this;
    }

    public function setCode(string $code): static
    {
        $this->calls[__FUNCTION__][] = \func_get_args();

        return $this;
    }

    public function setQuantity(int $quantity): static
    {
        $this->calls[__FUNCTION__][] = \func_get_args();

        return $this;
    }

    public function setStamp(?string $stamp, bool $force = false): static
    {
        $this->calls[__FUNCTION__][] = \func_get_args();

        return $this;
    }

    public function setShelf(?object $shelf): static
    {
        $this->calls[__FUNCTION__][] = \func_get_args();

        return $this;
    }

    public function setShelfId(?int $shelfId): static
    {
        $this->calls[__FUNCTION__][] = \func_get_args();

        return $this;
    }

    public function setProductId(?int $productId): static
    {
        $this->calls[__FUNCTION__][] = \func_get_args();

        return $this;
    }
}
