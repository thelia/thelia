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
/**
 * Class LangController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class SystemLogController extends BaseAdminController
{

    protected function renderTemplate()
    {
        $destinations = array();

        $destination_directories = Tlog::getInstance()->getDestinationsDirectories();

        foreach($destination_directories as $dir) {
            $this->loadDefinedDestinations($dir, $destinations);
        }

        $active_destinations = explode(";", ConfigQuery::read(Tlog::VAR_DESTINATIONS, Tlog::DEFAUT_DESTINATIONS));

        return $this->render('system-logs',
                array(
                    'ip_address' => $this->getRequest()->getClientIp(),
                    'destinations' => $destinations,
                    'active_destinations' => $active_destinations
                )
        );
    }

    protected function loadDefinedDestinations($directory, &$destinations) {

        try {
            foreach (new \DirectoryIterator($directory) as $fileInfo) {

                if ($fileInfo->isDot()) continue;

                $matches = array();

                if (preg_match("/([^\.]+)\.php/", $fileInfo->getFilename(), $matches)) {

                    $classname = $matches[1];

                    if (! isset($destinations[$classname])) {

                        $full_class_name = "Thelia\\Log\\Destination\\".$classname;

                        $destinations[$classname] = new $full_class_name();
                    }
                }
            }
        } catch (\UnexpectedValueException $ex) {
            // Directory does no exists -> Nothing to do
        }
    }

    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::SYSTEM_LOG, AccessManager::VIEW)) return $response;

        /*
        const VAR_LEVEL 		= "tlog_level";
        const VAR_DESTINATIONS 	= "tlog_destinations";
        const VAR_PREFIXE 		= "tlog_prefix";
        const VAR_FILES 		= "tlog_files";
        const VAR_IP                = "tlog_ip";
        const VAR_SHOW_REDIRECT     = "tlog_show_redirect";

        const DEFAULT_LEVEL     	= self::DEBUG;
        const DEFAUT_DESTINATIONS   = "Thelia\Log\Destination\TlogDestinationFile";
        const DEFAUT_PREFIXE 	= "#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: ";
        const DEFAUT_FILES 		= "*";
        const DEFAUT_IP 		= "";
        const DEFAUT_SHOW_REDIRECT  = 0;

        */

        // Hydrate the general configuration form
        $systemLogForm = new SystemLogConfigurationForm($this->getRequest(), 'form', array(
            'level'             => ConfigQuery::read(Tlog::VAR_LEVEL, Tlog::DEFAULT_LEVEL),
            'format'            => ConfigQuery::read(Tlog::VAR_PREFIXE, Tlog::DEFAUT_PREFIXE),
            'show_redirections' => ConfigQuery::read(Tlog::VAR_SHOW_REDIRECT, Tlog::DEFAUT_SHOW_REDIRECT),
            'files'             => ConfigQuery::read(Tlog::VAR_FILES, Tlog::DEFAUT_FILES),
            'ip_addresses'      => ConfigQuery::read(Tlog::VAR_IP, Tlog::DEFAUT_IP),
        ));

        $this->getParserContext()->addForm($systemLogForm);

        return $this->renderTemplate();
    }

    public function saveAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::LANGUAGE, AccessManager::UPDATE)) return $response;

        $error_msg = false;

        $systemLogForm = new SystemLogConfigurationForm($this->getRequest());

        try {
            $form = $this->validateForm($systemLogForm);

            $data = $form->getData();

            ConfigQuery::write(Tlog::VAR_LEVEL         , $data['level']);
            ConfigQuery::write(Tlog::VAR_PREFIXE       , $data['format']);
            ConfigQuery::write(Tlog::VAR_SHOW_REDIRECT , $data['show_redirections']);
            ConfigQuery::write(Tlog::VAR_FILES         , $data['files']);
            ConfigQuery::write(Tlog::VAR_IP            , $data['ip_addresses']);

            // Save destination configuration
            $destinations = $this->getRequest()->get('destinations');
            $configs = $this->getRequest()->get('config');

            $active_destinations = array();

            foreach($destinations as $classname => $destination) {

                if (isset($destination['active'])) {
                    $active_destinations[] = $destination['classname'];
                }

                if (isset($configs[$classname])) {

                    // Update destinations configuration
                    foreach($configs[$classname] as $var => $value) {
                        ConfigQuery::write($var, $value, true, true);
                    }
                }
            }

            // Update active destinations list
            ConfigQuery::write(Tlog::VAR_DESTINATIONS, implode(';', $active_destinations));

            $this->adminLogAppend(AdminResources::SYSTEM_LOG, AccessManager::UPDATE, "System log configuration changed");

            $this->redirectToRoute('admin.configuration.system-logs.default');

        } catch (\Exception $ex) {
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
                $this->getTranslator()->trans("System log configuration failed."),
                $error_msg,
                $systemLogForm,
                $ex
        );

        return $this->renderTemplate();
    }
}
