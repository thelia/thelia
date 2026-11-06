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
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Domain\Catalog\Stock\StockLevelAnnouncer;
use Thelia\Model\ProductSaleElements as ProductSaleElementsModel;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Writes a sale element of the admin API through the generic persistence, then tells the listeners
 * of the stock levels when the write changed the quantity, the way the back office and the facade
 * tell them ({@see StockLevelAnnouncer}): the modules that follow the stock (the alert of a low
 * stock, a back in stock notice) hear of a stock edited by the API as they hear of one edited in the
 * back office.
 */
final readonly class ProductSaleElementsStockProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private StockLevelAnnouncer $stockLevelAnnouncer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $previousQuantity = isset($uriVariables['id']) ? $this->quantityOf((int) $uriVariables['id']) : null;

        $saved = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if (null !== $previousQuantity && $saved instanceof ProductSaleElementsResource) {
            $saleElements = $saved->getPropelModel();

            if ($saleElements instanceof ProductSaleElementsModel) {
                $this->stockLevelAnnouncer->announce((int) $saleElements->getId(), $previousQuantity, (float) $saleElements->getQuantity());
            }
        }

        return $saved;
    }

    private function quantityOf(int $productSaleElementsId): ?float
    {
        /** @var int|string|float|null $quantity */
        $quantity = ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsId)
            ->select('Quantity')
            ->findOne();

        return null === $quantity ? null : (float) $quantity;
    }
}
