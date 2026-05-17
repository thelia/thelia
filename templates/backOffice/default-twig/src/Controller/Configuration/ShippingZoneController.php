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

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\ShippingZone\ShippingZoneAddAreaEvent;
use Thelia\Core\Event\ShippingZone\ShippingZoneRemoveAreaEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\AreaQuery;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Twig\Environment;

#[Route('/admin/configuration/shipping_zones', name: 'admin.configuration.shipping-zones.')]
final class ShippingZoneController
{
    private const RESOURCE = AdminResources::SHIPPING_ZONE;
    private const LIST_ROUTE = 'admin.configuration.shipping-zones.default';
    private const EDIT_ROUTE = 'admin.configuration.shipping-zones.update.view';
    private const LIST_TEMPLATE = '@BackOfficeDefaultTwig/configuration/shipping-zone/list.html.twig';
    private const EDIT_TEMPLATE = '@BackOfficeDefaultTwig/configuration/shipping-zone/edit.html.twig';

    public function __construct(
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    #[Route('', name: 'default', methods: ['GET'])]
    public function list(): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->defaultLocale();

        $modules = ModuleQuery::create()
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE)
            ->joinWithI18n($locale)
            ->orderByCode()
            ->find();

        $rows = [];
        foreach ($modules as $module) {
            $module->setLocale($locale);
            $rows[] = [
                'id' => (int) $module->getId(),
                'code' => (string) $module->getCode(),
                'title' => (string) $module->getTitle(),
                'zones' => $this->describeZonesForModule((int) $module->getId(), $locale),
                'edit_url' => $this->urls->generate(self::EDIT_ROUTE, ['delivery_module_id' => (int) $module->getId()]),
            ];
        }

        $unattachedAreas = $this->unattachedAreas($locale);

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'rows' => $rows,
            'unattached_areas' => $unattachedAreas,
        ]));
    }

    #[Route('/update/{delivery_module_id}', name: 'update.view', methods: ['GET'], requirements: ['delivery_module_id' => '\d+'])]
    public function updateView(int $delivery_module_id): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->defaultLocale();

        $module = ModuleQuery::create()->findPk($delivery_module_id);
        if ($module === null) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }
        $module->setLocale($locale);

        $associated = $this->associatedAreas($delivery_module_id, $locale);
        $availableAreas = $this->availableAreas($delivery_module_id, $locale);

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'module' => $module,
            'delivery_module_id' => $delivery_module_id,
            'associated_areas' => $associated,
            'available_areas' => $availableAreas,
        ]));
    }

    #[Route('/area/add', name: 'area.add', methods: ['POST'])]
    public function addArea(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $deliveryModuleId = (int) $request->request->get('shipping_zone_id', 0);
        $areaId = (int) $request->request->get('area_id', 0);

        if ($deliveryModuleId === 0 || $areaId === 0) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $event = new ShippingZoneAddAreaEvent($areaId, $deliveryModuleId);
        $this->events->dispatch($event, TheliaEvents::SHIPPING_ZONE_ADD_AREA);

        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['delivery_module_id' => $deliveryModuleId]));
    }

    #[Route('/area/remove', name: 'area.remove', methods: ['POST'])]
    public function removeArea(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $deliveryModuleId = (int) $request->request->get('shipping_zone_id', 0);
        $areaId = (int) $request->request->get('area_id', 0);

        if ($deliveryModuleId === 0 || $areaId === 0) {
            return new RedirectResponse($this->urls->generate(self::LIST_ROUTE));
        }

        $event = new ShippingZoneRemoveAreaEvent($areaId, $deliveryModuleId);
        $this->events->dispatch($event, TheliaEvents::SHIPPING_ZONE_REMOVE_AREA);

        return new RedirectResponse($this->urls->generate(self::EDIT_ROUTE, ['delivery_module_id' => $deliveryModuleId]));
    }

    /** @return list<array{id: int, name: string, countries: string}> */
    private function describeZonesForModule(int $moduleId, string $locale): array
    {
        $deliveryRows = AreaDeliveryModuleQuery::create()
            ->filterByDeliveryModuleId($moduleId)
            ->find();

        $zones = [];
        foreach ($deliveryRows as $row) {
            $area = AreaQuery::create()->findPk((int) $row->getAreaId());
            if ($area === null) {
                continue;
            }
            $zones[] = [
                'id' => (int) $area->getId(),
                'name' => (string) $area->getName(),
                'countries' => $this->describeCountries((int) $area->getId(), $locale),
            ];
        }

        return $zones;
    }

    private function describeCountries(int $areaId, string $locale): string
    {
        $titles = [];
        foreach (CountryAreaQuery::create()->filterByAreaId($areaId)->find() as $countryArea) {
            $country = CountryQuery::create()->findPk((int) $countryArea->getCountryId());
            if ($country !== null) {
                $country->setLocale($locale);
                $titles[] = (string) $country->getTitle();
            }
        }

        return implode(', ', $titles);
    }

    /** @return list<array{id: int, name: string}> */
    private function associatedAreas(int $moduleId, string $locale): array
    {
        $deliveryRows = AreaDeliveryModuleQuery::create()
            ->filterByDeliveryModuleId($moduleId)
            ->find();

        $areas = [];
        foreach ($deliveryRows as $row) {
            $area = AreaQuery::create()->findPk((int) $row->getAreaId());
            if ($area === null) {
                continue;
            }
            $areas[] = [
                'id' => (int) $area->getId(),
                'name' => (string) $area->getName(),
            ];
        }

        usort($areas, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $areas;
    }

    /** @return list<array{id: int, name: string}> */
    private function availableAreas(int $moduleId, string $locale): array
    {
        $assignedIds = [];
        foreach (AreaDeliveryModuleQuery::create()->filterByDeliveryModuleId($moduleId)->find() as $row) {
            $assignedIds[] = (int) $row->getAreaId();
        }

        $query = AreaQuery::create()->orderByName();
        if ($assignedIds !== []) {
            $query->filterById($assignedIds, \Propel\Runtime\ActiveQuery\Criteria::NOT_IN);
        }

        $available = [];
        foreach ($query->find() as $area) {
            $available[] = [
                'id' => (int) $area->getId(),
                'name' => (string) $area->getName(),
            ];
        }

        return $available;
    }

    /** @return list<array{id: int, name: string}> */
    private function unattachedAreas(string $locale): array
    {
        $attachedIds = [];
        foreach (AreaDeliveryModuleQuery::create()->find() as $row) {
            $attachedIds[] = (int) $row->getAreaId();
        }

        $query = AreaQuery::create()->orderByName();
        if ($attachedIds !== []) {
            $query->filterById($attachedIds, \Propel\Runtime\ActiveQuery\Criteria::NOT_IN);
        }

        $orphans = [];
        foreach ($query->find() as $area) {
            $orphans[] = [
                'id' => (int) $area->getId(),
                'name' => (string) $area->getName(),
            ];
        }

        return $orphans;
    }

    private function defaultLocale(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getLocale() ?? 'en_US';
    }
}
