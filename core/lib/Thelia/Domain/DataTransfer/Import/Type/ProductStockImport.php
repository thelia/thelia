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

namespace Thelia\Domain\DataTransfer\Import\Type;

use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Catalog\Product\Identifier\InvalidGtinException;
use Thelia\Domain\Catalog\Product\Identifier\InvalidMpnException;
use Thelia\Domain\Catalog\Quantity\InvalidQuantityRuleException;
use Thelia\Domain\Catalog\Quantity\QuantityPolicy;
use Thelia\Domain\Catalog\Quantity\QuantityRefusedException;
use Thelia\Domain\Catalog\Quantity\UnreadableQuantityException;
use Thelia\Domain\DataTransfer\Import\AbstractImport;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ControllerTestBase.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ProductStockImport extends AbstractImport
{
    protected array $mandatoryColumns = [
        'id',
        'stock',
    ];

    protected array $optionalColumns = [
        'ean',
        'mpn',
    ];

    /** @var array<int, float> by sale element id, the stock before the first row that wrote it */
    private array $stockBefore = [];

    /** @var array<int, float> by sale element id, the stock after the last row that wrote it */
    private array $stockAfter = [];

    /**
     * What the rows written so far did to the stock, one change per sale element whatever the number
     * of rows that wrote it. A refused row is never part of it.
     *
     * @return list<StockLevelChange>
     */
    public function stockChanges(): array
    {
        $changes = [];

        foreach ($this->stockBefore as $productSaleElementsId => $before) {
            $changes[] = new StockLevelChange($productSaleElementsId, $before, $this->stockAfter[$productSaleElementsId]);
        }

        return $changes;
    }

    public function importData(array $data): ?string
    {
        $pse = ProductSaleElementsQuery::create()->findPk($data['id']);

        if (null === $pse) {
            return Translator::getInstance()->trans(
                "The product sale element id %id doesn't exist",
                [
                    '%id' => $data['id'],
                ],
            );
        }

        // A spreadsheet gives every cell as text: the stock is read as a number, and
        // a cell that is not one refuses this row only.
        if (!self::isStorableNumber($data['stock'])) {
            return Translator::getInstance()->trans(
                'The value "%value" of the column %column is not a number (product sale element id %id)',
                ['%value' => self::cellText($data['stock']), '%column' => 'stock', '%id' => self::cellText($data['id'])],
            );
        }

        // A stock holds the decimals its combination is sold with: 12.5 of a combination
        // sold by the piece is a unit the combination does not sell in.
        try {
            $stock = (new QuantityPolicy())->assertStockFits((float) $data['stock'], $pse);
        } catch (QuantityRefusedException|UnreadableQuantityException|InvalidQuantityRuleException $refusal) {
            return Translator::getInstance()->trans(
                'The stock %stock of the product sale element id %id cannot be kept: %reason',
                ['%stock' => self::cellText($data['stock']), '%id' => self::cellText($data['id']), '%reason' => $refusal->getMessage()],
            );
        }

        // Read from the table, not from the instance: a pooled one may be older than the row.
        $stockBefore = $this->stockBefore[(int) $pse->getId()] ?? $this->storedStockOf((int) $pse->getId());

        $pse->setQuantity($stock);

        if (isset($data['ean']) && !empty($data['ean'])) {
            $pse->setEanCode((string) $data['ean']);
        }

        if (isset($data['mpn']) && '' !== trim((string) $data['mpn'])) {
            $pse->setMpn((string) $data['mpn']);
        }

        // A code that is not a GTIN refuses this row only, with the reason, and the
        // import carries on with the next one. Checked before saving: refused by
        // save(), it would roll back a transaction nested in the import's, which
        // could then not be committed.
        try {
            $pse->assertIdentifiersAreValid();
        } catch (InvalidGtinException|InvalidMpnException $refusal) {
            // The refused values would otherwise stay on the pooled instance and come
            // back with a later row of the same combination.
            $pse->reload();

            return $refusal->getMessage();
        }

        $pse->save();

        $this->stockBefore[(int) $pse->getId()] = $stockBefore;
        $this->stockAfter[(int) $pse->getId()] = (float) $pse->getQuantity();

        ++$this->importedRows;

        return null;
    }

    private function storedStockOf(int $productSaleElementsId): float
    {
        /** @var int|string|float|null $stock */
        $stock = ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsId)
            ->select('Quantity')
            ->findOne();

        return (float) $stock;
    }
}
