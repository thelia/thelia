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

namespace Thelia\Domain\Catalog\Bundle;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Domain\Catalog\Bundle\Exception\BundleDefinitionException;
use Thelia\Domain\Catalog\Bundle\Exception\BundleWriteConflictException;
use Thelia\Domain\Shared\Database\LockConflictDetector;
use Thelia\Model\Map\ProductBundleItemTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductBundleItem;
use Thelia\Model\ProductBundleItemQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * The only writer of the definition of a bundle: the API of the back office and the events
 * of its forms both end here, so they refuse the same things.
 *
 * Refused: a component that is the bundle itself or a bundle, a bundle that is a component of
 * another bundle, the same sale element twice in one bundle, a quantity that is not positive,
 * a minimum above the maximum, a quantity outside its own bounds, a required item whose
 * minimum is not above zero. Every write is one transaction.
 */
final readonly class BundleCompositionService
{
    /**
     * @param int|null $position null puts the item after the others
     *
     * @throws BundleDefinitionException
     */
    public function addItem(
        int $bundleProductId,
        int $productSaleElementsId,
        string $quantity = '1',
        ?string $minQuantity = null,
        ?string $maxQuantity = null,
        bool $required = true,
        ?int $position = null,
    ): ProductBundleItem {
        return $this->inTransaction(function () use ($bundleProductId, $productSaleElementsId, $quantity, $minQuantity, $maxQuantity, $required, $position): ProductBundleItem {
            // Both rows are held, the smaller id first whichever of the two it is, so that two writers
            // taking the same pair never wait on each other: the pack, which a second writer cannot
            // then fill under this one, and the product of the component, which lockComponent reads.
            $componentProductId = $this->productOfSaleElements($productSaleElementsId);
            $isBundle = null;
            $productIds = array_unique([$bundleProductId, $componentProductId]);
            sort($productIds);

            foreach ($productIds as $productId) {
                $stored = $this->lockProduct($productId);

                if ($productId === $bundleProductId) {
                    $isBundle = $stored;
                }
            }

            $component = $this->lockComponent($productSaleElementsId);

            if (null === $isBundle) {
                throw BundleDefinitionException::productNotFound($bundleProductId);
            }

            if (1 !== $isBundle) {
                throw BundleDefinitionException::notABundle($bundleProductId);
            }

            $this->assertComponent($bundleProductId, $productSaleElementsId, $component);
            [$quantity, $minQuantity, $maxQuantity] = $this->assertQuantities($quantity, $minQuantity, $maxQuantity, $required);

            $item = (new ProductBundleItem())
                ->setBundleProductId($bundleProductId)
                ->setProductSaleElementsId($productSaleElementsId)
                ->setQuantity($quantity)
                ->setMinQuantity($minQuantity)
                ->setMaxQuantity($maxQuantity)
                ->setRequired($required ? 1 : 0)
                ->setPosition($position ?? $this->nextPosition($bundleProductId));
            $this->saveItem($item);

            return $item;
        });
    }

    /**
     * Replaces what an item says of its quantities: every argument is the new value, null for a
     * bound clears it. The position has its own method. An item stays in its bundle: a caller that
     * names the bundle it believes the item is in (an API write, where the bundle is a field of the
     * body) is refused when the item is in another. An item keeps its sale element, even once that
     * sale element is deleted: the carts name the item by its id, and another component is another
     * item, removed and added.
     *
     * @throws BundleDefinitionException
     */
    public function updateItem(
        int $bundleItemId,
        int $productSaleElementsId,
        string $quantity,
        ?string $minQuantity,
        ?string $maxQuantity,
        bool $required,
        ?int $expectedBundleProductId = null,
    ): ProductBundleItem {
        return $this->inTransaction(function () use ($bundleItemId, $productSaleElementsId, $quantity, $minQuantity, $maxQuantity, $required, $expectedBundleProductId): ProductBundleItem {
            $item = $this->item($bundleItemId);

            if (null !== $expectedBundleProductId && $expectedBundleProductId !== (int) $item->getBundleProductId()) {
                throw BundleDefinitionException::itemStaysInItsBundle($bundleItemId, (int) $item->getBundleProductId());
            }

            if ($productSaleElementsId !== (int) $item->getProductSaleElementsId()) {
                throw BundleDefinitionException::itemKeepsItsSaleElement($bundleItemId);
            }

            [$quantity, $minQuantity, $maxQuantity] = $this->assertQuantities($quantity, $minQuantity, $maxQuantity, $required);

            $item
                ->setQuantity($quantity)
                ->setMinQuantity($minQuantity)
                ->setMaxQuantity($maxQuantity)
                ->setRequired($required ? 1 : 0);
            $this->saveItem($item);

            return $item;
        });
    }

    /**
     * @throws BundleDefinitionException
     */
    public function removeItem(int $bundleItemId): void
    {
        $this->inTransaction(function () use ($bundleItemId): null {
            $this->item($bundleItemId)->delete();

            return null;
        });
    }

    /**
     * @throws BundleDefinitionException
     */
    public function updatePosition(int $bundleItemId, int $position): ProductBundleItem
    {
        return $this->inTransaction(function () use ($bundleItemId, $position): ProductBundleItem {
            $item = $this->item($bundleItemId);
            $item->setPosition(max(0, $position))->save();

            return $item;
        });
    }

    /**
     * Gives the target bundle the lines of the source, as they are, in the same order: the
     * clone of a bundle holds the same components. A line whose sale element was deleted is
     * copied too, as it still says the bundle cannot be sold without it.
     *
     * @throws BundleDefinitionException
     */
    public function copyItems(int $sourceBundleProductId, int $targetBundleProductId): void
    {
        $this->inTransaction(function () use ($sourceBundleProductId, $targetBundleProductId): null {
            $sources = ProductBundleItemQuery::create()->filterByBundleProductId($sourceBundleProductId)->orderByPosition()->orderById()->find();

            // The products the copies take as components are held like those of addItem(), the
            // smaller id first, the target bundle among them: a copy is a writer of items too.
            $productIds = [$targetBundleProductId];

            foreach ($sources as $source) {
                if (null !== $source->getProductSaleElementsId()) {
                    $productIds[] = $this->productOfSaleElements((int) $source->getProductSaleElementsId());
                }
            }

            $productIds = array_unique($productIds);
            sort($productIds);
            $isBundle = [];

            foreach ($productIds as $productId) {
                $isBundle[$productId] = $this->lockProduct($productId);
            }

            if (null === $isBundle[$targetBundleProductId]) {
                throw BundleDefinitionException::productNotFound($targetBundleProductId);
            }

            if (1 !== $isBundle[$targetBundleProductId]) {
                throw BundleDefinitionException::notABundle($targetBundleProductId);
            }

            foreach ($sources as $source) {
                $source->copy()->setBundleProductId($targetBundleProductId)->save();
            }

            return null;
        });
    }

    /**
     * Null leaves a setting as it is.
     *
     * @throws BundleDefinitionException
     */
    public function setBundleSettings(
        int $productId,
        ?bool $isBundle = null,
        ?BundlePriceMode $priceMode = null,
        ?string $discount = null,
        ?BundleDiscountType $discountType = null,
    ): Product {
        return $this->inTransaction(function () use ($productId, $isBundle, $priceMode, $discount, $discountType): Product {
            // Held before anything is read: the checks of a switch see what was committed before them.
            $this->lockProduct($productId);
            $product = ProductQuery::create()->findPk($productId) ?? throw BundleDefinitionException::productNotFound($productId);

            $this->applySettings($product, $isBundle, $priceMode, $discount, $discountType);
            $product->save();

            return $product;
        });
    }

    /**
     * Validates the settings and sets them on the product, which the caller saves. The update
     * of a product by the catalogue events uses it inside its own transaction.
     *
     * Turning a product into a bundle, or a bundle back into a product, discards its cart lines:
     * the Product model does it when it saves the switch, for every path that saves it.
     *
     * @throws BundleDefinitionException
     */
    public function applySettings(
        Product $product,
        ?bool $isBundle,
        ?BundlePriceMode $priceMode,
        ?string $discount,
        ?BundleDiscountType $discountType,
    ): void {
        if (true === $isBundle && 1 !== $this->lockProduct((int) $product->getId())) {
            $this->assertCanBecomeBundle((int) $product->getId());
        }

        if (null !== $isBundle) {
            $product->setIsBundle($isBundle ? 1 : 0);
        }

        if (null !== $priceMode) {
            $product->setBundlePriceMode($priceMode->value);
        }

        if (null !== $discountType) {
            $product->setBundleDiscountType($discountType->value);
        }

        if (null !== $discount) {
            try {
                $discount = BundleDecimal::normalize($discount);
            } catch (\InvalidArgumentException $exception) {
                throw BundleDefinitionException::invalidSetting('the discount is a non-negative decimal ('.$exception->getMessage().')');
            }

            // DECIMAL(16,6): the column refuses what a bigger number would write.
            if (BundleDecimal::toMicro($discount) > BundleDecimal::MAX_STORED) {
                throw BundleDefinitionException::invalidSetting('the discount is too large');
            }

            $product->setBundleDiscount($discount);
        }

        if (BundleDiscountType::Percent->value === $product->getBundleDiscountType() && BundleDecimal::toMicro((string) $product->getBundleDiscount()) > 100 * BundleDecimal::SCALE) {
            throw BundleDefinitionException::invalidSetting('a percent discount cannot exceed 100');
        }
    }

    /**
     * The index on (bundle product, sale element) is the last word on a duplicate: two writes
     * that both passed the check meet there, and the second is refused like the first would have been.
     *
     * @throws BundleDefinitionException
     */
    private function saveItem(ProductBundleItem $item): void
    {
        try {
            $item->save();
        } catch (PropelException $exception) {
            $previous = $exception->getPrevious();

            if ($previous instanceof \PDOException && 1062 === ($previous->errorInfo[1] ?? null)) {
                throw BundleDefinitionException::componentAlreadyInBundle((int) $item->getProductSaleElementsId(), (int) $item->getBundleProductId());
            }

            throw $exception;
        }
    }

    private function assertCanBecomeBundle(int $productId): void
    {
        // A bundle has a single sale element, the one that carries its price in fixed mode.
        $saleElementsCount = ProductSaleElementsQuery::create()->filterByProductId($productId)->count();

        if ($saleElementsCount > 1) {
            throw BundleDefinitionException::hasSeveralSaleElements($productId, $saleElementsCount);
        }

        // Read without a lock: every writer of an item holds the row of the product it takes as a
        // component until its commit (lockComponent), and the caller holds this product's row
        // before its first plain read (lockProduct), so what was committed is what is read here.
        // A shared lock on the sale elements of a product would lock a range of the index on
        // product_id, which the writers of other products' sale elements would wait on.
        $isComponent = ProductBundleItemQuery::create()
            ->useProductSaleElementsQuery()
                ->filterByProductId($productId)
            ->endUse()
            ->exists();

        if ($isComponent) {
            throw BundleDefinitionException::bundleIsAComponent($productId);
        }
    }

    /**
     * @param array{productId: int, isBundle: bool} $component what lockComponent() read
     */
    private function assertComponent(int $bundleProductId, int $productSaleElementsId, array $component): void
    {
        if ($component['productId'] === $bundleProductId) {
            throw BundleDefinitionException::componentIsTheBundle($component['productId']);
        }

        if ($component['isBundle']) {
            throw BundleDefinitionException::componentIsABundle($component['productId']);
        }

        $exists = ProductBundleItemQuery::create()
            ->filterByBundleProductId($bundleProductId)
            ->filterByProductSaleElementsId($productSaleElementsId)
            ->exists();

        if ($exists) {
            throw BundleDefinitionException::componentAlreadyInBundle($productSaleElementsId, $bundleProductId);
        }
    }

    /**
     * @return array{0: string, 1: string|null, 2: string|null} the quantity and its bounds, normalised
     */
    private function assertQuantities(string $quantity, ?string $minQuantity, ?string $maxQuantity, bool $required): array
    {
        try {
            $quantityMicro = BundleDecimal::toMicro($quantity);
            $minMicro = null === $minQuantity ? null : BundleDecimal::toMicro($minQuantity);
            $maxMicro = null === $maxQuantity ? null : BundleDecimal::toMicro($maxQuantity);
        } catch (\InvalidArgumentException $exception) {
            throw BundleDefinitionException::invalidQuantity($exception->getMessage());
        }

        if ($quantityMicro <= 0) {
            throw BundleDefinitionException::invalidQuantity('the quantity must be above zero');
        }

        // The bound of what a customer may ask for: an item above it could never be chosen.
        foreach ([$quantityMicro, $minMicro, $maxMicro] as $micro) {
            if (null !== $micro && $micro > BundleComposition::MAX_ITEM_QUANTITY * BundleDecimal::SCALE) {
                throw BundleDefinitionException::invalidQuantity(\sprintf('a quantity is at most %d', BundleComposition::MAX_ITEM_QUANTITY));
            }
        }

        if (null !== $maxMicro && $maxMicro <= 0) {
            throw BundleDefinitionException::invalidQuantity('the maximum must be above zero');
        }

        if (null !== $minMicro && null !== $maxMicro && $minMicro > $maxMicro) {
            throw BundleDefinitionException::invalidQuantity('the minimum is above the maximum');
        }

        if ((null !== $minMicro && $quantityMicro < $minMicro) || (null !== $maxMicro && $quantityMicro > $maxMicro)) {
            throw BundleDefinitionException::invalidQuantity('the quantity is outside its minimum and maximum');
        }

        if ($required && null !== $minMicro && $minMicro <= 0) {
            throw BundleDefinitionException::invalidQuantity('a required item has a minimum above zero, or none');
        }

        return [
            BundleDecimal::fromMicro($quantityMicro),
            null === $minMicro ? null : BundleDecimal::fromMicro($minMicro),
            null === $maxMicro ? null : BundleDecimal::fromMicro($maxMicro),
        ];
    }

    /**
     * "No bundle in a bundle" is decided under the lock of the product row that can break it: the
     * product of the component, which another writer may be turning into a bundle, and the product
     * that becomes a bundle, which another writer may be adding as a component. Both writers lock
     * it first and read it by a locking read, which sees the last commit (verrous d'abord).
     *
     * @return array{productId: int, isBundle: bool}
     */
    private function lockComponent(int $productSaleElementsId): array
    {
        $statement = Propel::getWriteConnection(ProductBundleItemTableMap::DATABASE_NAME)->prepare(
            'SELECT `product`.`id`, `product`.`is_bundle` FROM `product_sale_elements` INNER JOIN `product` ON `product`.`id` = `product_sale_elements`.`product_id` WHERE `product_sale_elements`.`id` = :id FOR UPDATE',
        );
        $statement->bindValue(':id', $productSaleElementsId, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_NUM);
        $statement->closeCursor();

        if (false === $row) {
            throw BundleDefinitionException::saleElementsNotFound($productSaleElementsId);
        }

        return ['productId' => (int) $row[0], 'isBundle' => 1 === (int) $row[1]];
    }

    private function productOfSaleElements(int $productSaleElementsId): int
    {
        $statement = Propel::getWriteConnection(ProductBundleItemTableMap::DATABASE_NAME)->prepare('SELECT `product_id` FROM `product_sale_elements` WHERE `id` = :id');
        $statement->bindValue(':id', $productSaleElementsId, \PDO::PARAM_INT);
        $statement->execute();
        $productId = $statement->fetchColumn();
        $statement->closeCursor();

        if (false === $productId || null === $productId || '' === $productId) {
            throw BundleDefinitionException::saleElementsNotFound($productSaleElementsId);
        }

        return (int) $productId;
    }

    /**
     * Takes the row of a product until the commit of the transaction in progress. A transaction
     * that decides on what other writers committed takes it before its first plain read: its
     * picture of the database is taken at that read, and the locking read that follows sees the
     * last commit, the plain ones that come after it see it too.
     *
     * @return int|null the stored is_bundle, null for a product that does not exist
     */
    public function lockProduct(int $productId): ?int
    {
        $statement = Propel::getWriteConnection(ProductBundleItemTableMap::DATABASE_NAME)->prepare(\sprintf('SELECT `is_bundle` FROM `product` WHERE `id` = %d FOR UPDATE', $productId));
        $statement->execute();
        $isBundle = $statement->fetchColumn();
        $statement->closeCursor();

        return null === $isBundle || false === $isBundle || '' === $isBundle ? null : (int) $isBundle;
    }

    private function item(int $bundleItemId): ProductBundleItem
    {
        return ProductBundleItemQuery::create()->findPk($bundleItemId) ?? throw BundleDefinitionException::itemNotFound($bundleItemId);
    }

    private function nextPosition(int $bundleProductId): int
    {
        $last = ProductBundleItemQuery::create()
            ->filterByBundleProductId($bundleProductId)
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        return null === $last ? 0 : (int) $last->getPosition() + 1;
    }

    /**
     * @template T
     *
     * @param callable(): T $write
     *
     * @return T
     */
    private function inTransaction(callable $write): mixed
    {
        $connection = Propel::getWriteConnection(ProductBundleItemTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $result = $write();
            $connection->commit();

            return $result;
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            // Another writer holds a row this one needs and the database broke the tie: nothing of
            // this write stands, and written again it is judged on what the other one left.
            if (LockConflictDetector::isLockConflict($throwable)) {
                throw BundleWriteConflictException::from($throwable);
            }

            throw $throwable;
        }
    }
}
