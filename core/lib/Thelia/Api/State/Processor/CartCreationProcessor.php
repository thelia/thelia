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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Api\Resource\Cart;
use Thelia\Api\Resource\Currency as CurrencyResource;
use Thelia\Api\Resource\Customer as CustomerResource;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Tools\TokenProvider;

/**
 * The cart token is a bearer secret: an anonymous cart is restored from a cookie
 * that carries it. POST /front/carts is anonymous, so the body must never fix the
 * token — that would let an attacker plant a known one and hijack the cart. The
 * server assigns an unguessable token here instead.
 *
 * The owner and the currency are not taken from the body either. A caller holding
 * a customer token gets a cart of its own; a guest token is left out, since the guest
 * row may be shared and the token is scoped to the one cart it was issued for. The
 * cart is priced in the currency being browsed, the shop's default without a session.
 */
final readonly class CartCreationProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Cart) {
            if (empty($data->token)) {
                $data->token = TokenProvider::generateToken();
            }

            $customer = $this->tokenStorage->getToken()?->getUser();

            if ($customer instanceof Customer && !$customer->isGuest()) {
                $data->setCustomer((new CustomerResource())->setId($customer->getId()));
            }

            if (null === $data->getCurrency()) {
                $data->setCurrency((new CurrencyResource())->setId($this->currentCurrency()->getId()));
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function currentCurrency(): Currency
    {
        $request = $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();
        $session = $request?->hasSession() ? $request->getSession() : null;

        return $session instanceof Session ? $session->getCurrency() : Currency::getDefaultCurrency();
    }
}
