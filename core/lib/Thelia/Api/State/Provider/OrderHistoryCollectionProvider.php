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
use Thelia\Api\Resource\OrderHistory;
use Thelia\Model\OrderHistory as OrderHistoryModel;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderQuery;

/**
 * Serves the journal of the order named in the uri.
 *
 * The order is the scope, not a filter the caller may drop: a history line is
 * read under the order it belongs to or not at all, so the collection is built
 * from the uri variable and nothing in the query string can widen it.
 *
 * An order number nobody issued answers 404 rather than an empty collection: an
 * order with no journal and an order that does not exist are two different
 * things, and the caller is entitled to tell them apart. Who may ask at all was
 * settled before this runs, by the permission on the admin surface.
 *
 * Entries are walked newest first on the primary key rather than on the
 * timestamp, for the reason OrderHistoryQuery already states: two entries
 * written in the same second are indistinguishable by their date, and the last
 * row written is the last thing that happened.
 */
final readonly class OrderHistoryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private Pagination $pagination,
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $query = OrderHistoryQuery::create()
            ->filterByOrderId($this->orderId($uriVariables))
            ->orderById(Criteria::DESC);

        if (!$this->pagination->isEnabled($operation, $context)) {
            return $this->toResources(iterator_to_array($query->find()), $context);
        }

        [$page, , $itemsPerPage] = $this->pagination->getPagination($operation, $context);

        $pager = $query->paginate($page, $itemsPerPage);

        return new PropelPaginator($pager, $this->toResources(iterator_to_array($pager->getResults()), $context));
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function orderId(array $uriVariables): int
    {
        $orderId = filter_var($uriVariables['orderId'] ?? null, \FILTER_VALIDATE_INT);

        if (false === $orderId || $orderId < 1 || null === OrderQuery::create()->findPk($orderId)) {
            throw new NotFoundHttpException('No such order.');
        }

        return $orderId;
    }

    /**
     * @param array<int, OrderHistoryModel> $models
     * @param array<string, mixed>          $context
     *
     * @return array<int, OrderHistory>
     */
    private function toResources(array $models, array $context): array
    {
        return array_map(
            fn (OrderHistoryModel $model): OrderHistory => $this->apiResourcePropelTransformerService->modelToResource(
                resourceClass: OrderHistory::class,
                propelModel: $model,
                context: $context,
            ),
            $models,
        );
    }
}
