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

namespace HookSocial\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

/**
 * Class Configuration
 * @package HookSocial\Controller
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class Configuration extends BaseAdminController
{
    public function saveAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ['hooksocial'], AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(Configuration::class);
        $resp = [
            "error" =>  0,
            "message" => ""
        ];
        $response=null;

        try {
            $vform = $this->validateForm($form);
            $data = $vform->getData();

            foreach ($data as $name => $value) {
                if (! $form->isTemplateDefinedHiddenFieldName($name)) {
                    ConfigQuery::write("hooksocial_" . $name, $value, false, true);
                }

                Tlog::getInstance()->debug(sprintf("%s => %s", $name, $value));
            }
        } catch (\Exception $e) {
            $resp["error"] = 1;
            $resp["message"] = $e->getMessage();
        }

        return JsonResponse::create($resp);
    }
}
