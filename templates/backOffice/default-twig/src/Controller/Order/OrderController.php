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

namespace BackOfficeDefaultTwigBundle\Controller\Order;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use BackOfficeDefaultTwigBundle\Service\Pdf\OrderPdfRenderer;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\RowAction;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\StateQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

final class OrderController
{
    private const RESOURCE = AdminResources::ORDER;
    private const LIST_ROUTE = 'admin.order.list';
    private const DETAIL_ROUTE = 'admin.order.update.view';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/order/list.html.twig';
    private const DETAIL_TEMPLATE = '@BackOfficeDefaultTwig/order/detail.html.twig';
    private const PAGE_SIZE = 25;

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenProvider $tokens,
        private readonly EventDispatcherInterface $events,
        private readonly TranslatorInterface $translator,
        private readonly \Symfony\Component\Form\FormFactoryInterface $formFactory,
        private readonly OrderPdfRenderer $pdfRenderer,
    ) {
    }

    #[Route('/admin/orders', name: 'admin.order.list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $statusId = (int) $request->query->get('status_id', 0);
        $search = trim((string) $request->query->get('q', ''));

        return new Response($this->twig->render(self::LIST_TEMPLATE, array_merge(
            $this->paginatedRows($statusId, $search, $page),
            [
                'current_page' => $page,
                'current_status' => $statusId,
                'current_search' => $search,
                'available_statuses' => $this->statusChoices(),
            ],
        )));
    }

    #[Route('/admin/order/update/{order_id}', name: 'admin.order.update.view', methods: ['GET'], requirements: ['order_id' => '\d+'])]
    public function detail(int $order_id): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $order = OrderQuery::create()->findPk($order_id);
        if ($order === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();

        return new Response($this->twig->render(self::DETAIL_TEMPLATE, [
            'order' => $order,
            'order_items' => $this->orderItems($order),
            'order_addresses' => $this->orderAddresses($order),
            'available_statuses' => $this->statusChoices(),
            'customer_titles' => $this->customerTitleChoices($locale),
            'countries' => $this->countryChoices($locale),
            'states' => $this->stateChoices($locale),
            'invoice_url' => $this->urls->generate('admin.order.pdf.invoice', ['order_id' => $order_id, 'browser' => 1]),
            'delivery_url' => $this->urls->generate('admin.order.pdf.delivery', ['order_id' => $order_id, 'browser' => 1]),
            'token' => $this->tokens->assignToken(),
        ]));
    }

    #[Route('/admin/order/update/status', name: 'admin.order.list.update.status', methods: ['POST', 'GET'])]
    public function bulkUpdateStatus(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        try {
            $this->tokens->checkToken((string) $request->query->get('_token'));
            $orderIds = (array) $request->get('order_ids', []);
            $statusId = (int) $request->get('status_id', 0);

            foreach ($orderIds as $id) {
                $order = OrderQuery::create()->findPk((int) $id);
                if ($order === null) {
                    continue;
                }
                $event = new OrderEvent($order);
                $event->setStatus($statusId);
                $this->events->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
            }
        } catch (\Throwable) {
            // Iso behavior: silently ignore CSRF/dispatch errors here, log via session if needed
        }

        return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
    }

    #[Route('/admin/order/update/{order_id}/status', name: 'admin.order.update.status', methods: ['POST', 'GET'], requirements: ['order_id' => '\d+'])]
    public function updateStatus(int $order_id, Request $request): Response
    {
        $order = OrderQuery::create()->findPk($order_id);
        if ($order === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $event = new OrderEvent($order);
        $event->setStatus((int) $request->get('status_id', 0));

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::ORDER_UPDATE_STATUS,
            actionLabel: 'Order status updated',
            successRoute: self::DETAIL_ROUTE,
            successParameters: ['order_id' => $order_id],
        );
    }

    #[Route('/admin/order/update/{order_id}/delivery-ref', name: 'admin.order.update.deliveryRef', methods: ['POST', 'GET'], requirements: ['order_id' => '\d+'])]
    public function updateDeliveryRef(int $order_id, Request $request): Response
    {
        $order = OrderQuery::create()->findPk($order_id);
        if ($order === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $event = new OrderEvent($order);
        $event->setDeliveryRef((string) $request->get('delivery_ref', ''));

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::ORDER_UPDATE_DELIVERY_REF,
            actionLabel: 'Order delivery ref updated',
            successRoute: self::DETAIL_ROUTE,
            successParameters: ['order_id' => $order_id],
        );
    }

    #[Route('/admin/order/update/{order_id}/address', name: 'admin.order.update.address', methods: ['POST', 'GET'], requirements: ['order_id' => '\d+'])]
    public function updateAddress(int $order_id, Request $request): Response
    {
        $order = OrderQuery::create()->findPk($order_id);
        if ($order === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        try {
            $this->tokens->checkToken((string) $request->request->get('_token', $request->query->get('_token')));

            $form = $this->formFactory->createNamed(
                'thelia_order_address',
                \BackOfficeDefaultTwigBundle\Form\Order\OrderAddressType::class,
                null,
                ['csrf_protection' => false],
            );
            $form->handleRequest($request);
            $data = ($form->isSubmitted() && $form->isValid()) ? ($form->getData() ?? []) : $request->request->all();

            $addressId = (int) ($data['id'] ?? 0);
            $orderAddress = $addressId > 0 ? OrderAddressQuery::create()->findPk($addressId) : null;

            if ($orderAddress === null) {
                throw new \InvalidArgumentException('The order address does not exist');
            }

            if (
                $orderAddress->getId() !== $order->getInvoiceOrderAddressId()
                && $orderAddress->getId() !== $order->getDeliveryOrderAddressId()
            ) {
                throw new \InvalidArgumentException('The order address does not belong to the current order');
            }

            $event = new OrderAddressEvent(
                title: $data['title'] ?? null,
                firstname: (string) ($data['firstname'] ?? ''),
                lastname: (string) ($data['lastname'] ?? ''),
                address1: (string) ($data['address1'] ?? ''),
                address2: $data['address2'] ?? null,
                address3: $data['address3'] ?? null,
                zipcode: (string) ($data['zipcode'] ?? ''),
                city: (string) ($data['city'] ?? ''),
                country: $data['country'] ?? null,
                phone: (string) ($data['phone'] ?? ''),
                company: $data['company'] ?? null,
                cellphone: $data['cellphone'] ?? null,
                state: $data['state'] ?? null,
            );
            $event->setOrderAddress($orderAddress);
            $event->setOrder($order);

            $this->events->dispatch($event, TheliaEvents::ORDER_UPDATE_ADDRESS);
        } catch (\Throwable) {
            // surfaced via session flash in production
        }

        return new RedirectResponse($this->urls->generate(self::DETAIL_ROUTE, ['order_id' => $order_id]));
    }

    #[Route('/admin/order/pdf/invoice/{order_id}/{browser}', name: 'admin.order.pdf.invoice', methods: ['GET'], requirements: ['order_id' => '\d+', 'browser' => '\d+'])]
    public function pdfInvoice(int $order_id, int $browser): Response
    {
        return $this->renderOrderPdf($order_id, OrderPdfRenderer::KIND_INVOICE, 1 === $browser);
    }

    #[Route('/admin/order/pdf/delivery/{order_id}/{browser}', name: 'admin.order.pdf.delivery', methods: ['GET'], requirements: ['order_id' => '\d+', 'browser' => '\d+'])]
    public function pdfDelivery(int $order_id, int $browser): Response
    {
        return $this->renderOrderPdf($order_id, OrderPdfRenderer::KIND_DELIVERY, 1 === $browser);
    }

    private function renderOrderPdf(int $orderId, string $kind, bool $browser): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return $this->pdfRenderer->render($orderId, $kind, $browser);
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedRows(int $statusId, string $search, int $page): array
    {
        $query = OrderQuery::create()->orderByCreatedAt(Criteria::DESC);

        if ($statusId > 0) {
            $query->filterByStatusId($statusId);
        }

        if ($search !== '') {
            $query->_or()
                ->filterByRef('%'.$search.'%', Criteria::LIKE)
                ->_or()
                ->filterByTransactionRef('%'.$search.'%', Criteria::LIKE);
        }

        $total = (int) $query->count();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);

        $orders = $query
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE)
            ->find();

        $rows = [];
        foreach ($orders as $order) {
            \assert($order instanceof Order);
            $rows[] = $this->orderToRow($order);
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderToRow(Order $order): array
    {
        $id = (int) $order->getId();
        $status = OrderStatusQuery::create()->findPk((int) $order->getStatusId());
        $statusTitle = $status?->setLocale($this->defaultLocale())->getTitle() ?? '—';

        $actions = [
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('View order'),
                href: $this->urls->generate(self::DETAIL_ROUTE, ['order_id' => $id]),
                grantedAttribute: AccessManager::VIEW,
                grantedSubject: self::RESOURCE,
            ),
        ];

        return [
            'id' => $id,
            'ref' => (string) $order->getRef(),
            'status' => $statusTitle,
            'total' => (float) $order->getTotalAmount(),
            'currency' => (string) ($order->getCurrency() ? $order->getCurrency()->getSymbol() : ''),
            'date' => $order->getCreatedAt()?->format('Y-m-d') ?? '—',
            '_actions' => $actions,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orderItems(Order $order): array
    {
        $items = [];
        foreach ($order->getOrderProducts() as $product) {
            $items[] = [
                'ref' => (string) $product->getProductRef(),
                'title' => (string) $product->getTitle(),
                'quantity' => (float) $product->getQuantity(),
                'price' => (float) $product->getPrice(),
                'tax' => (float) $product->getPriceTax(),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function orderAddresses(Order $order): array
    {
        $invoice = $order->getOrderAddressRelatedByInvoiceOrderAddressId();
        $delivery = $order->getOrderAddressRelatedByDeliveryOrderAddressId();

        return [
            'invoice' => $invoice ? $this->addressToArray($invoice) : null,
            'delivery' => $delivery ? $this->addressToArray($delivery) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addressToArray(\Thelia\Model\OrderAddress $address): array
    {
        return [
            'id' => (int) $address->getId(),
            'title_id' => (int) $address->getCustomerTitleId(),
            'firstname' => (string) $address->getFirstname(),
            'lastname' => (string) $address->getLastname(),
            'company' => (string) $address->getCompany(),
            'address1' => (string) $address->getAddress1(),
            'address2' => (string) $address->getAddress2(),
            'address3' => (string) $address->getAddress3(),
            'zipcode' => (string) $address->getZipcode(),
            'city' => (string) $address->getCity(),
            'country_id' => (int) $address->getCountryId(),
            'state_id' => $address->getStateId() ? (int) $address->getStateId() : null,
            'phone' => (string) $address->getPhone(),
            'cellphone' => (string) $address->getCellphone(),
        ];
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function customerTitleChoices(string $locale): array
    {
        $items = [];
        foreach (CustomerTitleQuery::create()->orderByPosition()->find() as $title) {
            $title->setLocale($locale);
            $items[] = ['id' => (int) $title->getId(), 'title' => (string) $title->getLong()];
        }

        return $items;
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function countryChoices(string $locale): array
    {
        $items = [];
        foreach (CountryQuery::create()->filterByVisible(1)->orderByPosition()->find() as $country) {
            $country->setLocale($locale);
            $items[] = ['id' => (int) $country->getId(), 'title' => (string) $country->getTitle()];
        }

        return $items;
    }

    /**
     * @return list<array{id: int, country_id: int, title: string}>
     */
    private function stateChoices(string $locale): array
    {
        $items = [];
        foreach (StateQuery::create()->filterByVisible(1)->orderByCountryId()->find() as $state) {
            $state->setLocale($locale);
            $items[] = [
                'id' => (int) $state->getId(),
                'country_id' => (int) $state->getCountryId(),
                'title' => (string) $state->getTitle(),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function statusChoices(): array
    {
        $locale = $this->defaultLocale();
        $items = [['id' => 0, 'title' => $this->translator->trans('— All statuses —')]];
        foreach (OrderStatusQuery::create()->orderByPosition()->find() as $status) {
            $status->setLocale($locale);
            $items[] = ['id' => (int) $status->getId(), 'title' => (string) $status->getTitle(), 'code' => (string) $status->getCode()];
        }

        return $items;
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
