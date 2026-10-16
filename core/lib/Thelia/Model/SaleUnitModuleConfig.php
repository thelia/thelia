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

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\SaleUnitModuleConfig as BaseSaleUnitModuleConfig;

/**
 * A value a sale unit gives a module setting, read by BaseModule::getConfigValue()
 * instead of the national value while the configuration of that unit is in scope.
 */
class SaleUnitModuleConfig extends BaseSaleUnitModuleConfig
{
    /**
     * The value the admin API reads: none for a secret.
     */
    public function getValueForApi(): ?string
    {
        return true === $this->getSecret() ? null : $this->getValue();
    }

    /**
     * The value the admin API writes: a secret is replaced by a new value, never
     * blanked by a write that does not send one.
     */
    public function setValueForApi(?string $value): static
    {
        if (null === $value && true === $this->getSecret()) {
            return $this;
        }

        return $this->setValue($value);
    }

    public function postSave(?ConnectionInterface $con = null): void
    {
        ModuleConfigQuery::resetConfigCache();

        parent::postSave($con);
    }

    public function postDelete(?ConnectionInterface $con = null): void
    {
        ModuleConfigQuery::resetConfigCache();

        parent::postDelete($con);
    }
}
