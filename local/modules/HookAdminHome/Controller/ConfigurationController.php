<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace HookAdminHome\Controller;

use HookAdminHome\HookAdminHome;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\URL;

class ConfigurationController extends BaseAdminController
{
    /**
     * @Route("/admin/module/HookAdminHome/configure", name="admin.home.config", methods={"POST"})
     */
    public function editConfiguration()
    {
        if (null !== $response = $this->checkAuth(
                AdminResources::MODULE,
                [HookAdminHome::DOMAIN_NAME],
                AccessManager::UPDATE
            )) {
            return $response;
        }

        $form = $this->createForm('hookadminhome.config.form');
        $error_message = null;

        try {
            $validateForm = $this->validateForm($form);
            $data = $validateForm->getData();

            HookAdminHome::setConfigValue(HookAdminHome::ACTIVATE_NEWS, 0);
            HookAdminHome::setConfigValue(HookAdminHome::ACTIVATE_SALES, 0);
            HookAdminHome::setConfigValue(HookAdminHome::ACTIVATE_INFO, 0);
            HookAdminHome::setConfigValue(HookAdminHome::ACTIVATE_STATS, 0);

            if($data['enabled-news']){
                HookAdminHome::setConfigValue(HookAdminHome::ACTIVATE_NEWS, 1);
            }

            if($data['enabled-sales']){
                HookAdminHome::setConfigValue(HookAdminHome::ACTIVATE_SALES, 1);
            }

            if($data['enabled-info']){
                HookAdminHome::setConfigValue(HookAdminHome::ACTIVATE_INFO, 1);
            }

            if($data['enabled-stats']){
                HookAdminHome::setConfigValue(HookAdminHome::ACTIVATE_STATS, 1);
            }

            return RedirectResponse::create(URL::getInstance()->absoluteUrl('/admin/module/HookAdminHome'));
        } catch (FormValidationException $e) {
            $error_message = $this->createStandardFormValidationErrorMessage($e);
        }

        if (null !== $error_message) {
            $this->setupFormErrorContext(
                'configuration',
                $error_message,
                $form
            );
            $response = $this->render("module-configure", ['module_code' => 'HookAdminHome']);
        }
        return $response;
    }
}
