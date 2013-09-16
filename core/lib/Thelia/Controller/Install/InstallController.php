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
use Thelia\Install\CheckPermission;

/**
 * Class InstallController
 *
 * @package Thelia\Controller\Install
 * @author  Manuel Raynaud <mraynaud@openstudio.fr>
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class InstallController extends BaseInstallController
{
    public function indexAction()
    {
        //$this->verifyStep(1);

        $this->getSession()->set("step", 1);

        return $this->render("index.html");
    }

    /**
     * Integration tests
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function checkPermissionAction()
    {
        $args = array();
        var_dump('step2');
        //$this->verifyStep(2);

        $checkPermission = new CheckPermission(true, $this->getTranslator());
        $args['isValid'] = $isValid = $checkPermission->exec();
        $args['validationMessages'] = $checkPermission->getValidationMessages();

        $this->getSession()->set("step", 2);

        return $this->render("step-2.html", $args);
    }

    /**
     * Database connexion tests
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function databaseConnection()
    {
        var_dump('step 3 bis');
    }

    /**
     * Database connexion tests
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function databaseConnectionAction()
    {
        var_dump('step 3');
        exit();
        //$this->verifyStep(2);

        //$permission = new CheckPermission();

        $this->getSession()->set("step", 3);

        return $this->render("step-3.html");
    }

    /**
     * Database selection
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function databaseSelectionAction()
    {
        //$this->verifyStep(2);

        //$permission = new CheckPermission();

        $this->getSession()->set("step", 4);

        return $this->render("step-4.html");
    }

    /**
     * Set general informations
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function generalInformationAction()
    {
        //$this->verifyStep(2);

        //$permission = new CheckPermission();

        $this->getSession()->set("step", 5);

        return $this->render("step-5.html");
    }

    /**
     * Display Thanks page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function thanksAction()
    {
        //$this->verifyStep(2);

        //$permission = new CheckPermission();

        $this->getSession()->set("step", 6);

        return $this->render("thanks.html");
    }

    /**
     * Verify each steps and redirect if one step has already been passed
     *
     * @param int $step Step number
     *
     * @return bool
     */
    protected function verifyStep($step)
    {
        $session = $this->getSession();

        if ($session->has("step")) {
            $sessionStep = $session->get("step");
        } else {
           return true;
        }

        switch ($step) {
            case "1" :
                if ($sessionStep > 1) {
                    $this->redirect("/install/step/2");
                }
                break;
        }
    }
}
