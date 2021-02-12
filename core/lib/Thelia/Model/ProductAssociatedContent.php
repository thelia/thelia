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

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\ProductAssociatedContent as BaseProductAssociatedContent;

class ProductAssociatedContent extends BaseProductAssociatedContent
{
    use \Thelia\Model\Tools\PositionManagementTrait;

    /**
     * Calculate next position relative to our product.
     */
    protected function addCriteriaToPositionQuery($query)
    {
        $query->filterByProductId($this->getProductId());
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        $this->setPosition($this->getNextPosition());

        return true;
    }
}
