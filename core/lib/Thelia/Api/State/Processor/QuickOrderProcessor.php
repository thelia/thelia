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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Api\Resource\QuickOrderInput;
use Thelia\Api\Security\CheckoutCartLocator;
use Thelia\Api\Service\ReferenceQuantityRows;
use Thelia\Domain\QuickOrder\Exception\QuickOrderCartNotFoundException;
use Thelia\Domain\QuickOrder\Exception\QuickOrderRateLimitedException;
use Thelia\Domain\QuickOrder\QuickOrderFacade;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * Both quick order operations: resolve when the route names no cart, add to the cart
 * it names otherwise. The cart is looked up the way the checkout looks it up, so a
 * cart of another account answers as a cart that does not exist.
 *
 * @implements ProcessorInterface<mixed, JsonResponse>
 */
final readonly class QuickOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private QuickOrderFacade $quickOrderFacade,
        private CheckoutCartLocator $cartLocator,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        if (!$data instanceof QuickOrderInput) {
            throw new UnprocessableEntityHttpException('Unsupported quick order body.');
        }

        $customer = $this->tokenStorage->getToken()?->getUser();

        if (!$customer instanceof Customer) {
            throw new AccessDeniedHttpException('A customer must be authenticated to order by reference.');
        }

        $lines = ReferenceQuantityRows::toLines($data->lines ?? []);

        try {
            if (!\array_key_exists('cartId', $uriVariables)) {
                return new JsonResponse($this->quickOrderFacade->resolve($customer, $lines, Currency::getDefaultCurrency())->toArray());
            }

            $table = $this->quickOrderFacade->addToCart($customer, $this->cartLocator->ownedCart($uriVariables), $lines);
        } catch (QuickOrderRateLimitedException $exception) {
            throw new TooManyRequestsHttpException(message: $exception->getMessage(), previous: $exception);
        } catch (QuickOrderCartNotFoundException $exception) {
            throw new NotFoundHttpException('No such cart.', $exception);
        }

        return new JsonResponse($table->toArray());
    }
}
