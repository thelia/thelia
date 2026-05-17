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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Hook\ModuleHookCreateEvent;
use Thelia\Core\Event\Hook\ModuleHookDeleteEvent;
use Thelia\Core\Event\Hook\ModuleHookToggleActivationEvent;
use Thelia\Core\Event\Hook\ModuleHookUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\HookQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

final class ModuleHookController
{
    private const RESOURCE = AdminResources::MODULE_HOOK;
    private const LIST_ROUTE = 'admin.module-hook';
    private const EDIT_ROUTE = 'admin.module-hook.update';

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

    #[Route('/admin/module-hooks', name: 'admin.module-hook', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/module/hook-list.html.twig', $this->buildListContext()));
    }

    #[Route('/admin/module-hooks/create', name: 'admin.module-hook.create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::CREATE)) {
            return $denied;
        }

        try {
            $event = new ModuleHookCreateEvent();
            $event->setModuleId((int) $request->request->get('module_id', 0));
            $event->setHookId((int) $request->request->get('hook_id', 0));
            $event->setClassname((string) $request->request->get('classname', ''));
            $event->setMethod((string) $request->request->get('method', ''));

            $this->events->dispatch($event, TheliaEvents::MODULE_HOOK_CREATE);
        } catch (\Throwable) {
        }

        return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
    }

    #[Route('/admin/module-hook/update/{module_hook_id}', name: 'admin.module-hook.update', methods: ['GET'], requirements: ['module_hook_id' => '\d+'])]
    public function updateView(int $module_hook_id): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $moduleHook = ModuleHookQuery::create()->findPk($module_hook_id);
        if ($moduleHook === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return new Response($this->twig->render('@BackOfficeDefaultTwig/module/hook-edit.html.twig', [
            'module_hook' => $moduleHook,
            'available_modules' => $this->moduleChoices(),
            'available_hooks' => $this->hookChoices(),
        ]));
    }

    #[Route('/admin/module-hook/save/{module_hook_id}', name: 'admin.module-hook.save', methods: ['POST'], requirements: ['module_hook_id' => '\d+'])]
    public function save(int $module_hook_id, Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        try {
            $moduleHook = ModuleHookQuery::create()->findPk($module_hook_id);
            $event = new ModuleHookUpdateEvent($moduleHook);
            $event->setModuleHookId($module_hook_id);
            $event->setModuleId((int) $request->request->get('module_id', 0));
            $event->setHookId((int) $request->request->get('hook_id', 0));
            $event->setClassname((string) $request->request->get('classname', ''));
            $event->setMethod((string) $request->request->get('method', ''));
            $event->setTemplates((string) $request->request->get('templates', ''));
            $event->setActive((bool) $request->request->get('active', false));

            $this->events->dispatch($event, TheliaEvents::MODULE_HOOK_UPDATE);
        } catch (\Throwable) {
        }

        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['module_hook_id' => $module_hook_id]));
    }

    #[Route('/admin/module-hooks/delete', name: 'admin.module-hook.delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        $moduleHookId = (int) $request->get('module_hook_id', 0);

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: new ModuleHookDeleteEvent($moduleHookId),
            eventName: TheliaEvents::MODULE_HOOK_DELETE,
            actionLabel: 'Module hook deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/admin/module-hooks/toggle-activation/{module_hook_id}', name: 'admin.module-hook.toggle-activation', methods: ['GET', 'POST'], requirements: ['module_hook_id' => '\d+'])]
    public function toggleActivation(int $module_hook_id, Request $request): Response
    {
        $moduleHook = ModuleHookQuery::create()->findPk($module_hook_id);
        if ($moduleHook === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ModuleHookToggleActivationEvent($moduleHook),
            eventName: TheliaEvents::MODULE_HOOK_TOGGLE_ACTIVATION,
            actionLabel: 'Module hook activation toggled',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/admin/module-hooks/update-position', name: 'admin.module-hook.update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('module_hook_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::MODULE_HOOK_UPDATE_POSITION,
            actionLabel: 'Module hook reorder',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/admin/module-hooks/get-module-hook-classnames/{moduleId}', name: 'admin.module-hook.get-module-hook-classnames', methods: ['GET'], requirements: ['moduleId' => '\d+'])]
    public function getClassnames(int $moduleId): JsonResponse
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        $module = ModuleQuery::create()->findPk($moduleId);
        if ($module === null) {
            return new JsonResponse(['classnames' => []]);
        }

        $namespace = (string) $module->getFullNamespace();
        $rootNamespace = trim(substr($namespace, 0, strrpos($namespace, '\\') ?: \strlen($namespace)), '\\');

        $classnames = [];
        foreach (get_declared_classes() as $class) {
            if (!str_starts_with($class, $rootNamespace.'\\')) {
                continue;
            }

            if (!is_subclass_of($class, \Thelia\Core\Hook\BaseHook::class)) {
                continue;
            }

            $classnames[] = $class;
        }

        sort($classnames);

        return new JsonResponse(['classnames' => $classnames]);
    }

    #[Route('/admin/module-hooks/get-module-hook-methods/{moduleId}/{className}', name: 'admin.module-hook.get-module-hook-methods', methods: ['GET'], requirements: ['moduleId' => '\d+'])]
    public function getMethods(int $moduleId, string $className): JsonResponse
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        $module = ModuleQuery::create()->findPk($moduleId);
        if ($module === null || !class_exists($className)) {
            return new JsonResponse(['methods' => []]);
        }

        $namespace = (string) $module->getFullNamespace();
        $rootNamespace = trim(substr($namespace, 0, strrpos($namespace, '\\') ?: \strlen($namespace)), '\\');
        if (!str_starts_with($className, $rootNamespace.'\\') || !is_subclass_of($className, \Thelia\Core\Hook\BaseHook::class)) {
            return new JsonResponse(['methods' => []]);
        }

        $methods = [];
        foreach ((new \ReflectionClass($className))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $className) {
                continue;
            }
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }
            $methods[] = $method->getName();
        }

        sort($methods);

        return new JsonResponse(['methods' => $methods]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListContext(): array
    {
        $moduleHooks = ModuleHookQuery::create()->orderByPosition()->find();
        $rows = [];
        foreach ($moduleHooks as $hook) {
            $module = ModuleQuery::create()->findPk((int) $hook->getModuleId());
            $hookModel = HookQuery::create()->findPk((int) $hook->getHookId());
            $rows[] = [
                'id' => (int) $hook->getId(),
                'module' => $module ? (string) $module->getCode() : '—',
                'hook' => $hookModel ? (string) $hookModel->getCode() : '—',
                'classname' => (string) $hook->getClassname(),
                'method' => (string) $hook->getMethod(),
                'active' => (bool) $hook->getActive(),
                'position' => (int) $hook->getPosition(),
                'edit_url' => $this->urls->generate(self::EDIT_ROUTE, ['module_hook_id' => (int) $hook->getId()]),
                'toggle_url' => $this->tokenizedUrl('admin.module-hook.toggle-activation', ['module_hook_id' => (int) $hook->getId()]),
            ];
        }

        return [
            'rows' => $rows,
            'available_modules' => $this->moduleChoices(),
            'available_hooks' => $this->hookChoices(),
            'update_position_url' => $this->urls->generate('admin.module-hook.update-position'),
            'update_position_token' => $this->tokens->assignToken(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function moduleChoices(): array
    {
        $locale = $this->defaultLocale();
        $items = [];
        foreach (ModuleQuery::create()->orderByPosition()->find() as $module) {
            $module->setLocale($locale);
            $items[] = ['id' => (int) $module->getId(), 'code' => (string) $module->getCode()];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hookChoices(): array
    {
        $locale = $this->defaultLocale();
        $items = [];
        foreach (HookQuery::create()->orderByCode()->find() as $hook) {
            $hook->setLocale($locale);
            $items[] = ['id' => (int) $hook->getId(), 'code' => (string) $hook->getCode()];
        }

        return $items;
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
