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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Api\Resource\PurchaseList;
use Thelia\Api\Service\PurchaseListPresenter;
use Thelia\Domain\CustomerList\Exception\PurchaseListNotFoundException;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Domain\QuickOrder\Exception\QuickOrderRateLimitedException;
use Thelia\Domain\QuickOrder\QuickOrderFacade;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * The purchase lists of the signed-in account, read through the facade: the
 * collection, one list with its lines, and one list loaded into a control table.
 *
 * @implements ProviderInterface<PurchaseList>
 */
final readonly class PurchaseListProvider implements ProviderInterface
{
    public function __construct(
        private PurchaseListFacade $purchaseListFacade,
        private PurchaseListPresenter $presenter,
        private QuickOrderFacade $quickOrderFacade,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<PurchaseList>|PurchaseList|JsonResponse
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|PurchaseList|JsonResponse
    {
        $customer = $this->tokenStorage->getToken()?->getUser();

        if (!$customer instanceof Customer) {
            throw new AccessDeniedHttpException('A customer must be authenticated to read purchase lists.');
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->presenter->presentAll($customer, $this->purchaseListFacade->listVisibleFor($customer));
        }

        $listId = (int) ($uriVariables['id'] ?? 0);

        if (PurchaseList::OPERATION_TABLE === $operation->getName()) {
            return $this->table($customer, $listId);
        }

        try {
            return $this->presenter->present($customer, $this->purchaseListFacade->getVisible($customer, $listId));
        } catch (PurchaseListNotFoundException $exception) {
            throw new NotFoundHttpException('No such purchase list.', $exception);
        }
    }

    /**
     * The facade spends the quick order budget before it looks the list up, so that
     * a list of another account and a list of one's own cost the same.
     */
    private function table(Customer $customer, int $listId): JsonResponse
    {
        try {
            return new JsonResponse(
                $this->quickOrderFacade->resolvePurchaseList($customer, $listId, Currency::getDefaultCurrency())->toArray(),
            );
        } catch (QuickOrderRateLimitedException $exception) {
            throw new TooManyRequestsHttpException(message: $exception->getMessage(), previous: $exception);
        } catch (PurchaseListNotFoundException $exception) {
            throw new NotFoundHttpException('No such purchase list.', $exception);
        }
    }
}
