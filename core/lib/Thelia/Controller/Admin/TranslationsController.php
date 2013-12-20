<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Controller\Admin;


use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\AccessManager;
use Thelia\Form\SystemLogConfigurationForm;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Core\Template\TemplateHelper;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Tools\URL;
/**
 * Class LangController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class TranslationsController extends BaseAdminController
{
    protected function renderTemplate()
    {
        // Find modules
        $modules = ModuleQuery::create()->joinI18n($this->getCurrentEditionLocale())->orderByPosition()->find();

        // Get related strings, if all input data are here
        $item_to_translate = $this->getRequest()->get('item_to_translate');

        $item_id = $this->getRequest()->get('item_id', '');

        $all_strings = array();

        $template = $directory = $i18n_directory = false;

        $walkMode = TemplateHelper::WALK_MODE_TEMPLATE;

        $templateArguments = array(
                'item_to_translate'             => $item_to_translate,
                'item_id'                       => $item_id,
                'view_missing_traductions_only' => $this->getRequest()->get('view_missing_traductions_only', 0),
                'max_input_vars_warning'        => false,
        );

        // Find the i18n directory, and the directory to examine.

        if (! empty($item_id) || $item_to_translate == 'co') {

            switch($item_to_translate) {

                case 'mo' :
                    if (null !== $module = ModuleQuery::create()->findPk($item_id)) {
                        $directory = $module->getAbsoluteBaseDir();
                        $i18n_directory = $module->getAbsoluteI18nPath();
                        $walkMode = TemplateHelper::WALK_MODE_PHP;
                    }
                    break;

                case 'co' :
                    $directory = THELIA_ROOT . 'core/lib/Thelia';
                    $i18n_directory = THELIA_ROOT . 'core/lib/Thelia/Config/I18n';
                    $walkMode = TemplateHelper::WALK_MODE_PHP;
                break;

                case 'fo' :
                    $template = new TemplateDefinition($item_id, TemplateDefinition::FRONT_OFFICE);
                break;

                case 'bo' :
                    $template = new TemplateDefinition($item_id, TemplateDefinition::BACK_OFFICE);
                break;

                case 'pf' :
                    $template = new TemplateDefinition($item_id, TemplateDefinition::PDF);
                break;

                case 'ma' :
                    $template = new TemplateDefinition($item_id, TemplateDefinition::EMAIL);
                break;
            }

            if ($template) {
                $directory = $template->getAbsolutePath();
                $i18n_directory = $template->getAbsoluteI18nPath();
            }

            // Load strings to translate
            if ($directory) {

                // Save the string set, if the form was submitted
                if ($i18n_directory) {

                    $save_mode = $this->getRequest()->get('save_mode', false);

                    if ($save_mode !== false) {

                        $texts = $this->getRequest()->get('text', array());

                        if (! empty($texts)) {

                            $file = sprintf("%s/%s.php", $i18n_directory, $this->getCurrentEditionLocale());

                            $translations = $this->getRequest()->get('translation', array());

                            TemplateHelper::getInstance()->write_translation($file, $texts, $translations);

                            if ($save_mode == 'stay')
                                $this->redirectToRoute("admin.configuration.translations", $templateArguments);
                            else
                                $this->redirect(URL::getInstance()->adminViewUrl('configuration'));
                        }
                    }
                }

                // Load strings
                $stringsCount = TemplateHelper::getInstance()->walkDir(
                        $directory,
                        $walkMode,
                        $this->getTranslator(),
                        $this->getCurrentEditionLocale(),
                        $all_strings
                );

                // Estimate number of fields, and compare to php ini max_input_vars
                $stringsCount = $stringsCount * 2 + 6;

                if ($stringsCount > ini_get('max_input_vars')) {
                   $templateArguments['max_input_vars_warning']  = true;
                   $templateArguments['required_max_input_vars'] = $stringsCount;
                   $templateArguments['current_max_input_vars']  = ini_get('max_input_vars');
                }
                else {
                    $templateArguments['all_strings'] = $all_strings;
                }
            }
        }

        return $this->render('translations', $templateArguments);
    }

    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::TRANSLATIONS, array(), AccessManager::VIEW)) return $response;

        return $this->renderTemplate();
    }

    public function updateAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, array(), AccessManager::UPDATE)) return $response;

        return $this->renderTemplate();
    }
}
