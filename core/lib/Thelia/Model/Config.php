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
use Thelia\Model\Base\Config as BaseConfig;

class Config extends BaseConfig
{
    /**
     * {@inheritDoc}
     */
    public function postUpdate(ConnectionInterface $con = null): void
    {
        parent::postUpdate($con);

        $this->resetQueryCache();
    }

    /**
     * {@inheritDoc}
     */
    public function postDelete(ConnectionInterface $con = null): void
    {
        parent::postDelete($con);

        $this->resetQueryCache();
    }

    public function resetQueryCache(): void
    {
        ConfigQuery::resetCache($this->getName());
    }
}
