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
use Thelia\Core\Event\Config\ConfigEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Config as BaseConfig;

class Config extends BaseConfig
{
    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null)
    {
        parent::postUpdate($con);

        $this->resetQueryCache();
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null)
    {
        parent::postDelete($con);

        $this->resetQueryCache();
    }

    public function resetQueryCache()
    {
        ConfigQuery::resetCache($this->getName());
    }
}
