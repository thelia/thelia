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

namespace Thelia\Domain\Quote\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Sequence\GaplessSequenceGenerator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\QuoteQuery;

/**
 * Numbers the quotes: one series of its own, gapless, whatever the format of the reference.
 *
 * The number comes from a counter whose row stays locked until the transaction of the caller
 * ends, so it is asked for last: two quotes written at once queue up for the length of the
 * commit, and a quote that is rolled back gives its number back. The reference is never one an
 * order or another quote holds.
 */
final readonly class QuoteRefAllocator
{
    public const string SEQUENCE_NAME = 'quote_ref';

    public const string CONFIG_FORMAT = 'quote_ref_format';

    public const string DEFAULT_FORMAT = 'QUO%08d';

    private const int MAXIMUM_ATTEMPTS = 5;

    public function __construct(private GaplessSequenceGenerator $sequenceGenerator)
    {
    }

    /**
     * Must run inside the transaction that writes the quote.
     */
    public function allocate(ConnectionInterface $connection): string
    {
        for ($attempt = 0; $attempt < self::MAXIMUM_ATTEMPTS; ++$attempt) {
            $ref = $this->format($this->sequenceGenerator->next(self::SEQUENCE_NAME, $connection));

            // A format an order series also uses can land on a reference that exists: the
            // number is lost, the reference is not given twice.
            if (!OrderQuery::create()->filterByRef($ref)->exists($connection)
                && !QuoteQuery::create()->filterByRef($ref)->exists($connection)) {
                return $ref;
            }
        }

        throw new \LogicException('The format of the quote references keeps giving references that are already taken.');
    }

    public function format(int $number): string
    {
        $format = (string) ConfigQuery::read(self::CONFIG_FORMAT, self::DEFAULT_FORMAT);

        // One number placeholder and no other: anything else is a typo, not a wish.
        if (1 !== preg_match('/^[^%]*%0?\d{0,2}d[^%]*$/D', $format)) {
            $format = self::DEFAULT_FORMAT;
        }

        return mb_substr(\sprintf($format, $number), 0, 45);
    }
}
