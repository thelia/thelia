<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Controller\Admin;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ShippingZone\ShippingZoneAddAreaEvent;
use Thelia\Core\Event\ShippingZone\ShippingZoneRemoveAreaEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\ShippingZone\ShippingZoneRemoveArea;

/**
 * Class ShippingZoneController.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ShippingZoneController extends BaseAdminController
{
    public $objectName = 'areaDeliveryModule';

    public function indexAction()
    {
        if (($response = $this->checkAuth(AdminResources::SHIPPING_ZONE, [], AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        return $this->render('shipping-zones', ['display_shipping_zone' => 20]);
    }

    public function updateAction($delivery_module_id)
    {
        if (($response = $this->checkAuth(AdminResources::SHIPPING_ZONE, [], AccessManager::VIEW)) instanceof Response) {
            return $response;
        }

        return $this->render(
            'shipping-zones-edit',
            ['delivery_module_id' => $delivery_module_id],
        );
    }

    /**
     * @return mixed|Response
     */
    public function addArea(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse|null
    {
        if (($response = $this->checkAuth(AdminResources::SHIPPING_ZONE, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $shippingAreaForm = $this->createForm('thelia.shipping_zone_area');
        $error_msg = null;

        try {
            $form = $this->validateForm($shippingAreaForm);

            $event = new ShippingZoneAddAreaEvent(
                $form->get('area_id')->getData(),
                $form->get('shipping_zone_id')->getData(),
            );

            $eventDispatcher->dispatch($event, TheliaEvents::SHIPPING_ZONE_ADD_AREA);

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
            $this->getTranslator()->trans('%obj modification', ['%obj' => $this->objectName]),
            $error_msg,
            $shippingAreaForm,
        );

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    public function removeArea(EventDispatcherInterface $eventDispatcher): Response|RedirectResponse|null
    {
        if (($response = $this->checkAuth(AdminResources::SHIPPING_ZONE, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        $shippingAreaForm = $this->createForm(ShippingZoneRemoveArea::class);
        $error_msg = null;

        try {
            $form = $this->validateForm($shippingAreaForm);

            $event = new ShippingZoneRemoveAreaEvent(
                $form->get('area_id')->getData(),
                $form->get('shipping_zone_id')->getData(),
            );

            $eventDispatcher->dispatch($event, TheliaEvents::SHIPPING_ZONE_REMOVE_AREA);

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
            $this->getTranslator()->trans('%obj modification', ['%obj' => $this->objectName]),
            $error_msg,
            $shippingAreaForm,
        );

        // At this point, the form has errors, and should be redisplayed.
        return $this->renderEditionTemplate();
    }

    /**
     * Render the edition template.
     */
    protected function renderEditionTemplate(): Response
    {
        return $this->render(
            'shipping-zones-edit',
            ['delivery_module_id' => $this->getDeliveryModuleId()],
        );
    }

    protected function getDeliveryModuleId(): mixed
    {
        return $this->getRequest()->get('delivery_module_id', 0);
    }
}
