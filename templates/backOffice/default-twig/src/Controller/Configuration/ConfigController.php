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

use BackOfficeDefaultTwigBundle\Form\Config\ConfigType;
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
use Thelia\Core\Event\Config\ConfigCreateEvent;
use Thelia\Core\Event\Config\ConfigDeleteEvent;
use Thelia\Core\Event\Config\ConfigUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Config;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/configuration/variables', name: 'admin.configuration.variables.')]
final class ConfigController
{
    private const RESOURCE = AdminResources::CONFIG;
    private const LIST_ROUTE = 'admin.configuration.variables.default';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/variable/list.html.twig';

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

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, $this->buildListContext()));
    }

    #[Route('/update-values', name: 'update-values', methods: ['POST'])]
    public function updateValues(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        /** @var array<int|string, string> $variables */
        $variables = $request->request->all('variable');

        foreach ($variables as $id => $value) {
            $event = new ConfigUpdateEvent((int) $id);
            $event->setValue((string) $value);
            $this->events->dispatch($event, TheliaEvents::CONFIG_SETVALUE);
        }

        return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->formFactory->createNamed('thelia_config_create', ConfigType::class, null, [
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::CONFIG_CREATE,
            eventFactory: function (FormInterface $validated): ConfigCreateEvent {
                $event = new ConfigCreateEvent();
                $event
                    ->setEventName((string) $validated->get('name')->getData())
                    ->setTitle((string) $validated->get('title')->getData())
                    ->setValue((string) ($validated->get('value')->getData() ?? ''))
                    ->setLocale((string) $validated->get('locale')->getData())
                    ->setHidden((bool) $validated->get('hidden')->getData())
                    ->setSecured((bool) $validated->get('secured')->getData());

                return $event;
            },
            actionLabel: 'Variable creation',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): Response => $this->renderListWithError(),
            describeForLog: fn (ConfigCreateEvent $event): array => $this->describeCreated($event),
        );
    }

    #[Route('/update', name: 'update', methods: ['GET'])]
    public function updateRedirect(): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(): Response
    {
        $form = $this->formFactory->createNamed('thelia_config_update', ConfigType::class, null, [
            'include_id' => true,
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::CONFIG_UPDATE,
            eventFactory: function (FormInterface $validated): ConfigUpdateEvent {
                $event = new ConfigUpdateEvent((int) $validated->get('id')->getData());
                $event
                    ->setEventName((string) $validated->get('name')->getData())
                    ->setTitle((string) $validated->get('title')->getData())
                    ->setValue((string) ($validated->get('value')->getData() ?? ''))
                    ->setLocale((string) $validated->get('locale')->getData())
                    ->setHidden((bool) $validated->get('hidden')->getData())
                    ->setSecured((bool) $validated->get('secured')->getData())
                    ->setChapo((string) ($validated->get('chapo')->getData() ?? ''))
                    ->setDescription((string) ($validated->get('description')->getData() ?? ''))
                    ->setPostscriptum((string) ($validated->get('postscriptum')->getData() ?? ''));

                return $event;
            },
            actionLabel: 'Variable update',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): Response => $this->renderListWithError(),
            describeForLog: fn (ConfigUpdateEvent $event): array => $this->describeUpdated($event),
        );
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: new ConfigDeleteEvent((int) $request->get('variable_id', 0)),
            eventName: TheliaEvents::CONFIG_DELETE,
            actionLabel: 'Variable deletion',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): Response => $this->renderListWithError(),
        );
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeCreated(ConfigCreateEvent $event): array
    {
        if (!$event->hasConfig()) {
            throw new \LogicException($this->translator->trans('No variable was created.'));
        }

        $config = $event->getConfig();

        return [\sprintf('Variable %s (ID %d) created', $config->getName(), $config->getId()), $config->getId()];
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeUpdated(ConfigUpdateEvent $event): array
    {
        if (!$event->hasConfig()) {
            throw new \LogicException($this->translator->trans('No variable was updated.'));
        }

        $config = $event->getConfig();

        return [\sprintf('Variable %s (ID %d) modified', $config->getName(), $config->getId()), $config->getId()];
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
        $configs = ConfigQuery::create()
            ->filterByHidden(0)
            ->orderByName()
            ->find();

        $rows = [];
        $editForms = [];
        $defaultLocale = $this->resolveDefaultLocale();

        foreach ($configs as $config) {
            $rows[] = $this->configToRow($config);
            $editForms[$config->getId()] = $this->createEditForm($config, $defaultLocale)->createView();
        }

        $createForm = $this->formFactory->createNamed('thelia_config_create', ConfigType::class, [
            'locale' => $defaultLocale,
        ], [
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
    private function configToRow(Config $config): array
    {
        $id = $config->getId();
        $envOverridden = $config->isOverriddenInEnv();
        $secured = $envOverridden || $config->getSecured();
        $value = (string) ($config->getValue() ?? '');

        $actions = [];

        if (!$envOverridden) {
            $actions[] = new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Edit this variable'),
                modalTarget: '#variable-edit-modal-'.$id,
                grantedAttribute: AccessManager::UPDATE,
                grantedSubject: self::RESOURCE,
                dataAttributes: ['variable-id' => $id],
            );

            if (!$config->getSecured()) {
                $actions[] = new RowAction(
                    kind: 'delete',
                    label: $this->translator->trans('Delete this variable'),
                    modalTarget: '#variable-delete-modal',
                    grantedAttribute: AccessManager::DELETE,
                    grantedSubject: self::RESOURCE,
                    dataAttributes: ['variable-id' => $id, 'variable-name' => $config->getName()],
                );
            }
        }

        return [
            'id' => $id,
            'title' => $config->getTitle(),
            'name' => $config->getName(),
            'value_html' => $this->renderValueCell($id, $value, $secured, $envOverridden),
            'env_overridden' => $envOverridden,
            'secured' => $secured,
            '_actions' => $actions,
        ];
    }

    private function renderValueCell(int $id, string $value, bool $secured, bool $envOverridden): string
    {
        $escapedValue = htmlspecialchars($value, \ENT_QUOTES | \ENT_HTML5);

        if ($envOverridden) {
            return \sprintf(
                '<span class="badge bg-secondary me-1" title="%s">.env</span><code>%s</code>',
                htmlspecialchars(
                    $this->translator->trans('This variable is overridden in an .env file.'),
                    \ENT_QUOTES | \ENT_HTML5,
                ),
                $escapedValue,
            );
        }

        if ($secured) {
            return '<code>'.$escapedValue.'</code>';
        }

        return \sprintf(
            '<input type="text" class="form-control form-control-sm" name="variable[%d]" value="%s" data-testid="variable-inline-value-%d">',
            $id,
            $escapedValue,
            $id,
        );
    }

    private function createEditForm(Config $config, string $defaultLocale): FormInterface
    {
        $config->setLocale($defaultLocale);

        return $this->formFactory->createNamed('thelia_config_update_'.$config->getId(), ConfigType::class, [
            'id' => $config->getId(),
            'name' => $config->getName(),
            'title' => $config->getTitle(),
            'value' => $config->getValue(),
            'hidden' => (bool) $config->getHidden(),
            'secured' => (bool) $config->getSecured(),
            'locale' => $defaultLocale,
            'chapo' => $config->getChapo(),
            'description' => $config->getDescription(),
            'postscriptum' => $config->getPostscriptum(),
        ], [
            'include_id' => true,
            'csrf_protection' => false,
        ]);
    }

    private function resolveDefaultLocale(): string
    {
        $defaultLang = \Thelia\Model\LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
