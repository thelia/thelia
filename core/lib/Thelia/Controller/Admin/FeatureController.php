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

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Feature\FeatureAvUpdateEvent;
use Thelia\Core\Event\Feature\FeatureCreateEvent;
use Thelia\Core\Event\Feature\FeatureDeleteEvent;
use Thelia\Core\Event\Feature\FeatureEvent;
use Thelia\Core\Event\Feature\FeatureUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\Feature;
use Thelia\Model\FeatureQuery;

/**
 * Manages features.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class FeatureController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'feature',
            'manual',
            'order',
            AdminResources::FEATURE,
            TheliaEvents::FEATURE_CREATE,
            TheliaEvents::FEATURE_UPDATE,
            TheliaEvents::FEATURE_DELETE,
            null, // No visibility toggle
            TheliaEvents::FEATURE_UPDATE_POSITION,
        );
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::FEATURE_CREATION);
    }

    protected function getUpdateForm(): BaseForm
    {
        return $this->createForm(AdminForm::FEATURE_MODIFICATION);
    }

    protected function getCreationEvent(array $formData): ActionEvent
    {
        $createEvent = new FeatureCreateEvent();

        $createEvent
            ->setTitle($formData['title'])
            ->setLocale($formData['locale'])
            ->setAddToAllTemplates($formData['add_to_all']);

        return $createEvent;
    }

    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $changeEvent = new FeatureUpdateEvent((int) $formData['id']);

        // Create and dispatch the change event
        $changeEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum']);

        return $changeEvent;
    }

    /**
     * Process the features values (fix it in future version to integrate it in the feature form as a collection).
     *
     * @see \Thelia\Controller\Admin\AbstractCrudController::performAdditionalUpdateAction()
     */
    protected function performAdditionalUpdateAction(EventDispatcherInterface $eventDispatcher, ActionEvent $updateEvent): null
    {
        $attr_values = $this->getRequest()->get('feature_values');

        if (null !== $attr_values) {
            foreach ($attr_values as $id => $value) {
                $event = new FeatureAvUpdateEvent($id);

                $event->setTitle($value);
                $event->setLocale($this->getCurrentEditionLocale());

                $eventDispatcher->dispatch($event, TheliaEvents::FEATURE_AV_UPDATE);
            }
        }

        return null;
    }

    protected function createUpdatePositionEvent(int $positionChangeMode, ?int $positionValue = null): UpdatePositionEvent
    {
        return new UpdatePositionEvent(
            (int) $this->getRequest()->get('feature_id'),
            $positionChangeMode,
            $positionValue,
        );
    }

    protected function getDeleteEvent(): FeatureDeleteEvent
    {
        return new FeatureDeleteEvent($this->getRequest()->get('feature_id'));
    }

    protected function eventContainsObject($event): bool
    {
        return $event->hasFeature();
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
        return $this->createForm(AdminForm::FEATURE_MODIFICATION, FormType::class, $data);
    }

    protected function getObjectFromEvent($event): mixed
    {
        return $event->hasFeature() ? $event->getFeature() : null;
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $feature = FeatureQuery::create()
            ->findOneById($this->getRequest()->get('feature_id', 0));

        if (null !== $feature) {
            $feature->setLocale($this->getCurrentEditionLocale());
        }

        return $feature;
    }

    /**
     * @param Feature $object
     */
    protected function getObjectLabel(ActiveRecordInterface $object): ?string
    {
        return $object->getTitle();
    }

    /**
     * @param Feature $object
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    protected function renderListTemplate(string $currentOrder): Response
    {
        return $this->render('features', ['order' => $currentOrder]);
    }

    protected function renderEditionTemplate(): Response
    {
        return $this->render(
            'feature-edit',
            [
                'feature_id' => $this->getRequest()->get('feature_id'),
                'featureav_order' => $this->getFeatureAvListOrder(),
            ],
        );
    }

    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.features.update',
            [
                'feature_id' => $this->getRequest()->get('feature_id'),
                'featureav_order' => $this->getFeatureAvListOrder(),
            ],
        );
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute('admin.configuration.features.default');
    }

    /**
     * Get the Feature value list order.
     *
     * @return string the current list order
     */
    protected function getFeatureAvListOrder(): ?string
    {
        return $this->getListOrderFromSession(
            'featureav',
            'featureav_order',
            'manual',
        );
    }

    /**
     * Add or Remove from all product templates.
     */
    protected function addRemoveFromAllTemplates(EventDispatcherInterface $eventDispatcher, object $eventType): Response
    {
        // Check current user authorization
        if (($response = $this->checkAuth($this->resourceCode, [], AccessManager::UPDATE)) instanceof Response) {
            return $response;
        }

        try {
            if (($object = $this->getExistingObject()) instanceof ActiveRecordInterface) {
                $event = new FeatureEvent($object);

                $eventDispatcher->dispatch($eventType, $event);
            }
        } catch (\Exception $exception) {
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
        return $this->addRemoveFromAllTemplates($eventDispatcher, TheliaEvents::FEATURE_REMOVE_FROM_ALL_TEMPLATES);
    }

    /**
     * Add to all product templates.
     */
    public function addToAllTemplates(EventDispatcherInterface $eventDispatcher): Response
    {
        return $this->addRemoveFromAllTemplates($eventDispatcher, TheliaEvents::FEATURE_ADD_TO_ALL_TEMPLATES);
    }
}
