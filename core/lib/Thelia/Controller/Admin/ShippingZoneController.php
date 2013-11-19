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
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Event\ShippingZone\ShippingZoneAddAreaEvent;
use Thelia\Core\Event\ShippingZone\ShippingZoneRemoveAreaEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\ShippingZone\ShippingZoneAddArea;
use Thelia\Form\ShippingZone\ShippingZoneRemoveArea;

/**
 * Class ShippingZoneController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ShippingZoneController extends BaseAdminController
{
    public $objectName = 'areaDeliveryModule';

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::SHIPPING_ZONE, array(), AccessManager::VIEW)) return $response;
        return $this->render("shipping-zones", array("display_shipping_zone" => 20));
    }

    public function updateAction($shipping_zones_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::SHIPPING_ZONE, array(), AccessManager::VIEW)) return $response;
        return $this->render("shipping-zones-edit", array(
            "shipping_zones_id" => $shipping_zones_id
        ));
    }

    /**
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function addArea()
    {
        if (null !== $response = $this->checkAuth(AdminResources::SHIPPING_ZONE, array(), AccessManager::UPDATE)) return $response;

        $shippingAreaForm = new ShippingZoneAddArea($this->getRequest());
        $error_msg = null;

        try {
            $form = $this->validateForm($shippingAreaForm);

            $event = new ShippingZoneAddAreaEvent(
                $form->get('area_id')->getData(),
                $form->get('shipping_zone_id')->getData()
            );

            $this->dispatch(TheliaEvents::SHIPPING_ZONE_ADD_AREA, $event);

            // Redirect to the success URL
            $this->redirect($shippingAreaForm->getSuccessUrl());

        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $this->getTranslator()->trans("%obj modification", array('%obj' => $this->objectName)), $error_msg, $shippingAreaForm);

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    public function removeArea()
    {
        if (null !== $response = $this->checkAuth(AdminResources::SHIPPING_ZONE, array(), AccessManager::UPDATE)) return $response;

        $shippingAreaForm = new ShippingZoneRemoveArea($this->getRequest());
        $error_msg = null;

        try {
            $form = $this->validateForm($shippingAreaForm);

            $event = new ShippingZoneRemoveAreaEvent(
                $form->get('area_id')->getData(),
                $form->get('shipping_zone_id')->getData()
            );

            $this->dispatch(TheliaEvents::SHIPPING_ZONE_REMOVE_AREA, $event);

            // Redirect to the success URL
            $this->redirect($shippingAreaForm->getSuccessUrl());

        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $this->getTranslator()->trans("%obj modification", array('%obj' => $this->objectName)), $error_msg, $shippingAreaForm);

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        return $this->render("shipping-zones-edit", array(
            "shipping_zones_id" => $this->getShippingZoneId()
        ));
    }

    protected function getShippingZoneId()
    {
        return $this->getRequest()->get('shipping_zone_id', 0);
    }

}
