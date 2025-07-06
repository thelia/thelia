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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Sale;

/**
 * Class SaleEvent.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 *
 * @deprecated since 2.4, please use \Thelia\Model\Event\SaleEvent
 */
class SaleEvent extends ActionEvent
{
    public function __construct(protected ?Sale $sale = null)
    {
    }

    public function setSale(Sale $sale): static
    {
        $this->sale = $sale;

        return $this;
    }

    public function getSale(): ?Sale
    {
        return $this->sale;
    }

    /**
     * check if sale exists.
     */
    public function hasSale(): bool
    {
        return $this->sale instanceof Sale;
    }
}
