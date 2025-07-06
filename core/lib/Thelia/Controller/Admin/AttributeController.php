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


use Exception;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Attribute\AttributeAvUpdateEvent;
use Thelia\Core\Event\Attribute\AttributeCreateEvent;
use Thelia\Core\Event\Attribute\AttributeDeleteEvent;
use Thelia\Core\Event\Attribute\AttributeEvent;
use Thelia\Core\Event\Attribute\AttributeUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeQuery;

/**
 * Manages attributes.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class AttributeController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'attribute',
            'manual',
            'order',
            AdminResources::ATTRIBUTE,
            TheliaEvents::ATTRIBUTE_CREATE,
            TheliaEvents::ATTRIBUTE_UPDATE,
            TheliaEvents::ATTRIBUTE_DELETE,
            null, // No visibility toggle
            TheliaEvents::ATTRIBUTE_UPDATE_POSITION
        );
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::ATTRIBUTE_CREATION);
    }

    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::ATTRIBUTE_MODIFICATION);
    }

    protected function getCreationEvent(array $formData): ActionEvent
    {
        $createEvent = new AttributeCreateEvent();

        $createEvent
            ->setTitle($formData['title'])
            ->setLocale($formData['locale'])
            ->setAddToAllTemplates($formData['add_to_all'])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $changeEvent = new AttributeUpdateEvent($formData['id']);

        $changeEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
        ;

        return $changeEvent;
    }

    /**
     * Process the attributes values (fix it in future version to integrate it in the attribute form as a collection).
     *
     * @see \Thelia\Controller\Admin\AbstractCrudController::performAdditionalUpdateAction()
     */
    protected function performAdditionalUpdateAction(EventDispatcherInterface $eventDispatcher, ActionEvent $updateEvent): null
    {
        $attr_values = $this->getRequest()->get('attribute_values');

        if ($attr_values !== null) {
            foreach ($attr_values as $id => $value) {
                $event = new AttributeAvUpdateEvent($id);

                $event->setTitle($value);
                $event->setLocale($this->getCurrentEditionLocale());

                $eventDispatcher->dispatch($event, TheliaEvents::ATTRIBUTE_AV_UPDATE);
            }
        }

        return null;
    }

    protected function createUpdatePositionEvent($positionChangeMode, $positionValue): UpdatePositionEvent
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('attribute_id'),
            $positionChangeMode,
            $positionValue
        );
    }

    protected function getDeleteEvent(): AttributeDeleteEvent
    {
        return new AttributeDeleteEvent($this->getRequest()->get('attribute_id'));
    }

    protected function eventContainsObject($event): bool
    {
        return $event->hasAttribute();
    }

    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        $data = [
            'id' => $object->getId(),
            'locale' => $object->getLocale(),
            'title' => $object->getTitle(),
            'chapo' => $object->getChapo(),
            'description' => $object->getDescription(),
            'postscriptum' => $object->getPostscriptum(),
        ];

        // Setup the object form
        return $this->createForm(AdminForm::ATTRIBUTE_MODIFICATION, FormType::class, $data);
    }

    protected function getObjectFromEvent($event): mixed
    {
        return $event->hasAttribute() ? $event->getAttribute() : null;
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $attribute = AttributeQuery::create()
        ->findOneById($this->getRequest()->get('attribute_id', 0));

        if (null !== $attribute) {
            $attribute->setLocale($this->getCurrentEditionLocale());
        }

        return $attribute;
    }

    /**
     * @param Attribute $object
     *
     * @return string
     */
    protected function getObjectLabel(activeRecordInterface $object): ?string    {
        return $object->getTitle();
    }

    /**
     * @param Attribute $object
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    protected function renderListTemplate($currentOrder): Response
    {
        return $this->render('attributes', ['order' => $currentOrder]);
    }

    protected function renderEditionTemplate(): Response
    {
        return $this->render(
            'attribute-edit',
            [
                'attribute_id' => $this->getRequest()->get('attribute_id'),
                'attributeav_order' => $this->getAttributeAvListOrder(),
            ]
        );
    }

    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.attributes.update',
            [
                'attribute_id' => $this->getRequest()->get('attribute_id'),
                'attributeav_order' => $this->getAttributeAvListOrder(),
            ]
        );
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute('admin.configuration.attributes.default');
    }

    /**
     * Get the Attribute value list order.
     *
     * @return string the current list order
     */
    protected function getAttributeAvListOrder(): ?string
    {
        return $this->getListOrderFromSession(
            'attributeav',
            'attributeav_order',
            'manual'
        );
    }

    /**
     * Add or Remove from all product templates.
     */
    protected function addRemoveFromAllTemplates(EventDispatcherInterface $eventDispatcher, ?string $eventType): Response
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof \Symfony\Component\HttpFoundation\Response) {
            return $response;
        }

        try {
            if (($object = $this->getExistingObject()) instanceof ActiveRecordInterface) {
                $event = new AttributeEvent($object);

                $eventDispatcher->dispatch($event, $eventType);
            }
        } catch (Exception $exception) {
            // Any error
            return $this->errorPage($exception);
        }

        return $this->redirectToListTemplate();
    }

    /**
     * Remove from all product templates.
     */
    public function removeFromAllTemplates(EventDispatcherInterface $eventDispatcher): Response
    {
        return $this->addRemoveFromAllTemplates($eventDispatcher, TheliaEvents::ATTRIBUTE_REMOVE_FROM_ALL_TEMPLATES);
    }

    /**
     * Add to all product templates.
     */
    public function addToAllTemplates(EventDispatcherInterface $eventDispatcher): Response
    {
        return $this->addRemoveFromAllTemplates($eventDispatcher, TheliaEvents::ATTRIBUTE_ADD_TO_ALL_TEMPLATES);
    }
}
