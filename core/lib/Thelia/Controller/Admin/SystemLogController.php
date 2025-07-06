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
use DirectoryIterator;
use Exception;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Definition\AdminForm;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use UnexpectedValueException;

/**
 * Class LangController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class SystemLogController extends BaseAdminController
{
    protected function renderTemplate(): Response
    {
        $destinations = [];

        $destination_directories = Tlog::getInstance()->getDestinationsDirectories();

        foreach ($destination_directories as $dir) {
            $this->loadDefinedDestinations($dir, $destinations);
        }

        $active_destinations = explode(';', (string) ConfigQuery::read(Tlog::VAR_DESTINATIONS, Tlog::DEFAUT_DESTINATIONS));

        return $this->render(
            'system-logs',
            [
                'ip_address' => $this->getRequest()->getClientIp(),
                'destinations' => $destinations,
                'active_destinations' => $active_destinations,
            ]
        );
    }

    protected function loadDefinedDestinations($directory, &$destinations): void
    {
        try {
            foreach (new DirectoryIterator($directory) as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }

                $matches = [];

                if (preg_match("/([^\.]+)\.php/", $fileInfo->getFilename(), $matches)) {
                    $classname = $matches[1];

                    if (!isset($destinations[$classname])) {
                        $full_class_name = 'Thelia\\Log\\Destination\\'.$classname;

                        $destinations[$classname] = new $full_class_name();
                    }
                }
            }
        } catch (UnexpectedValueException) {
            // Directory does no exists -> Nothing to do
        }
    }

    /**
     * @return mixed|Response
     */
    public function defaultAction()
    {
        if (($response = $this->checkAuth(AdminResources::SYSTEM_LOG, [], AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        // Hydrate the general configuration form
        $systemLogForm = $this->createForm(AdminForm::SYSTEM_LOG_CONFIGURATION, FormType::class, [
            'level' => ConfigQuery::read(Tlog::VAR_LEVEL, Tlog::DEFAULT_LEVEL),
            'format' => ConfigQuery::read(Tlog::VAR_PREFIXE, Tlog::DEFAUT_PREFIXE),
            'show_redirections' => ConfigQuery::read(Tlog::VAR_SHOW_REDIRECT, Tlog::DEFAUT_SHOW_REDIRECT),
            'files' => ConfigQuery::read(Tlog::VAR_FILES, Tlog::DEFAUT_FILES),
            'ip_addresses' => ConfigQuery::read(Tlog::VAR_IP, Tlog::DEFAUT_IP),
        ]);

        $this->getParserContext()->addForm($systemLogForm);

        return $this->renderTemplate();
    }

    public function saveAction(): Response|RedirectResponse
    {
        if (($response = $this->checkAuth(AdminResources::SYSTEM_LOG, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

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

            $active_destinations = [];

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
                'System log configuration changed'
            );

            $response = $this->generateRedirectFromRoute('admin.configuration.system-logs.default');
        } catch (Exception $exception) {
            $error_msg = $exception->getMessage();

            $this->setupFormErrorContext(
                $this->getTranslator()->trans('System log configuration failed.'),
                $error_msg,
                $systemLogForm,
                $exception
            );

            $response = $this->renderTemplate();
        }

        return $response;
    }
}
