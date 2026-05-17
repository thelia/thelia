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

namespace BackOfficeDefaultTwigBundle\Controller\Configuration;

use BackOfficeDefaultTwigBundle\Form\Order\OrderStatusType;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\RowAction;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\OrderStatus\OrderStatusCreateEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusDeleteEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\LangQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/configuration/order-status', name: 'admin.order-status.')]
final class OrderStatusController
{
    private const RESOURCE = AdminResources::ORDER_STATUS;
    private const LIST_ROUTE = 'admin.order-status.default';
    private const EDIT_ROUTE = 'admin.order-status.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/order-status/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/configuration/order-status/edit.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenProvider $tokens,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, $this->buildListContext()));
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->formFactory->createNamed('thelia_order_status_creation', OrderStatusType::class, [
            'locale' => $this->defaultLocale(),
        ], [
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::ORDER_STATUS_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Order status creation',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::LIST_ROUTE)),
        );
    }

    #[Route('/update/{order_status_id}', name: 'update', methods: ['GET'], requirements: ['order_status_id' => '\d+'])]
    public function updateView(int $order_status_id): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $status = OrderStatusQuery::create()->findPk($order_status_id);
        if ($status === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $status->setLocale($locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'status' => $status,
            'form' => $this->buildUpdateForm($status, $locale)->createView(),
        ]));
    }

    #[Route('/save/{order_status_id}', name: 'save', methods: ['POST'], requirements: ['order_status_id' => '\d+'])]
    public function processUpdate(int $order_status_id): Response
    {
        $form = $this->formFactory->createNamed('thelia_order_status_modification', OrderStatusType::class, null, [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::ORDER_STATUS_UPDATE,
            eventFactory: $this->updateEvent(...),
            actionLabel: 'Order status update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['order_status_id' => $order_status_id],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['order_status_id' => $order_status_id])),
        );
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        $statusId = (int) $request->get('order_status_id', 0);
        $event = new OrderStatusDeleteEvent($statusId);

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::ORDER_STATUS_DELETE,
            actionLabel: 'Order status deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/update-position', name: 'update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('order_status_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::ORDER_STATUS_UPDATE_POSITION,
            actionLabel: 'Order status reorder',
            successRoute: self::LIST_ROUTE,
        );
    }

    private function createEvent(FormInterface $validated): OrderStatusCreateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new OrderStatusCreateEvent();
        $event->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setCode((string) ($data['code'] ?? ''))
            ->setColor((string) ($data['color'] ?? '#000000'));

        return $event;
    }

    private function updateEvent(FormInterface $validated): OrderStatusUpdateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new OrderStatusUpdateEvent((int) ($data['id'] ?? 0));
        $event->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setCode((string) ($data['code'] ?? ''))
            ->setColor((string) ($data['color'] ?? '#000000'))
            ->setChapo((string) ($data['chapo'] ?? ''))
            ->setDescription((string) ($data['description'] ?? ''))
            ->setPostscriptum((string) ($data['postscriptum'] ?? ''));

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListContext(): array
    {
        $locale = $this->defaultLocale();
        $statuses = OrderStatusQuery::create()->orderByPosition()->find();
        $rows = [];
        foreach ($statuses as $status) {
            \assert($status instanceof OrderStatus);
            $status->setLocale($locale);
            $rows[] = $this->statusToRow($status);
        }

        $createForm = $this->formFactory->createNamed('thelia_order_status_creation', OrderStatusType::class, [
            'locale' => $locale,
        ], [
            'csrf_protection' => false,
        ]);

        return [
            'rows' => $rows,
            'create_form' => $createForm->createView(),
            'update_position_url' => $this->urls->generate('admin.order-status.update-position'),
            'update_position_token' => $this->tokens->assignToken(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusToRow(OrderStatus $status): array
    {
        $id = (int) $status->getId();
        $protected = (bool) $status->getProtectedStatus();

        $actions = [
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Edit'),
                href: $this->urls->generate(self::EDIT_ROUTE, ['order_status_id' => $id]),
                grantedAttribute: AccessManager::UPDATE,
                grantedSubject: self::RESOURCE,
            ),
        ];

        if (!$protected) {
            $actions[] = new RowAction(
                kind: 'delete',
                label: $this->translator->trans('Delete'),
                modalTarget: '#order-status-delete-modal',
                grantedAttribute: AccessManager::DELETE,
                grantedSubject: self::RESOURCE,
                dataAttributes: ['order-status-id' => $id, 'order-status-label' => (string) $status->getTitle()],
            );
        }

        return [
            'id' => $id,
            'title' => (string) $status->getTitle(),
            'code' => (string) $status->getCode(),
            'color' => (string) $status->getColor(),
            'position' => (int) $status->getPosition(),
            '_actions' => $actions,
        ];
    }

    private function buildUpdateForm(OrderStatus $status, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_order_status_modification', OrderStatusType::class, [
            'id' => $status->getId(),
            'locale' => $locale,
            'title' => $status->getTitle(),
            'code' => $status->getCode(),
            'color' => $status->getColor(),
            'chapo' => $status->getChapo(),
            'description' => $status->getDescription(),
            'postscriptum' => $status->getPostscriptum(),
        ], [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
