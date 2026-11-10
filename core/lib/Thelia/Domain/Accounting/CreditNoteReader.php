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

namespace Thelia\Domain\Accounting;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\ModuleQuery;

/**
 * The numbered credit notes of the CreditNote module, read from its tables: the core does not
 * depend on the module, so it reads the columns of CreditNote 4.4.0 while the module is active.
 *
 * A credit note is in the journal once it has an invoice reference and a status the module
 * marks invoiced (accepted, used); the module also sets an invoice date at creation, so the
 * date alone says nothing. The module never writes the exchange rate of a credit note: it
 * is the one of its order, which the invoice it reverses was booked at. The credit notes are read by batches, after the last one read by
 * date and id: a batch is never shifted by a credit note numbered while the export runs.
 */
final class CreditNoteReader
{
    public const MODULE_CODE = 'CreditNote';

    private const TABLES = ['credit_note', 'credit_note_detail', 'credit_note_status', 'credit_note_type', 'credit_note_address'];

    private const BATCH_SIZE = 200;

    public function isModuleActive(): bool
    {
        return null !== ModuleQuery::create()->filterByCode(self::MODULE_CODE)->filterByActivate(1)->findOne();
    }

    public function hasItsTables(): bool
    {
        $statement = $this->connection()->prepare(\sprintf(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (%s)',
            implode(', ', array_fill(0, \count(self::TABLES), '?')),
        ));
        $statement->execute(self::TABLES);

        return \count(self::TABLES) === (int) $statement->fetchColumn();
    }

    /**
     * @return \Generator<int, CreditNoteRecord> by invoice date, then id
     */
    public function invoiced(?\DateTimeInterface $from, ?\DateTimeInterface $to): \Generator
    {
        $after = null;

        do {
            $notes = $this->batch($from, $to, $after);
            $lines = $this->linesOf(array_keys($notes));

            foreach ($notes as $id => $note) {
                yield new CreditNoteRecord(
                    $id,
                    (string) $note['invoice_ref'],
                    new \DateTimeImmutable((string) $note['invoice_date']),
                    (string) $note['type_code'],
                    (string) $note['customer_ref'],
                    trim((string) $note['company']) ?: trim($note['firstname'].' '.$note['lastname']),
                    (int) $note['currency_id'],
                    null !== $note['currency_code'] ? (string) $note['currency_code'] : null,
                    (float) $note['currency_rate'],
                    (float) $note['total_price_with_tax'],
                    (float) $note['discount_with_tax'],
                    $lines[$id] ?? [],
                );

                $after = [(string) $note['invoice_date'], $id];
            }
        } while (self::BATCH_SIZE === \count($notes));
    }

    /**
     * @param array{string, int}|null $after the date and id of the last credit note read
     *
     * @return array<int, array<string, mixed>> by id
     */
    private function batch(?\DateTimeInterface $from, ?\DateTimeInterface $to, ?array $after): array
    {
        $conditions = ['cn.invoice_ref IS NOT NULL', "cn.invoice_ref <> ''", 'cn.invoice_date IS NOT NULL'];
        $parameters = [];

        if (null !== $from) {
            $conditions[] = 'cn.invoice_date >= ?';
            $parameters[] = $from->format('Y-m-d H:i:s');
        }

        if (null !== $to) {
            $conditions[] = 'cn.invoice_date <= ?';
            $parameters[] = $to->format('Y-m-d H:i:s');
        }

        if (null !== $after) {
            $conditions[] = '(cn.invoice_date > ? OR (cn.invoice_date = ? AND cn.id > ?))';
            array_push($parameters, $after[0], $after[0], $after[1]);
        }

        $statement = $this->connection()->prepare(
            'SELECT cn.id, cn.invoice_ref, cn.invoice_date, cn.currency_id, CASE WHEN cn.currency_rate > 0 THEN cn.currency_rate ELSE o.currency_rate END AS currency_rate, cn.total_price_with_tax, cn.discount_with_tax,
                    t.code AS type_code, c.ref AS customer_ref, a.company, a.firstname, a.lastname, cur.code AS currency_code
             FROM credit_note cn
             INNER JOIN credit_note_status s ON s.id = cn.status_id AND s.invoiced = 1
             INNER JOIN credit_note_type t ON t.id = cn.type_id
             LEFT JOIN customer c ON c.id = cn.customer_id
             LEFT JOIN credit_note_address a ON a.id = cn.invoice_address_id
             LEFT JOIN currency cur ON cur.id = cn.currency_id
             LEFT JOIN `order` o ON o.id = cn.order_id
             WHERE '.implode(' AND ', $conditions).'
             ORDER BY cn.invoice_date, cn.id
             LIMIT '.self::BATCH_SIZE,
        );
        $statement->execute($parameters);

        $notes = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $notes[(int) $row['id']] = $row;
        }

        return $notes;
    }

    /**
     * The lines of a batch of credit notes, in one query.
     *
     * @param list<int> $creditNoteIds
     *
     * @return array<int, list<CreditNoteLine>> by credit note
     */
    private function linesOf(array $creditNoteIds): array
    {
        if ([] === $creditNoteIds) {
            return [];
        }

        $statement = $this->connection()->prepare(\sprintf(
            'SELECT credit_note_id, type, price, price_with_tax, quantity FROM credit_note_detail WHERE credit_note_id IN (%s) ORDER BY credit_note_id, id',
            implode(', ', array_fill(0, \count($creditNoteIds), '?')),
        ));
        $statement->execute($creditNoteIds);

        $lines = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $lines[(int) $row['credit_note_id']][] = new CreditNoteLine((string) $row['type'], (float) $row['price'], (float) $row['price_with_tax'], (int) $row['quantity']);
        }

        return $lines;
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getConnection(OrderTableMap::DATABASE_NAME);
    }
}
