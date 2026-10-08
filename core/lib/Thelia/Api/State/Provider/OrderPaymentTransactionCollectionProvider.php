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

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Bridge\Propel\State\Pagination\PropelPaginator;
use Thelia\Api\Resource\OrderPaymentTransaction;
use Thelia\Model\OrderPaymentTransaction as OrderPaymentTransactionModel;
use Thelia\Model\OrderPaymentTransactionQuery;
use Thelia\Model\OrderQuery;

/**
 * The payment journal of one order, latest movement first, as a sub-collection of the
 * order: a caller never lists the lines of every order at once.
 */
final readonly class OrderPaymentTransactionCollectionProvider implements ProviderInterface
{
    public function __construct(
        private Pagination $pagination,
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $query = OrderPaymentTransactionQuery::create()
            ->filterByOrderId($this->orderId($uriVariables))
            ->orderById(Criteria::DESC);

        if (!$this->pagination->isEnabled($operation, $context)) {
            return $this->toResources(iterator_to_array($query->find()), $context);
        }

        [$page, , $itemsPerPage] = $this->pagination->getPagination($operation, $context);

        $pager = $query->paginate($page, $itemsPerPage);

        return new PropelPaginator($pager, $this->toResources(iterator_to_array($pager->getResults()), $context));
    }

    private function orderId(array $uriVariables): int
    {
        $orderId = filter_var($uriVariables['orderId'] ?? null, \FILTER_VALIDATE_INT);

        if (false === $orderId || $orderId < 1 || null === OrderQuery::create()->findPk($orderId)) {
            throw new NotFoundHttpException('No such order.');
        }

        return $orderId;
    }

    private function toResources(array $models, array $context): array
    {
        return array_map(
            fn (OrderPaymentTransactionModel $model): OrderPaymentTransaction => $this->apiResourcePropelTransformerService->modelToResource(
                resourceClass: OrderPaymentTransaction::class,
                propelModel: $model,
                context: $context,
            ),
            $models,
        );
    }
}
