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

use Thelia\Core\Event\ActionEvent;
use Thelia\Form\BaseForm;
use Exception;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Thelia\Core\Event\OrderStatus\OrderStatusCreateEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusDeleteEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Definition\AdminForm;
use Thelia\Form\OrderStatus\OrderStatusModificationForm;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * Class OrderStatusController.
 *
 * @author  Gilles Bourgeat <gbourgeat@openstudio.com>
 *
 * @since 2.4
 */
class OrderStatusController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'orderstatus',
            'manual',
            'order',
            AdminResources::ORDER_STATUS,
            TheliaEvents::ORDER_STATUS_CREATE,
            TheliaEvents::ORDER_STATUS_UPDATE,
            TheliaEvents::ORDER_STATUS_DELETE,
            null,
            TheliaEvents::ORDER_STATUS_UPDATE_POSITION
        );
    }

    /**
     * Return the creation form for this object.
     */
    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::ORDER_STATUS_CREATION);
    }

    /**
     * Return the update form for this object.
     */
    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::ORDER_STATUS_MODIFICATION);
    }

    /**
     * Hydrate the update form for this object, before passing it to the update template.
     *
     * @param OrderStatus $object
     *
     * @return OrderStatusModificationForm $object
     */
    protected function hydrateObjectForm(ParserContext $parserContext, $object)
    {
        // Prepare the data that will hydrate the form
        $data = [
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'title' => $object->getTitle(),
            'chapo' => $object->getChapo(),
            'description' => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum(),
            'color' => $object->getColor(),
            'code' => $object->getCode(),
        ];

        $form = $this->createForm(AdminForm::ORDER_STATUS_MODIFICATION, FormType::class, $data);

        // Setup the object form
        return $form;
    }

    /**
     * Creates the creation event with the provided form data.
     *
     * @param array $formData
     */
    protected function getCreationEvent($formData): OrderStatusCreateEvent
    {
        $orderStatusCreateEvent = new OrderStatusCreateEvent();

        $orderStatusCreateEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setCode($formData['code'])
            ->setColor($formData['color'])
        ;

        return $orderStatusCreateEvent;
    }

    /**
     * Creates the update event with the provided form data.
     *
     * @param array $formData
     */
    protected function getUpdateEvent($formData): OrderStatusUpdateEvent
    {
        $orderStatusUpdateEvent = new OrderStatusUpdateEvent($formData['id']);

        $orderStatusUpdateEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
            ->setCode($formData['code'])
            ->setColor($formData['color'])
        ;

        return $orderStatusUpdateEvent;
    }

    /**
     * Creates the delete event with the provided form data.
     *
     * @throws Exception
     */
    protected function getDeleteEvent(): OrderStatusDeleteEvent
    {
        return new OrderStatusDeleteEvent((int) $this->getRequest()->get('order_status_id'));
    }

    /**
     * Return true if the event contains the object, e.g. the action has updated the object in the event.
     *
     * @param OrderStatusEvent $event
     */
    protected function eventContainsObject($event): bool
    {
        return $event->hasOrderStatus();
    }

    /**
     * Get the created object from an event.
     *
     * @param $event \Thelia\Core\Event\OrderStatus\OrderStatusEvent
     *
     * @return OrderStatus|null
     */
    protected function getObjectFromEvent($event)
    {
        return $event->getOrderStatus();
    }

    /**
     * Load an existing object from the database.
     *
     * @return OrderStatus
     */
    protected function getExistingObject()
    {
        $orderStatus = OrderStatusQuery::create()
            ->findOneById($this->getRequest()->get('order_status_id', 0));

        if (null !== $orderStatus) {
            $orderStatus->setLocale($this->getCurrentEditionLocale());
        }

        return $orderStatus;
    }

    /**
     * Returns the object label form the object event (name, title, etc.).
     *
     * @param OrderStatus $object
     *
     * @return string order status title
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * Returns the object ID from the object.
     *
     * @param OrderStatus $object
     *
     * @return int order status id
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    /**
     * Render the main list template.
     *
     * @param string $currentOrder , if any, null otherwise
     *
     * @return Response
     */
    protected function renderListTemplate($currentOrder)
    {
        $this->getListOrderFromSession('orderstatus', 'order', 'manual');

        return $this->render('order-status', [
            'order' => $currentOrder,
        ]);
    }

    protected function getEditionArguments(): array
    {
        return [
            'order_status_id' => $this->getRequest()->get('order_status_id', 0),
            'current_tab' => $this->getRequest()->get('current_tab', 'general'),
        ];
    }

    /**
     * Render the edition template.
     */
    protected function renderEditionTemplate()
    {
        return $this->render('order-status-edit', $this->getEditionArguments());
    }

    /**
     * Redirect to the edition template.
     */
    protected function redirectToEditionTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.order-status.update',
            [],
            $this->getEditionArguments()
        );
    }

    /**
     * Redirect to the list template.
     */
    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute('admin.order-status.default');
    }

    /**
     * @return UpdatePositionEvent|void
     */
    protected function createUpdatePositionEvent($positionChangeMode, $positionValue): UpdatePositionEvent
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('order_status_id', null),
            $positionChangeMode,
            $positionValue
        );
    }

    /**
     * @param $positionChangeEvent ActionEvent
     *
     * @return Response|null
     */
    protected function performAdditionalUpdatePositionAction(ActionEvent $positionChangeEvent)
    {
        $folder = OrderStatusQuery::create()->findPk($positionChangeEvent->getObjectId());

        if ($folder != null) {
            return $this->generateRedirectFromRoute(
                'admin.order-status.default'
            );
        }

        return null;
    }
}
