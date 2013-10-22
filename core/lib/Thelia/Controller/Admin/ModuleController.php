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

namespace Thelia\Controller\Admin;

use Thelia\Core\Event\AdminResources;

use Thelia\Core\Event\Module\ModuleDeleteEvent;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Module\ModuleManagement;

/**
 * Class ModuleController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ModuleController extends BaseAdminController
{
    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE_VIEW)) return $response;
        
        $modulemanagement = new ModuleManagement();
        $modulemanagement->updateModules();

        return $this->render("modules");
    }

    public function updateAction($module_id)
    {
        return $this->render("module-edit", array(
            "module_id" => $module_id
        ));
    }

    public function toggleActivationAction($module_id)
    {
        if (null !== $response = $this->checkAuth("admin.module.update")) return $response;
        $message = null;
        try {
            $event = new ModuleToggleActivationEvent($module_id);
            $this->dispatch(TheliaEvents::MODULE_TOGGLE_ACTIVATION, $event);

            if (null === $event->getModule()) {
                throw new \LogicException(
                    $this->getTranslator()->trans("No %obj was updated.", array('%obj' => 'Module')));
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            if ($message) {
                $response = $this->jsonResponse(json_encode(array(
                    "error" => $message
                )), 500);
            } else {
                $response = $this->nullResponse();
            }

        } else {
            $this->redirectToRoute('admin.module');
        }

        return $response;
    }

    public function deleteAction()
    {
        if (null !== $response = $this->checkAuth("admin.module.delete")) return $response;

        $message = null;
        try {

            $module_id = $this->getRequest()->get('module_id');

            $deleteEvent = new ModuleDeleteEvent($module_id);

            $this->dispatch(TheliaEvents::MODULE_DELETE, $deleteEvent);

            if($deleteEvent->hasModule() === false) {
                throw new \LogicException(
                    $this->getTranslator()->trans("No %obj was updated.", array('%obj' => 'Module')));
            }

        } catch (\Exception $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf("error during module removal : %s", $message));
            $message = $e->getMessage();
        }

        if($message) {
            return $this->render("modules", array(
                "error_message" => $message
            ));
        } else {
            $this->redirectToRoute('admin.module');
        }
    }
}
