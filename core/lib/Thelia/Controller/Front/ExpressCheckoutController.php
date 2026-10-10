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

namespace Thelia\Controller\Front;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementResult;
use Thelia\Domain\Checkout\Enum\PaymentActionType;
use Thelia\Domain\Checkout\Exception\CheckoutException;
use Thelia\Domain\Checkout\Exception\CheckoutPlacementInProgressException;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutConfirmationDeniedException;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;
use Thelia\Domain\Checkout\Service\ExpressCheckoutAmountService;
use Thelia\Domain\Checkout\Service\ExpressCheckoutConfirmationService;
use Thelia\Domain\Order\Exception\StockShortageException;

/**
 * Where a wallet asks what the checkout comes to, and where it posts the buyer's payment,
 * whatever the wallet.
 *
 * It answers in JSON because the caller is the module's script, sitting on a payment
 * sheet: it needs to know whether to close the sheet on a success or a failure, and what
 * the browser does next. It never answers with a page.
 */
#[AsController]
final readonly class ExpressCheckoutController
{
    public const TOKEN_HEADER = 'X-Express-Confirmation-Token';

    public const TOKEN_FIELD = 'express_confirmation_token';

    public function __construct(
        private ExpressCheckoutConfirmationService $confirmationService,
        private ExpressCheckoutAmountService $amountService,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * What a sheet opened in the checkout will charge, asked again every time the checkout
     * changes the carrier or the cart. `deliveryChosen` false tells the module to keep its
     * button disabled.
     */
    public function amount(Request $request, string $moduleCode): Response
    {
        try {
            $total = $this->amountService->amountOfTheCheckout($request, $moduleCode, $this->tokenOf($request));
        } catch (ExpressCheckoutConfirmationDeniedException $denied) {
            return new JsonResponse(['error' => $denied->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (ExpressCheckoutRefusedException $refusal) {
            return new JsonResponse(['error' => $refusal->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'deliveryChosen' => null !== $total,
            'totalTaxIncluded' => $total,
        ]);
    }

    public function confirm(Request $request, string $moduleCode): Response
    {
        try {
            $result = $this->confirmationService->confirm($request, $moduleCode, $this->tokenOf($request));
        } catch (ExpressCheckoutConfirmationDeniedException $denied) {
            return new JsonResponse(['error' => $denied->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (CheckoutPlacementInProgressException|StockShortageException $conflict) {
            // As the checkout API answers them: a state of the shop rather than a bad
            // request — the same confirmation a moment earlier or later may go through.
            return new JsonResponse(['error' => $conflict->getMessage()], Response::HTTP_CONFLICT);
        } catch (ExpressCheckoutRefusedException|CheckoutException $refusal) {
            return new JsonResponse(['error' => $refusal->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'orderId' => $result->orderId,
            'orderReference' => $result->orderReference,
            'orderStatus' => $result->orderStatusCode,
            'paid' => $result->paid,
            'alreadyPlaced' => $result->alreadyPlaced,
            'nextUrl' => $this->nextUrlAfter($result),
            'paymentAction' => [
                'type' => $result->paymentAction->type->value,
                'url' => $result->paymentAction->url,
                'html' => $result->paymentAction->html,
            ],
        ]);
    }

    /**
     * Where the browser goes once the sheet is closed: to the payment module's page when
     * it asked for one, otherwise to the confirmation page the theme serves after any
     * order, the same one every payment module sends a buyer back to.
     */
    private function nextUrlAfter(CheckoutPlacementResult $result): ?string
    {
        if (PaymentActionType::Redirect === $result->paymentAction->type) {
            return $result->paymentAction->url;
        }

        try {
            return $this->urlGenerator->generate('checkout_confirm', ['order_id' => $result->orderId]);
        } catch (RouteNotFoundException) {
            // A theme with no confirmation page: the script stays where it is.
            return null;
        }
    }

    private function tokenOf(Request $request): string
    {
        return (string) ($request->headers->get(self::TOKEN_HEADER) ?? $request->request->get(self::TOKEN_FIELD, ''));
    }
}
