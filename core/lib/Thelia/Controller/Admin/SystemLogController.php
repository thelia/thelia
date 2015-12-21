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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

/**
 * Class LangController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class SystemLogController extends BaseAdminController
{
    protected function renderTemplate()
    {
        $destinations = array();

        $destination_directories = Tlog::getInstance()->getDestinationsDirectories();

        foreach ($destination_directories as $dir) {
            $this->loadDefinedDestinations($dir, $destinations);
        }

        $active_destinations = explode(";", ConfigQuery::read(Tlog::VAR_DESTINATIONS, Tlog::DEFAUT_DESTINATIONS));

        return $this->render(
            'system-logs',
            array(
                'ip_address' => $this->getRequest()->getClientIp(),
                'destinations' => $destinations,
                'active_destinations' => $active_destinations
            )
        );
    }

    protected function loadDefinedDestinations($directory, &$destinations)
    {
        try {
            foreach (new \DirectoryIterator($directory) as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }

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

    /**
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function defaultAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::SYSTEM_LOG, array(), AccessManager::VIEW)) {
            return $response;
        }

        // Hydrate the general configuration form
        $systemLogForm = $this->createForm(AdminForm::SYSTEM_LOG_CONFIGURATION, 'form', array(
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
        if (null !== $response = $this->checkAuth(AdminResources::SYSTEM_LOG, array(), AccessManager::UPDATE)) {
            return $response;
        }

        $error_msg = false;

        $systemLogForm = $this->createForm(AdminForm::SYSTEM_LOG_CONFIGURATION);

        try {
            $form = $this->validateForm($systemLogForm);

            $data = $form->getData();

            ConfigQuery::write(Tlog::VAR_LEVEL, $data['level']);
            ConfigQuery::write(Tlog::VAR_PREFIXE, $data['format']);
            ConfigQuery::write(Tlog::VAR_SHOW_REDIRECT, $data['show_redirections']);
            ConfigQuery::write(Tlog::VAR_FILES, $data['files']);
            ConfigQuery::write(Tlog::VAR_IP, $data['ip_addresses']);

            // Save destination configuration
            $destinations = $this->getRequest()->get('destinations');
            $configs = $this->getRequest()->get('config');

            $active_destinations = array();

            foreach ($destinations as $classname => $destination) {
                if (isset($destination['active'])) {
                    $active_destinations[] = $destination['classname'];
                }

                if (isset($configs[$classname])) {
                    // Update destinations configuration
                    foreach ($configs[$classname] as $var => $value) {
                        ConfigQuery::write($var, $value, true, true);
                    }
                }
            }

            // Update active destinations list
            ConfigQuery::write(Tlog::VAR_DESTINATIONS, implode(';', $active_destinations));

            $this->adminLogAppend(
                AdminResources::SYSTEM_LOG,
                AccessManager::UPDATE,
                "System log configuration changed"
            );

            $response = $this->generateRedirectFromRoute('admin.configuration.system-logs.default');
        } catch (\Exception $ex) {
            $error_msg = $ex->getMessage();
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("System log configuration failed."),
                $error_msg,
                $systemLogForm,
                $ex
            );

            $response = $this->renderTemplate();
        }

        return $response;
    }
}
