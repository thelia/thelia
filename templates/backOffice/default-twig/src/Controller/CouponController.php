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

namespace BackOfficeDefaultTwigBundle\Controller;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use BackOfficeDefaultTwigBundle\Service\Admin\AdminFormAction;
use BackOfficeDefaultTwigBundle\Service\Coupon\CouponConditionsRenderer;
use BackOfficeDefaultTwigBundle\Service\Coupon\CouponEditContextBuilder;
use BackOfficeDefaultTwigBundle\UiComponents\DataTable\RowAction;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\ConditionFactory;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\Coupon\CouponDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Promotion\Coupon\CouponFactory;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Model\Coupon;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponModule;
use Thelia\Model\CouponQuery;
use Thelia\Model\LangQuery;
use Thelia\Tools\TokenProvider;
use Twig\Environment;

#[Route('/admin/coupon', name: 'admin.coupon.')]
final class CouponController
{
    private const RESOURCE = AdminResources::COUPON;
    private const LIST_ROUTE = 'admin.coupon.default';
    private const EDIT_ROUTE = 'admin.coupon.update';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/coupon/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/coupon/edit.html.twig';
    private const CONDITIONS_TEMPLATE = '@BackOfficeDefaultTwig/coupon/conditions.html.twig';
    private const CONDITION_INPUT_TEMPLATE = '@BackOfficeDefaultTwig/coupon/condition-input-ajax.html.twig';

    public function __construct(
        private readonly AdminFormAction $action,
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenProvider $tokens,
        private readonly TranslatorInterface $translator,
        private readonly EventDispatcherInterface $events,
        private readonly CouponEditContextBuilder $contextBuilder,
        private readonly CouponConditionsRenderer $conditionsRenderer,
        #[Autowire(service: 'thelia.coupon.factory')]
        private readonly CouponFactory $couponFactory,
        #[Autowire(service: 'thelia.condition.factory')]
        private readonly ConditionFactory $conditionFactory,
        #[Autowire(service: 'thelia.coupon.manager')]
        private readonly CouponManager $couponManager,
    ) {
    }

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->defaultLocale();
        $rows = [];
        foreach (CouponQuery::create()->orderByCode()->find() as $coupon) {
            \assert($coupon instanceof Coupon);
            $coupon->setLocale($locale);
            $rows[] = $this->couponToRow($coupon);
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'rows' => $rows,
        ]));
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::CREATE)) {
            return $denied;
        }

        if ($request->isMethod('POST')) {
            return $this->handleCreateOrUpdate($request, null);
        }

        $context = $this->contextBuilder->buildForCreate($this->defaultLocale());

        return new Response($this->twig->render(self::EDIT_TEMPLATE, $context));
    }

    #[Route('/update/{couponId}', name: 'update', methods: ['GET', 'POST'], requirements: ['couponId' => '\d+'])]
    public function update(Request $request, int $couponId): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $coupon = CouponQuery::create()->findPk($couponId);
        if ($coupon === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        if ($request->isMethod('POST')) {
            return $this->handleCreateOrUpdate($request, $coupon);
        }

        $context = $this->contextBuilder->buildForUpdate($coupon, $this->defaultLocale());

        return new Response($this->twig->render(self::EDIT_TEMPLATE, $context));
    }

    #[Route('/delete', name: 'delete', methods: ['POST', 'GET'])]
    public function delete(Request $request): Response
    {
        $coupon = CouponQuery::create()->findPk((int) $request->get('coupon_id', 0));
        if ($coupon === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        return $this->action->tokenAction(
            resource: self::RESOURCE,
            access: AccessManager::DELETE,
            request: $request,
            event: new CouponDeleteEvent($coupon),
            eventName: TheliaEvents::COUPON_DELETE,
            actionLabel: 'Coupon deletion',
            successRoute: self::LIST_ROUTE,
        );
    }

    #[Route('/draw/inputs/{couponServiceId}', name: 'draw.inputs.ajax', methods: ['GET', 'POST'], requirements: ['couponServiceId' => '.+'])]
    public function drawInputs(string $couponServiceId): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $html = $this->contextBuilder->renderCouponInputs($couponServiceId, null);

        return new JsonResponse([$html]);
    }

    #[Route('/draw/conditionsSummaries/{couponId}', name: 'draw.condition.summaries.ajax', methods: ['GET'], requirements: ['couponId' => '\d+'])]
    public function drawConditionSummaries(int $couponId): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $coupon = CouponQuery::create()->findPk($couponId);
        if ($coupon === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $manager = $this->couponFactory->buildCouponFromModel($coupon);
        if (!$manager instanceof CouponInterface) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response($this->twig->render(self::CONDITIONS_TEMPLATE, [
            'conditions' => $this->contextBuilder->summarizeConditions($manager->getConditions()),
            'coupon_id' => $couponId,
        ]));
    }

    #[Route('/draw/read/conditionInputs/{conditionId}', name: 'draw.condition.read.inputs.ajax', methods: ['GET', 'POST'], requirements: ['conditionId' => '.+'])]
    public function drawConditionReadInputs(string $conditionId): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        return new Response($this->conditionsRenderer->renderEmptyInputs($conditionId));
    }

    #[Route('/draw/update/conditionInputs/{couponId}/{conditionIndex}', name: 'draw.condition.update.inputs.ajax', methods: ['GET'], requirements: ['couponId' => '\d+', 'conditionIndex' => '\d+'])]
    public function drawConditionUpdateInputs(int $couponId, int $conditionIndex): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $coupon = CouponQuery::create()->findPk($couponId);
        if ($coupon === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $manager = $this->couponFactory->buildCouponFromModel($coupon);
        if (!$manager instanceof CouponInterface) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $conditions = $manager->getConditions();
        if (!isset($conditions[$conditionIndex])) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response($this->conditionsRenderer->renderExistingInputs($conditions[$conditionIndex], $conditionIndex));
    }

    #[Route('/{couponId}/condition/save', name: 'condition.save', methods: ['POST'], requirements: ['couponId' => '\d+'])]
    public function conditionSave(Request $request, int $couponId): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $coupon = CouponQuery::create()->findPk($couponId);
        if ($coupon === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $manager = $this->couponFactory->buildCouponFromModel($coupon);
        if (!$manager instanceof CouponInterface) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $built = $this->conditionsRenderer->buildConditionFromRequest($request);
        $conditions = $manager->getConditions();
        $conditionIndex = $request->request->get('conditionIndex');

        if ($conditionIndex !== null && $conditionIndex !== '' && (int) $conditionIndex >= 0) {
            $conditions[(int) $conditionIndex] = $built;
        } else {
            $conditions[] = $built;
        }

        $manager->setConditions($conditions);
        $this->dispatchConditionUpdate($coupon, $conditions);

        return new Response();
    }

    #[Route('/{couponId}/condition/delete/{conditionIndex}', name: 'condition.delete', methods: ['GET', 'POST'], requirements: ['couponId' => '\d+', 'conditionIndex' => '\d+'])]
    public function conditionDelete(int $couponId, int $conditionIndex): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $coupon = CouponQuery::create()->findPk($couponId);
        if ($coupon === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $manager = $this->couponFactory->buildCouponFromModel($coupon);
        if (!$manager instanceof CouponInterface) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $conditions = $manager->getConditions();
        unset($conditions[$conditionIndex]);

        $manager->setConditions($conditions);
        $this->dispatchConditionUpdate($coupon, $conditions);

        return new Response();
    }

    #[Route('/{couponId}/condition/reorder', name: 'condition.reorder', methods: ['POST'], requirements: ['couponId' => '\d+'])]
    public function conditionReorder(Request $request, int $couponId): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $coupon = CouponQuery::create()->findPk($couponId);
        if ($coupon === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $token = (string) $request->query->get('_token', $request->request->get('_token', ''));
        $this->tokens->checkToken($token);

        $manager = $this->couponFactory->buildCouponFromModel($coupon);
        if (!$manager instanceof CouponInterface) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $fromIndex = (int) $request->query->get('id', $request->request->get('id', -1));
        $newPosition = (int) $request->query->get('position', $request->request->get('position', 0));

        $current = iterator_to_array($manager->getConditions());
        if (!isset($current[$fromIndex])) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $moved = $current[$fromIndex];
        unset($current[$fromIndex]);
        $reordered = array_values($current);

        $targetIndex = max(0, min(\count($reordered), $newPosition - 1));
        array_splice($reordered, $targetIndex, 0, [$moved]);

        $collection = new ConditionCollection();
        foreach ($reordered as $condition) {
            $collection[] = $condition;
        }

        $manager->setConditions($collection);
        $this->dispatchConditionUpdate($coupon, $collection);

        return new Response();
    }

    private function handleCreateOrUpdate(Request $request, ?Coupon $coupon): Response
    {
        $eventName = $coupon === null ? TheliaEvents::COUPON_CREATE : TheliaEvents::COUPON_UPDATE;
        $data = $request->request->all();

        $dateFormat = $this->defaultDateFormat();
        $expiration = \DateTime::createFromFormat($dateFormat, (string) ($data['expirationDate'] ?? ''));
        $start = isset($data['startDate']) && $data['startDate'] !== ''
            ? (\DateTime::createFromFormat($dateFormat, (string) $data['startDate']) ?: null)
            : null;

        if ($expiration === false) {
            $expiration = new \DateTime('+2 months');
        }

        $serviceId = urldecode((string) ($data['type'] ?? ''));
        $couponTypeManager = $this->couponManager->isCouponAvailable($serviceId);
        if ($couponTypeManager === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $effects = $couponTypeManager->getEffects($data);

        $event = new CouponCreateOrUpdateEvent(
            (string) ($data['code'] ?? ''),
            $serviceId,
            (string) ($data['title'] ?? ''),
            $effects,
            $this->nullable($data['shortDescription'] ?? null),
            $this->nullable($data['description'] ?? null),
            $this->boolish($data['isEnabled'] ?? null),
            $expiration,
            $this->boolish($data['isAvailableOnSpecialOffers'] ?? null),
            $this->boolish($data['isCumulative'] ?? null),
            $this->boolish($data['isRemovingPostage'] ?? null),
            $this->maxUsage($data['maxUsage'] ?? null, $data['is-unlimited'] ?? null),
            (string) ($data['locale'] ?? $this->defaultLocale()),
            $this->arrayify($data['freeShippingForCountries'] ?? []),
            $this->arrayify($data['freeShippingForModules'] ?? []),
            ((int) ($data['perCustomerUsageCount'] ?? 0)) === 1,
            $start,
        );

        if ($coupon !== null) {
            $event->setCouponModel($coupon);
        }

        try {
            $this->events->dispatch($event, $eventName);
        } catch (\Throwable $exception) {
            return $this->renderWithError($request, $coupon, $exception->getMessage());
        }

        $savedCoupon = $event->getCouponModel();
        $couponId = (int) $savedCoupon->getId();

        if (($data['save_mode'] ?? '') === 'stay') {
            return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['couponId' => $couponId]));
        }

        return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
    }

    private function renderWithError(Request $request, ?Coupon $coupon, string $message): Response
    {
        $locale = $this->defaultLocale();
        $context = $coupon === null
            ? $this->contextBuilder->buildForCreate($locale)
            : $this->contextBuilder->buildForUpdate($coupon, $locale);
        $context['error_message'] = $message;
        $context['posted'] = $request->request->all();

        return new Response($this->twig->render(self::EDIT_TEMPLATE, $context));
    }

    private function dispatchConditionUpdate(Coupon $coupon, ConditionCollection $conditions): void
    {
        $freeShippingCountries = [];
        foreach ($coupon->getFreeShippingForCountries() as $row) {
            \assert($row instanceof CouponCountry);
            $freeShippingCountries[] = (int) $row->getCountryId();
        }
        $freeShippingModules = [];
        foreach ($coupon->getFreeShippingForModules() as $row) {
            \assert($row instanceof CouponModule);
            $freeShippingModules[] = (int) $row->getModuleId();
        }

        $event = new CouponCreateOrUpdateEvent(
            (string) $coupon->getCode(),
            (string) $coupon->getType(),
            (string) $coupon->getTitle(),
            $coupon->getEffects(),
            $coupon->getShortDescription(),
            $coupon->getDescription(),
            (bool) $coupon->getIsEnabled(),
            $coupon->getExpirationDate(),
            (bool) $coupon->getIsAvailableOnSpecialOffers(),
            (bool) $coupon->getIsCumulative(),
            (bool) $coupon->getIsRemovingPostage(),
            $coupon->getMaxUsage(),
            (string) $coupon->getLocale(),
            $freeShippingCountries,
            $freeShippingModules,
            (bool) $coupon->getPerCustomerUsageCount(),
            $coupon->getStartDate(),
        );
        $event->setCouponModel($coupon);
        $event->setConditions($conditions);

        $this->events->dispatch($event, TheliaEvents::COUPON_CONDITION_UPDATE);
    }

    /** @return array<string, mixed> */
    private function couponToRow(Coupon $coupon): array
    {
        $id = (int) $coupon->getId();
        $editHref = $this->urls->generate(self::EDIT_ROUTE, ['couponId' => $id]);
        $actions = [
            new RowAction(kind: 'edit', label: $this->translator->trans('Edit'), href: $editHref, grantedAttribute: AccessManager::UPDATE, grantedSubject: self::RESOURCE),
            new RowAction(kind: 'delete', label: $this->translator->trans('Delete'), modalTarget: '#coupon-delete-modal', grantedAttribute: AccessManager::DELETE, grantedSubject: self::RESOURCE, dataAttributes: ['coupon-id' => $id, 'coupon-label' => (string) $coupon->getCode()]),
        ];

        return [
            'id' => $id,
            'code' => (string) $coupon->getCode(),
            'title' => (string) $coupon->getTitle(),
            'type' => (string) $coupon->getType(),
            'enabled' => (bool) $coupon->getIsEnabled(),
            'enabled_label' => $coupon->getIsEnabled() ? $this->translator->trans('Enabled') : $this->translator->trans('Disabled'),
            '_actions' => $actions,
        ];
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }

    private function defaultDateFormat(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getDatetimeFormat() ?? 'Y-m-d H:i:s';
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function boolish(mixed $value): bool
    {
        if (\is_string($value)) {
            $normalized = strtolower($value);

            return $normalized === 'on' || $normalized === '1' || $normalized === 'true';
        }

        return (bool) $value;
    }

    private function maxUsage(mixed $value, mixed $unlimited): ?int
    {
        if ($this->boolish($unlimited)) {
            return -1;
        }

        if ($value === null || $value === '') {
            return -1;
        }

        return (int) $value;
    }

    /** @return list<int> */
    private function arrayify(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn ($item): int => (int) $item, $value));
    }
}
