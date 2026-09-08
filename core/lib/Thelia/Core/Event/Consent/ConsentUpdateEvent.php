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

namespace Thelia\Core\Event\Consent;

class ConsentUpdateEvent extends ConsentCreateEvent
{
    protected int $consentId;

    public function __construct(int $consentId)
    {
        parent::__construct();

        $this->consentId = $consentId;
    }

    public function getConsentId(): int
    {
        return $this->consentId;
    }

    public function setConsentId(int $consentId): static
    {
        $this->consentId = $consentId;

        return $this;
    }
}
