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

namespace BackOfficeDefaultTwigBundle\Controller\Module;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\RowAction;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Archiver\Archiver\ZipArchiver;
use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\Module\ModuleEvent;
use Thelia\Core\Event\Module\ModuleInstallEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\Validator\ModuleValidator;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin', name: 'admin.')]
final class ModuleController
{
    private const RESOURCE = AdminResources::MODULE;
    private const LIST_ROUTE = 'admin.module';
    private const EDIT_ROUTE = 'admin.module.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/module/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/module/edit.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenProvider $tokens,
        private readonly EventDispatcherInterface $events,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/modules', name: 'module', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, $this->buildListContext()));
    }

    #[Route('/module/update/{module_id}', name: 'module.update', methods: ['GET'], requirements: ['module_id' => '\d+'])]
    public function updateView(int $module_id): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $module = ModuleQuery::create()->findPk($module_id);
        if ($module === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $module->setLocale($locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'module' => $module,
            'locale' => $locale,
        ]));
    }

    #[Route('/module/save', name: 'module.save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $moduleId = (int) $request->request->get('module_id', $request->request->get('id', 0));
        $module = ModuleQuery::create()->findPk($moduleId);
        if ($module === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        try {
            $event = new ModuleEvent($module);
            $event->setId($moduleId);
            $event->setLocale((string) $request->request->get('locale', $this->defaultLocale()));
            $event->setTitle((string) $request->request->get('title', ''));
            $event->setChapo($request->request->get('chapo') !== null ? (string) $request->request->get('chapo') : null);
            $event->setDescription($request->request->get('description') !== null ? (string) $request->request->get('description') : null);
            $event->setPostscriptum($request->request->get('postscriptum') !== null ? (string) $request->request->get('postscriptum') : null);

            $this->events->dispatch($event, TheliaEvents::MODULE_UPDATE);
        } catch (\Throwable) {
        }

        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['module_id' => $moduleId]));
    }

    #[Route('/module/toggle-activation/{module_id}', name: 'module.toggle-activation', methods: ['GET', 'POST'], requirements: ['module_id' => '\d+'])]
    public function toggleActivation(int $module_id, Request $request): Response
    {
        if (ModuleQuery::create()->findPk($module_id) === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ModuleToggleActivationEvent($module_id),
            eventName: TheliaEvents::MODULE_TOGGLE_ACTIVATION,
            actionLabel: 'Module activation toggled',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/module/delete', name: 'module.delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        $moduleId = (int) $request->get('module_id', 0);
        if (ModuleQuery::create()->findPk($moduleId) === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: new ModuleDeleteEvent($moduleId),
            eventName: TheliaEvents::MODULE_DELETE,
            actionLabel: 'Module deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/module/update-position', name: 'module.update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('module_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::MODULE_UPDATE_POSITION,
            actionLabel: 'Module reorder',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/module/install', name: 'module.install', methods: ['POST'])]
    public function install(Request $request): RedirectResponse
    {
        if ($this->access->check(self::RESOURCE, [], AccessManager::CREATE)) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        try {
            $this->tokens->checkToken((string) $request->request->get('_token', $request->query->get('_token')));
        } catch (\Throwable) {
            $this->flash($request, 'danger', $this->translator->trans('Invalid CSRF token.'));

            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $upload = $request->files->get('module');
        if (!$upload instanceof UploadedFile) {
            $this->flash($request, 'danger', $this->translator->trans('Please upload a valid Zip file.'));

            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        try {
            $extractPath = $this->extractModule($upload);
            $children = array_values(array_filter(
                array_diff(scandir($extractPath) ?: [], ['.', '..']),
                static fn (string $name) => is_dir($extractPath.\DIRECTORY_SEPARATOR.$name),
            ));

            if (\count($children) !== 1) {
                throw new \RuntimeException($this->translator->trans('Your zip must contain a single root directory.'));
            }

            $modulePath = $extractPath.\DIRECTORY_SEPARATOR.$children[0];

            $validator = new ModuleValidator($modulePath);
            $validator->validate();

            $event = new ModuleInstallEvent();
            $event->setModulePath($modulePath)->setModuleDefinition($validator->getModuleDefinition());

            $this->events->dispatch($event, TheliaEvents::MODULE_INSTALL);

            $this->flash(
                $request,
                'success',
                $this->translator->trans('Module %code installed successfully.', ['%code' => $validator->getModuleDefinition()->getCode()]),
            );
        } catch (\Throwable $exception) {
            $this->flash($request, 'danger', $this->translator->trans('Module installation failed: %message', ['%message' => $exception->getMessage()]));
        }

        return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
    }

    private function extractModule(UploadedFile $file): string
    {
        $zip = new ZipArchiver(true);

        if (!$zip->open($file->getRealPath())) {
            throw new \RuntimeException($this->translator->trans('Unable to open the uploaded archive.'));
        }

        $tempName = tempnam(sys_get_temp_dir(), 'thelia_module_');
        if ($tempName === false) {
            throw new \RuntimeException($this->translator->trans('Unable to create a temporary directory.'));
        }
        unlink($tempName);
        mkdir($tempName);

        if (!$zip->extract($tempName)) {
            $zip->close();

            throw new \RuntimeException($this->translator->trans('Unable to extract the uploaded archive.'));
        }

        $zip->close();

        return $tempName;
    }

    private function flash(Request $request, string $type, string $message): void
    {
        try {
            $session = $request->getSession();
            if (method_exists($session, 'getFlashBag')) {
                $session->getFlashBag()->add($type, $message);
            }
        } catch (\Throwable) {
            // silent — CLI session has no FlashBag
        }
    }

    #[Route('/module/information/{module_id}', name: 'module.information', methods: ['GET'], requirements: ['module_id' => '\d+'])]
    public function information(int $module_id): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['module_id' => $module_id]));
    }

    #[Route('/module/documentation/{module_id}', name: 'module.documentation', methods: ['GET'], requirements: ['module_id' => '\d+'])]
    public function documentation(int $module_id): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['module_id' => $module_id]));
    }

    #[Route('/module/{module_code}', name: 'module.configure', methods: ['GET', 'POST'], requirements: ['module_code' => '[A-Za-z][A-Za-z0-9_-]*'])]
    public function configure(string $module_code): RedirectResponse
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $module = ModuleQuery::create()->findOneByCode($module_code);
        if ($module === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['module_id' => (int) $module->getId()]));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListContext(): array
    {
        $locale = $this->defaultLocale();
        $modules = ModuleQuery::create()->orderByPosition()->find();
        $rows = [];
        foreach ($modules as $module) {
            \assert($module instanceof Module);
            $module->setLocale($locale);
            $rows[] = $this->moduleToRow($module);
        }

        return [
            'rows' => $rows,
            'update_position_url' => $this->urls->generate('admin.module.update-position'),
            'update_position_token' => $this->tokens->assignToken(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleToRow(Module $module): array
    {
        $id = (int) $module->getId();
        $activated = (bool) $module->getActivate();

        $actions = [
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Edit module info'),
                href: $this->urls->generate(self::EDIT_ROUTE, ['module_id' => $id]),
                grantedAttribute: AccessManager::UPDATE,
                grantedSubject: self::RESOURCE,
            ),
            new RowAction(
                kind: 'delete',
                label: $this->translator->trans('Delete this module'),
                modalTarget: '#module-delete-modal',
                grantedAttribute: AccessManager::DELETE,
                grantedSubject: self::RESOURCE,
                dataAttributes: ['module-id' => $id, 'module-label' => (string) $module->getTitle()],
            ),
        ];

        return [
            'id' => $id,
            'code' => (string) $module->getCode(),
            'title' => (string) $module->getTitle(),
            'type' => (string) $module->getType(),
            'version' => (string) $module->getVersion(),
            'activated' => $activated,
            'position' => (int) $module->getPosition(),
            'toggle_url' => $this->tokenizedUrl('admin.module.toggle-activation', ['module_id' => $id]),
            'configure_url' => $this->urls->generate('admin.module.configure', ['module_code' => (string) $module->getCode()]),
            '_actions' => $actions,
        ];
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function tokenizedUrl(string $route, array $parameters): string
    {
        $url = $this->urls->generate($route, $parameters);
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'_token='.$this->tokens->assignToken();
    }
}
