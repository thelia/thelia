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
use Thelia\Tools\URL;

class AdminController extends BaseAdminController
{
    public function indexAction()
    {
        if (!$this->getSecurityContext()->hasAdminUser()) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl($this->getRoute("admin.login")));
        }

        return $this->render("home");
    }

    public function updateAction()
    {
        return $this->render("profile-edit");
    }
}
