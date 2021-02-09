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

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\Security\AccessManager;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Tools\URL;

class AdminController extends BaseAdminController
{
    const RESOURCE_CODE = "admin.home";

    /**
     * @Route("/admin", name="admin")
     * @Route("/admin/home", name="admin_home")
     */
    public function indexAction(TaxEngine $taxEngine)
    {
        if (!$this->getSecurityContext()->hasAdminUser()) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl($this->getRoute("admin.login")));
        }

        if (null !== $response = $this->checkAuth(self::RESOURCE_CODE, array(), AccessManager::VIEW)) {
            return $response;
        }

        return $this->render("home");
    }
}
