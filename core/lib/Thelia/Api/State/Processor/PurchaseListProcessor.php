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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Api\Resource\PurchaseList;
use Thelia\Api\Resource\PurchaseListInput;
use Thelia\Api\Resource\QuickOrderInput;
use Thelia\Api\Security\CheckoutCartLocator;
use Thelia\Api\Service\PurchaseListPresenter;
use Thelia\Api\Service\ReferenceQuantityRows;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\Catalog\Exception\InvalidReferenceQuantityException;
use Thelia\Domain\CustomerList\Exception\InvalidPurchaseListException;
use Thelia\Domain\CustomerList\Exception\PurchaseListAccessDeniedException;
use Thelia\Domain\CustomerList\Exception\PurchaseListNotFoundException;
use Thelia\Domain\CustomerList\Exception\PurchaseListSourceNotFoundException;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Model\Customer;

/**
 * Every write on the purchase lists of the signed-in account, each handed to the
 * facade as it is. The facade decides; this only turns its refusals into answers:
 * a list, a cart or an order the account may not see is a 404 worded as if it did
 * not exist, a list it sees but may not change a 403.
 *
 * @implements ProcessorInterface<mixed, PurchaseList|null>
 */
final readonly class PurchaseListProcessor implements ProcessorInterface
{
    public function __construct(
        private PurchaseListFacade $purchaseListFacade,
        private PurchaseListPresenter $presenter,
        private CheckoutCartLocator $cartLocator,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?PurchaseList
    {
        $customer = $this->tokenStorage->getToken()?->getUser();

        if (!$customer instanceof Customer) {
            throw new AccessDeniedHttpException('A customer must be authenticated to change purchase lists.');
        }

        $listId = (int) ($uriVariables['id'] ?? 0);

        try {
            $list = match ($operation->getName()) {
                PurchaseList::OPERATION_CREATE => $this->purchaseListFacade->create(
                    $customer,
                    self::title($data),
                    null !== self::input($data)->lines ? ReferenceQuantityRows::toLines(self::input($data)->lines) : new ReferenceQuantityLines(),
                ),
                PurchaseList::OPERATION_FROM_CART => $this->purchaseListFacade->createFromCart(
                    $customer,
                    $this->cartLocator->ownedCart($uriVariables),
                    self::title($data),
                ),
                PurchaseList::OPERATION_FROM_ORDER => $this->purchaseListFacade->createFromOrder(
                    $customer,
                    (int) ($uriVariables['orderId'] ?? 0),
                    self::title($data),
                ),
                PurchaseList::OPERATION_RENAME => $this->purchaseListFacade->rename($customer, $listId, self::title($data)),
                PurchaseList::OPERATION_DUPLICATE => $this->purchaseListFacade->duplicate($customer, $listId, self::input($data)->title),
                PurchaseList::OPERATION_APPEND_ITEMS => $this->purchaseListFacade->appendItems($customer, $listId, self::lines($data)),
                PurchaseList::OPERATION_REPLACE_ITEMS => $this->purchaseListFacade->replaceItems($customer, $listId, self::lines($data)),
                PurchaseList::OPERATION_DELETE => $this->delete($customer, $listId),
                default => throw new \LogicException(\sprintf('The purchase list processor does not handle the operation "%s".', (string) $operation->getName())),
            };
        } catch (PurchaseListNotFoundException $exception) {
            throw new NotFoundHttpException('No such purchase list.', $exception);
        } catch (PurchaseListSourceNotFoundException $exception) {
            throw new NotFoundHttpException(PurchaseList::OPERATION_FROM_CART === $operation->getName() ? CheckoutCartLocator::NOT_FOUND_MESSAGE : 'No such order.', $exception);
        } catch (PurchaseListAccessDeniedException $exception) {
            throw new AccessDeniedHttpException('This purchase list is read only for this account.', $exception);
        } catch (InvalidPurchaseListException|InvalidReferenceQuantityException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return null === $list ? null : $this->presenter->present($customer, $list);
    }

    private function delete(Customer $customer, int $listId): null
    {
        $this->purchaseListFacade->delete($customer, $listId);

        return null;
    }

    private static function input(mixed $data): PurchaseListInput
    {
        return $data instanceof PurchaseListInput ? $data : throw new UnprocessableEntityHttpException('Unsupported purchase list body.');
    }

    private static function title(mixed $data): string
    {
        return (string) self::input($data)->title;
    }

    private static function lines(mixed $data): ReferenceQuantityLines
    {
        if (!$data instanceof QuickOrderInput) {
            throw new UnprocessableEntityHttpException('Unsupported purchase list lines body.');
        }

        return ReferenceQuantityRows::toLines($data->lines ?? []);
    }
}
