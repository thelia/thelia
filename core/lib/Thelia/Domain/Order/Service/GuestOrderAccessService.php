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

namespace Thelia\Domain\Order\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;

/**
 * Gives someone who ordered without an account a way back to that order.
 *
 * A guest has no account to sign into, so the link is the only thing that identifies
 * the person entitled to see the order. It is signed rather than stored: it names the
 * order and the moment it stops being accepted, and it is signed together with the
 * address and the password hash of the customer behind it. Nothing is written when a
 * link is handed out, a link issued for one order cannot be replayed on another, and a
 * link stops being accepted the moment the address or the password behind it changes.
 *
 * A guest carries an empty password hash, so converting the guest account into a real
 * one invalidates every link issued while it was a guest — which is what should happen:
 * from then on the orders are reached by signing in.
 */
final readonly class GuestOrderAccessService
{
    /**
     * A guest has nowhere else to look the order up, so the link has to outlive the
     * delivery it follows: a month covers ordering, shipping and the usual complaint.
     */
    public const DEFAULT_LINK_LIFETIME_IN_SECONDS = 2592000;

    /**
     * Overrides the lifetime above, in seconds, when the shop has an opinion.
     */
    public const LINK_LIFETIME_CONFIG_KEY = 'guest_order_tracking_link_lifetime';

    private const SIGNATURE_ALGORITHM = 'sha256';

    /**
     * Distinct from every other signing domain of the shop, so that a token issued
     * here can never be presented where another kind of token is expected.
     */
    private const SIGNATURE_DOMAIN = 'thelia.guest_order_access';

    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private string $applicationSecret,
    ) {
    }

    /**
     * Build the token that names this order in a tracking link.
     */
    public function createToken(Order $order, ?int $lifetime = null): string
    {
        $orderId = (int) $order->getId();
        $expiresAt = time() + ($lifetime ?? $this->getLinkLifetimeInSeconds());
        $customer = $order->getCustomer();

        return \sprintf(
            '%d.%d.%s',
            $orderId,
            $expiresAt,
            $this->sign(
                $orderId,
                $expiresAt,
                (string) $customer?->getEmail(),
                (string) $customer?->getPassword(),
            ),
        );
    }

    /**
     * The order a token names, or null when the token is not one this shop issued, no
     * longer matches the order, or has expired.
     */
    public function findOrderForToken(string $token): ?Order
    {
        $parts = explode('.', $token);

        if (3 !== \count($parts)) {
            return null;
        }

        [$rawOrderId, $rawExpiresAt, $signature] = $parts;

        if (!ctype_digit($rawOrderId) || !ctype_digit($rawExpiresAt)) {
            return null;
        }

        $orderId = (int) $rawOrderId;
        $expiresAt = (int) $rawExpiresAt;
        $order = OrderQuery::create()->findPk($orderId);
        $customer = $order?->getCustomer();

        // Signed even when the order is gone, and always compared, so an id that names
        // no order and a signature that does not match take the same path and the same time.
        $expectedSignature = $this->sign(
            $orderId,
            $expiresAt,
            (string) $customer?->getEmail(),
            (string) $customer?->getPassword(),
        );

        if (!hash_equals($expectedSignature, $signature) || !$order instanceof Order) {
            return null;
        }

        if ($expiresAt <= time()) {
            return null;
        }

        return $order;
    }

    public function getLinkLifetimeInSeconds(): int
    {
        $configured = (int) ConfigQuery::read(
            self::LINK_LIFETIME_CONFIG_KEY,
            (string) self::DEFAULT_LINK_LIFETIME_IN_SECONDS,
        );

        // A lifetime of zero or less would hand out links nothing can accept, which reads
        // as a broken shop rather than as a strict one.
        return $configured > 0 ? $configured : self::DEFAULT_LINK_LIFETIME_IN_SECONDS;
    }

    private function sign(int $orderId, int $expiresAt, string $email, string $passwordHash): string
    {
        $key = hash_hmac(
            self::SIGNATURE_ALGORITHM,
            self::SIGNATURE_DOMAIN,
            $this->applicationSecret,
            true,
        );

        return hash_hmac(
            self::SIGNATURE_ALGORITHM,
            implode("\0", [$orderId, $expiresAt, $email, $passwordHash]),
            $key,
        );
    }
}
