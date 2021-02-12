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
    /**
     * @var \Thelia\Model\Sale
     */
    protected $sale;

    public function __construct(Sale $sale = null)
    {
        $this->sale = $sale;
    }

    /**
     * @return SaleEvent
     */
    public function setSale(Sale $sale)
    {
        $this->sale = $sale;

        return $this;
    }

    /**
     * @return \Thelia\Model\Sale
     */
    public function getSale()
    {
        return $this->sale;
    }

    /**
     * check if sale exists.
     *
     * @return bool
     */
    public function hasSale()
    {
        return null !== $this->sale;
    }
}
