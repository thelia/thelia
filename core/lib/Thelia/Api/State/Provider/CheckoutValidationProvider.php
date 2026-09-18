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

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Api\Resource\CheckoutValidationOutput;
use Thelia\Api\Security\CheckoutCartLocator;
use Thelia\Domain\Checkout\Service\CheckoutValidationService;

/**
 * The verdict on a cart, without placing anything.
 *
 * The same questions the placement asks, asked ahead of it: a client rendering a
 * checkout knows what is left to settle before the buyer commits to paying, and it is
 * told all of it at once rather than one field per round trip.
 *
 * Reading this changes nothing — in particular, it does not settle the carrier of a cart
 * with nothing to ship the way the placement does, so such a cart is reported here as
 * still missing its delivery and is placed all the same.
 *
 * The answer is written out rather than serialized from a resource: `{ready, violations}`
 * is a published contract that a refused placement answers with too, and it must be the
 * same two keys in both places whatever the format the caller asked for.
 */
final readonly class CheckoutValidationProvider implements ProviderInterface
{
    public function __construct(
        private CheckoutCartLocator $cartLocator,
        private CheckoutValidationService $validationService,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @throws PropelException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $cart = $this->cartLocator->ownedCart($uriVariables);

        $verdict = CheckoutValidationOutput::ofDomainViolations(
            $this->validationService->collectViolations($cart),
        );

        return new JsonResponse($verdict->toArray());
    }
}
