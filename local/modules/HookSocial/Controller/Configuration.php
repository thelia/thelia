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

        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), array('hooksocial'), AccessManager::UPDATE)) {
            return $response;
        }

        $form = new \HookSocial\Form\Configuration($this->getRequest());
        $resp = array(
            "error" =>  0,
            "message" => ""
        );
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