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

use BackOfficeDefaultTwigBundle\Form\Profile\ProfileType;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Profile\ProfileEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Profile;
use Thelia\Model\ProfileQuery;
use Thelia\Model\ResourceQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/configuration/profiles', name: 'admin.configuration.profiles.')]
final class ProfileController
{
    private const RESOURCE = AdminResources::PROFILE;
    private const LIST_ROUTE = 'admin.configuration.profiles.list';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/profile/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/configuration/profile/edit.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly TokenProvider $tokens,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, $this->buildListContext()));
    }

    #[Route('/update/{profile_id}', name: 'update', requirements: ['profile_id' => '\d+'], methods: ['GET'])]
    public function edit(int $profile_id): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $profile = ProfileQuery::create()->findPk($profile_id);
        if ($profile === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return new Response($this->twig->render(self::EDIT_TEMPLATE, $this->buildEditContext($profile)));
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(): Response
    {
        $form = $this->formFactory->createNamed('thelia_profile_create', ProfileType::class, null, [
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::PROFILE_CREATE,
            eventFactory: function (FormInterface $validated): ProfileEvent {
                $event = new ProfileEvent();
                $event
                    ->setLocale((string) $validated->get('locale')->getData())
                    ->setCode((string) $validated->get('code')->getData())
                    ->setTitle((string) $validated->get('title')->getData())
                    ->setChapo((string) ($validated->get('chapo')->getData() ?? ''))
                    ->setDescription((string) ($validated->get('description')->getData() ?? ''))
                    ->setPostscriptum((string) ($validated->get('postscriptum')->getData() ?? ''));

                return $event;
            },
            actionLabel: 'Profile creation',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): Response => $this->renderListWithError(),
            describeForLog: fn (ProfileEvent $event): array => $this->describeProfile($event, 'created'),
        );
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_profile_update', ProfileType::class, null, [
            'include_id' => true,
            'csrf_protection' => false,
        ]);

        $profileId = (int) $request->request->get('thelia_profile_update', ['id' => 0])['id'];

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::PROFILE_UPDATE,
            eventFactory: function (FormInterface $validated): ProfileEvent {
                $event = new ProfileEvent();
                $event
                    ->setId((int) $validated->get('id')->getData())
                    ->setLocale((string) $validated->get('locale')->getData())
                    ->setTitle((string) $validated->get('title')->getData())
                    ->setChapo((string) ($validated->get('chapo')->getData() ?? ''))
                    ->setDescription((string) ($validated->get('description')->getData() ?? ''))
                    ->setPostscriptum((string) ($validated->get('postscriptum')->getData() ?? ''));

                return $event;
            },
            actionLabel: 'Profile update',
            successRoute: 'admin.configuration.profiles.update',
            successParameters: ['profile_id' => $profileId],
            renderError: fn (): Response => new RedirectResponse(
                $this->urls->generate('admin.configuration.profiles.update', ['profile_id' => $profileId]),
            ),
            describeForLog: fn (ProfileEvent $event): array => $this->describeProfile($event, 'modified'),
        );
    }

    #[Route('/saveResourceAccess', name: 'saveResourceAccess', methods: ['POST'])]
    public function saveResourceAccess(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $profileId = (int) $request->request->get('profile_id', 0);
        $resources = (array) $request->request->all('resource');

        $event = new ProfileEvent();
        $event->setId($profileId);
        $event->setResourceAccess($this->flattenAccessKeys($resources));

        $this->events->dispatch($event, TheliaEvents::PROFILE_RESOURCE_ACCESS_UPDATE);

        return new RedirectResponse($this->urls->generate('admin.configuration.profiles.update', ['profile_id' => $profileId]));
    }

    #[Route('/saveModuleAccess', name: 'saveModuleAccess', methods: ['POST'])]
    public function saveModuleAccess(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $profileId = (int) $request->request->get('profile_id', 0);
        $modules = (array) $request->request->all('module');

        $event = new ProfileEvent();
        $event->setId($profileId);
        $event->setModuleAccess($this->flattenAccessKeys($modules));

        $this->events->dispatch($event, TheliaEvents::PROFILE_MODULE_ACCESS_UPDATE);

        return new RedirectResponse($this->urls->generate('admin.configuration.profiles.update', ['profile_id' => $profileId]));
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        $event = new ProfileEvent();
        $event->setId((int) $request->get('profile_id', 0));

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::PROFILE_DELETE,
            actionLabel: 'Profile deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    /**
     * @param array<string, mixed> $rawInput Keys like "admin:configuration:lang" → list of access codes.
     *
     * @return array<string, list<string>> Keys flattened to dot-separated resource codes.
     */
    private function flattenAccessKeys(array $rawInput): array
    {
        $output = [];
        foreach ($rawInput as $key => $access) {
            $resourceCode = str_replace(':', '.', (string) $key);
            $output[$resourceCode] = (array) $access;
        }

        return $output;
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeProfile(ProfileEvent $event, string $action): array
    {
        if (!$event->hasProfile()) {
            throw new \LogicException($this->translator->trans('No profile was '.$action.'.'));
        }

        $profile = $event->getProfile();

        return [\sprintf('Profile %s (ID %d) %s', $profile->getTitle(), $profile->getId(), $action), $profile->getId()];
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
        $profiles = ProfileQuery::create()->orderById()->find();
        $rows = [];
        $defaultLocale = $this->resolveDefaultLocale();

        foreach ($profiles as $profile) {
            $rows[] = $this->profileToRow($profile);
        }

        $createForm = $this->formFactory->createNamed('thelia_profile_create', ProfileType::class, [
            'locale' => $defaultLocale,
        ], [
            'csrf_protection' => false,
        ]);

        return [
            'rows' => $rows,
            'create_form' => $createForm->createView(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEditContext(Profile $profile): array
    {
        $defaultLocale = $this->resolveDefaultLocale();
        $profile->setLocale($defaultLocale);

        $updateForm = $this->formFactory->createNamed('thelia_profile_update', ProfileType::class, [
            'id' => $profile->getId(),
            'locale' => $defaultLocale,
            'code' => $profile->getCode(),
            'title' => $profile->getTitle(),
            'chapo' => $profile->getChapo(),
            'description' => $profile->getDescription(),
            'postscriptum' => $profile->getPostscriptum(),
        ], [
            'include_id' => true,
            'csrf_protection' => false,
        ]);

        return [
            'profile' => $profile,
            'update_form' => $updateForm->createView(),
            'resources' => ResourceQuery::create()->orderByCode()->find(),
            'modules' => ModuleQuery::create()->filterByActivate(1)->orderByCode()->find(),
            'resource_access' => $this->buildAccessMap($profile, 'Resource'),
            'module_access' => $this->buildAccessMap($profile, 'Module'),
            'access_codes' => [
                AccessManager::VIEW => 'VIEW',
                AccessManager::CREATE => 'CREATE',
                AccessManager::UPDATE => 'UPDATE',
                AccessManager::DELETE => 'DELETE',
            ],
        ];
    }

    /**
     * @return array<string, list<string>> resource/module code → list of granted access codes.
     */
    private function buildAccessMap(Profile $profile, string $kind): array
    {
        $accessMap = [];
        $method = 'getProfile'.$kind.'s';

        foreach ($profile->{$method}() as $relation) {
            $codeMethod = 'get'.$kind;
            $entity = $relation->{$codeMethod}();
            if ($entity === null) {
                continue;
            }
            $code = $entity->getCode();
            $accessMap[$code] = AccessManager::getAccessNameByValue($relation->getAccess());
        }

        return $accessMap;
    }

    /**
     * @return array<string, mixed>
     */
    private function profileToRow(Profile $profile): array
    {
        $id = $profile->getId();

        $actions = [
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Edit this profile'),
                href: $this->urls->generate('admin.configuration.profiles.update', ['profile_id' => $id]),
                grantedAttribute: AccessManager::UPDATE,
                grantedSubject: self::RESOURCE,
            ),
            new RowAction(
                kind: 'delete',
                label: $this->translator->trans('Delete this profile'),
                modalTarget: '#profile-delete-modal',
                grantedAttribute: AccessManager::DELETE,
                grantedSubject: self::RESOURCE,
                dataAttributes: ['profile-id' => $id, 'profile-title' => $profile->getTitle()],
            ),
        ];

        return [
            'id' => $id,
            'code' => $profile->getCode(),
            'title' => $profile->getTitle(),
            '_actions' => $actions,
        ];
    }

    private function resolveDefaultLocale(): string
    {
        $defaultLang = \Thelia\Model\LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
