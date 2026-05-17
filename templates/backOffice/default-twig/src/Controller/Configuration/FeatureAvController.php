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

use BackOfficeDefaultTwigBundle\Form\Feature\FeatureAvType;
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
use Thelia\Core\Event\Feature\FeatureAvCreateEvent;
use Thelia\Core\Event\Feature\FeatureAvDeleteEvent;
use Thelia\Core\Event\Feature\FeatureAvUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\LangQuery;

#[Route('/admin/configuration/features-av', name: 'admin.configuration.features-av.')]
final class FeatureAvController
{
    private const RESOURCE = AdminResources::FEATURE;
    private const FEATURE_EDIT_ROUTE = 'admin.configuration.features.update';

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
        $form = $this->formFactory->createNamed('thelia_featureav_creation', FeatureAvType::class, null, [
            'csrf_protection' => false,
        ]);

        $featureId = (int) $request->request->get('feature_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::FEATURE_AV_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Feature value creation',
            successRoute: self::FEATURE_EDIT_ROUTE,
            successParameters: ['feature_id' => $featureId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::FEATURE_EDIT_ROUTE, ['feature_id' => $featureId])),
        );
    }

    #[Route('/update', name: 'update', methods: ['GET'])]
    public function updateRedirect(Request $request): RedirectResponse
    {
        $featureId = (int) $request->query->get('feature_id', 0);

        return new RedirectResponse($this->urls->generate(self::FEATURE_EDIT_ROUTE, ['feature_id' => $featureId]));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_featureav_modification', FeatureAvType::class, null, [
            'include_id' => true,
            'csrf_protection' => false,
        ]);

        $featureId = (int) $request->request->get('feature_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::FEATURE_AV_UPDATE,
            eventFactory: $this->updateEvent(...),
            actionLabel: 'Feature value update',
            successRoute: self::FEATURE_EDIT_ROUTE,
            successParameters: ['feature_id' => $featureId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::FEATURE_EDIT_ROUTE, ['feature_id' => $featureId])),
        );
    }

    #[Route('/delete', name: 'delete', methods: ['GET', 'POST'])]
    public function delete(Request $request): Response
    {
        $featureAvId = (int) $request->get('featureav_id', 0);
        $featureAv = FeatureAvQuery::create()->findPk($featureAvId);
        $featureId = $featureAv?->getFeatureId() ?? 0;

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new FeatureAvDeleteEvent($featureAvId),
            eventName: TheliaEvents::FEATURE_AV_DELETE,
            actionLabel: 'Feature value deletion',
            successRoute: self::FEATURE_EDIT_ROUTE,
            successParameters: ['feature_id' => $featureId],
        );
    }

    #[Route('/update-position', name: 'update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $featureAvId = (int) $request->get('featureav_id', 0);
        $featureAv = FeatureAvQuery::create()->findPk($featureAvId);
        $featureId = $featureAv?->getFeatureId() ?? 0;

        $event = new UpdatePositionEvent(
            $featureAvId,
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::FEATURE_AV_UPDATE_POSITION,
            actionLabel: 'Feature value reorder',
            successRoute: self::FEATURE_EDIT_ROUTE,
            successParameters: ['feature_id' => $featureId],
        );
    }

    private function createEvent(FormInterface $validated): FeatureAvCreateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new FeatureAvCreateEvent();
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setFeatureId((int) ($data['feature_id'] ?? 0));

        return $event;
    }

    private function updateEvent(FormInterface $validated): FeatureAvUpdateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new FeatureAvUpdateEvent((int) ($data['id'] ?? 0));
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setFeatureId((int) ($data['feature_id'] ?? 0));

        return $event;
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
