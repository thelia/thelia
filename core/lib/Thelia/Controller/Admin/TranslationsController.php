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

        TemplateHelper::getInstance()->getList(TemplateDefinition::BACK_OFFICE);
        TemplateHelper::getInstance()->getList(TemplateDefinition::PDF);
        TemplateHelper::getInstance()->getList(TemplateDefinition::FRONT_OFFICE);

        // Get related strings, if all input data are here
        $item_to_translate = $this->getRequest()->get('item_to_translate');

        $item_id = $this->getRequest()->get('item_id', '');

        $all_strings = $translated_strings = array();

        $template = $directory = $i18n_directory = false;

        if (! empty($item_id)) {

            switch($item_to_translate) {

                case 'mo' :
                    if (null !== $module = ModuleQuery::create()->findPk($item_id)) {
                        $directory = THELIA_MODULE_DIR . $module->getBaseDir();
                        $i18n_directory = THELIA_TEMPLATE_DIR . $template->getI18nPath();
                    }
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
            }

            if ($template) {
                $directory = THELIA_TEMPLATE_DIR . $template->getPath();
                $i18n_directory = THELIA_TEMPLATE_DIR . $template->getI18nPath();
            }

            if ($directory) {

                // Load strings
                $this->walkDir($directory, $all_strings);

                // Load translated strings
                if ($i18n_directory) {
                    $locale = $this->getCurrentEditionLocale();
                }
            }
        }

        return $this->render('translations', array(
                'item_to_translate'             => $item_to_translate,
                'item_id'                       => $item_id,
                'all_strings'                   => $all_strings,
                'translated_strings'            => $translated_strings,
                'view_missing_traductions_only' => $this->getRequest()->get('view_missing_traductions_only', 0)
        ));
    }

    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::TRANSLATIONS, AccessManager::VIEW)) return $response;

        return $this->renderTemplate();
    }

    public function updateAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, AccessManager::UPDATE)) return $response;

        return $this->renderTemplate();
    }

    protected function normalize_path($path)
    {
        $path =
        str_replace(
                str_replace('\\', '/', THELIA_ROOT),
                '',
                str_replace('\\', '/', realpath($path))
        );

        if ($path[0] == '/') $path = substr($path, 1);

        return $path;
    }

    protected function walkDir($directory, &$strings) {
        try {
            //echo "walking in $directory<br />";

            foreach (new \DirectoryIterator($directory) as $fileInfo) {

                if ($fileInfo->isDot()) continue;

                if ($fileInfo->isDir()) $this->walkDir($fileInfo->getPathName(), $strings);

                if ($fileInfo->isFile()) {

                    $ext = $fileInfo->getExtension();

                    if ($ext == 'html' || $ext == 'tpl' || $ext == 'xml') {

                        if ($content = file_get_contents($fileInfo->getPathName())) {

                            $short_path = $this->normalize_path($fileInfo->getPathName());

                            // echo "   examining $short_path\n";

                            $matches = array();

                            if (preg_match_all('/{intl[\s]l=((?<![\\\\])[\'"])((?:.(?!(?<![\\\\])\1))*.?)\1/', $content, $matches)) {

                                // print_r($matches[2]);

                                foreach($matches[2] as $match) {

                                    $hash = md5($match);

                                    if (isset($strings[$hash]))
                                    {
                                        if (! in_array($short_path, $strings[$hash]['files']))
                                        {
                                            $strings[$hash]['files'][] = $short_path;
                                        }
                                    }
                                    else
                                        $strings[$hash] = array(
                                                'files'   => array($short_path),
                                                'chaine'  => $match,
                                                'dollar'  => strstr($match, '$') !== false);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\UnexpectedValueException $ex) {
            echo $ex;
        }
    }

}
