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

namespace HookAnalytics\Controller;
use HookAnalytics\HookAnalytics;
use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

/**
 * Class Configuration
 * @package HookSocial\Controller
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class Configuration extends BaseAdminController {
    public function saveAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ['hookanalytics'], AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(\HookAnalytics\Form\Configuration::class);
        $resp = [
            "error" =>  0,
            "message" => ""
        ];
        $response=null;
        $lang = $this->getSession()->get("thelia.admin.edition.lang");
        try {
            $vform = $this->validateForm($form);
            $data = $vform->getData();

            HookAnalytics::setConfigValue("hookanalytics_trackingcode", $data["trackingcode"], $lang->getLocale(), true);

        } catch (\Exception $e) {
            $resp["error"] = 1;
            $resp["message"] = $e->getMessage();
        }

        return JsonResponse::create($resp);
    }
}
