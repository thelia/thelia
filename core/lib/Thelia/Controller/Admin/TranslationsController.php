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
use DirectoryIterator;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Translation\TranslationEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Tools\URL;

/**
 * Class TranslationsController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class TranslationsController extends BaseAdminController
{
    /**
     * @param string
     */
    protected function getModule(TranslatorInterface $translator, string $moduleCode): Module
    {
        if (null !== $module = ModuleQuery::create()->findPk($moduleCode)) {
            return $module;
        }

        throw new InvalidArgumentException(
            $translator->trans("No module found for code '%item'", ['%item' => $moduleCode])
        );
    }

    protected function getModuleTemplateNames(TemplateHelperInterface $templateHelper, Module $module, int $templateType): array
    {
        $templates =
            $templateHelper->getList(
                $templateType,
                $module->getAbsoluteTemplateBasePath()
            );

        $names = [];

        foreach ($templates as $template) {
            $names[] = $template->getName();
        }

        return $names;
    }

    protected function renderTemplate(
        Request $request,
        TemplateHelperInterface $templateHelper,
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator
    ): RedirectResponse|Response {
        // Get related strings, if all input data are here
        $itemToTranslate = $request->get('item_to_translate');

        $itemName = $request->get('item_name', '');

        $modulePart = false;

        if ($itemToTranslate == 'mo' && !empty($itemName)) {
            $modulePart = $request->get('module_part', '');
        }

        $template = false;
        $directory = false;
        $i18nDirectory = false;

        $walkMode = TranslationEvent::WALK_MODE_TEMPLATE;

        $templateArguments = [
                'item_to_translate' => $itemToTranslate,
                'item_name' => $itemName,
                'module_part' => $modulePart,
                'view_missing_traductions_only' => $request->get('view_missing_traductions_only'),
                'max_input_vars_warning' => false,
        ];

        // Find the i18n directory, and the directory to examine.

        $domain = '';
        if (!empty($itemName) || $itemToTranslate === 'co' || $itemToTranslate === 'in' || $itemToTranslate === 'wi') {
            switch ($itemToTranslate) {
                // Module core
                case 'mo':
                    $module = $this->getModule($translator, $itemName);

                    if ($modulePart === 'core') {
                        $directory = $module->getAbsoluteBaseDir();
                        $domain = $module->getTranslationDomain();
                        $i18nDirectory = $module->getAbsoluteI18nPath();
                        $walkMode = TranslationEvent::WALK_MODE_PHP;
                    } elseif ($modulePart === 'admin-includes') {
                        $directory = $module->getAbsoluteAdminIncludesPath();
                        $domain = $module->getAdminIncludesTranslationDomain();
                        $i18nDirectory = $module->getAbsoluteAdminIncludesI18nPath();
                        $walkMode = TranslationEvent::WALK_MODE_TEMPLATE;
                    } elseif (!empty($modulePart)) {
                        // Front, back, pdf or email office template,
                        // form of $module_part is [bo|fo|pdf|email].subdir-name
                        [$type, $subdir] = explode('.', (string) $modulePart);

                        switch ($type) {
                            case 'bo':
                                $directory = $module->getAbsoluteBackOfficeTemplatePath($subdir);
                                $domain = $module->getBackOfficeTemplateTranslationDomain($subdir);
                                $i18nDirectory = $module->getAbsoluteBackOfficeI18nTemplatePath($subdir);
                                break;
                            case 'fo':
                                $directory = $module->getAbsoluteFrontOfficeTemplatePath($subdir);
                                $domain = $module->getFrontOfficeTemplateTranslationDomain($subdir);
                                $i18nDirectory = $module->getAbsoluteFrontOfficeI18nTemplatePath($subdir);
                                break;
                            case 'email':
                                $directory = $module->getAbsoluteEmailTemplatePath($subdir);
                                $domain = $module->getEmailTemplateTranslationDomain($subdir);
                                $i18nDirectory = $module->getAbsoluteEmailI18nTemplatePath($subdir);
                                break;
                            case 'pdf':
                                $directory = $module->getAbsolutePdfTemplatePath($subdir);
                                $domain = $module->getPdfTemplateTranslationDomain($subdir);
                                $i18nDirectory = $module->getAbsolutePdfI18nTemplatePath($subdir);
                                break;
                            default:
                                throw new InvalidArgumentException(sprintf("Undefined module template type: '%s'.", $type));
                        }

                        $walkMode = TranslationEvent::WALK_MODE_TEMPLATE;
                    }

                    // Modules translations files are in the cache, and are not always
                    // updated. Force a reload of the files to get last changes.
                    if ($domain !== '' && $domain !== '0') {
                        $this->loadTranslation($i18nDirectory, $domain);
                    }

                    // List front and back office templates defined by this module
                    $templateArguments['back_office_templates'] =
                        implode(',', $this->getModuleTemplateNames($templateHelper, $module, TemplateDefinition::BACK_OFFICE));

                    $templateArguments['front_office_templates'] =
                        implode(',', $this->getModuleTemplateNames($templateHelper, $module, TemplateDefinition::FRONT_OFFICE));

                    $templateArguments['email_templates'] =
                        implode(',', $this->getModuleTemplateNames($templateHelper, $module, TemplateDefinition::EMAIL));

                    $templateArguments['pdf_templates'] =
                        implode(',', $this->getModuleTemplateNames($templateHelper, $module, TemplateDefinition::PDF));

                    // Check if we have admin-include files
                    try {
                        $finder = Finder::create()
                            ->files()
                            ->depth(0)
                            ->in($module->getAbsoluteAdminIncludesPath())
                            ->name('/\.html$/i')
                        ;

                        $hasAdminIncludes = $finder->count() > 0;
                    } catch (InvalidArgumentException) {
                        $hasAdminIncludes = false;
                    }

                    $templateArguments['has_admin_includes'] = $hasAdminIncludes;

                    break;
                    // Thelia Core
                case 'co':
                    $directory = THELIA_LIB;
                    $domain = 'core';
                    $i18nDirectory = THELIA_LIB.'Config'.DS.'I18n';
                    $walkMode = TranslationEvent::WALK_MODE_PHP;
                    break;
                    // Thelia Install
                case 'in':
                    $directory = THELIA_SETUP_DIRECTORY;
                    $domain = 'install';
                    $i18nDirectory = THELIA_SETUP_DIRECTORY.'I18n';
                    $walkMode = TranslationEvent::WALK_MODE_TEMPLATE;
                    // resources not loaded by default
                    $this->loadTranslation($i18nDirectory, $domain);
                    break;
                    // Thelia Install wizard
                case 'wi':
                    $directory = THELIA_SETUP_WIZARD_DIRECTORY;
                    $domain = 'wizard';
                    $i18nDirectory = THELIA_SETUP_WIZARD_DIRECTORY.'I18n';
                    $walkMode = TranslationEvent::WALK_MODE_PHP;
                    // resources not loaded by default
                    $this->loadTranslation($i18nDirectory, $domain);
                    break;
                    // Front-office template
                case 'fo':
                    $template = new TemplateDefinition($itemName, TemplateDefinition::FRONT_OFFICE);
                    break;
                    // Back-office template
                case 'bo':
                    $template = new TemplateDefinition($itemName, TemplateDefinition::BACK_OFFICE);
                    break;
                    // PDF templates
                case 'pf':
                    $template = new TemplateDefinition($itemName, TemplateDefinition::PDF);
                    break;
                    // Email templates
                case 'ma':
                    $template = new TemplateDefinition($itemName, TemplateDefinition::EMAIL);
                    break;
            }

            if ($template) {
                $directory = $template->getAbsolutePath();

                $i18nDirectory = $template->getAbsoluteI18nPath();

                $domain = $template->getTranslationDomain();

                // Load translations files if this template is not the current template
                // as it is not loaded in Thelia.php
                if (!$templateHelper->isActive($template)) {
                    $this->loadTranslation($i18nDirectory, $domain);
                }
            }

            // Load strings to translate
            if ($directory && ($domain !== '' && $domain !== '0')) {
                // Save the string set, if the form was submitted
                if ($i18nDirectory) {
                    $save_mode = $request->get('save_mode', false);

                    if ($save_mode !== false) {
                        $texts = $request->get('text', []);

                        if (!empty($texts)) {
                            $event = TranslationEvent::createWriteFileEvent(
                                sprintf('%s'.DS.'%s.php', $i18nDirectory, $this->getCurrentEditionLocale()),
                                $texts,
                                $request->get('translation', []),
                                true
                            );

                            $event
                                ->setDomain($domain)
                                ->setLocale($this->getCurrentEditionLocale())
                                ->setCustomFallbackStrings($request->get('translation_custom', []))
                                ->setGlobalFallbackStrings($request->get('translation_global', []));

                            $eventDispatcher->dispatch($event, TheliaEvents::TRANSLATION_WRITE_FILE);

                            if ($save_mode == 'stay') {
                                return $this->generateRedirectFromRoute(
                                    'admin.configuration.translations',
                                    $templateArguments
                                );
                            }

                            return $this->generateRedirect(URL::getInstance()->adminViewUrl('configuration'));
                        }
                    }
                }

                // Load strings
                $event = TranslationEvent::createGetStringsEvent(
                    $directory,
                    $walkMode,
                    $this->getCurrentEditionLocale(),
                    $domain
                );

                $eventDispatcher->dispatch($event, TheliaEvents::TRANSLATION_GET_STRINGS);

                // Estimate number of fields, and compare to php ini max_input_vars
                $stringsCount = $event->getTranslatableStringCount() * 4 + 6;

                if ($stringsCount > \ini_get('max_input_vars')) {
                    $templateArguments['max_input_vars_warning'] = true;
                    $templateArguments['required_max_input_vars'] = $stringsCount;
                    $templateArguments['current_max_input_vars'] = \ini_get('max_input_vars');
                } else {
                    $templateArguments['all_strings'] = $event->getTranslatableStrings();
                }

                $templateArguments['is_writable'] = $this->checkWritableI18nDirectory(THELIA_LOCAL_DIR.'I18n');
            }
        }

        return $this->render('translations', $templateArguments);
    }

    /**
     * Check if a directory is writable or if the parent directory is writable.
     *
     * @param string $dir the directory to test
     *
     * @return bool return true if the directory is writable otr if the parent dir is writable
     */
    public function checkWritableI18nDirectory($dir): bool
    {
        if (file_exists($dir)) {
            return is_writable($dir);
        }

        $parentDir = \dirname($dir);

        return file_exists($parentDir) && is_writable($parentDir);
    }

    public function defaultAction(
        Request $request,
        TemplateHelperInterface $templateHelper,
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator
    ): Response|RedirectResponse {
        if (($response = $this->checkAuth(AdminResources::TRANSLATIONS, [], AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        return $this->renderTemplate($request, $templateHelper, $eventDispatcher, $translator);
    }

    public function updateAction(
        Request $request,
        TemplateHelperInterface $templateHelper,
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator
    ): Response|RedirectResponse {
        if (($response = $this->checkAuth(AdminResources::LANGUAGE, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        return $this->renderTemplate($request, $templateHelper, $eventDispatcher, $translator);
    }

    private function loadTranslation(string|bool $directory, string $domain): void
    {
        try {
            $finder = Finder::create()
                ->files()
                ->depth(0)
                ->in($directory);

            /** @var DirectoryIterator $file */
            foreach ($finder as $file) {
                [$locale, $format] = explode('.', $file->getBaseName(), 2);

                Translator::getInstance()->addResource($format, $file->getPathname(), $locale, $domain);
            }
        } catch (InvalidArgumentException) {
            // Ignore missing I18n directories
        }
    }
}
