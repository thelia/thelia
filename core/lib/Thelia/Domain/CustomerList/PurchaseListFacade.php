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

namespace Thelia\Domain\CustomerList;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\CustomerList\PurchaseListEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\DTO\ReferenceQuantity;
use Thelia\Domain\CustomerList\DTO\PurchaseListLines;
use Thelia\Domain\CustomerList\Exception\InvalidPurchaseListException;
use Thelia\Domain\CustomerList\Exception\PurchaseListAccessDeniedException;
use Thelia\Domain\CustomerList\Exception\PurchaseListNotFoundException;
use Thelia\Domain\CustomerList\Exception\PurchaseListSourceNotFoundException;
use Thelia\Domain\CustomerList\Service\PurchaseListAccessPolicy;
use Thelia\Domain\CustomerList\Service\PurchaseListRepository;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
use Thelia\Model\CustomerList;

/**
 * The purchase lists of a signed-in customer: the one entry point the API, the
 * theme and the modules call.
 *
 * The customer is always given, never read from the session, so the same rules
 * hold on the command line. A list the customer may not see answers as if it
 * did not exist. Loading a list never touches the cart: its lines go through
 * the reference resolver first, so that a reference gone from the catalog is
 * shown rather than dropped.
 */
final readonly class PurchaseListFacade
{
    public const int MAX_LISTS_PER_CUSTOMER = 100;

    public const int MAX_TITLE_LENGTH = 255;

    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private PurchaseListRepository $repository,
        private PurchaseListAccessPolicy $accessPolicy,
    ) {
    }

    /**
     * @return list<CustomerList>
     */
    public function listVisibleFor(Customer $customer): array
    {
        return $this->repository->findReadableBy($customer);
    }

    public function getVisible(Customer $customer, int $listId): CustomerList
    {
        return $this->repository->findOneReadableBy($customer, $listId)
            ?? throw new PurchaseListNotFoundException(\sprintf('Purchase list %d not found.', $listId));
    }

    public function canWrite(Customer $customer, CustomerList $list): bool
    {
        return $this->accessPolicy->canWrite($customer, $list);
    }

    public function canShare(Customer $customer, CustomerList $list): bool
    {
        return $this->accessPolicy->canShare($customer, $list);
    }

    public function create(Customer $customer, string $title, PurchaseListLines $lines = new PurchaseListLines()): CustomerList
    {
        if ($this->repository->countOwnedBy($customer) >= self::MAX_LISTS_PER_CUSTOMER) {
            throw new InvalidPurchaseListException(\sprintf('A customer keeps at most %d purchase lists.', self::MAX_LISTS_PER_CUSTOMER));
        }

        $event = new PurchaseListEvent($customer, null, self::normalizeTitle($title), $lines);
        $this->dispatcher->dispatch($event, TheliaEvents::PURCHASE_LIST_CREATE);

        return $event->getCustomerList()
            ?? throw new \LogicException('No listener created the purchase list.');
    }

    public function createFromCart(Customer $customer, Cart $cart, string $title): CustomerList
    {
        if ((int) $cart->getCustomerId() !== (int) $customer->getId()) {
            throw new PurchaseListSourceNotFoundException('Cart not found.');
        }

        return $this->create($customer, $title, $this->repository->linesOfCart($cart));
    }

    public function createFromOrder(Customer $customer, int $orderId, string $title): CustomerList
    {
        $lines = $this->repository->linesOfOrder($customer, $orderId)
            ?? throw new PurchaseListSourceNotFoundException(\sprintf('Order %d not found.', $orderId));

        return $this->create($customer, $title, $lines);
    }

    public function rename(Customer $customer, int $listId, string $title): CustomerList
    {
        return $this->update($this->getWritable($customer, $listId), $customer, self::normalizeTitle($title), null);
    }

    /**
     * Adds lines after the current ones; a reference already on the list adds
     * its quantity to the existing line.
     */
    public function appendItems(Customer $customer, int $listId, PurchaseListLines $lines): CustomerList
    {
        $list = $this->getWritable($customer, $listId);

        return $this->update($list, $customer, null, $this->repository->linesOf($list)->merge($lines));
    }

    public function replaceItems(Customer $customer, int $listId, PurchaseListLines $lines): CustomerList
    {
        return $this->update($this->getWritable($customer, $listId), $customer, null, $lines);
    }

    /**
     * A copy owned by the customer, from any list the customer can see.
     */
    public function duplicate(Customer $customer, int $listId, ?string $title = null): CustomerList
    {
        $source = $this->getVisible($customer, $listId);

        return $this->create($customer, $title ?? (string) $source->getTitle(), $this->repository->linesOf($source));
    }

    public function delete(Customer $customer, int $listId): void
    {
        $this->dispatcher->dispatch(
            new PurchaseListEvent($customer, $this->getWritable($customer, $listId)),
            TheliaEvents::PURCHASE_LIST_DELETE,
        );
    }

    /**
     * @return list<ReferenceQuantity>
     */
    public function linesToLoad(Customer $customer, int $listId): array
    {
        return $this->repository->linesOf($this->getVisible($customer, $listId))->all();
    }

    private function getWritable(Customer $customer, int $listId): CustomerList
    {
        $list = $this->getVisible($customer, $listId);

        if (!$this->accessPolicy->canWrite($customer, $list)) {
            throw new PurchaseListAccessDeniedException(\sprintf('Purchase list %d is read only for this customer.', $listId));
        }

        return $list;
    }

    private function update(CustomerList $list, Customer $customer, ?string $title, ?PurchaseListLines $lines): CustomerList
    {
        $event = new PurchaseListEvent($customer, $list, $title, $lines);
        $this->dispatcher->dispatch($event, TheliaEvents::PURCHASE_LIST_UPDATE);

        return $event->getCustomerList() ?? $list;
    }

    private static function normalizeTitle(string $title): string
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));

        if ('' === $title) {
            throw new InvalidPurchaseListException('A purchase list needs a title.');
        }

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new InvalidPurchaseListException(\sprintf('A purchase list title is at most %d characters long.', self::MAX_TITLE_LENGTH));
        }

        return $title;
    }
}
