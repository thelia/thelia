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

namespace Thelia\Api\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Api\Resource\OrderReturn;
use Thelia\Api\Resource\OrderReturnLine;
use Thelia\Api\Resource\OrderReturnReason;
use Thelia\Api\Resource\OrderReturnReasonI18n;
use Thelia\Api\Resource\OrderReturnStatus;
use Thelia\Api\Resource\OrderReturnStatusI18n;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;

/**
 * Takes the whole returns surface of the API off a shop where the feature is
 * turned off.
 *
 * The switch used to be read on the two creation paths only, so a shop that
 * enabled returns, collected a few, then turned the feature back off went on
 * serving them: the customer could still list and read their requests, and the
 * back office could still read, change, transition and delete every one of
 * them. Turning a feature off has to mean it is gone, on both sides.
 *
 * Answered as "not found" rather than "forbidden": on this shop there is no
 * such thing as a return, and a 403 would say the opposite - that returns
 * exist and this caller is not the one allowed to see them.
 *
 * Runs at priority 5, after the firewall authenticates at 8 and before API
 * Platform reads at 4, so a refused request never touches the database. The
 * eligibility service keeps its own check on the creation paths: the Flexy
 * front and the back-office controllers go through it without passing here.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
final readonly class OrderReturnFeatureListener
{
    /**
     * The API resources the returns feature owns. A resource absent from this
     * list is none of this listener's business.
     */
    private const array RETURN_RESOURCES = [
        OrderReturn::class,
        OrderReturnLine::class,
        OrderReturnReason::class,
        OrderReturnReasonI18n::class,
        OrderReturnStatus::class,
        OrderReturnStatusI18n::class,
    ];

    public function __construct(
        private ReturnEligibilityChecker $eligibility,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $resourceClass = $event->getRequest()->attributes->get('_api_resource_class');

        if (!\is_string($resourceClass) || !\in_array($resourceClass, self::RETURN_RESOURCES, true)) {
            return;
        }

        if ($this->eligibility->isFeatureEnabled()) {
            return;
        }

        throw new NotFoundHttpException('The product returns feature is disabled on this shop.');
    }
}
