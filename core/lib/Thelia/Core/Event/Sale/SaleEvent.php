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

namespace Thelia\Core\Event\Sale;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Sale;

/**
 * Class SaleEvent
 * @package Thelia\Core\Event\Sale
 * @author  Franck Allimant <franck@cqfdev.fr>
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
     * @param  \Thelia\Model\Sale $sale
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
     * check if sale exists
     *
     * @return bool
     */
    public function hasSale()
    {
        return null !== $this->sale;
    }
}
