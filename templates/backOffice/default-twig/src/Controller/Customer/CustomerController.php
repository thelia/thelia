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

namespace BackOfficeDefaultTwigBundle\Controller\Customer;

use BackOfficeDefaultTwigBundle\Form\Customer\AddressType;
use BackOfficeDefaultTwigBundle\Form\Customer\CustomerType;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormErrorRenderer;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormValidator;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminLogger;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\RowAction;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Customer\Service\CustomerTitleService;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Event\CustomerEvent;
use Thelia\Model\LangQuery;
use Thelia\Tools\Password;
use Twig\Environment;

#[Route('/admin', name: 'admin.')]
final class CustomerController
{
    private const RESOURCE = AdminResources::CUSTOMER;
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/customer/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/customer/edit.html.twig';
    private const LIST_ROUTE = 'admin.customers';
    private const EDIT_ROUTE = 'admin.customer.update.view';
    private const CREATE_FORM_NAME = 'thelia_customer_create';
    private const UPDATE_FORM_NAME = 'thelia_customer_update';
    private const PAGE_SIZE = 25;

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly AdminFormValidator $validator,
        private readonly AdminFormErrorRenderer $errorRenderer,
        private readonly AdminLogger $adminLogger,
        private readonly EventDispatcherInterface $events,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        private readonly CustomerTitleService $titleService,
    ) {
    }

    #[Route('/customers', name: 'customers', methods: ['GET'])]
    public function list(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $order = (string) $request->query->get('customer_order', 'lastname');
        $search = trim((string) $request->query->get('q', ''));

        $locale = $this->defaultLocale();

        return new Response($this->twig->render(self::LIST_TEMPLATE, array_merge(
            $this->paginatedRows($search, $order, $page),
            [
                'current_order' => $order,
                'current_page' => $page,
                'current_search' => $search,
                'create_form' => $this->buildCreateForm($locale)->createView(),
            ],
        )));
    }

    #[Route('/customer/update', name: 'customer.update.view', methods: ['GET'])]
    public function view(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $customerId = (int) $request->query->get('customer_id', 0);
        $customer = CustomerQuery::create()->findPk($customerId);
        if ($customer === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $form = $this->buildUpdateForm($this->defaultLocale(), $this->customerToFormData($customer));
        $addressCreateForm = $this->formFactory->createNamed(
            'thelia_address_create',
            AddressType::class,
            ['customer_id' => $customer->getId()],
            [
                'title_choices' => $this->titleService->getTitleAsFormChoices(),
                'country_choices' => $this->countryChoices($this->defaultLocale()),
                'csrf_protection' => false,
            ],
        );

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'form' => $form->createView(),
            'customer' => $customer,
            'addresses' => $this->addressRows($customer),
            'address_create_form' => $addressCreateForm->createView(),
        ]));
    }

    #[Route('/customer/create', name: 'customer.create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->buildCreateForm($this->defaultLocale());

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::CUSTOMER_CREATEACCOUNT,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Customer creation',
            successRoute: self::EDIT_ROUTE,
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::LIST_ROUTE)),
            describeForLog: $this->describeCreated(...),
        );
    }

    #[Route('/customer/save', name: 'customer.update.process', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $customerId = (int) $request->request->get('id', $request->request->get('customer_id', 0));
        $form = $this->buildUpdateForm($this->defaultLocale(), null);

        try {
            $validated = $this->validator->validate($form);
            $data = $validated->getData() ?? [];

            $event = $this->buildEvent($data);
            $customer = CustomerQuery::create()->findPk($customerId);
            if ($customer !== null) {
                $event->setCustomer($customer);
            }
            $event->setEmailUpdateAllowed(true);

            $this->events->dispatch($event, TheliaEvents::CUSTOMER_UPDATEACCOUNT);

            $updated = $event->getCustomer() ?? $customer;
            if ($updated !== null) {
                $this->adminLogger->log(
                    self::RESOURCE,
                    AccessManager::UPDATE,
                    \sprintf('Customer %s %s (ID %d) modified', (string) $updated->getFirstname(), (string) $updated->getLastname(), (int) $updated->getId()),
                    (int) $updated->getId(),
                );
            }

            return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['customer_id' => $updated?->getId() ?? $customerId]));
        } catch (\Throwable $exception) {
            $this->errorRenderer->setup(
                $this->translator->trans('Customer update'),
                $exception->getMessage(),
                $form,
                $exception,
            );

            return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['customer_id' => $customerId]));
        }
    }

    #[Route('/customer/delete', name: 'customer.delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        $customer = CustomerQuery::create()->findPk((int) $request->get('customer_id', 0));
        $event = new CustomerEvent($customer);

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::CUSTOMER_DELETEACCOUNT,
            actionLabel: 'Customer deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    private function buildCreateForm(string $locale): FormInterface
    {
        return $this->formFactory->createNamed(self::CREATE_FORM_NAME, CustomerType::class, [
            'discount' => 0,
        ], array_merge($this->formOptions($locale), [
            'csrf_protection' => false,
        ]));
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function buildUpdateForm(string $locale, ?array $data): FormInterface
    {
        return $this->formFactory->createNamed(self::UPDATE_FORM_NAME, CustomerType::class, $data, array_merge($this->formOptions($locale), [
            'include_id' => true,
            'include_password' => true,
            'password_required' => false,
            'csrf_protection' => false,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(string $locale): array
    {
        return [
            'title_choices' => $this->titleService->getTitleAsFormChoices(),
            'country_choices' => $this->countryChoices($locale),
            'lang_choices' => $this->langChoices(),
        ];
    }

    private function createEvent(FormInterface $validated): CustomerCreateOrUpdateEvent
    {
        $event = $this->buildEvent($validated->getData() ?? []);
        $event->setPassword(Password::generateRandom());
        $event->setNotifyCustomerOfAccountCreation(true);

        return $event;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildEvent(array $data): CustomerCreateOrUpdateEvent
    {
        return new CustomerCreateOrUpdateEvent(
            title: isset($data['title']) ? (int) $data['title'] : null,
            firstname: $this->stringOrNull($data['firstname'] ?? null),
            lastname: $this->stringOrNull($data['lastname'] ?? null),
            address1: $this->stringOrNull($data['address1'] ?? null),
            address2: $this->stringOrNull($data['address2'] ?? null) ?? '',
            address3: $this->stringOrNull($data['address3'] ?? null) ?? '',
            phone: $this->stringOrNull($data['phone'] ?? null),
            cellphone: $this->stringOrNull($data['cellphone'] ?? null),
            zipcode: $this->stringOrNull($data['zipcode'] ?? null),
            city: $this->stringOrNull($data['city'] ?? null),
            country: $this->stringOrNull($data['country'] ?? null),
            email: $this->stringOrNull($data['email'] ?? null),
            password: !empty($data['password']) ? (string) $data['password'] : null,
            langId: isset($data['lang_id']) && $data['lang_id'] !== '' ? (int) $data['lang_id'] : null,
            reseller: (bool) ($data['reseller'] ?? false),
            sponsor: null,
            discount: isset($data['discount']) && $data['discount'] !== '' ? (float) $data['discount'] : null,
            company: $this->stringOrNull($data['company'] ?? null),
            ref: null,
            state: null,
        );
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeCreated(CustomerCreateOrUpdateEvent $event): array
    {
        $customer = $event->getCustomer();
        if ($customer === null) {
            throw new \LogicException($this->translator->trans('No customer was created.'));
        }

        return [
            \sprintf('Customer %s %s (ID %d) created', (string) $customer->getFirstname(), (string) $customer->getLastname(), (int) $customer->getId()),
            (int) $customer->getId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedRows(string $search, string $order, int $page): array
    {
        $query = CustomerQuery::create();

        if ($search !== '') {
            $query->_or()
                ->filterByEmail('%'.$search.'%', Criteria::LIKE)
                ->_or()
                ->filterByLastname('%'.$search.'%', Criteria::LIKE)
                ->_or()
                ->filterByFirstname('%'.$search.'%', Criteria::LIKE)
                ->_or()
                ->filterByRef('%'.$search.'%', Criteria::LIKE);
        }

        $this->applyOrder($query, $order);

        $total = (int) $query->count();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);

        $customers = $query
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE)
            ->find();

        $rows = [];
        foreach ($customers as $customer) {
            \assert($customer instanceof Customer);
            $rows[] = [
                'ref' => $customer->getRef() ?? '—',
                'lastname' => $customer->getLastname() ?? '',
                'firstname' => $customer->getFirstname() ?? '',
                'email' => $customer->getEmail() ?? '',
                'registration_date' => $customer->getCreatedAt()?->format('Y-m-d') ?? '—',
                '_actions' => [
                    new RowAction(
                        kind: 'edit',
                        label: $this->translator->trans('Edit this customer'),
                        href: $this->urls->generate(self::EDIT_ROUTE, ['customer_id' => (int) $customer->getId()]),
                        grantedAttribute: AccessManager::UPDATE,
                        grantedSubject: 'admin.customer',
                    ),
                    new RowAction(
                        kind: 'delete',
                        label: $this->translator->trans('Delete this customer'),
                        modalTarget: '#customer-delete-modal',
                        grantedAttribute: AccessManager::DELETE,
                        grantedSubject: 'admin.customer',
                        dataAttributes: [
                            'customer-id' => (int) $customer->getId(),
                            'customer-label' => trim(($customer->getFirstname() ?? '').' '.($customer->getLastname() ?? '')),
                        ],
                    ),
                ],
            ];
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
        ];
    }

    private function applyOrder(CustomerQuery $query, string $order): void
    {
        match ($order) {
            'reference' => $query->orderByRef(Criteria::ASC),
            'reference_reverse' => $query->orderByRef(Criteria::DESC),
            'firstname' => $query->orderByFirstname(Criteria::ASC),
            'firstname_reverse' => $query->orderByFirstname(Criteria::DESC),
            'registration_date' => $query->orderByCreatedAt(Criteria::ASC),
            'registration_date_reverse' => $query->orderByCreatedAt(Criteria::DESC),
            'lastname_reverse' => $query->orderByLastname(Criteria::DESC),
            default => $query->orderByLastname(Criteria::ASC),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function addressRows(Customer $customer): array
    {
        $rows = [];
        foreach ($customer->getAddresses() as $address) {
            $rows[] = [
                'id' => (int) $address->getId(),
                'label' => $address->getLabel() ?? '',
                'firstname' => $address->getFirstname() ?? '',
                'lastname' => $address->getLastname() ?? '',
                'address' => trim(($address->getAddress1() ?? '').' '.($address->getZipcode() ?? '').' '.($address->getCity() ?? '')),
                'is_default' => (bool) $address->getIsDefault(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function customerToFormData(Customer $customer): array
    {
        $address = $customer->getDefaultAddress();

        return [
            'id' => $customer->getId(),
            'title' => $customer->getTitleId(),
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'email' => $customer->getEmail(),
            'lang_id' => $customer->getLangId(),
            'discount' => $customer->getDiscount(),
            'reseller' => (bool) $customer->getReseller(),
            'company' => $address?->getCompany(),
            'address1' => $address?->getAddress1(),
            'address2' => $address?->getAddress2(),
            'address3' => $address?->getAddress3(),
            'phone' => $address?->getPhone(),
            'cellphone' => $address?->getCellphone(),
            'zipcode' => $address?->getZipcode(),
            'city' => $address?->getCity(),
            'country' => $address?->getCountryId(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function countryChoices(string $locale): array
    {
        $choices = [];
        foreach (CountryQuery::create()->find() as $country) {
            $country->setLocale($locale);
            $title = $country->getTitle();
            if (!\is_string($title) || $title === '') {
                continue;
            }
            $choices[$title] = (int) $country->getId();
        }
        ksort($choices);

        return $choices;
    }

    /**
     * @return array<string, int>
     */
    private function langChoices(): array
    {
        $choices = [];
        foreach (LangQuery::create()->find() as $lang) {
            $choices[$lang->getTitle() ?? $lang->getCode() ?? '—'] = (int) $lang->getId();
        }

        return $choices;
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!\is_scalar($value) && $value !== null) {
            return null;
        }

        $cast = (string) $value;

        return $cast === '' ? null : $cast;
    }
}
