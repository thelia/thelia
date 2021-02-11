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

namespace Thelia\Core\Event\Sale;

/**
 * Class SaleDeleteEvent
 * @package Thelia\Core\Event\Sale
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class SaleDeleteEvent extends SaleEvent
{
    /** @var int */
    protected $saleId;

    /**
     * @param int $saleId
     */
    public function __construct($saleId)
    {
        $this->saleId = $saleId;
    }

    /**
     * @param int $saleId
     *
     * @return SaleDeleteEvent $this
     */
    public function setSaleId($saleId)
    {
        $this->saleId = $saleId;

        return $this;
    }

    /**
     * @return int
     */
    public function getSaleId()
    {
        return $this->saleId;
    }
}
