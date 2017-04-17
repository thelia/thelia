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

namespace Colissimo\Controller;

use Colissimo\Colissimo;
use Colissimo\Model\Config\ColissimoConfigValue;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Tools\URL;

/**
 * Class FreeShipping
 * @package Colissimo\Controller
 * @author Thomas Arnaud <tarnaud@openstudio.fr>
 */
class FreeShipping extends BaseAdminController
{
    public function set()
    {
        $response = $this->checkAuth(AdminResources::MODULE, [Colissimo::DOMAIN_NAME], AccessManager::UPDATE);
        if (null !== $response) {
            return $response;
        }

        $form = $this->createForm('colissimo.freeshipping.form');


        try {
            $validateForm = $this->validateForm($form);
            $data = $validateForm->getData();

            Colissimo::setConfigValue(ColissimoConfigValue::FREE_SHIPPING, (int) ($data["freeshipping"]));
            return $this->redirectToConfigurationPage();

        } catch (\Exception $e) {
            $response = JsonResponse::create(array("error"=>$e->getMessage()), 500);
        }

        return $response;
    }

    /**
     * Redirect to the configuration page
     */
    protected function redirectToConfigurationPage()
    {
        return RedirectResponse::create(URL::getInstance()->absoluteUrl('/admin/module/Colissimo'));
    }
}
