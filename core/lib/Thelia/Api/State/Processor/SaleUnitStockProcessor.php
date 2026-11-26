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
use Thelia\Api\Resource\SaleUnitProductSaleElements as SaleUnitProductSaleElementsResource;
use Thelia\Domain\Catalog\Stock\StockLevelAnnouncer;
use Thelia\Model\SaleUnitProductSaleElements as SaleUnitProductSaleElementsModel;
use Thelia\Model\SaleUnitProductSaleElementsQuery;

/**
 * Writes the stock of a sale unit through the generic persistence, then tells the listeners of the
 * stock of the sale units when the write changed the quantity ({@see StockLevelAnnouncer}), as the
 * sale element processor does for the national counter.
 */
final readonly class SaleUnitStockProcessor implements ProcessorInterface
{
    public function __construct(
        private PropelPersistProcessor $persistProcessor,
        private StockLevelAnnouncer $stockLevelAnnouncer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $previous = isset($uriVariables['id']) ? SaleUnitProductSaleElementsQuery::create()->findPk((int) $uriVariables['id']) : null;
        $previousQuantity = null === $previous ? null : (float) $previous->getQuantity();

        $saved = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if (null !== $previousQuantity && $saved instanceof SaleUnitProductSaleElementsResource) {
            $stock = $saved->getPropelModel();

            if ($stock instanceof SaleUnitProductSaleElementsModel) {
                $this->stockLevelAnnouncer->announce((int) $stock->getProductSaleElementsId(), $previousQuantity, (float) $stock->getQuantity(), (int) $stock->getSaleUnitId());
            }
        }

        return $saved;
    }
}
