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

use BackOfficeDefaultTwigBundle\Form\Administrator\AdministratorType;
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
use Thelia\Core\Event\Administrator\AdministratorEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;
use Thelia\Model\ProfileQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/configuration/administrators', name: 'admin.configuration.administrators.')]
final class AdministratorController
{
    private const RESOURCE = AdminResources::ADMINISTRATOR;
    private const LIST_ROUTE = 'admin.configuration.administrators.view';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/administrator/list.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly TokenProvider $tokens,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'view', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, $this->buildListContext()));
    }

    #[Route('/view', name: 'view-profile', methods: ['GET'])]
    public function viewProfile(): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(): Response
    {
        $form = $this->formFactory->createNamed('thelia_administrator_create', AdministratorType::class, null, [
            'profile_choices' => $this->profileChoices(),
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::ADMINISTRATOR_CREATE,
            eventFactory: function (FormInterface $validated): AdministratorEvent {
                $event = new AdministratorEvent();
                $event
                    ->setLogin((string) $validated->get('login')->getData())
                    ->setFirstname((string) $validated->get('firstname')->getData())
                    ->setLastname((string) $validated->get('lastname')->getData())
                    ->setEmail((string) $validated->get('email')->getData())
                    ->setPassword((string) $validated->get('password')->getData())
                    ->setProfile($validated->get('profile')->getData() ?: null)
                    ->setLocale((string) $validated->get('locale')->getData());

                return $event;
            },
            actionLabel: 'Administrator creation',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): Response => $this->renderListWithError(),
            describeForLog: fn (AdministratorEvent $event): array => $this->describe($event, 'created'),
        );
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(): Response
    {
        $form = $this->formFactory->createNamed('thelia_administrator_update', AdministratorType::class, null, [
            'include_id' => true,
            'profile_choices' => $this->profileChoices(),
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::ADMINISTRATOR_UPDATE,
            eventFactory: function (FormInterface $validated): AdministratorEvent {
                $event = new AdministratorEvent();
                $event
                    ->setId((int) $validated->get('id')->getData())
                    ->setLogin((string) $validated->get('login')->getData())
                    ->setFirstname((string) $validated->get('firstname')->getData())
                    ->setLastname((string) $validated->get('lastname')->getData())
                    ->setEmail((string) $validated->get('email')->getData())
                    ->setPassword((string) ($validated->get('password')->getData() ?? ''))
                    ->setProfile($validated->get('profile')->getData() ?: null)
                    ->setLocale((string) $validated->get('locale')->getData());

                return $event;
            },
            actionLabel: 'Administrator update',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): Response => $this->renderListWithError(),
            describeForLog: fn (AdministratorEvent $event): array => $this->describe($event, 'modified'),
        );
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        $event = new AdministratorEvent();
        $event->setId((int) $request->get('administrator_id', 0));

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::ADMINISTRATOR_DELETE,
            actionLabel: 'Administrator deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describe(AdministratorEvent $event, string $action): array
    {
        if (!$event->hasAdministrator()) {
            throw new \LogicException($this->translator->trans('No administrator was '.$action.'.'));
        }

        $admin = $event->getAdministrator();

        return [\sprintf('Administrator %s (ID %d) %s', $admin->getLogin(), $admin->getId(), $action), $admin->getId()];
    }

    private function renderListWithError(): Response
    {
        return new Response(
            $this->twig->render(self::LIST_TEMPLATE, $this->buildListContext()),
            Response::HTTP_BAD_REQUEST,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListContext(): array
    {
        $admins = AdminQuery::create()->orderByLogin()->find();
        $rows = [];
        $editForms = [];
        $defaultLocale = $this->resolveDefaultLocale();
        $profileChoices = $this->profileChoices();

        foreach ($admins as $admin) {
            $rows[] = $this->administratorToRow($admin);
            $editForms[$admin->getId()] = $this->createEditForm($admin, $defaultLocale, $profileChoices)->createView();
        }

        $createForm = $this->formFactory->createNamed('thelia_administrator_create', AdministratorType::class, [
            'locale' => $defaultLocale,
        ], [
            'profile_choices' => $profileChoices,
            'csrf_protection' => false,
        ]);

        return [
            'rows' => $rows,
            'edit_forms' => $editForms,
            'create_form' => $createForm->createView(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function administratorToRow(Admin $admin): array
    {
        $id = $admin->getId();

        $actions = [
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Edit this administrator'),
                modalTarget: '#administrator-edit-modal-'.$id,
                grantedAttribute: AccessManager::UPDATE,
                grantedSubject: self::RESOURCE,
                dataAttributes: ['administrator-id' => $id],
            ),
            new RowAction(
                kind: 'delete',
                label: $this->translator->trans('Delete this administrator'),
                modalTarget: '#administrator-delete-modal',
                grantedAttribute: AccessManager::DELETE,
                grantedSubject: self::RESOURCE,
                dataAttributes: ['administrator-id' => $id, 'administrator-login' => $admin->getLogin()],
            ),
        ];

        return [
            'id' => $id,
            'login' => $admin->getLogin(),
            'name' => trim($admin->getFirstname().' '.$admin->getLastname()),
            'email' => $admin->getEmail(),
            'profile' => $admin->getProfile()?->getTitle() ?? $this->translator->trans('(No profile)'),
            '_actions' => $actions,
        ];
    }

    /**
     * @param array<int|string, int|string> $profileChoices
     */
    private function createEditForm(Admin $admin, string $defaultLocale, array $profileChoices): FormInterface
    {
        return $this->formFactory->createNamed('thelia_administrator_update_'.$admin->getId(), AdministratorType::class, [
            'id' => $admin->getId(),
            'login' => $admin->getLogin(),
            'firstname' => $admin->getFirstname(),
            'lastname' => $admin->getLastname(),
            'email' => $admin->getEmail(),
            'profile' => $admin->getProfileId(),
            'locale' => $admin->getLocale() ?: $defaultLocale,
        ], [
            'include_id' => true,
            'profile_choices' => $profileChoices,
            'csrf_protection' => false,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function profileChoices(): array
    {
        $choices = [];
        foreach (ProfileQuery::create()->orderByCode()->find() as $profile) {
            $choices[(string) $profile->getTitle()] = (int) $profile->getId();
        }

        return $choices;
    }

    private function resolveDefaultLocale(): string
    {
        $defaultLang = \Thelia\Model\LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
