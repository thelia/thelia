<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Controller\Admin;

use Symfony\Component\Finder\Finder;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Core\Template\TemplateHelper;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Tools\URL;

/**
 * Class LangController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <manu@thelia.net>
 */
class TranslationsController extends BaseAdminController
{
    /**
     * @param  string                    $item_name the modume code
     * @return Module                    the module object
     * @throws \InvalidArgumentException if module was not found
     */
    protected function getModule($item_name)
    {
        if (null !== $module = ModuleQuery::create()->findPk($item_name)) {
            return $module;
        }

        throw new \InvalidArgumentException(
            $this->getTranslator()->trans("No module found for code '%item'", ['%item' => $item_name])
        );
    }

    protected function getModuleTemplateNames(Module $module, $template_type)
    {
        $templates =
            TemplateHelper::getInstance()->getList(
                $template_type,
                $module->getAbsoluteTemplateBasePath()
            );

        $names = [];

        foreach ($templates as $template) {
            $names[] = $template->getName();
        }

        return $names;
    }

    protected function renderTemplate()
    {
        // Get related strings, if all input data are here
        $item_to_translate = $this->getRequest()->get('item_to_translate');

        $item_name = $this->getRequest()->get('item_name', '');

        if ($item_to_translate == 'mo' && ! empty($item_name)) {
            $module_part = $this->getRequest()->get('module_part', '');
        } else {
            $module_part = false;
        }

        $all_strings = array();

        $template = $directory = $i18n_directory = false;

        $walkMode = TemplateHelper::WALK_MODE_TEMPLATE;

        $templateArguments = array(
                'item_to_translate'             => $item_to_translate,
                'item_name'                     => $item_name,
                'module_part'                   => $module_part,
                'view_missing_traductions_only' => $this->getRequest()->get('view_missing_traductions_only', 1),
                'max_input_vars_warning'        => false,
        );

        // Find the i18n directory, and the directory to examine.

        if (! empty($item_name) || $item_to_translate == 'co') {
            switch ($item_to_translate) {

                // Module core
                case 'mo':
                    $module = $this->getModule($item_name);

                    if ($module_part == 'core') {
                        $directory = $module->getAbsoluteBaseDir();
                        $domain = $module->getTranslationDomain();
                        $i18n_directory = $module->getAbsoluteI18nPath();
                        $walkMode = TemplateHelper::WALK_MODE_PHP;
                    } elseif ($module_part == 'admin-includes') {
                        $directory = $module->getAbsoluteAdminIncludesPath();
                        $domain = $module->getAdminIncludesTranslationDomain();
                        $i18n_directory = $module->getAbsoluteAdminIncludesI18nPath();
                        $walkMode = TemplateHelper::WALK_MODE_TEMPLATE;
                    } elseif (! empty($module_part)) {
                        // Front or back office template, form of $module_part is [bo|fo].subdir-name
                        list($type, $subdir) = explode('.', $module_part);

                        if ($type == 'bo') {
                            $directory = $module->getAbsoluteBackOfficeTemplatePath($subdir);
                            $domain = $module->getBackOfficeTemplateTranslationDomain($subdir);
                            $i18n_directory = $module->getAbsoluteBackOfficeI18nTemplatePath($subdir);
                        } elseif ($type == 'fo') {
                            $directory = $module->getAbsoluteFrontOfficeTemplatePath($subdir);
                            $domain = $module->getFrontOfficeTemplateTranslationDomain($subdir);
                            $i18n_directory = $module->getAbsoluteFrontOfficeI18nTemplatePath($subdir);
                        } else {
                            throw new \InvalidArgumentException("Undefined module template type: '$type'.");
                        }

                        $walkMode = TemplateHelper::WALK_MODE_TEMPLATE;
                    }

                    // Modules translations files are in the cache, and are not always
                    // updated. Force a reload of the files to get last changes.
                    if (! empty($domain)) {
                        $this->loadTranslation($i18n_directory, $domain);
                    }

                    // List front and back office templates defined by this module
                    $templateArguments['back_office_templates'] =
                        implode(',', $this->getModuleTemplateNames($module, TemplateDefinition::BACK_OFFICE));

                    $templateArguments['front_office_templates'] =
                        implode(',', $this->getModuleTemplateNames($module, TemplateDefinition::FRONT_OFFICE));

                    // Check if we have admin-include files
                    try {
                        $finder = Finder::create()
                                    ->files()
                                    ->depth(0)
                                    ->in($module->getAbsoluteAdminIncludesPath())
                                    ->name('/\.html$/i')
                        ;

                        $hasAdminIncludes = $finder->count() > 0;
                    } catch (\InvalidArgumentException $ex) {
                        $hasAdminIncludes = false;
                    }

                    $templateArguments['has_admin_includes'] = $hasAdminIncludes;

                    break;

                // Thelia Core
                case 'co':
                    $directory = THELIA_ROOT . 'core/lib/Thelia';
                    $domain = 'core';
                    $i18n_directory = THELIA_ROOT . 'core/lib/Thelia/Config/I18n';
                    $walkMode = TemplateHelper::WALK_MODE_PHP;
                    break;

                // Front-office template
                case 'fo':
                    $template = new TemplateDefinition($item_name, TemplateDefinition::FRONT_OFFICE);
                    break;

                // Back-office template
                case 'bo':
                    $template = new TemplateDefinition($item_name, TemplateDefinition::BACK_OFFICE);
                    break;

                // PDF templates
                case 'pf':
                    $template = new TemplateDefinition($item_name, TemplateDefinition::PDF);
                    break;

                // Email templates
                case 'ma':
                    $template = new TemplateDefinition($item_name, TemplateDefinition::EMAIL);
                    break;
            }

            if ($template) {
                $directory = $template->getAbsolutePath();

                $i18n_directory = $template->getAbsoluteI18nPath();

                $domain = $template->getTranslationDomain();

                // Load translations files is this template is not the current template
                // as it is not loaded in Thelia.php
                if (! TemplateHelper::getInstance()->isActive($template)) {
                    $this->loadTranslation($i18n_directory, $domain);
                }
            }

            // Load strings to translate
            if ($directory && ! empty($domain)) {
                // Save the string set, if the form was submitted
                if ($i18n_directory) {
                    $save_mode = $this->getRequest()->get('save_mode', false);

                    if ($save_mode !== false) {
                        $texts = $this->getRequest()->get('text', array());

                        if (! empty($texts)) {
                            $file = sprintf("%s".DS."%s.php", $i18n_directory, $this->getCurrentEditionLocale());

                            $translations = $this->getRequest()->get('translation', array());

                            TemplateHelper::getInstance()->writeTranslation($file, $texts, $translations, true);

                            if ($save_mode == 'stay') {
                                return $this->generateRedirectFromRoute("admin.configuration.translations", $templateArguments);
                            } else {
                                return $this->generateRedirect(URL::getInstance()->adminViewUrl('configuration'));
                            }
                        }
                    }
                }

                // Load strings
                $stringsCount = TemplateHelper::getInstance()->walkDir(
                    $directory,
                    $walkMode,
                    $this->getTranslator(),
                    $this->getCurrentEditionLocale(),
                    $domain,
                    $all_strings
                );

                // Estimate number of fields, and compare to php ini max_input_vars
                $stringsCount = $stringsCount * 2 + 6;

                if ($stringsCount > ini_get('max_input_vars')) {
                    $templateArguments['max_input_vars_warning']  = true;
                    $templateArguments['required_max_input_vars'] = $stringsCount;
                    $templateArguments['current_max_input_vars']  = ini_get('max_input_vars');
                } else {
                    $templateArguments['all_strings'] = $all_strings;
                }
            }
        }

        return $this->render('translations', $templateArguments);
    }

    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::TRANSLATIONS, array(), AccessManager::VIEW)) {
            return $response;
        }
        return $this->renderTemplate();
    }

    public function updateAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, array(), AccessManager::UPDATE)) {
            return $response;
        }
        return $this->renderTemplate();
    }

    private function loadTranslation($directory, $domain)
    {
        try {
            $finder = Finder::create()
                ->files()
                ->depth(0)
                ->in($directory);

            /** @var \DirectoryIterator $file */
            foreach ($finder as $file) {
                list($locale, $format) = explode('.', $file->getBaseName(), 2);

                Translator::getInstance()->addResource($format, $file->getPathname(), $locale, $domain);
            }
        } catch (\InvalidArgumentException $ex) {
            // Ignore missing I18n directories
        }
    }
}
