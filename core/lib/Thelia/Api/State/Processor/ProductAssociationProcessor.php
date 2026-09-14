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

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Resource\ProductAssociation;
use Thelia\Domain\Catalog\Product\Exception\ProductAssociationTypeNotFoundException;
use Thelia\Domain\Catalog\Product\Exception\ProductNotFoundException;
use Thelia\Domain\Catalog\Product\Exception\SelfAssociationException;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Model\Accessory;
use Thelia\Model\ProductAssociationTypeQuery;

/**
 * Writes a relation between two products through the product facade instead of
 * persisting it.
 *
 * The facade is what makes the events fire, writes the mirror row of a reciprocal
 * type, and refuses a product related to itself. A Propel persist here would save
 * one row, announce nothing, and leave a cross-selling relation readable from one
 * side only.
 *
 * The type is resolved by its id against the database rather than read off the
 * denormalized relation: whichever way the IRI was resolved, the code that reaches
 * the facade is a code the shop really has.
 */
final readonly class ProductAssociationProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductFacade $productFacade,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ProductAssociation) {
            return $data;
        }

        if ($operation instanceof DeleteOperationInterface) {
            $this->remove($data);

            return null;
        }

        return $this->add($data);
    }

    private function add(ProductAssociation $data): ProductAssociation
    {
        $productId = (int) $data->getProduct()->getId();
        $associatedProductId = (int) $data->getAssociatedProduct()->getId();
        $typeCode = $this->resolveTypeCode((int) $data->getType()->getId());

        try {
            $this->productFacade->addAssociation($productId, $associatedProductId, $typeCode);
        } catch (ProductNotFoundException|SelfAssociationException|ProductAssociationTypeNotFoundException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        $written = $this->findWrittenRelation($productId, $associatedProductId, $typeCode);

        if ($written instanceof Accessory) {
            $data->setId($written->getId());
            $data->setPosition($written->getPosition());
            $data->setCreatedAt($written->getCreatedAt());
            $data->setUpdatedAt($written->getUpdatedAt());
            $data->setPropelModel($written);
        }

        return $data;
    }

    private function remove(ProductAssociation $data): void
    {
        $relation = $data->getPropelModel();

        if (!$relation instanceof Accessory) {
            throw new UnprocessableEntityHttpException('This relation cannot be read back to be removed.');
        }

        try {
            $this->productFacade->removeAssociation(
                (int) $relation->getProductId(),
                (int) $relation->getAccessory(),
                (string) $relation->getProductAssociationType()->getCode(),
            );
        } catch (ProductNotFoundException|SelfAssociationException|ProductAssociationTypeNotFoundException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }

    private function resolveTypeCode(int $typeId): string
    {
        $type = ProductAssociationTypeQuery::create()->findPk($typeId);

        if (null === $type) {
            throw new UnprocessableEntityHttpException(\sprintf('No product relation type has the id %d.', $typeId));
        }

        return (string) $type->getCode();
    }

    private function findWrittenRelation(int $productId, int $associatedProductId, string $typeCode): ?Accessory
    {
        foreach ($this->productFacade->getAssociations($productId, $typeCode) as $relation) {
            if ((int) $relation->getAccessory() === $associatedProductId) {
                return $relation;
            }
        }

        return null;
    }
}
