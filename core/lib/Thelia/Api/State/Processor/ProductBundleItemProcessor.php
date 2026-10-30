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
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Thelia\Api\Resource\ProductBundleItem;
use Thelia\Domain\Catalog\Bundle\BundleCompositionService;
use Thelia\Domain\Catalog\Bundle\BundleDecimal;
use Thelia\Domain\Catalog\Bundle\Exception\BundleDefinitionException;
use Thelia\Model\ProductBundleItem as ProductBundleItemModel;

/**
 * Writes the components of a bundle through BundleCompositionService, never through the
 * model: the service is the one place that knows what a bundle can hold.
 */
final readonly class ProductBundleItemProcessor implements ProcessorInterface
{
    public function __construct(private BundleCompositionService $bundleCompositionService)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ProductBundleItem) {
            return $data;
        }

        try {
            if ($operation instanceof DeleteOperationInterface) {
                $this->bundleCompositionService->removeItem((int) $data->getId());

                return null;
            }

            $item = $operation instanceof Post ? $this->add($data) : $this->update($data, (int) ($uriVariables['id'] ?? $data->getId()));
        } catch (BundleDefinitionException|\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        $data->setId((int) $item->getId());
        $data->setPosition((int) $item->getPosition());
        $data->setCreatedAt($item->getCreatedAt());
        $data->setUpdatedAt($item->getUpdatedAt());
        $data->setPropelModel($item);

        return $data;
    }

    private function add(ProductBundleItem $data): ProductBundleItemModel
    {
        if (null === $data->getProductSaleElements()) {
            throw new UnprocessableEntityHttpException('A bundle component needs a sale element.');
        }

        return $this->bundleCompositionService->addItem(
            (int) $data->getBundleProduct()->getId(),
            (int) $data->getProductSaleElements()->getId(),
            BundleDecimal::normalize($data->getQuantity()),
            null === $data->getMinQuantity() ? null : BundleDecimal::normalize($data->getMinQuantity()),
            null === $data->getMaxQuantity() ? null : BundleDecimal::normalize($data->getMaxQuantity()),
            $data->isRequired(),
            $data->getPosition(),
        );
    }

    /**
     * The item is the one of the URI: a PUT replaces the resource by what the body says, and
     * the body does not carry the identifier.
     */
    private function update(ProductBundleItem $data, int $bundleItemId): ProductBundleItemModel
    {
        if (null === $data->getProductSaleElements()) {
            throw new UnprocessableEntityHttpException('A bundle component keeps its sale element: delete it and add a new one.');
        }

        $item = $this->bundleCompositionService->updateItem(
            $bundleItemId,
            (int) $data->getProductSaleElements()->getId(),
            BundleDecimal::normalize($data->getQuantity()),
            null === $data->getMinQuantity() ? null : BundleDecimal::normalize($data->getMinQuantity()),
            null === $data->getMaxQuantity() ? null : BundleDecimal::normalize($data->getMaxQuantity()),
            $data->isRequired(),
            (int) $data->getBundleProduct()->getId(),
        );

        if (null !== $data->getPosition() && $data->getPosition() !== (int) $item->getPosition()) {
            $item = $this->bundleCompositionService->updatePosition($bundleItemId, $data->getPosition());
        }

        return $item;
    }
}
