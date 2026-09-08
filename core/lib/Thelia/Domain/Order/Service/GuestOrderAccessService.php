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
 * address behind it and with whether that record is still a passwordless one. Nothing is
 * written when a link is handed out, a link issued for one order cannot be replayed on
 * another, and a link stops being accepted the moment the address changes or the account
 * is opened.
 *
 * Deliberately not the password hash: a buyer who chooses a password is still a guest
 * until the activation code is answered, so signing the hash killed the link at the very
 * moment it was the only way back to the order — the buyer could neither sign in nor
 * reopen their link, and a code that never arrived put the order out of reach for good.
 * What is signed is the state that actually decides, so the link dies when the account
 * becomes usable and not before.
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
                true === $customer?->isGuest(),
            ),
        );
    }

    /**
     * The order a token names, or null when the token is not one this shop issued, no
     * longer matches the order, or has expired.
     */
    public function findOrderForToken(string $token): ?Order
    {
        [$order, $accountWasOpened] = $this->resolve($token);

        return $accountWasOpened ? null : $order;
    }

    /**
     * The order a link would still open, were the account it belongs to not open already.
     *
     * The one case worth telling the buyer about: their order is not gone, it is behind a
     * sign-in. Everything else — expired, forged, naming no order — stays indistinguishable
     * from everything else, so a token nobody was issued learns nothing from asking.
     */
    public function findOrderNowBehindAnAccount(string $token): ?Order
    {
        [$order, $accountWasOpened] = $this->resolve($token);

        return $accountWasOpened ? $order : null;
    }

    /**
     * @return array{0: ?Order, 1: bool} the order the token names, and whether it is only
     *                                   turned away because the account has been opened
     */
    private function resolve(string $token): array
    {
        $parts = explode('.', $token);

        if (3 !== \count($parts)) {
            return [null, false];
        }

        [$rawOrderId, $rawExpiresAt, $signature] = $parts;

        if (!ctype_digit($rawOrderId) || !ctype_digit($rawExpiresAt)) {
            return [null, false];
        }

        $orderId = (int) $rawOrderId;
        $expiresAt = (int) $rawExpiresAt;
        $order = OrderQuery::create()->findPk($orderId);
        $customer = $order?->getCustomer();
        $email = (string) $customer?->getEmail();

        // Both variants are computed for every token, so that telling the two apart costs
        // the same work whichever one matches — and so that an id that names no order and
        // a signature that does not match take the same path.
        $whileAGuest = $this->sign($orderId, $expiresAt, $email, true);
        $onceAnAccount = $this->sign($orderId, $expiresAt, $email, false);

        $issuedByThisShop = hash_equals($whileAGuest, $signature) || hash_equals($onceAnAccount, $signature);

        if (!$issuedByThisShop || !$order instanceof Order) {
            return [null, false];
        }

        if ($expiresAt <= time()) {
            return [null, false];
        }

        // The link is the way in only while there is no account to sign into. An opened
        // account is the buyer's own order waiting behind a sign-in, not a dead link.
        if (true !== $customer?->isGuest()) {
            return [$order, true];
        }

        return [$order, false];
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

    private function sign(int $orderId, int $expiresAt, string $email, bool $recordIsStillAGuest): string
    {
        $key = hash_hmac(
            self::SIGNATURE_ALGORITHM,
            self::SIGNATURE_DOMAIN,
            $this->applicationSecret,
            true,
        );

        return hash_hmac(
            self::SIGNATURE_ALGORITHM,
            implode("\0", [$orderId, $expiresAt, $email, $recordIsStillAGuest ? 'guest' : 'account']),
            $key,
        );
    }
}
