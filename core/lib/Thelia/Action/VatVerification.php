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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Legal\VatNumberVerifiedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Legal\Enum\VatVerificationStatus;

/**
 * Records on the address what a verification service answered about its VAT number.
 *
 * Only this listener writes those columns. A refusal and an unanswered check
 * both clear the state rather than leaving the previous answer standing: a
 * number that no longer verifies must stop exempting, and a service that could
 * not be reached has told us nothing about it either.
 */
class VatVerification extends BaseAction implements EventSubscriberInterface
{
    public function record(VatNumberVerifiedEvent $event): void
    {
        $result = $event->getResult();
        $verified = VatVerificationStatus::VERIFIED === $result->status;

        $event->getAddress()
            ->setVatVerifiedAt($verified ? $result->verifiedAt : null)
            ->setVatVerifiedName($verified ? $result->verifiedName : null)
            ->save();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::VAT_NUMBER_VERIFIED => ['record', 128],
        ];
    }
}
