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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Resource\CheckoutPlacementInput;
use Thelia\Api\Resource\CheckoutPlacementOutput;
use Thelia\Api\Resource\CheckoutValidationOutput;
use Thelia\Api\Security\CheckoutCartLocator;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\Exception\CheckoutPlacementInProgressException;
use Thelia\Domain\Checkout\Exception\CheckoutRefusedException;
use Thelia\Domain\Checkout\Exception\GuestCheckoutNotAllowedException;
use Thelia\Domain\Checkout\Service\CheckoutPlacementService;
use Thelia\Domain\Order\Exception\StockShortageException;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * Turning the cart of an account into an order, with nobody at a browser.
 *
 * Almost nothing to post: every choice the order is built from is already on the cart,
 * put there by the four selection operations. Restating them here would be a second
 * chance to disagree with the cart the buyer was shown, and an amount or a carrier
 * stated at the last moment is exactly what must not be takeable.
 *
 * The consents are the exception, and the body is optional for that alone. An answer to
 * a box has nowhere to wait: the shop keeps no record of what a cart abandoned at the
 * payment step agreed to, and the firewall of the API is stateless, so the request that
 * writes the order is the only one that can carry it. The body is read by hand rather
 * than deserialized, because a placement posted without one is the placement this
 * endpoint has always taken and must go on taking.
 *
 * What a session used to answer is answered from what the request has: the currency is
 * the one the cart was priced in, and the language is the one the account reads the shop
 * in. Neither is read off a session, and neither is taken from the body.
 */
final readonly class CheckoutPlacementProcessor implements ProcessorInterface
{
    public function __construct(
        private CheckoutCartLocator $cartLocator,
        private CheckoutPlacementService $placementService,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $cart = $this->cartLocator->ownedCart($uriVariables);
        $customer = $this->security->getUser();
        $input = CheckoutPlacementInput::ofRequest($this->requestStack->getCurrentRequest());

        // The locator already established the cart belongs to an authenticated account,
        // so this cannot be anything else — it is read again rather than assumed because
        // the order is written from it.
        if (!$customer instanceof Customer) {
            throw new NotFoundHttpException(CheckoutCartLocator::NOT_FOUND_MESSAGE);
        }

        try {
            $result = $this->placementService->place(new CheckoutPlacementRequest(
                $cart,
                $customer,
                $this->currencyOf($cart),
                $customer->getCustomerLang(),
                $input->consentAnswers,
            ));
        } catch (CheckoutRefusedException $refusal) {
            return $this->refusalResponse($refusal);
        } catch (CheckoutPlacementInProgressException $inProgress) {
            // 409 and not 202: there is nothing to poll, and the caller is expected to
            // post again — by then the request that holds the placement has an order to
            // hand back.
            throw new ConflictHttpException($inProgress->getMessage(), $inProgress);
        } catch (GuestCheckoutNotAllowedException $refusal) {
            throw new AccessDeniedHttpException($refusal->getMessage(), $refusal);
        } catch (StockShortageException $shortage) {
            // The shop cannot sell what it no longer holds. It is a conflict with the
            // state of the stock rather than a bad request: the same request posted a
            // minute earlier would have gone through, and the message names the product
            // the buyer has to take out — a reference off the catalogue, not an internal
            // detail.
            //
            // Only this one. Every other TheliaProcessException — a missing customer id,
            // a module that answered nothing, a third-party module that raised its own —
            // is a defect of the shop: it is left to become a 500 with no message, rather
            // than a 409 telling a client to retry something that will never work and
            // handing it a sentence written for a log.
            throw new ConflictHttpException($shortage->getMessage(), $shortage);
        }

        return new JsonResponse(CheckoutPlacementOutput::fromResult($result)->toArray());
    }

    /**
     * The refusal is answered in the very shape `GET .../validation` answers in, so that
     * a client has one payload to read and one list of codes to branch on, whether it
     * asked before placing or found out while placing.
     */
    private function refusalResponse(CheckoutRefusedException $refusal): JsonResponse
    {
        return new JsonResponse(
            CheckoutValidationOutput::ofDomainViolations($refusal->violations)->toArray(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    private function currencyOf(Cart $cart): Currency
    {
        return $cart->getCurrency() ?? Currency::getDefaultCurrency();
    }
}
