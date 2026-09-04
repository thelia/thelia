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
use Propel\Runtime\Propel;
use Thelia\Domain\OrderReturn\Service\SequenceOrderReturnRefGenerator;
use Thelia\Domain\Sequence\GaplessSequenceGenerator;
use Thelia\Model\Base\OrderReturn as BaseOrderReturn;
use Thelia\Model\Map\OrderReturnTableMap;

class OrderReturn extends BaseOrderReturn
{
    /**
     * What the customer expects the return to settle into.
     */
    public const RESOLUTION_REFUND = 'refund';
    public const RESOLUTION_CREDIT = 'credit';
    public const RESOLUTION_EXCHANGE = 'exchange';

    public const RESOLUTIONS = [
        self::RESOLUTION_REFUND,
        self::RESOLUTION_CREDIT,
        self::RESOLUTION_EXCHANGE,
    ];

    protected bool $disableVersioning = false;

    protected bool $refGenerationDeferred = false;

    public function setDisableVersioning(bool $disableVersioning): static
    {
        $this->disableVersioning = $disableVersioning;

        return $this;
    }

    public function isVersioningDisable(): bool
    {
        return $this->disableVersioning;
    }

    public function isVersioningNecessary(?ConnectionInterface $con = null): bool
    {
        if ($this->isVersioningDisable()) {
            return false;
        }

        return parent::isVersioningNecessary($con);
    }

    /**
     * Defer the reference allocation so a caller can decide when the gapless
     * number is consumed (e.g. only once the whole return is validated).
     *
     * @return $this
     */
    public function deferRefGeneration(bool $deferred = true): static
    {
        $this->refGenerationDeferred = $deferred;

        return $this;
    }

    public function postInsert(?ConnectionInterface $con = null): void
    {
        parent::postInsert($con);

        if ($this->refGenerationDeferred || null !== $this->getRef()) {
            return;
        }

        $this->setRef($this->generateRef($con))
            ->setDisableVersioning(true)
            ->save($con);
    }

    /**
     * Allocate the next reference from the gapless order_return_ref sequence.
     *
     * Each call consumes a number: only call this to assign a reference that
     * will be persisted.
     */
    public function generateRef(?ConnectionInterface $con = null): string
    {
        $con ??= Propel::getConnection(OrderReturnTableMap::DATABASE_NAME);

        return SequenceOrderReturnRefGenerator::format(
            (new GaplessSequenceGenerator())->next(SequenceOrderReturnRefGenerator::SEQUENCE_NAME, $con)
        );
    }

    /**
     * The effective code of the current return status, or null when no status is loaded.
     */
    public function getStatusCode(): ?string
    {
        return $this->getOrderReturnStatus()?->getEffectiveCode();
    }
}
