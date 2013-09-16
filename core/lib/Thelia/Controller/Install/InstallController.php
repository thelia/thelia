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
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\InstallStep3Form;
use Thelia\Install\CheckDatabaseConnection;
use Thelia\Install\CheckPermission;
use Thelia\Install\Exception\AlreadyInstallException;
use Thelia\Install\Exception\InstallException;

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
        $args = array();
        try {
            //$this->verifyStep(1); // @todo implement
            $this->getSession()->set("step", 1);
        } catch (AlreadyInstallException $e) {
            $args['isAlreadyInstalled'] = true;
        }


        return $this->render("index.html");
    }

    /**
     * Integration tests
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function checkPermissionAction()
    {
        try {
            //$this->verifyStep(2); // @todo implement
            $checkPermission = new CheckPermission(true, $this->getTranslator());
            $args['isValid'] = $isValid = $checkPermission->exec();
            $args['validationMessages'] = $checkPermission->getValidationMessages();

            $this->getSession()->set("step", 2);
        } catch (AlreadyInstallException $e) {
            $args['isAlreadyInstalled'] = true;
        }
        $args = array();


        return $this->render("step-2.html", $args);
    }

    /**
     * Database connexion tests
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function databaseConnectionAction()
    {
        $args = array();

        try {
            //$this->verifyStep(2); // @todo implement

            if ($this->getRequest()->isMethod('POST')) {
                // Create the form from the request
                $step3Form = new InstallStep3Form($this->getRequest());

                $message = false;
                try {
                    // Check the form against constraints violations
                    $form = $this->validateForm($step3Form, 'POST');

                    // Get the form field values
                    $data = $form->getData();
                    var_dump('data', $data);

                    // @todo implement tests
                    try {
                        new CheckDatabaseConnection(
                            $data['host'],
                            $data['user'],
                            $data['password'],
                            $data['port'],
                            true,
                            $this->getTranslator()
                        );

                        $this->getSession()->set('install', array(
                                'host' =>$data['host'],
                                'user' => $data['user'],
                                'password' => $data['password'],
                                'port' => $data['port']
                            )
                        );
                    } catch (InstallException $e) {
                        $message = $this->getTranslator()->trans(
                            'Can\'t connect with these credentials to this server',
                            array(),
                            'install-wizard'
                        );
                    }

    //                $this->redirect(
    //                    str_replace(
    //                        '{id}',
    //                        $couponEvent->getCoupon()->getId(),
    //                        $creationForm->getSuccessUrl()
    //                    )
    //                );
                } catch (FormValidationException $e) {
                    // Invalid data entered
                    $message = $this->getTranslator()->trans(
                        'Please check your input:',
                        array(),
                        'install-wizard'
                    );

                } catch (\Exception $e) {
                    // Any other error
                    $message = $this->getTranslator()->trans(
                        'Sorry, an error occurred:',
                        array(),
                        'install-wizard'
                    );
                }

                if ($message !== false) {
                    // Mark the form as with error
                    $step3Form->setErrorMessage($message);

                    // Send the form and the error to the parser
                    $this->getParserContext()
                        ->addForm($step3Form)
                        ->setGeneralError($message);
                }
            }

            $this->getSession()->set("step", 3);

            $args['edit_language_locale'] = $this->getSession()->getLang()->getLocale();
            $args['formAction'] = 'install/step/3';

        } catch (AlreadyInstallException $e) {
            $args['isAlreadyInstalled'] = true;
        }

        return $this->render('step-3.html', $args);
    }

    /**
     * Database selection
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function databaseSelectionAction()
    {
        $args = array();
        try {

        } catch (AlreadyInstallException $e) {
            $args['isAlreadyInstalled'] = true;
        }

        //$this->verifyStep(2); // @todo implement

        //$permission = new CheckPermission();

        $this->getSession()->set("step", 4);

        return $this->render("step-4.html");
    }

    /**
     * Set general information
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function generalInformationAction()
    {
        $args = array();
        try {

        } catch (AlreadyInstallException $e) {
            $args['isAlreadyInstalled'] = true;
        }
        //$this->verifyStep(2); // @todo implement

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
        $args = array();
        try {

        } catch (AlreadyInstallException $e) {
            $args['isAlreadyInstalled'] = true;
        }
        //$this->verifyStep(2); // @todo implement

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

        return true;
    }
}
