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

namespace Thelia\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Thelia\Api\Bridge\Propel\State\PropelPersistProcessor;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Domain\Catalog\Stock\StockLevelAnnouncer;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Writes a product of the admin API (a PUT or a PATCH, which may carry its combinations) through
 * the generic persistence, then tells the listeners of the stock levels what the write did to the
 * stock of its combinations ({@see StockLevelAnnouncer}), as the write of a combination alone does.
 *
 * The stocks are read from the table before and after, never from the models: those may hold
 * the old value or the new one on either side. A combination the write creates has no stock
 * before it, and one it removes none after: neither is a change.
 *
 * The two reads take no lock: an order that lowers the stock of a combination between them has its
 * decrease told by the order and told again by this write: a narrow window, accepted.
 */
final readonly class ProductStockProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private StockLevelAnnouncer $stockLevelAnnouncer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $productId = isset($uriVariables['id']) ? (int) $uriVariables['id'] : null;
        $before = null === $productId ? [] : $this->stocksOf($productId);

        $saved = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ([] !== $before) {
            $changes = [];

            foreach ($this->stocksOf((int) $productId) as $productSaleElementsId => $after) {
                if (isset($before[$productSaleElementsId])) {
                    $changes[] = new StockLevelChange($productSaleElementsId, $before[$productSaleElementsId], $after);
                }
            }

            $this->stockLevelAnnouncer->announceChanges($changes);
        }

        return $saved;
    }

    /**
     * @return array<int, float> the stock of each combination of the product, by combination id
     */
    private function stocksOf(int $productId): array
    {
        /** @var list<array{Id: int|string, Quantity: int|string|float|null}> $rows */
        $rows = ProductSaleElementsQuery::create()
            ->filterByProductId($productId)
            ->select(['Id', 'Quantity'])
            ->find()
            ->getData();
        $stocks = [];

        foreach ($rows as $row) {
            $stocks[(int) $row['Id']] = (float) $row['Quantity'];
        }

        return $stocks;
    }
}
