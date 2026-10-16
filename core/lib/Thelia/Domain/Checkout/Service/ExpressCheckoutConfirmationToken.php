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

namespace Thelia\Domain\Checkout\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Model\Cart;

/**
 * The proof that a confirmation comes from a page the shop served for this very cart.
 *
 * It is handed out with each express button and checked when the wallet confirms, so a
 * page on another site cannot have a visitor's browser order that visitor's cart, and a
 * button rendered for one cart cannot be confirmed on another. It is signed rather than
 * stored: the buttons are rendered on every render of the payment step, and a token kept
 * in the session would have to be written on each of them.
 *
 * It is bound to the cart id, and a cart id changes when the cart is replaced — at a
 * sign-in, or after a payment. A token issued before that no longer matches, which is
 * the point: the page that showed it was showing another cart.
 */
final readonly class ExpressCheckoutConfirmationToken
{
    private const SIGNATURE_ALGORITHM = 'sha256';

    private const SIGNATURE_DOMAIN = 'thelia.express_checkout.confirmation';

    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private string $applicationSecret,
    ) {
    }

    public function issueFor(Cart $cart, int $paymentModuleId): string
    {
        return $this->sign((int) $cart->getId(), $paymentModuleId);
    }

    public function isIssuedFor(string $token, Cart $cart, int $paymentModuleId): bool
    {
        return hash_equals($this->sign((int) $cart->getId(), $paymentModuleId), $token);
    }

    private function sign(int $cartId, int $paymentModuleId): string
    {
        $key = hash_hmac(self::SIGNATURE_ALGORITHM, self::SIGNATURE_DOMAIN, $this->applicationSecret, true);

        return hash_hmac(self::SIGNATURE_ALGORITHM, implode("\0", [$cartId, $paymentModuleId]), $key);
    }
}
