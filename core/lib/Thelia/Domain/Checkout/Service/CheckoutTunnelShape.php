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

use Thelia\Model\CheckoutStep;

/**
 * The shape a checkout cannot be sold without: it opens on the cart, it takes the money
 * next to last and it ends on the confirmation.
 *
 * The rule lives here and nowhere else, because it is asked in two opposite ways. The
 * back office asks it before writing — a move that would break the tunnel is refused —
 * and the front office asks it after reading, since a row written straight into the
 * table, by a migration or by hand, never went through that refusal. Two copies of the
 * rule would mean a configuration the writing side rejects and the reading side serves,
 * which is the failure this class exists to prevent.
 *
 * It works on codes and on integers alone: whether they come from the `checkout_step`
 * table, from a step provider or from a test changes nothing to the shape.
 */
final readonly class CheckoutTunnelShape
{
    /**
     * The required step this list does not hold at all, or null when it holds them all.
     *
     * @param list<string> $orderedCodes
     */
    public function missingCode(array $orderedCodes): ?string
    {
        foreach (CheckoutStep::REQUIRED_CODES as $requiredCode) {
            if (!\in_array($requiredCode, $orderedCodes, true)) {
                return $requiredCode;
            }
        }

        return null;
    }

    /**
     * The step standing somewhere the tunnel cannot be sold through, or null when every
     * step the list holds is where it belongs.
     *
     * A required code the list does not hold is a missing step and not a misplaced one:
     * that is what missingCode() answers.
     *
     * @param list<string> $orderedCodes
     */
    public function misplacedCode(array $orderedCodes): ?string
    {
        $count = \count($orderedCodes);

        $expectedRanks = [
            CheckoutStep::CODE_CART => 0,
            CheckoutStep::CODE_PAYMENT => $count - 2,
            CheckoutStep::CODE_CONFIRMATION => $count - 1,
        ];

        foreach ($expectedRanks as $code => $expectedRank) {
            $rank = array_search($code, $orderedCodes, true);

            if (false !== $rank && $rank !== $expectedRank) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @param list<string> $orderedCodes
     */
    public function isRespectedBy(array $orderedCodes): bool
    {
        return [] !== $orderedCodes
            && null === $this->missingCode($orderedCodes)
            && null === $this->misplacedCode($orderedCodes);
    }

    /**
     * Between which positions a step of that code may be created, given where the cart
     * and the payment stand today.
     *
     * Null when the rule has nothing to say: one of the three required steps, which is
     * placed by the rule rather than between its landmarks, or a table holding neither
     * a cart nor a payment row to measure against.
     *
     * A highest lower than the lowest is not a contradiction but an answer: the tunnel
     * has no room left between the cart and the payment, and the caller has to make
     * some before writing at the lowest.
     *
     * @return array{lowest: int, highest: int}|null
     */
    public function creationBounds(string $code, ?int $cartPosition, ?int $paymentPosition): ?array
    {
        if (\in_array($code, CheckoutStep::REQUIRED_CODES, true)) {
            return null;
        }

        if (null === $cartPosition && null === $paymentPosition) {
            return null;
        }

        return [
            'lowest' => null === $cartPosition ? 1 : $cartPosition + 1,
            'highest' => null === $paymentPosition ? \PHP_INT_MAX : $paymentPosition - 1,
        ];
    }
}
