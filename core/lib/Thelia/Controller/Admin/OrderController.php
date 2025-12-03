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

namespace Thelia\Controller\Admin;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\OrderUpdateAddress;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;

/**
 * Class OrderController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class OrderController extends BaseAdminController
{
    public function indexAction()
    {
        if (($response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        return $this->render('orders', [
            'display_order' => 20,
            'orders_order' => $this->getListOrderFromSession('orders', 'orders_order', 'create-date-reverse'),
        ]);
    }

    public function viewAction($order_id): Response
    {
        return $this->render('order-edit', [
            'order_id' => $order_id,
        ]);
    }

    public function updateStatus(EventDispatcherInterface $eventDispatcher, $order_id = null): Response|RedirectResponse
    {
        if (($response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $message = null;

        try {
            if (null === $order_id) {
                $order_id = $this->getRequest()->get('order_id');
            }

            $order = OrderQuery::create()->findPk($order_id);

            $statusId = $this->getRequest()->get('status_id');
            $status = OrderStatusQuery::create()->findPk($statusId);

            if (null === $order) {
                throw new \InvalidArgumentException('The order you want to update status does not exist');
            }

            if (null === $status) {
                throw new \InvalidArgumentException('The status you want to set to the order does not exist');
            }

            $event = new OrderEvent($order);
            $event->setStatus((int) $statusId);

            $eventDispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
        }

        $params = [];

        if ($message) {
            $params['update_status_error_message'] = $message;
        }

        $browsedPage = $this->getRequest()->get('order_page');
        $currentStatus = $this->getRequest()->get('status');

        if ($browsedPage) {
            $params['order_page'] = $browsedPage;

            if (null !== $currentStatus) {
                $params['status'] = $currentStatus;
            }

            $response = $this->generateRedirectFromRoute('admin.order.list', $params);
        } else {
            $params['tab'] = $this->getRequest()->get('tab', 'cart');

            $response = $this->generateRedirectFromRoute('admin.order.update.view', $params, ['order_id' => $order_id]);
        }

        return $response;
    }

    public function updateDeliveryRef(EventDispatcherInterface $eventDispatcher, $order_id): Response|RedirectResponse
    {
        if (($response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $message = null;

        try {
            $order = OrderQuery::create()->findPk($order_id);

            $deliveryRef = $this->getRequest()->get('delivery_ref');

            if (null === $order) {
                throw new \InvalidArgumentException('The order you want to update status does not exist');
            }

            $event = new OrderEvent($order);
            $event->setDeliveryRef($deliveryRef);

            $eventDispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_DELIVERY_REF);
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
        }

        $params = [];

        if ($message) {
            $params['update_status_error_message'] = $message;
        }

        $params['tab'] = $this->getRequest()->get('tab', 'bill');

        return $this->generateRedirectFromRoute(
            'admin.order.update.view',
            $params,
            ['order_id' => $order_id],
        );
    }

    public function updateAddress(EventDispatcherInterface $eventDispatcher, $order_id): Response|RedirectResponse
    {
        if (($response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $message = null;

        $orderUpdateAddress = $this->createForm(OrderUpdateAddress::getName());

        try {
            $order = OrderQuery::create()->findPk($order_id);

            if (null === $order) {
                throw new \InvalidArgumentException('The order you want to update does not exist');
            }

            $form = $this->validateForm($orderUpdateAddress, 'post');

            $orderAddress = OrderAddressQuery::create()->findPk($form->get('id')->getData());

            if ($orderAddress->getId() !== $order->getInvoiceOrderAddressId() && $orderAddress->getId() !== $order->getDeliveryOrderAddressId()) {
                throw new \InvalidArgumentException('The order address you want to update does not belong to the current order not exist');
            }

            $event = new OrderAddressEvent(
                $form->get('title')->getData(),
                $form->get('firstname')->getData(),
                $form->get('lastname')->getData(),
                $form->get('address1')->getData(),
                $form->get('address2')->getData(),
                $form->get('address3')->getData(),
                $form->get('zipcode')->getData(),
                $form->get('city')->getData(),
                $form->get('country')->getData(),
                $form->get('phone')->getData(),
                $form->get('company')->getData(),
                $form->get('cellphone')->getData(),
                $form->get('state')->getData(),
            );
            $event->setOrderAddress($orderAddress);
            $event->setOrder($order);

            $eventDispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_ADDRESS);
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
        }

        $params = [];

        if ($message) {
            $params['update_status_error_message'] = $message;
        }

        $params['tab'] = $this->getRequest()->get('tab', 'bill');

        return $this->generateRedirectFromRoute(
            'admin.order.update.view',
            $params,
            ['order_id' => $order_id],
        );
    }

    public function generateInvoicePdf(EventDispatcherInterface $eventDispatcher, int $order_id, $browser): Response|RedirectResponse
    {
        if (($response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        return $this->generateBackOfficeOrderPdf($eventDispatcher, $order_id, ConfigQuery::read('pdf_invoice_file', 'invoice'), "1" === $browser);
    }

    public function generateDeliveryPdf(EventDispatcherInterface $eventDispatcher, int $order_id, $browser): Response|RedirectResponse
    {
        if (($response = $this->checkAuth(AdminResources::ORDER, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        return $this->generateBackOfficeOrderPdf($eventDispatcher, $order_id, ConfigQuery::read('pdf_delivery_file', 'delivery'), "1" === $browser);
    }

    private function generateBackOfficeOrderPdf(EventDispatcherInterface $eventDispatcher, int $orderId, string $fileName, bool $browser): RedirectResponse|Response
    {
        return $this->generateOrderPdf($eventDispatcher, $orderId, $fileName, true, true, $browser);
    }
}
