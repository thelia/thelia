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

namespace Thelia\Domain\Order\Reminder;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;

/**
 * The link of a payment reminder: it names the order and the moment it stops being
 * accepted, signed together with the customer's address and the cart the order was
 * placed from. Nothing is stored. It opens the order only while the order still waits
 * for its payment, under the same address and from the same cart: paid, cancelled, an
 * address changed or a cart replaced, and the link is dead.
 *
 * It never signs anybody in: it hands the order back to the checkout, and an account
 * still asks for its password.
 */
final readonly class UnpaidOrderPaymentLink
{
    private const SIGNATURE_ALGORITHM = 'sha256';

    /**
     * Distinct from every other signing domain of the shop, so that a token issued here
     * can never be presented where another kind of token is expected.
     */
    private const SIGNATURE_DOMAIN = 'thelia.unpaid_order_payment';

    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private string $applicationSecret,
    ) {
    }

    public function createToken(Order $order, int $expiresAt): string
    {
        $orderId = (int) $order->getId();

        return \sprintf('%d.%d.%s', $orderId, $expiresAt, $this->sign($order, $expiresAt));
    }

    /**
     * The order a token names, or null when the token is not one this shop issued, has
     * expired, or names an order that no longer waits for its payment.
     */
    public function findOrderForToken(string $token): ?Order
    {
        $parts = explode('.', $token);

        if (3 !== \count($parts) || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
            return null;
        }

        [$rawOrderId, $rawExpiresAt, $signature] = $parts;
        $order = OrderQuery::create()->findPk((int) $rawOrderId);
        $expiresAt = (int) $rawExpiresAt;

        // Signed even when the id names no order, so that both cases cost the same.
        $expected = null !== $order ? $this->sign($order, $expiresAt) : hash_hmac(self::SIGNATURE_ALGORITHM, $token, $this->applicationSecret);

        if (!hash_equals($expected, $signature) || null === $order || $expiresAt <= time()) {
            return null;
        }

        return true === $order->getOrderStatus()?->isNotPaid(true) ? $order : null;
    }

    private function sign(Order $order, int $expiresAt): string
    {
        $key = hash_hmac(self::SIGNATURE_ALGORITHM, self::SIGNATURE_DOMAIN, $this->applicationSecret, true);

        return hash_hmac(
            self::SIGNATURE_ALGORITHM,
            implode("\0", [(int) $order->getId(), $expiresAt, (string) $order->getCustomer()?->getEmail(), (int) $order->getCartId()]),
            $key,
        );
    }
}
