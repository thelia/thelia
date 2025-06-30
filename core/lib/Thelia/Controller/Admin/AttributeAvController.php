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


use LogicException;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Event\Attribute\AttributeAvCreateEvent;
use Thelia\Core\Event\Attribute\AttributeAvDeleteEvent;
use Thelia\Core\Event\Attribute\AttributeAvUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\BaseForm;
use Thelia\Form\Definition\AdminForm;
use Thelia\Model\AttributeAv;
use Thelia\Model\AttributeAvQuery;

/**
 * Manages attributes-av.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class AttributeAvController extends AbstractCrudController
{
    public function __construct()
    {
        parent::__construct(
            'attributeav',
            'manual',
            'order',
            AdminResources::ATTRIBUTE,
            TheliaEvents::ATTRIBUTE_AV_CREATE,
            TheliaEvents::ATTRIBUTE_AV_UPDATE,
            TheliaEvents::ATTRIBUTE_AV_DELETE,
            null, // No visibility toggle
            TheliaEvents::ATTRIBUTE_AV_UPDATE_POSITION
        );
    }

    protected function getCreationForm(): BaseForm
    {
        return $this->createForm(AdminForm::ATTRIBUTE_AV_CREATION);
    }

    protected function getUpdateForm(): null
    {
        return null;
    }

    protected function getCreationEvent(array $formData): ActionEvent
    {
        $createEvent = new AttributeAvCreateEvent();

        $createEvent
            ->setAttributeId($formData['attribute_id'])
            ->setTitle($formData['title'])
            ->setLocale($formData['locale'])
        ;

        return $createEvent;
    }

    protected function getUpdateEvent(array $formData): ActionEvent
    {
        $changeEvent = new AttributeAvUpdateEvent($formData['id']);

        // Create and dispatch the change event
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
            $this->getRequest()->get('attributeav_id'),
            $positionChangeMode,
            $positionValue
        );
    }

    protected function getDeleteEvent(): AttributeAvDeleteEvent
    {
        return new AttributeAvDeleteEvent($this->getRequest()->get('attributeav_id'));
    }

    protected function eventContainsObject($event): bool
    {
        return $event->hasAttributeAv();
    }

    protected function hydrateObjectForm(ParserContext $parserContext, ActiveRecordInterface $object): BaseForm
    {
        throw new LogicException('Attribute Av. modification is not yet implemented');
    }

    protected function getObjectFromEvent($event): mixed
    {
        return $event->hasAttributeAv() ? $event->getAttributeAv() : null;
    }

    protected function getExistingObject(): ?ActiveRecordInterface
    {
        $attributeAv = AttributeAvQuery::create()
        ->findOneById($this->getRequest()->get('attributeav_id', 0));

        if (null !== $attributeAv) {
            $attributeAv->setLocale($this->getCurrentEditionLocale());
        }

        return $attributeAv;
    }

    /**
     * @param AttributeAv $object
     *
     * @return string
     */
    protected function getObjectLabel(activeRecordInterface $object): ?string    {
        return $object->getTitle();
    }

    /**
     * @param AttributeAv $object
     */
    protected function getObjectId(ActiveRecordInterface $object): int
    {
        return $object->getId();
    }

    protected function getViewArguments(): array
    {
        return [
            'attribute_id' => $this->getRequest()->get('attribute_id'),
            'order' => $this->getCurrentListOrder(),
        ];
    }

    protected function renderListTemplate($currentOrder): Response
    {
        // We always return to the attribute edition form
        return $this->render(
            'attribute-edit',
            $this->getViewArguments()
        );
    }

    protected function renderEditionTemplate(): Response
    {
        // We always return to the attribute edition form
        return $this->render('attribute-edit', $this->getViewArguments());
    }

    protected function redirectToEditionTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.attributes.update',
            $this->getViewArguments()
        );
    }

    protected function redirectToListTemplate(): Response|RedirectResponse
    {
        return $this->generateRedirectFromRoute(
            'admin.configuration.attributes.update',
            $this->getViewArguments()
        );
    }
}
