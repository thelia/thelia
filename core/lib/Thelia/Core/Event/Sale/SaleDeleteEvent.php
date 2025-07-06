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

namespace Thelia\Core\Event\Sale;

/**
 * Class SaleDeleteEvent.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class SaleDeleteEvent extends SaleEvent
{
    /**
     * @param int $saleId
     */
    public function __construct(protected $saleId)
    {
    }

    /**
     * @return SaleDeleteEvent $this
     */
    public function setSaleId(int $saleId): static
    {
        $this->saleId = $saleId;

        return $this;
    }

    public function getSaleId(): int
    {
        return $this->saleId;
    }
}
