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

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Translation\TranslationEvent;
use Thelia\Core\HttpFoundation\Session\Session as TheliaSession;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\LangQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Twig\Environment;

final class TranslationsController
{
    private const RESOURCE = AdminResources::TRANSLATIONS;
    private const ROUTE = 'admin.configuration.translations';
    private const TEMPLATE = '@BackOfficeDefaultTwig/configuration/translations/edit.html.twig';

    public function __construct(
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly EventDispatcherInterface $events,
        private readonly TemplateHelperInterface $templateHelper,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/admin/configuration/translations', name: 'admin.configuration.translations', methods: ['GET'])]
    public function defaultAction(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return $this->renderEditor($request);
    }

    #[Route('/admin/configuration/translations/update', name: 'admin.configuration.translations.update', methods: ['POST'])]
    public function updateAction(Request $request): Response
    {
        if ($denied = $this->access->check(AdminResources::LANGUAGE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        return $this->renderEditor($request);
    }

    private function renderEditor(Request $request): Response
    {
        $itemToTranslate = (string) ($request->get('item_to_translate') ?? '');
        $itemName = (string) ($request->get('item_name') ?? '');
        $modulePart = '';
        if ($itemToTranslate === 'mo' && $itemName !== '') {
            $modulePart = (string) ($request->get('module_part') ?? '');
        }

        $locale = $this->editionLocale($request);
        $viewMissingOnly = (bool) $request->get('view_missing_traductions_only', false);

        $context = [
            'item_to_translate' => $itemToTranslate,
            'item_name' => $itemName,
            'module_part' => $modulePart,
            'view_missing_only' => $viewMissingOnly,
            'edit_language_id' => $this->editionLanguageId($request),
            'edit_language_locale' => $locale,
            'modules' => $this->moduleOptions(),
            'fo_templates' => $this->templateNames(TemplateDefinition::FRONT_OFFICE, THELIA_TEMPLATE_DIR),
            'bo_templates' => $this->templateNames(TemplateDefinition::BACK_OFFICE, THELIA_TEMPLATE_DIR),
            'pdf_templates' => $this->templateNames(TemplateDefinition::PDF, THELIA_TEMPLATE_DIR),
            'email_templates' => $this->templateNames(TemplateDefinition::EMAIL, THELIA_TEMPLATE_DIR),
            'available_languages' => $this->languageOptions(),
            'is_writable' => $this->isWritableI18nDirectory(THELIA_LOCAL_DIR.'I18n'),
            'all_strings' => [],
            'max_input_vars_warning' => false,
            'required_max_input_vars' => 0,
            'current_max_input_vars' => (int) \ini_get('max_input_vars'),
            'back_office_templates' => '',
            'front_office_templates' => '',
            'email_templates_for_module' => '',
            'pdf_templates_for_module' => '',
            'has_admin_includes' => false,
        ];

        $resolved = $this->resolveTarget($itemToTranslate, $itemName, $modulePart, $locale);
        $context = array_merge($context, $resolved['context'] ?? []);

        if ($resolved['directory'] !== null && $resolved['domain'] !== '') {
            $i18nDirectory = $resolved['i18n_directory'];
            $domain = $resolved['domain'];
            $walkMode = $resolved['walk_mode'];

            if ($request->isMethod('POST') && $i18nDirectory !== null) {
                $saveMode = $request->request->get('save_mode');
                if ($saveMode !== null && $saveMode !== '' && $saveMode !== false) {
                    $texts = (array) $request->request->all('text');
                    if (\count($texts) > 0) {
                        $event = TranslationEvent::createWriteFileEvent(
                            \sprintf('%s'.\DIRECTORY_SEPARATOR.'%s.php', $i18nDirectory, $locale),
                            $texts,
                            (array) $request->request->all('translation'),
                            true,
                        );

                        $event
                            ->setDomain($domain)
                            ->setLocale($locale)
                            ->setCustomFallbackStrings((array) $request->request->all('translation_custom'))
                            ->setGlobalFallbackStrings((array) $request->request->all('translation_global'));

                        $this->events->dispatch($event, TheliaEvents::TRANSLATION_WRITE_FILE);

                        if ($saveMode === 'stay') {
                            return new RedirectResponse($this->urls->generate(self::ROUTE, [
                                'item_to_translate' => $itemToTranslate,
                                'item_name' => $itemName,
                                'module_part' => $modulePart,
                                'view_missing_traductions_only' => $viewMissingOnly ? 1 : 0,
                                'edit_language_id' => $context['edit_language_id'],
                            ]));
                        }

                        return new RedirectResponse('/admin/configuration');
                    }
                }
            }

            $event = TranslationEvent::createGetStringsEvent(
                $resolved['directory'],
                $walkMode,
                $locale,
                $domain,
            );
            $this->events->dispatch($event, TheliaEvents::TRANSLATION_GET_STRINGS);

            $stringsCount = $event->getTranslatableStringCount() * 4 + 6;
            if ($stringsCount > $context['current_max_input_vars']) {
                $context['max_input_vars_warning'] = true;
                $context['required_max_input_vars'] = $stringsCount;
            } else {
                $context['all_strings'] = $event->getTranslatableStrings();
            }
        }

        return new Response($this->twig->render(self::TEMPLATE, $context));
    }

    /**
     * @return array{directory: ?string, i18n_directory: ?string, domain: string, walk_mode: string, context: array<string, mixed>}
     */
    private function resolveTarget(string $itemToTranslate, string $itemName, string $modulePart, string $locale): array
    {
        $result = [
            'directory' => null,
            'i18n_directory' => null,
            'domain' => '',
            'walk_mode' => TranslationEvent::WALK_MODE_TEMPLATE,
            'context' => [],
        ];

        if ($itemToTranslate === '' && $itemName === '' && $itemToTranslate !== 'co' && $itemToTranslate !== 'in' && $itemToTranslate !== 'wi') {
            return $result;
        }

        switch ($itemToTranslate) {
            case 'mo':
                if ($itemName === '') {
                    return $result;
                }
                $module = ModuleQuery::create()->findPk($itemName);
                if (!$module instanceof Module) {
                    return $result;
                }
                $this->fillModuleContext($module, $modulePart, $result);
                break;
            case 'co':
                $result['directory'] = THELIA_LIB;
                $result['domain'] = 'core';
                $result['i18n_directory'] = THELIA_LIB.'Config'.\DIRECTORY_SEPARATOR.'I18n';
                $result['walk_mode'] = TranslationEvent::WALK_MODE_PHP;
                break;
            case 'in':
                if (\defined('THELIA_SETUP_DIRECTORY')) {
                    $result['directory'] = THELIA_SETUP_DIRECTORY;
                    $result['domain'] = 'install';
                    $result['i18n_directory'] = THELIA_SETUP_DIRECTORY.'I18n';
                    $result['walk_mode'] = TranslationEvent::WALK_MODE_TEMPLATE;
                    $this->loadTranslation($result['i18n_directory'], $result['domain']);
                }
                break;
            case 'wi':
                if (\defined('THELIA_SETUP_WIZARD_DIRECTORY')) {
                    $result['directory'] = THELIA_SETUP_WIZARD_DIRECTORY;
                    $result['domain'] = 'wizard';
                    $result['i18n_directory'] = THELIA_SETUP_WIZARD_DIRECTORY.'I18n';
                    $result['walk_mode'] = TranslationEvent::WALK_MODE_PHP;
                    $this->loadTranslation($result['i18n_directory'], $result['domain']);
                }
                break;
            case 'fo':
            case 'bo':
            case 'pf':
            case 'ma':
                if ($itemName === '') {
                    return $result;
                }
                $typeMap = [
                    'fo' => TemplateDefinition::FRONT_OFFICE,
                    'bo' => TemplateDefinition::BACK_OFFICE,
                    'pf' => TemplateDefinition::PDF,
                    'ma' => TemplateDefinition::EMAIL,
                ];
                $template = new TemplateDefinition($itemName, $typeMap[$itemToTranslate]);
                $result['directory'] = $template->getAbsolutePath();
                $result['i18n_directory'] = $template->getAbsoluteI18nPath();
                $result['domain'] = $template->getTranslationDomain();
                $result['walk_mode'] = TranslationEvent::WALK_MODE_TEMPLATE;
                if (!$this->templateHelper->isActive($template)) {
                    $this->loadTranslation($result['i18n_directory'], $result['domain']);
                }
                break;
        }

        return $result;
    }

    /** @param array{directory: ?string, i18n_directory: ?string, domain: string, walk_mode: string, context: array<string, mixed>} $result */
    private function fillModuleContext(Module $module, string $modulePart, array &$result): void
    {
        $context = [
            'back_office_templates' => implode(',', $this->moduleTemplateNames($module, TemplateDefinition::BACK_OFFICE)),
            'front_office_templates' => implode(',', $this->moduleTemplateNames($module, TemplateDefinition::FRONT_OFFICE)),
            'email_templates_for_module' => implode(',', $this->moduleTemplateNames($module, TemplateDefinition::EMAIL)),
            'pdf_templates_for_module' => implode(',', $this->moduleTemplateNames($module, TemplateDefinition::PDF)),
            'has_admin_includes' => $this->moduleHasAdminIncludes($module),
        ];

        if ($modulePart === 'core') {
            $result['directory'] = $module->getAbsoluteBaseDir();
            $result['domain'] = $module->getTranslationDomain();
            $result['i18n_directory'] = $module->getAbsoluteI18nPath();
            $result['walk_mode'] = TranslationEvent::WALK_MODE_PHP;
        } elseif ($modulePart === 'admin-includes') {
            $result['directory'] = $module->getAbsoluteAdminIncludesPath();
            $result['domain'] = $module->getAdminIncludesTranslationDomain();
            $result['i18n_directory'] = $module->getAbsoluteAdminIncludesI18nPath();
            $result['walk_mode'] = TranslationEvent::WALK_MODE_TEMPLATE;
        } elseif ($modulePart !== '') {
            [$type, $subdir] = explode('.', $modulePart) + [null, null];
            switch ($type) {
                case 'bo':
                    $result['directory'] = $module->getAbsoluteBackOfficeTemplatePath($subdir);
                    $result['domain'] = $module->getBackOfficeTemplateTranslationDomain($subdir);
                    $result['i18n_directory'] = $module->getAbsoluteBackOfficeI18nTemplatePath($subdir);
                    break;
                case 'fo':
                    $result['directory'] = $module->getAbsoluteFrontOfficeTemplatePath($subdir);
                    $result['domain'] = $module->getFrontOfficeTemplateTranslationDomain($subdir);
                    $result['i18n_directory'] = $module->getAbsoluteFrontOfficeI18nTemplatePath($subdir);
                    break;
                case 'email':
                    $result['directory'] = $module->getAbsoluteEmailTemplatePath($subdir);
                    $result['domain'] = $module->getEmailTemplateTranslationDomain($subdir);
                    $result['i18n_directory'] = $module->getAbsoluteEmailI18nTemplatePath($subdir);
                    break;
                case 'pdf':
                    $result['directory'] = $module->getAbsolutePdfTemplatePath($subdir);
                    $result['domain'] = $module->getPdfTemplateTranslationDomain($subdir);
                    $result['i18n_directory'] = $module->getAbsolutePdfI18nTemplatePath($subdir);
                    break;
            }
            $result['walk_mode'] = TranslationEvent::WALK_MODE_TEMPLATE;
        }

        if ($result['domain'] !== '' && $result['i18n_directory'] !== null) {
            $this->loadTranslation($result['i18n_directory'], $result['domain']);
        }

        $result['context'] = $context;
    }

    /** @return list<string> */
    private function moduleTemplateNames(Module $module, int $type): array
    {
        $templates = $this->templateHelper->getList($type, $module->getAbsoluteTemplateBasePath());
        $names = [];
        foreach ($templates as $template) {
            $names[] = $template->getName();
        }

        return $names;
    }

    /** @return list<string> */
    private function templateNames(int $type, string $base): array
    {
        $names = [];
        try {
            foreach ($this->templateHelper->getList($type, $base) as $template) {
                $names[] = $template->getName();
            }
        } catch (\Throwable) {
            // base directory missing → no templates to list
        }

        return $names;
    }

    private function moduleHasAdminIncludes(Module $module): bool
    {
        try {
            $finder = Finder::create()
                ->files()
                ->depth(0)
                ->in($module->getAbsoluteAdminIncludesPath())
                ->name('/\.html$/i');

            return $finder->count() > 0;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private function loadTranslation(string $directory, string $domain): void
    {
        try {
            $finder = Finder::create()
                ->files()
                ->depth(0)
                ->in($directory);

            foreach ($finder as $file) {
                [$loc, $format] = explode('.', $file->getBaseName(), 2);
                Translator::getInstance()->addResource($format, $file->getPathname(), $loc, $domain);
            }
        } catch (\InvalidArgumentException) {
        }
    }

    /** @return list<array{id: int, code: string, title: string}> */
    private function moduleOptions(): array
    {
        $options = [];
        foreach (ModuleQuery::create()->orderByCode()->find() as $module) {
            $module->setLocale($this->defaultLocale());
            $options[] = [
                'id' => (int) $module->getId(),
                'code' => (string) $module->getCode(),
                'title' => (string) $module->getTitle(),
            ];
        }

        return $options;
    }

    /** @return list<array{id: int, title: string, locale: string, code: string}> */
    private function languageOptions(): array
    {
        $options = [];
        foreach (LangQuery::create()->orderByPosition()->find() as $lang) {
            $options[] = [
                'id' => (int) $lang->getId(),
                'title' => (string) $lang->getTitle(),
                'locale' => (string) $lang->getLocale(),
                'code' => (string) $lang->getCode(),
            ];
        }

        return $options;
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }

    private function editionLocale(Request $request): string
    {
        $editionId = $request->get('edit_language_id');
        if ($editionId !== null && (int) $editionId > 0) {
            $lang = LangQuery::create()->findPk((int) $editionId);
            if ($lang !== null) {
                return (string) $lang->getLocale();
            }
        }

        $session = $request->getSession();
        if ($session instanceof TheliaSession) {
            $lang = $session->getAdminEditionLang();

            return (string) $lang->getLocale();
        }

        return $this->defaultLocale();
    }

    private function editionLanguageId(Request $request): int
    {
        $editionId = $request->get('edit_language_id');
        if ($editionId !== null && (int) $editionId > 0) {
            return (int) $editionId;
        }

        $session = $request->getSession();
        if ($session instanceof TheliaSession) {
            $lang = $session->getAdminEditionLang();

            return (int) $lang->getId();
        }

        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return (int) ($defaultLang?->getId() ?? 0);
    }

    private function isWritableI18nDirectory(string $dir): bool
    {
        if (file_exists($dir)) {
            return is_writable($dir);
        }

        $parent = \dirname($dir);

        return file_exists($parent) && is_writable($parent);
    }
}
