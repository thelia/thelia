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

use BackOfficeDefaultTwigBundle\Form\Product\ProductCloneType;
use BackOfficeDefaultTwigBundle\Form\Product\ProductSeoType;
use BackOfficeDefaultTwigBundle\Form\Product\ProductType;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use BackOfficeDefaultTwigBundle\Service\Catalog\ProductRelationsContext;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\RowAction;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Product\ProductCloneEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\Product\ProductToggleVisibilityEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Core\Event\UpdateSeoEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CategoryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\Model\TemplateQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/products', name: 'admin.products.')]
final class ProductController
{
    private const RESOURCE = AdminResources::PRODUCT;
    private const LIST_ROUTE = 'admin.products.default';
    private const EDIT_ROUTE = 'admin.products.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/catalog/product/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/catalog/product/edit.html.twig';
    private const PAGE_SIZE = 25;

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenProvider $tokens,
        private readonly TranslatorInterface $translator,
        private readonly ProductRelationsContext $relations,
    ) {
    }

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $categoryId = (int) $request->query->get('category_id', 0);
        $search = trim((string) $request->query->get('q', ''));
        $order = (string) $request->query->get('product_order', 'manual');

        return new Response($this->twig->render(self::LIST_TEMPLATE, array_merge(
            $this->paginatedRows($categoryId, $search, $order, $page),
            [
                'current_page' => $page,
                'current_category' => $categoryId,
                'current_search' => $search,
                'current_order' => $order,
                'create_form' => $this->buildCreateForm()->createView(),
                'clone_form' => $this->buildCloneForm()->createView(),
                'available_categories' => $this->categoryChoices(),
                'available_currencies' => $this->currencyChoices(),
                'available_tax_rules' => $this->taxRuleChoices(),
            ],
        )));
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(): Response
    {
        $form = $this->buildCreateForm();

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::PRODUCT_CREATE,
            eventFactory: $this->createEvent(...),
            actionLabel: 'Product creation',
            successRoute: self::EDIT_ROUTE,
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::LIST_ROUTE)),
            describeForLog: $this->describeCreated(...),
        );
    }

    #[Route('/clone', name: 'clone', methods: ['POST'])]
    public function clone(Request $request): Response
    {
        $form = $this->buildCloneForm();

        $productId = (int) $request->request->get('thelia_product_clone', [])['productId'] ?? 0;
        if ($productId === 0) {
            $productId = (int) $request->request->get('productId', 0);
        }

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::CREATE,
            form: $form,
            eventName: TheliaEvents::PRODUCT_CLONE,
            eventFactory: function (FormInterface $validated) use ($productId): ProductCloneEvent {
                $data = $validated->getData() ?? [];
                $resolvedId = (int) ($data['productId'] ?? $productId);
                $original = ProductQuery::create()->findPk($resolvedId);
                if ($original === null) {
                    throw new \LogicException($this->translator->trans('Original product not found.'));
                }

                return new ProductCloneEvent(
                    (string) ($data['newRef'] ?? ''),
                    $this->defaultLocale(),
                    $original,
                );
            },
            actionLabel: 'Product clone',
            successRoute: self::LIST_ROUTE,
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::LIST_ROUTE)),
        );
    }

    #[Route('/update', name: 'update', methods: ['GET'])]
    public function updateView(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $productId = (int) $request->query->get('product_id', 0);
        $product = ProductQuery::create()->findPk($productId);
        if ($product === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $locale = $this->defaultLocale();
        $product->setLocale($locale);

        $form = $this->buildUpdateForm($product, $locale);
        $seoForm = $this->buildSeoForm($product, $locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, array_merge(
            [
                'product' => $product,
                'form' => $form->createView(),
                'seo_form' => $seoForm->createView(),
                'current_tab' => (string) $request->query->get('current_tab', 'general'),
                'available_templates' => $this->templateChoices(),
            ],
            $this->relations->build($product, $locale),
        )));
    }

    #[Route('/save', name: 'save', methods: ['POST'])]
    public function processUpdate(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_product_modification', ProductType::class, null, [
            'include_id' => true,
            'csrf_protection' => false,
        ]);

        $productId = (int) $request->request->get('product_id', 0);

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::PRODUCT_UPDATE,
            eventFactory: $this->updateEvent(...),
            actionLabel: 'Product update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => $productId],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['product_id' => $productId])),
            describeForLog: $this->describeUpdated(...),
        );
    }

    #[Route('/seo/save', name: 'seo.save', methods: ['POST'])]
    public function processSeo(Request $request): Response
    {
        $form = $this->formFactory->createNamed('thelia_product_seo', ProductSeoType::class, null, [
            'csrf_protection' => false,
        ]);

        $productId = (int) $request->request->get('product_id', 0);
        if ($productId === 0 && $request->request->has('thelia_product_seo')) {
            $raw = (array) $request->request->all('thelia_product_seo');
            $productId = (int) ($raw['id'] ?? 0);
        }

        return $this->action->submit(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            form: $form,
            eventName: TheliaEvents::PRODUCT_UPDATE_SEO,
            eventFactory: $this->seoEvent(...),
            actionLabel: 'Product SEO update',
            successRoute: self::EDIT_ROUTE,
            successParameters: ['product_id' => $productId, 'current_tab' => 'seo'],
            renderError: fn (): RedirectResponse => new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['product_id' => $productId, 'current_tab' => 'seo'])),
            describeForLog: $this->describeSeoUpdated(...),
        );
    }

    #[Route('/toggle-online', name: 'set-default', methods: ['GET', 'POST'])]
    public function toggleOnline(Request $request): Response
    {
        $productId = (int) $request->get('product_id', 0);
        $product = ProductQuery::create()->findPk($productId);
        if ($product === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: new ProductToggleVisibilityEvent($product),
            eventName: TheliaEvents::PRODUCT_TOGGLE_VISIBILITY,
            actionLabel: 'Product visibility',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/update-position', name: 'update-position', methods: ['GET', 'POST'])]
    public function updatePosition(Request $request): Response
    {
        $event = new UpdatePositionEvent(
            (int) $request->get('product_id', 0),
            (int) $request->get('mode', UpdatePositionEvent::POSITION_ABSOLUTE),
            (int) $request->get('position', 0),
        );

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::UPDATE,
            request: $request,
            event: $event,
            eventName: TheliaEvents::PRODUCT_UPDATE_POSITION,
            actionLabel: 'Product reorder',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: new ProductDeleteEvent((int) $request->get('product_id', 0)),
            eventName: TheliaEvents::PRODUCT_DELETE,
            actionLabel: 'Product deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    private function buildCreateForm(): FormInterface
    {
        return $this->formFactory->createNamed('thelia_product_creation', ProductType::class, [
            'locale' => $this->defaultLocale(),
        ], [
            'csrf_protection' => false,
        ]);
    }

    private function buildCloneForm(): FormInterface
    {
        return $this->formFactory->createNamed('thelia_product_clone', ProductCloneType::class, null, [
            'csrf_protection' => false,
        ]);
    }

    private function buildUpdateForm(Product $product, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_product_modification', ProductType::class, [
            'id' => $product->getId(),
            'locale' => $locale,
            'ref' => $product->getRef(),
            'title' => $product->getTitle(),
            'default_category' => $product->getDefaultCategoryId(),
            'visible' => (bool) $product->getVisible(),
            'virtual' => (bool) $product->getVirtual(),
            'chapo' => $product->getChapo(),
            'description' => $product->getDescription(),
            'postscriptum' => $product->getPostscriptum(),
            'template_id' => $product->getTemplateId(),
            'brand_id' => $product->getBrandId(),
            'virtual_document_id' => null,
        ], [
            'include_id' => true,
            'csrf_protection' => false,
        ]);
    }

    private function buildSeoForm(Product $product, string $locale): FormInterface
    {
        return $this->formFactory->createNamed('thelia_product_seo', ProductSeoType::class, [
            'id' => $product->getId(),
            'locale' => $locale,
            'url' => $product->getRewrittenUrl($locale),
            'meta_title' => $product->getMetaTitle(),
            'meta_description' => $product->getMetaDescription(),
            'meta_keywords' => $product->getMetaKeywords(),
        ], [
            'csrf_protection' => false,
        ]);
    }

    private function createEvent(FormInterface $validated): ProductCreateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new ProductCreateEvent();
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setRef((string) ($data['ref'] ?? ''))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setDefaultCategory((int) ($data['default_category'] ?? 0))
            ->setVisible((bool) ($data['visible'] ?? false))
            ->setVirtual((bool) ($data['virtual'] ?? false))
            ->setBasePrice((float) ($data['price'] ?? 0))
            ->setCurrencyId((int) ($data['currency'] ?? 0))
            ->setTaxRuleId((int) ($data['tax_rule'] ?? 0))
            ->setBaseWeight((float) ($data['weight'] ?? 0))
            ->setBaseQuantity((int) ($data['quantity'] ?? 0));

        return $event;
    }

    private function updateEvent(FormInterface $validated): ProductUpdateEvent
    {
        $data = $validated->getData() ?? [];

        $event = new ProductUpdateEvent((int) ($data['id'] ?? 0));
        $event
            ->setLocale((string) ($data['locale'] ?? $this->defaultLocale()))
            ->setRef((string) ($data['ref'] ?? ''))
            ->setTitle((string) ($data['title'] ?? ''))
            ->setDefaultCategory((int) ($data['default_category'] ?? 0))
            ->setVisible((bool) ($data['visible'] ?? false))
            ->setVirtual((bool) ($data['virtual'] ?? false))
            ->setChapo($this->stringOrNull($data['chapo'] ?? null))
            ->setDescription($this->stringOrNull($data['description'] ?? null))
            ->setPostscriptum($this->stringOrNull($data['postscriptum'] ?? null))
            ->setTemplateId($this->intOrNull($data['template_id'] ?? null))
            ->setBrandId($this->intOrNull($data['brand_id'] ?? null))
            ->setVirtualDocumentId($this->intOrNull($data['virtual_document_id'] ?? null));

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
    private function describeCreated(ProductCreateEvent $event): array
    {
        if (!$event->hasProduct()) {
            throw new \LogicException($this->translator->trans('No product was created.'));
        }
        $product = $event->getProduct();

        return [\sprintf('Product %s (ID %d) created', (string) $product->getRef(), (int) $product->getId()), (int) $product->getId()];
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeUpdated(ProductUpdateEvent $event): array
    {
        if (!$event->hasProduct()) {
            return ['Product modified', null];
        }
        $product = $event->getProduct();

        return [\sprintf('Product %s (ID %d) modified', (string) $product->getRef(), (int) $product->getId()), (int) $product->getId()];
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function describeSeoUpdated(UpdateSeoEvent $event): array
    {
        return [\sprintf('Product SEO (ID %d) modified', (int) $event->getObjectId()), (int) $event->getObjectId()];
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedRows(int $categoryId, string $search, string $order, int $page): array
    {
        $locale = $this->defaultLocale();
        $query = ProductQuery::create();

        if ($categoryId > 0) {
            $query->useProductCategoryQuery()
                ->filterByCategoryId($categoryId)
                ->endUse();
        }

        if ($search !== '') {
            $titleIds = \Thelia\Model\ProductI18nQuery::create()
                ->filterByLocale($locale)
                ->filterByTitle('%'.$search.'%', Criteria::LIKE)
                ->select(['Id'])
                ->find()
                ->toArray();

            $query->_or()
                ->filterById($titleIds, Criteria::IN)
                ->_or()
                ->filterByRef('%'.$search.'%', Criteria::LIKE);
        }

        $this->applyOrder($query, $order);

        $total = (int) $query->count();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);

        $products = $query
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE)
            ->find();

        $rows = [];
        foreach ($products as $product) {
            \assert($product instanceof Product);
            $product->setLocale($locale);
            $rows[] = $this->productToRow($product);
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'pages' => $pages,
            'update_position_url' => $this->urls->generate('admin.products.update-position'),
            'update_position_token' => $this->tokens->assignToken(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productToRow(Product $product): array
    {
        $id = (int) $product->getId();

        $actions = [
            new RowAction(
                kind: 'edit',
                label: $this->translator->trans('Edit this product'),
                href: $this->urls->generate(self::EDIT_ROUTE, ['product_id' => $id]),
                grantedAttribute: AccessManager::UPDATE,
                grantedSubject: self::RESOURCE,
            ),
            new RowAction(
                kind: 'delete',
                label: $this->translator->trans('Delete this product'),
                modalTarget: '#product-delete-modal',
                grantedAttribute: AccessManager::DELETE,
                grantedSubject: self::RESOURCE,
                dataAttributes: [
                    'product-id' => $id,
                    'product-label' => (string) $product->getTitle(),
                ],
            ),
        ];

        return [
            'id' => $id,
            'ref' => (string) $product->getRef(),
            'title' => (string) $product->getTitle(),
            'visible' => (bool) $product->getVisible(),
            'position' => (int) $product->getPosition(),
            'toggle_visible_url' => $this->tokenizedUrl('admin.products.set-default', ['product_id' => $id]),
            '_actions' => $actions,
        ];
    }

    private function applyOrder(ProductQuery $query, string $order): void
    {
        match ($order) {
            'ref' => $query->orderByRef(Criteria::ASC),
            'ref_reverse' => $query->orderByRef(Criteria::DESC),
            'visible' => $query->orderByVisible(Criteria::ASC),
            'visible_reverse' => $query->orderByVisible(Criteria::DESC),
            'created' => $query->orderByCreatedAt(Criteria::ASC),
            'created_reverse' => $query->orderByCreatedAt(Criteria::DESC),
            default => $query->orderByPosition(Criteria::ASC),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryChoices(): array
    {
        $locale = $this->defaultLocale();
        $items = [['id' => 0, 'title' => $this->translator->trans('— All categories —')]];
        foreach (CategoryQuery::create()->orderById()->find() as $category) {
            $category->setLocale($locale);
            $items[] = ['id' => (int) $category->getId(), 'title' => (string) $category->getTitle()];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function currencyChoices(): array
    {
        $items = [];
        foreach (CurrencyQuery::create()->orderByPosition()->find() as $currency) {
            $items[] = ['id' => (int) $currency->getId(), 'name' => (string) $currency->getName(), 'default' => (bool) $currency->getByDefault()];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function taxRuleChoices(): array
    {
        $locale = $this->defaultLocale();
        $items = [];
        foreach (TaxRuleQuery::create()->orderById()->find() as $taxRule) {
            $taxRule->setLocale($locale);
            $items[] = ['id' => (int) $taxRule->getId(), 'title' => (string) $taxRule->getTitle()];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function templateChoices(): array
    {
        $locale = $this->defaultLocale();
        $items = [['id' => null, 'name' => $this->translator->trans('— No template —')]];
        foreach (TemplateQuery::create()->orderById()->find() as $template) {
            $template->setLocale($locale);
            $items[] = ['id' => (int) $template->getId(), 'name' => (string) $template->getName()];
        }

        return $items;
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
