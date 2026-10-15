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
use Thelia\Api\Resource\ProductExcludedDeliveryModule;
use Thelia\Domain\Catalog\Product\Exception\NotAnActiveDeliveryModuleException;
use Thelia\Domain\Catalog\Product\Exception\ProductNotFoundException;
use Thelia\Domain\Catalog\Product\ProductFacade;
use Thelia\Model\ProductExcludedDeliveryModule as ProductExcludedDeliveryModuleModel;
use Thelia\Model\ProductExcludedDeliveryModuleQuery;

final readonly class ProductExcludedDeliveryModuleProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductFacade $productFacade,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ProductExcludedDeliveryModule) {
            return $data;
        }

        if ($operation instanceof DeleteOperationInterface) {
            $exclusion = $data->getPropelModel();

            if (!$exclusion instanceof ProductExcludedDeliveryModuleModel) {
                throw new UnprocessableEntityHttpException('This exclusion cannot be read back to be removed.');
            }

            $this->productFacade->allowDeliveryModule((int) $exclusion->getProductId(), (int) $exclusion->getModuleId());

            return null;
        }

        $productId = (int) $data->getProduct()->getId();
        $moduleId = (int) $data->getModule()->getId();

        try {
            $this->productFacade->excludeDeliveryModule($productId, $moduleId);
        } catch (ProductNotFoundException|NotAnActiveDeliveryModuleException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        $written = ProductExcludedDeliveryModuleQuery::create()
            ->filterByProductId($productId)
            ->filterByModuleId($moduleId)
            ->findOne();

        if (null !== $written) {
            $data->setId($written->getId());
            $data->setCreatedAt($written->getCreatedAt());
            $data->setUpdatedAt($written->getUpdatedAt());
            $data->setPropelModel($written);
        }

        return $data;
    }
}
