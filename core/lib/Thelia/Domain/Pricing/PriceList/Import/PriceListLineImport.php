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

namespace Thelia\Domain\Pricing\PriceList\Import;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Model\PriceList;
use Thelia\Model\PriceListItem;
use Thelia\Model\PriceListItemQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Loads the lines of a price list from a CSV file: preview() says what apply() would do and
 * writes nothing, apply() does it.
 *
 * A line is identified by (price list, sale element, minimum quantity): it is updated when it
 * exists and created otherwise. A line in error is not written, the others are; the report says
 * which and why, with the line number of the file. The writes go by batches of BATCH_SIZE lines,
 * each in its own transaction: a failure of the database rolls back the batch it hit, the earlier
 * ones stay, and the exception says so.
 */
final readonly class PriceListLineImport
{
    public const int BATCH_SIZE = 500;

    private const string DECIMAL_PATTERN = '/^(\d{1,10})(?:\.(\d{1,6}))?$/';

    public function __construct(private PriceListCsvReader $reader)
    {
    }

    /**
     * @throws PriceListImportException
     */
    public function preview(PriceList $priceList, \SplFileInfo $file): PriceListImportReport
    {
        return $this->run($priceList, $file, false);
    }

    /**
     * @throws PriceListImportException
     */
    public function apply(PriceList $priceList, \SplFileInfo $file): PriceListImportReport
    {
        return $this->run($priceList, $file, true);
    }

    private function run(PriceList $priceList, \SplFileInfo $file, bool $apply): PriceListImportReport
    {
        $created = 0;
        $updated = 0;
        $errorCount = 0;
        $errors = [];
        /** @var array<string, int> $seen the line of the file that first carried a (sale element, minimum quantity) */
        $seen = [];
        $batch = [];
        $writtenBatches = 0;

        foreach ($this->reader->read($file) as $row) {
            $batch[] = $row;

            if (\count($batch) >= self::BATCH_SIZE) {
                $this->processBatch($priceList, $batch, $seen, $apply, $writtenBatches, $created, $updated, $errorCount, $errors);
                $batch = [];
                ++$writtenBatches;
            }
        }

        if ([] !== $batch) {
            $this->processBatch($priceList, $batch, $seen, $apply, $writtenBatches, $created, $updated, $errorCount, $errors);
        }

        return new PriceListImportReport($apply, $created, $updated, $errorCount, $errors);
    }

    /**
     * @param list<PriceListCsvRow>          $rows
     * @param array<string, int>             $seen
     * @param list<PriceListImportLineError> $errors
     */
    private function processBatch(
        PriceList $priceList,
        array $rows,
        array &$seen,
        bool $apply,
        int $writtenBatches,
        int &$created,
        int &$updated,
        int &$errorCount,
        array &$errors,
    ): void {
        $connection = $apply ? Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME) : null;
        $connection?->beginTransaction();

        try {
            $plans = $this->plan($priceList, $rows, $seen, $errorCount, $errors);

            foreach ($plans as $plan) {
                if (null === $plan['existing']) {
                    ++$created;
                } else {
                    ++$updated;
                }

                if (null !== $connection) {
                    $this->write($priceList, $plan, $connection);
                }
            }

            $connection?->commit();
        } catch (\Throwable $exception) {
            $connection?->rollBack();

            throw new PriceListImportException(\sprintf('The database refused a batch of lines (lines %d to %d); %d earlier batch(es) of %d lines were written.', $rows[0]->line, $rows[array_key_last($rows)]->line, $writtenBatches, self::BATCH_SIZE), 0, $exception);
        }
    }

    /**
     * @param list<PriceListCsvRow>          $rows
     * @param array<string, int>             $seen
     * @param list<PriceListImportLineError> $errors
     *
     * @return list<array{saleElementId: int, minimumQuantity: int, price: ?string, discountPercent: ?string, existing: ?PriceListItem}>
     */
    private function plan(PriceList $priceList, array $rows, array &$seen, int &$errorCount, array &$errors): array
    {
        $saleElementIdsByReference = $this->saleElementIdsByReference($rows);
        $knownIds = $this->knownSaleElementIds($rows);
        /** @var list<array{line: int, saleElementId: int, minimumQuantity: int, price: ?string, discountPercent: ?string}> $valid */
        $valid = [];

        foreach ($rows as $row) {
            $failure = $row->error;
            $saleElementId = null;
            $minimumQuantity = 1;
            $price = null;
            $discountPercent = null;

            if (null === $failure) {
                $failure = $this->saleElementFailure($row, $saleElementIdsByReference, $knownIds, $saleElementId)
                    ?? $this->quantityFailure($row, $minimumQuantity)
                    ?? $this->amountFailure($row, $price, $discountPercent);
            }

            if (null === $failure && null !== $saleElementId) {
                $key = $saleElementId.':'.$minimumQuantity;

                if (isset($seen[$key])) {
                    $failure = new PriceListImportLineError($row->line, PriceListImportLineError::DUPLICATE_IN_FILE, (string) $seen[$key]);
                } else {
                    $seen[$key] = $row->line;
                }
            }

            if (null !== $failure || null === $saleElementId) {
                ++$errorCount;

                if (\count($errors) < PriceListImportReport::MAX_REPORTED_ERRORS) {
                    $errors[] = $failure ?? new PriceListImportLineError($row->line, PriceListImportLineError::NO_SALE_ELEMENT);
                }

                continue;
            }

            $valid[] = ['line' => $row->line, 'saleElementId' => $saleElementId, 'minimumQuantity' => $minimumQuantity, 'price' => $price, 'discountPercent' => $discountPercent];
        }

        $existing = $this->existingItems($priceList, array_column($valid, 'saleElementId'));
        $plans = [];

        foreach ($valid as $line) {
            $plans[] = [
                'saleElementId' => $line['saleElementId'],
                'minimumQuantity' => $line['minimumQuantity'],
                'price' => $line['price'],
                'discountPercent' => $line['discountPercent'],
                'existing' => $existing[$line['saleElementId'].':'.$line['minimumQuantity']] ?? null,
            ];
        }

        return $plans;
    }

    /**
     * @param array{saleElementId: int, minimumQuantity: int, price: ?string, discountPercent: ?string, existing: ?PriceListItem} $plan
     */
    private function write(PriceList $priceList, array $plan, ConnectionInterface $connection): void
    {
        $item = $plan['existing'] ?? (new PriceListItem())
            ->setPriceListId($priceList->getId())
            ->setProductSaleElementsId($plan['saleElementId'])
            ->setMinQuantity($plan['minimumQuantity']);

        $item
            ->setPrice($plan['price'])
            ->setDiscountPercent($plan['discountPercent'])
            ->save($connection);
    }

    /**
     * @param list<PriceListCsvRow> $rows
     *
     * @return array<string, list<int>> lower-cased reference => ids of the sale elements that carry it
     */
    private function saleElementIdsByReference(array $rows): array
    {
        $references = array_values(array_unique(array_filter(array_map(static fn (PriceListCsvRow $row): ?string => $row->saleElementReference, $rows))));

        if ([] === $references) {
            return [];
        }

        $found = [];

        /** @var list<array{Id: int|string, Ref: string}> $matches */
        $matches = ProductSaleElementsQuery::create()->filterByRef($references, Criteria::IN)->select(['Id', 'Ref'])->find()->toArray();

        foreach ($matches as $match) {
            $found[mb_strtolower($match['Ref'])][] = (int) $match['Id'];
        }

        return $found;
    }

    /**
     * @param list<PriceListCsvRow> $rows
     *
     * @return array<int, true>
     */
    private function knownSaleElementIds(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            if (null !== $row->saleElementId && 1 === preg_match('/^\d{1,10}$/', $row->saleElementId)) {
                $ids[] = (int) $row->saleElementId;
            }
        }

        if ([] === $ids) {
            return [];
        }

        $known = [];

        foreach (ProductSaleElementsQuery::create()->filterById(array_values(array_unique($ids)), Criteria::IN)->select('Id')->find()->toArray() as $id) {
            $known[(int) $id] = true;
        }

        return $known;
    }

    /**
     * @param list<int> $saleElementIds
     *
     * @return array<string, PriceListItem>
     */
    private function existingItems(PriceList $priceList, array $saleElementIds): array
    {
        if ([] === $saleElementIds) {
            return [];
        }

        $items = [];

        foreach (PriceListItemQuery::create()->filterByPriceListId($priceList->getId())->filterByProductSaleElementsId(array_values(array_unique($saleElementIds)), Criteria::IN)->find() as $item) {
            $items[$item->getProductSaleElementsId().':'.$item->getMinQuantity()] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, list<int>> $saleElementIdsByReference
     * @param array<int, true>         $knownIds
     */
    private function saleElementFailure(PriceListCsvRow $row, array $saleElementIdsByReference, array $knownIds, ?int &$saleElementId): ?PriceListImportLineError
    {
        $fromReference = null;
        $fromId = null;
        $sharedBy = [];

        if (null !== $row->saleElementReference) {
            $matches = $saleElementIdsByReference[mb_strtolower($row->saleElementReference)] ?? [];

            if ([] === $matches) {
                return new PriceListImportLineError($row->line, PriceListImportLineError::UNKNOWN_REFERENCE, $this->printable($row->saleElementReference));
            }

            if (\count($matches) > 1) {
                // The reference is shared (nothing makes it unique): only the identifier of the line can tell which one it names.
                if (null === $row->saleElementId) {
                    return new PriceListImportLineError($row->line, PriceListImportLineError::AMBIGUOUS_REFERENCE, $this->printable($row->saleElementReference));
                }

                $sharedBy = $matches;
            } else {
                $fromReference = $matches[0];
            }
        }

        if (null !== $row->saleElementId) {
            if (1 !== preg_match('/^\d{1,10}$/', $row->saleElementId) || !isset($knownIds[(int) $row->saleElementId])) {
                return new PriceListImportLineError($row->line, PriceListImportLineError::UNKNOWN_SALE_ELEMENT_ID, $this->printable($row->saleElementId));
            }

            $fromId = (int) $row->saleElementId;
        }

        if (null === $fromReference && null === $fromId) {
            return new PriceListImportLineError($row->line, PriceListImportLineError::NO_SALE_ELEMENT);
        }

        if (null !== $fromReference && null !== $fromId && $fromReference !== $fromId) {
            return new PriceListImportLineError($row->line, PriceListImportLineError::REFERENCE_AND_ID_DISAGREE);
        }

        if ([] !== $sharedBy && !\in_array($fromId, $sharedBy, true)) {
            return new PriceListImportLineError($row->line, PriceListImportLineError::REFERENCE_AND_ID_DISAGREE);
        }

        $saleElementId = $fromReference ?? $fromId;

        return null;
    }

    private function quantityFailure(PriceListCsvRow $row, int &$minimumQuantity): ?PriceListImportLineError
    {
        if (null === $row->minimumQuantity) {
            $minimumQuantity = 1;

            return null;
        }

        if (1 !== preg_match('/^\d{1,9}$/', $row->minimumQuantity) || (int) $row->minimumQuantity < 1) {
            return new PriceListImportLineError($row->line, PriceListImportLineError::INVALID_MINIMUM_QUANTITY, $this->printable($row->minimumQuantity));
        }

        $minimumQuantity = (int) $row->minimumQuantity;

        return null;
    }

    private function amountFailure(PriceListCsvRow $row, ?string &$price, ?string &$discountPercent): ?PriceListImportLineError
    {
        if (null !== $row->price && null !== $row->discountPercent) {
            return new PriceListImportLineError($row->line, PriceListImportLineError::TWO_AMOUNTS);
        }

        if (null === $row->price && null === $row->discountPercent) {
            return new PriceListImportLineError($row->line, PriceListImportLineError::NO_AMOUNT);
        }

        if (null !== $row->price) {
            $price = $this->decimal($row->price);

            return null === $price ? new PriceListImportLineError($row->line, PriceListImportLineError::INVALID_PRICE, $this->printable($row->price)) : null;
        }

        $discountPercent = $this->decimal((string) $row->discountPercent);

        if (null === $discountPercent || (float) $discountPercent <= 0.0 || (float) $discountPercent > 100.0) {
            $discountPercent = null;

            return new PriceListImportLineError($row->line, PriceListImportLineError::INVALID_DISCOUNT_PERCENT, $this->printable((string) $row->discountPercent));
        }

        return null;
    }

    /**
     * A non-negative decimal, a comma or a point as separator, at most 6 decimals, rewritten with
     * exactly 6 ('9.5' => '9.500000'); null for anything else, signs and exponents included.
     */
    private function decimal(string $value): ?string
    {
        if (1 !== preg_match(self::DECIMAL_PATTERN, str_replace(',', '.', $value), $parts)) {
            return null;
        }

        return $parts[1].'.'.str_pad($parts[2] ?? '', 6, '0');
    }

    private function printable(string $value): string
    {
        return mb_strimwidth(preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '', 0, 40, '...');
    }
}
