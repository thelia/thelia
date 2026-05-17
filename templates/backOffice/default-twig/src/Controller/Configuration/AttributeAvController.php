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

namespace BackOfficeDefaultTwigBundle\Controller\Configuration;

use BackOfficeDefaultTwigBundle\Form\Attribute\AttributeAvType;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Attribute\AttributeAvCreateEvent;
use Thelia\Core\Event\Attribute\AttributeAvDeleteEvent;
use Thelia\Core\Event\Attribute\AttributeAvUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\LangQuery;

#[Route('/admin/configuration/attributes-av', name: 'admin.configuration.attributes-av.')]
final class AttributeAvController
{
    private const RESOURCE = AdminResources::ATTRIBUTE;
    private const ATTRIBUTE_EDIT_ROUTE = 'admin.configuration.attributes.update';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_attributeav_creation', AttributeAvType::class, null, [
            'csrf_protection' => false,
        ]);

        $attributeId = (int) $request->request->get('attribute_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::ATTRIBUTE_AV_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Attribute value creation',
            successRoute: self::ATTRIBUTE_EDIT_ROUTE,
            successParameters: ['attribute_id' => $attributeId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::ATTRIBUTE_EDIT_ROUTE, ['attribute_id' => $attributeId])),
        );
    }

    #[Route('/update', name: 'update', methods: ['GET'])]
    public function updateRedirect(Request $request): RedirectResponse
    {
        $attributeId = (int) $request->query->get('attribute_id', 0);

        return new RedirectResponse($this->urls->generate(self::ATTRIBUTE_EDIT_ROUTE, ['attribute_id' => $attributeId]));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_attributeav_modification', AttributeAvType::class, null, [
            'include_id' => true,
            'csrf_protection' => false,
        ]);

        $attributeId = (int) $request->request->get('attribute_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::ATTRIBUTE_AV_UPDATE,
            eventFactory: $this->updateEvent(...),
            actionLabel: 'Attribute value update',
            successRoute: self::ATTRIBUTE_EDIT_ROUTE,
            successParameters: ['attribute_id' => $attributeId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::ATTRIBUTE_EDIT_ROUTE, ['attribute_id' => $attributeId])),
        );
    }

    #[Route('/delete', name: 'delete', methods: ['GET', 'POST'])]
    public function delete(Request $request): Response
    {
        $attributeAvId = (int) $request->get('attributeav_id', 0);
        $attributeAv = AttributeAvQuery::create()->findPk($attributeAvId);
        $attributeId = $attributeAv?->getAttributeId() ?? 0;

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new AttributeAvDeleteEvent($attributeAvId),
            eventName: TheliaEvents::ATTRIBUTE_AV_DELETE,
            actionLabel: 'Attribute value deletion',
            successRoute: self::ATTRIBUTE_EDIT_ROUTE,
            successParameters: ['attribute_id' => $attributeId],
        );
    }

    #[Route('/update-position', name: 'update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $attributeAvId = (int) $request->get('attributeav_id', 0);
        $attributeAv = AttributeAvQuery::create()->findPk($attributeAvId);
        $attributeId = $attributeAv?->getAttributeId() ?? 0;

        $event = new UpdatePositionEvent(
            $attributeAvId,
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::ATTRIBUTE_AV_UPDATE_POSITION,
            actionLabel: 'Attribute value reorder',
            successRoute: self::ATTRIBUTE_EDIT_ROUTE,
            successParameters: ['attribute_id' => $attributeId],
        );
    }

    private function createEvent(FormInterface $validated): AttributeAvCreateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new AttributeAvCreateEvent();
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setAttributeId((int) ($data['attribute_id'] ?? 0));

        return $event;
    }

    private function updateEvent(FormInterface $validated): AttributeAvUpdateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new AttributeAvUpdateEvent((int) ($data['id'] ?? 0));
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setAttributeId((int) ($data['attribute_id'] ?? 0));

        return $event;
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
