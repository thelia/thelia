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

use BackOfficeDefaultTwigBundle\Form\Configuration\CustomerTitleType;
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
use Thelia\Core\Event\CustomerTitle\CustomerTitleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CustomerTitle;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\LangQuery;
use Twig\Environment;

#[Route('/admin/configuration/customer-titles', name: 'admin.configuration.customer-titles.')]
final class CustomerTitleController
{
    private const RESOURCE = AdminResources::TITLE;
    private const LIST_ROUTE = 'admin.configuration.customer-titles.default';
    private const EDIT_ROUTE = 'admin.configuration.customer-titles.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/customer-title/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/configuration/customer-title/edit.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->defaultLocale();
        $titles = CustomerTitleQuery::create()->orderByPosition()->find();
        $rows = [];
        foreach ($titles as $title) {
            \assert($title instanceof CustomerTitle);
            $title->setLocale($locale);
            $rows[] = $this->titleToRow($title);
        }

        $createForm = $this->formFactory->createNamed('thelia_customer_title_create', CustomerTitleType::class, [
            'locale' => $locale,
        ], ['csrf_protection' => false]);

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'rows' => $rows,
            'create_form' => $createForm->createView(),
        ]));
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->formFactory->createNamed('thelia_customer_title_create', CustomerTitleType::class, [
            'locale' => $this->defaultLocale(),
        ], ['csrf_protection' => false]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::CUSTOMER_TITLE_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Customer title creation',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::LIST_ROUTE)),
        );
    }

    #[Route('/update/{customer_title_id}', name: 'update', methods: ['GET'], requirements: ['customer_title_id' => '\d+'])]
    public function updateView(int $customer_title_id): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $title = CustomerTitleQuery::create()->findPk($customer_title_id);
        if ($title === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $title->setLocale($locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'title' => $title,
            'form' => $this->buildUpdateForm($title, $locale)->createView(),
        ]));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_customer_title_update', CustomerTitleType::class, null, [
            'include_id' => true,
            'csrf_protection' => false,
        ]);

        $titleId = (int) $request->request->get('customer_title_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::CUSTOMER_TITLE_UPDATE,
            eventFactory: fn (FormInterface $validated) => $this->updateEvent($validated),
            actionLabel: 'Customer title update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['customer_title_id' => $titleId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['customer_title_id' => $titleId])),
        );
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        $title = CustomerTitleQuery::create()->findPk((int) $request->get('customer_title_id', 0));
        if ($title === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $event = new CustomerTitleEvent();
        $event->setCustomerTitle($title);

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::CUSTOMER_TITLE_DELETE,
            actionLabel: 'Customer title deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/update-position', name: 'update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('customer_title_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::CUSTOMER_TITLE_UPDATE_POSITION ?? 'action.customer-title.update-position',
            actionLabel: 'Customer title reorder',
            successRoute: self::LIST_ROUTE,
        );
    }

    private function createEvent(FormInterface $validated): CustomerTitleEvent
    {
        $data = $validated->getData() ?? [];
        $event = new CustomerTitleEvent();
        $event->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setShort((string) ($data['short'] ?? ''))
            ->setLong((string) ($data['long'] ?? ''))
            ->setDefault((bool) ($data['default'] ?? false));

        return $event;
    }

    private function updateEvent(FormInterface $validated): CustomerTitleEvent
    {
        $data = $validated->getData() ?? [];
        $title = CustomerTitleQuery::create()->findPk((int) ($data['id'] ?? 0))
            ?? throw new \LogicException('Customer title not found');

        $event = new CustomerTitleEvent();
        $event->setCustomerTitle($title)
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setShort((string) ($data['short'] ?? ''))
            ->setLong((string) ($data['long'] ?? ''))
            ->setDefault((bool) ($data['default'] ?? false));

        return $event;
    }

    private function buildUpdateForm(CustomerTitle $title, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_customer_title_update', CustomerTitleType::class, [
            'id' => $title->getId(),
            'locale' => $locale,
            'short' => $title->getShort(),
            'long' => $title->getLong(),
            'default' => (bool) $title->getByDefault(),
        ], [
            'include_id' => true,
            'csrf_protection' => false,
        ]);
    }

    /** @return array<string, mixed> */
    private function titleToRow(CustomerTitle $title): array
    {
        $id = (int) $title->getId();
        $actions = [
            new RowAction(kind: 'edit', label: $this->translator->trans('Edit'), href: $this->urls->generate(self::EDIT_ROUTE, ['customer_title_id' => $id]), grantedAttribute: AccessManager::UPDATE, grantedSubject: self::RESOURCE),
            new RowAction(kind: 'delete', label: $this->translator->trans('Delete'), modalTarget: '#customer-title-delete-modal', grantedAttribute: AccessManager::DELETE, grantedSubject: self::RESOURCE, dataAttributes: ['customer-title-id' => $id, 'customer-title-label' => (string) $title->getLong()]),
        ];

        return [
            'id' => $id,
            'short' => (string) $title->getShort(),
            'long' => (string) $title->getLong(),
            'default' => (bool) $title->getByDefault(),
            'position' => (int) $title->getPosition(),
            '_actions' => $actions,
        ];
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
