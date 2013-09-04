<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Controller\Install;
use Thelia\Install\BaseInstall;
use Thelia\Install\CheckPermission;

/**
 * Class InstallController
 * @package Thelia\Controller\Install
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class InstallController extends BaseInstallController {

    public function index()
    {
        $this->verifyStep(1);

        $this->getSession()->set("step", 1);

        $this->render("index.html");
    }

    public function checkPermission()
    {
        $this->verifyStep(2);

        $permission = new CheckPermission();
    }

    protected function verifyStep($step)
    {
        $session = $this->getSession();

        if ($session->has("step")) {
            $sessionStep = $session->get("step");
        } else {
           return true;
        }

        switch($step) {
            case "1" :
                if ($sessionStep > 1) {
                    $this->redirect("/install/step/2");
                }
                break;
        }
    }
}