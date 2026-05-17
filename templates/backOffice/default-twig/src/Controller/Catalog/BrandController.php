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

namespace BackOfficeDefaultTwigBundle\Controller\Catalog;

use BackOfficeDefaultTwigBundle\Form\Brand\BrandSeoType;
use BackOfficeDefaultTwigBundle\Form\Brand\BrandType;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\RowAction;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Brand\BrandCreateEvent;
use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Brand\BrandToggleVisibilityEvent;
use Thelia\Core\Event\Brand\BrandUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Brand;
use Thelia\Model\BrandQuery;
use Thelia\Model\LangQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/brand', name: 'admin.brand.')]
final class BrandController
{
    private const RESOURCE = AdminResources::BRAND;
    private const LIST_ROUTE = 'admin.brand.default';
    private const EDIT_ROUTE = 'admin.brand.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/catalog/brand/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/catalog/brand/edit.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenProvider $tokens,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, $this->buildListContext()));
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->formFactory->createNamed('thelia_brand_creation', BrandType::class, [
            'locale' => $this->defaultLocale(),
        ], [
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::BRAND_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Brand creation',
            successRoute: self::EDIT_ROUTE,
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::LIST_ROUTE)),
            describeForLog: $this->describeCreated(...),
        );
    }

    #[Route('/update/{brand_id}', name: 'update', methods: ['GET'], requirements: ['brand_id' => '\d+'])]
    public function updateView(int $brand_id, Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $brand = BrandQuery::create()->findPk($brand_id);
        if ($brand === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $brand->setLocale($locale);

        $form = $this->buildUpdateForm($brand, $locale);
        $seoForm = $this->buildSeoForm($brand, $locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'brand' => $brand,
            'form' => $form->createView(),
            'seo_form' => $seoForm->createView(),
            'current_tab' => (string) $request->query->get('current_tab', 'general'),
        ]));
    }

    #[Route('/save/{brand_id}', name: 'save', methods: ['POST'], requirements: ['brand_id' => '\d+'])]
    public function processUpdate(int $brand_id): Response
    {
        $form = $this->formFactory->createNamed('thelia_brand_modification', BrandType::class, null, [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::BRAND_UPDATE,
            eventFactory: $this->updateEvent(...),
            actionLabel: 'Brand update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['brand_id' => $brand_id],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['brand_id' => $brand_id])),
            describeForLog: $this->describeUpdated(...),
        );
    }

    #[Route('/seo/save', name: 'seo.save', methods: ['POST'])]
    public function processSeo(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_brand_seo_modification', BrandSeoType::class, null, [
            'csrf_protection' => false,
        ]);

        $brandId = (int) $request->request->get('brand_id', $request->request->get('id', 0));
        if ($brandId === 0 && $request->request->has('thelia_brand_seo_modification')) {
            $raw = (array) $request->request->all('thelia_brand_seo_modification');
            $brandId = (int) ($raw['id'] ?? 0);
        }

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::BRAND_UPDATE_SEO,
            eventFactory: $this->seoEvent(...),
            actionLabel: 'Brand SEO update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['brand_id' => $brandId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['brand_id' => $brandId, 'current_tab' => 'seo'])),
            describeForLog: $this->describeUpdated(...),
        );
    }

    #[Route('/toggle-online', name: 'toggle-online', methods: ['GET', 'POST'])]
    public function toggleOnline(Request $request): Response
    {
        $brand = BrandQuery::create()->findPk((int) $request->get('brand_id', 0));
        if ($brand === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new BrandToggleVisibilityEvent($brand),
            eventName: TheliaEvents::BRAND_TOGGLE_VISIBILITY,
            actionLabel: 'Brand visibility',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/update-position', name: 'update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('brand_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::BRAND_UPDATE_POSITION,
            actionLabel: 'Brand reorder',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/delete', name: 'delete', methods: ['GET', 'POST'])]
    public function delete(Request $request): Response
    {
        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: new BrandDeleteEvent((int) $request->get('brand_id', 0)),
            eventName: TheliaEvents::BRAND_DELETE,
            actionLabel: 'Brand deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    private function buildUpdateForm(Brand $brand, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_brand_modification', BrandType::class, [
            'id' => $brand->getId(),
            'locale' => $locale,
            'title' => $brand->getTitle(),
            'visible' => (bool) $brand->getVisible(),
            'chapo' => $brand->getChapo(),
            'description' => $brand->getDescription(),
            'postscriptum' => $brand->getPostscriptum(),
            'logo_image_id' => $brand->getLogoImageId(),
        ], [
            'include_id' => true,
            'include_description' => true,
            'csrf_protection' => false,
        ]);
    }

    private function buildSeoForm(Brand $brand, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_brand_seo_modification', BrandSeoType::class, [
            'id' => $brand->getId(),
            'locale' => $locale,
            'url' => $brand->getRewrittenUrl($locale),
            'meta_title' => $brand->getMetaTitle(),
            'meta_description' => $brand->getMetaDescription(),
            'meta_keywords' => $brand->getMetaKeywords(),
        ], [
            'csrf_protection' => false,
        ]);
    }

    private function createEvent(FormInterface $validated): BrandCreateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new BrandCreateEvent();
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setVisible((bool) ($data['visible'] ?? false));

        return $event;
    }

    private function updateEvent(FormInterface $validated): BrandUpdateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new BrandUpdateEvent((int) ($data['id'] ?? 0));
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setVisible((bool) ($data['visible'] ?? false))
            ->setChapo($this->stringOrNull($data['chapo'] ?? null))
            ->setDescription($this->stringOrNull($data['description'] ?? null))
            ->setPostscriptum($this->stringOrNull($data['postscriptum'] ?? null))
            ->setLogoImageId($this->intOrNull($data['logo_image_id'] ?? null));

        return $event;
    }

    private function seoEvent(FormInterface $validated): UpdateSeoEvent
    {
        $data = $validated->getData() ?? [];

        return new UpdateSeoEvent(
            (int) ($data['id'] ?? 0),
            (string) ($data['locale'] ?? $this->defaultLocale()),
            $this->stringOrNull($data['url'] ?? null),
            $this->stringOrNull($data['meta_title'] ?? null),
            $this->stringOrNull($data['meta_description'] ?? null),
            $this->stringOrNull($data['meta_keywords'] ?? null),
        );
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeCreated(BrandCreateEvent $event): array
    {
        $brand = $event->getBrand();
        if ($brand === null) {
            throw new \LogicException($this->translator->trans('No brand was created.'));
        }

        return [\sprintf('Brand %s (ID %d) created', (string) $brand->getTitle(), (int) $brand->getId()), (int) $brand->getId()];
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeUpdated(object $event): array
    {
        if ($event instanceof BrandUpdateEvent && $event->hasBrand()) {
            $brand = $event->getBrand();

            return [\sprintf('Brand %s (ID %d) modified', (string) $brand->getTitle(), (int) $brand->getId()), (int) $brand->getId()];
        }

        if ($event instanceof UpdateSeoEvent) {
            return [\sprintf('Brand SEO (ID %d) modified', (int) $event->getObjectId()), (int) $event->getObjectId()];
        }

        return ['Brand modified', null];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListContext(): array
    {
        $locale = $this->defaultLocale();
        $brands = BrandQuery::create()->orderByPosition()->find();
        $rows = [];

        foreach ($brands as $brand) {
            \assert($brand instanceof Brand);
            $brand->setLocale($locale);
            $rows[] = $this->brandToRow($brand);
        }

        $createForm = $this->formFactory->createNamed('thelia_brand_creation', BrandType::class, [
            'locale' => $locale,
        ], [
            'csrf_protection' => false,
        ]);

        return [
            'rows' => $rows,
            'create_form' => $createForm->createView(),
            'update_position_url' => $this->urls->generate('admin.brand.update-position'),
            'update_position_token' => $this->tokens->assignToken(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function brandToRow(Brand $brand): array
    {
        $id = (int) $brand->getId();

        $actions = [
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Edit this brand'),
                href: $this->urls->generate(self::EDIT_ROUTE, ['brand_id' => $id]),
                grantedAttribute: AccessManager::UPDATE,
                grantedSubject: self::RESOURCE,
            ),
            new RowAction(
                kind: 'delete',
                label: $this->translator->trans('Delete this brand'),
                modalTarget: '#brand-delete-modal',
                grantedAttribute: AccessManager::DELETE,
                grantedSubject: self::RESOURCE,
                dataAttributes: [
                    'brand-id' => $id,
                    'brand-label' => (string) $brand->getTitle(),
                ],
            ),
        ];

        return [
            'id' => $id,
            'title' => (string) $brand->getTitle(),
            'visible' => (bool) $brand->getVisible(),
            'position' => (int) $brand->getPosition(),
            'toggle_visible_url' => $this->tokenizedUrl('admin.brand.toggle-online', ['brand_id' => $id]),
            '_actions' => $actions,
        ];
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!\is_scalar($value) && $value !== null) {
            return null;
        }
        $cast = (string) $value;

        return $cast === '' ? null : $cast;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function tokenizedUrl(string $route, array $parameters): string
    {
        $url = $this->urls->generate($route, $parameters);
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'_token='.$this->tokens->assignToken();
    }
}
