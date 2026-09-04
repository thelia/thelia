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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Order;
use Thelia\Domain\Order\Enum\GuestOrderAccessVerdict;
use Thelia\Domain\Order\Service\GuestOrderAccessLimiter;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Model\Order as OrderModel;

/**
 * Serves the order a guest tracking link names.
 *
 * The link is the whole of the authentication: whoever holds it sees the order, which
 * is the point — the person it was mailed to has no account to sign into. So the order
 * number in front of the signature is a small integer an anonymous caller can walk,
 * and the limiter is spent before anything is checked, so that a valid token and a
 * made-up one cost the same.
 *
 * Every refusal is the same 404, with one exception. A 403 on a real order and a 404 on
 * a made-up one would answer, one request at a time, which order numbers this shop has
 * issued — and so would a 429 raised by the counter attached to an order number. The
 * per-client counter is different: it is spent by the caller's own traffic and says
 * nothing about any order, so that one is answered honestly, which is the only way a
 * caller who is merely too fast can tell that from a link that stopped working.
 */
final readonly class GuestOrderProvider implements ProviderInterface
{
    public function __construct(
        private GuestOrderAccessLimiter $guestOrderAccessLimiter,
        private GuestOrderAccessService $guestOrderAccessService,
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        $token = $uriVariables['token'] ?? null;

        if (!\is_string($token) || '' === $token) {
            throw new NotFoundHttpException('No such order.');
        }

        $verdict = $this->guestOrderAccessLimiter->check($token);

        if (GuestOrderAccessVerdict::ClientBudgetExhausted === $verdict) {
            throw new TooManyRequestsHttpException(message: 'Too many order tracking requests from here. Try again later.');
        }

        if (GuestOrderAccessVerdict::Allowed !== $verdict) {
            throw new NotFoundHttpException('No such order.');
        }

        $order = $this->guestOrderAccessService->findOrderForToken($token);

        if (!$order instanceof OrderModel) {
            throw new NotFoundHttpException('No such order.');
        }

        return $this->apiResourcePropelTransformerService->modelToResource(
            Order::class,
            $order,
            $operation->getNormalizationContext() ?? [],
        );
    }
}
