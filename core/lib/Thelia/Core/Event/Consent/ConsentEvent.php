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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Consent;

class ConsentEvent extends ActionEvent
{
    public function __construct(protected ?Consent $consent = null)
    {
    }

    public function hasConsent(): bool
    {
        return $this->consent instanceof Consent;
    }

    public function getConsent(): ?Consent
    {
        return $this->consent;
    }

    public function setConsent(Consent $consent): static
    {
        $this->consent = $consent;

        return $this;
    }
}
