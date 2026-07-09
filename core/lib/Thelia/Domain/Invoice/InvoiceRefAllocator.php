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

namespace Thelia\Domain\Invoice;

use Propel\Runtime\Propel;
use Thelia\Domain\Sequence\GaplessSequenceGenerator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;

/**
 * Assigns the legal invoice number (order.invoice_ref) from a gapless yearly
 * sequence, as required for French invoices (CGI, ann. II, art. 242 nonies A:
 * chronological, continuous numbering — distinct yearly series are allowed).
 *
 * The order ref identifies the order; the invoice ref numbers the invoice.
 * Keeping them separate lets the order series absorb business hazards while
 * the invoice series stays strictly continuous.
 */
readonly class InvoiceRefAllocator
{
    public const CONFIG_ENABLED = 'invoice_ref_auto';

    public const CONFIG_FORMAT = 'invoice_ref_format';

    public const DEFAULT_FORMAT = '%year%-%number%';

    public const SEQUENCE_PREFIX = 'invoice_ref_';

    public function __construct(
        private GaplessSequenceGenerator $sequenceGenerator,
    ) {
    }

    public function isEnabled(): bool
    {
        return '1' === ConfigQuery::read(self::CONFIG_ENABLED, '0');
    }

    /**
     * Allocates the next invoice number of the current yearly series and
     * persists it on the order. Runs in its own (possibly nested) transaction
     * so the counter increment commits or rolls back with the invoice ref.
     */
    public function allocate(Order $order): void
    {
        $connection = Propel::getConnection(OrderTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $year = ($order->getInvoiceDate() ?? new \DateTime())->format('Y');
            $number = $this->sequenceGenerator->next(self::SEQUENCE_PREFIX.$year, $connection);

            $order
                ->setInvoiceRef($this->format($year, $number))
                ->setDisableVersioning(true)
                ->save($connection);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();
            throw $throwable;
        }
    }

    private function format(string $year, int $number): string
    {
        $format = ConfigQuery::read(self::CONFIG_FORMAT, self::DEFAULT_FORMAT) ?: self::DEFAULT_FORMAT;

        return str_replace(
            ['%year%', '%number%'],
            [$year, str_pad((string) $number, 6, '0', \STR_PAD_LEFT)],
            $format,
        );
    }
}
