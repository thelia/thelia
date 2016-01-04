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

use Thelia\Core\Event\ShippingZone\ShippingZoneAddAreaEvent;
use Thelia\Core\Event\ShippingZone\ShippingZoneRemoveAreaEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\ShippingZone\ShippingZoneAddArea;
use Thelia\Form\ShippingZone\ShippingZoneRemoveArea;

/**
 * Class ShippingZoneController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ShippingZoneController extends BaseAdminController
{
    public $objectName = 'areaDeliveryModule';

    public function indexAction()
    {
        if (null !== $response = $this->checkAuth(AdminResources::SHIPPING_ZONE, array(), AccessManager::VIEW)) {
            return $response;
        }
        return $this->render("shipping-zones", array("display_shipping_zone" => 20));
    }

    public function updateAction($delivery_module_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::SHIPPING_ZONE, array(), AccessManager::VIEW)) {
            return $response;
        }

        return $this->render(
            "shipping-zones-edit",
            ["delivery_module_id" => $delivery_module_id]
        );
    }

    /**
     * @return mixed|\Thelia\Core\HttpFoundation\Response
     */
    public function addArea()
    {
        if (null !== $response = $this->checkAuth(AdminResources::SHIPPING_ZONE, array(), AccessManager::UPDATE)) {
            return $response;
        }

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
            return $this->generateSuccessRedirect($shippingAreaForm);
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $this->getTranslator()->trans("%obj modification", array('%obj' => $this->objectName)),
            $error_msg,
            $shippingAreaForm
        );

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    public function removeArea()
    {
        if (null !== $response = $this->checkAuth(AdminResources::SHIPPING_ZONE, array(), AccessManager::UPDATE)) {
            return $response;
        }

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
            return $this->generateSuccessRedirect($shippingAreaForm);
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
            $error_msg = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $this->getTranslator()->trans("%obj modification", array('%obj' => $this->objectName)),
            $error_msg,
            $shippingAreaForm
        );

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    /**
     * Render the edition template
     */
    protected function renderEditionTemplate()
    {
        return $this->render(
            "shipping-zones-edit",
            ["delivery_module_id" => $this->getDeliveryModuleId()]
        );
    }

    protected function getDeliveryModuleId()
    {
        return $this->getRequest()->get('delivery_module_id', 0);
    }
}
