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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Resource\ExpressPaymentButton;
use Thelia\Domain\Checkout\DTO\ExpressPaymentButton as ExpressPaymentButtonData;
use Thelia\Domain\Checkout\Enum\ExpressPaymentZone;
use Thelia\Domain\Checkout\Service\ExpressPaymentButtonCollector;

/**
 * The buttons for the cart this request carries.
 *
 * The cart is read from the session and never from a parameter: that is what lets the
 * operation stay open without letting anyone read anybody else's cart. A request with no
 * session, or no cart, has no buttons — not an error, since a shop with an empty cart has
 * nothing to pay for either.
 */
final readonly class ExpressPaymentButtonProvider implements ProviderInterface
{
    public function __construct(
        private ExpressPaymentButtonCollector $collector,
        private RequestStack $requestStack,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @return list<ExpressPaymentButton>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $zone = ExpressPaymentZone::tryFrom((string) ($context['filters']['zone'] ?? ExpressPaymentZone::Checkout->value));

        if (null === $zone) {
            return [];
        }

        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();

        if (null === $request || !$request->hasSession()) {
            return [];
        }

        $cart = $request->getSession()->getSessionCart($this->dispatcher);

        if (null === $cart) {
            return [];
        }

        return array_map(
            static function (ExpressPaymentButtonData $button): ExpressPaymentButton {
                $resource = new ExpressPaymentButton();
                $resource->paymentModuleId = $button->paymentModuleId;
                $resource->paymentModuleCode = $button->paymentModuleCode;
                $resource->code = $button->code;
                $resource->label = $button->label;
                $resource->logo = $button->logo;
                $resource->attributes = $button->attributes;
                $resource->confirmationUrl = $button->confirmationUrl;
                $resource->confirmationToken = $button->confirmationToken;
                $resource->amountUrl = $button->amountUrl;

                return $resource;
            },
            $this->collector->collect($cart, $zone)
        );
    }
}
