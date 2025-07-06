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

use Thelia\Form\BaseForm;
use LogicException;
use Thelia\Core\Event\Feature\FeatureAvCreateEvent;
use Thelia\Core\Event\Feature\FeatureAvDeleteEvent;
use Thelia\Core\Event\Feature\FeatureAvUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\FeatureAv;
use Thelia\Model\FeatureAvQuery;

/**
 * Manages features-av.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class FeatureAvController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'featureav',
            'manual',
            'featureav_order',
            AdminResources::FEATURE,
            TheliaEvents::FEATURE_AV_CREATE,
            TheliaEvents::FEATURE_AV_UPDATE,
            TheliaEvents::FEATURE_AV_DELETE,
            null, // No visibility toggle
            TheliaEvents::FEATURE_AV_UPDATE_POSITION
        );
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::FEATURE_AV_CREATION);
    }

    protected function getUpdateForm(): void
    {
        throw new LogicException('Featiure Av. modification is not yet implemented');
    }

    protected function getCreationEvent($formData): FeatureAvCreateEvent
    {
        $createEvent = new FeatureAvCreateEvent();

        $createEvent
            ->setFeatureId($formData['feature_id'])
            ->setTitle($formData['title'])
            ->setLocale($formData['locale'])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent($formData): FeatureAvUpdateEvent
    {
        $changeEvent = new FeatureAvUpdateEvent($formData['id']);

        $changeEvent
            ->setLocale($formData['locale'])
            ->setTitle($formData['title'])
            ->setChapo($formData['chapo'])
            ->setDescription($formData['description'])
            ->setPostscriptum($formData['postscriptum'])
        ;

        return $changeEvent;
    }

    protected function createUpdatePositionEvent($positionChangeMode, $positionValue): UpdatePositionEvent
    {
        return new UpdatePositionEvent(
            $this->getRequest()->get('featureav_id', null),
            $positionChangeMode,
            $positionValue
        );
    }

    protected function getDeleteEvent(): FeatureAvDeleteEvent
    {
        return new FeatureAvDeleteEvent($this->getRequest()->get('featureav_id'));
    }

    protected function eventContainsObject($event)
    {
        return $event->hasFeatureAv();
    }

    protected function hydrateObjectForm(ParserContext $parserContext, $object): void
    {
        throw new LogicException('Feature Av. modification is not yet implemented');
    }

    protected function getObjectFromEvent($event)
    {
        return $event->hasFeatureAv() ? $event->getFeatureAv() : null;
    }

    protected function getExistingObject()
    {
        $featureAv = FeatureAvQuery::create()
        ->findOneById($this->getRequest()->get('featureav_id', 0));

        if (null !== $featureAv) {
            $featureAv->setLocale($this->getCurrentEditionLocale());
        }

        return $featureAv;
    }

    /**
     * @param FeatureAv $object
     *
     * @return string
     */
    protected function getObjectLabel($object)
    {
        return $object->getTitle();
    }

    /**
     * @param FeatureAv $object
     *
     * @return int
     */
    protected function getObjectId($object)
    {
        return $object->getId();
    }

    protected function getViewArguments(): array
    {
        return [
            'feature_id' => $this->getRequest()->get('feature_id'),
            'featureav_order' => $this->getCurrentListOrder(),
        ];
    }

    protected function renderListTemplate($currentOrder)
    {
        // We always return to the feature edition form
        return $this->render(
            'feature-edit',
            $this->getViewArguments()
        );
    }

    protected function renderEditionTemplate()
    {
        // We always return to the feature edition form
        return $this->render('feature-edit', $this->getViewArguments());
    }

    protected function redirectToEditionTemplate()
    {
        // We always return to the feature edition form
        return $this->generateRedirectFromRoute(
            'admin.configuration.features.update',
            $this->getViewArguments()
        );
    }

    protected function redirectToListTemplate()
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.features.update',
            $this->getViewArguments()
        );
    }
}
