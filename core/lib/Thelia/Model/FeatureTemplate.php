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
namespace Thelia\Model;

use Thelia\Model\Tools\PositionManagementTrait;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\FeatureTemplate as BaseFeatureTemplate;

class FeatureTemplate extends BaseFeatureTemplate
{
    use PositionManagementTrait;

    /**
     * Calculate next position relative to our template.
     */
    protected function addCriteriaToPositionQuery($query): void
    {
        $query->filterByTemplateId($this->getTemplateId());
    }

    public function preInsert(ConnectionInterface $con = null)
    {
        parent::preInsert($con);

        // Set the current position for the new object
        $this->setPosition($this->getNextPosition());

        return true;
    }
}
