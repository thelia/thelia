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

namespace BackOfficeDefaultTwigBundle\Service\Coupon;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Condition\ConditionCollection;
use Thelia\Condition\Implementation\ConditionInterface;
use Thelia\Domain\Promotion\Coupon\CouponFactory;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Type\CouponInterface;
use Thelia\Model\Coupon;
use Thelia\Model\CouponCountry;
use Thelia\Model\CouponModule;
use Thelia\Model\CouponQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

final readonly class CouponEditContextBuilder
{
    public function __construct(
        #[Autowire(service: 'thelia.coupon.manager')]
        private CouponManager $couponManager,
        #[Autowire(service: 'thelia.coupon.factory')]
        private CouponFactory $couponFactory,
        private CouponInputsRenderer $inputsRenderer,
        private TranslatorInterface $translator,
    ) {
    }

    /** @return array<string, mixed> */
    public function buildForCreate(string $locale): array
    {
        $now = new \DateTime();
        $context = $this->buildCommon($locale);
        $context['mode'] = 'create';
        $context['coupon'] = null;
        $context['coupon_id'] = null;
        $context['data'] = $this->defaultData($locale);
        $context['data']['startDate'] = $now->format($context['date_format']);
        $context['data']['expirationDate'] = (clone $now)->modify('+2 months')->format($context['date_format']);
        $context['coupon_inputs_html'] = '';
        $context['conditions'] = [];
        $context['no_conditions'] = true;

        return $context;
    }

    /** @return array<string, mixed> */
    public function buildForUpdate(Coupon $coupon, string $locale): array
    {
        $coupon->setLocale($locale);
        $manager = $this->couponFactory->buildCouponFromModel($coupon);
        \assert($manager instanceof CouponInterface);

        $conditionCollection = $manager->getConditions();

        $context = $this->buildCommon($locale);
        $context['mode'] = 'update';
        $context['coupon'] = $coupon;
        $context['coupon_id'] = (int) $coupon->getId();
        $context['data'] = $this->dataFromModel($coupon, $locale);
        $context['coupon_inputs_html'] = $this->inputsRenderer->renderForServiceId((string) $coupon->getType(), $manager);
        $context['conditions'] = $this->summarizeConditions($conditionCollection);
        $context['no_conditions'] = false;

        return $context;
    }

    public function renderCouponInputs(string $couponServiceId, ?CouponInterface $manager): string
    {
        return $this->inputsRenderer->renderForServiceId($couponServiceId, $manager);
    }

    /**
     * @return list<array{serviceId: string, index: int, name: string, toolTip: string, summary: string}>
     */
    public function summarizeConditions(ConditionCollection $conditions): array
    {
        $items = [];
        foreach ($conditions as $index => $condition) {
            \assert($condition instanceof ConditionInterface);
            $items[] = [
                'serviceId' => $condition->getServiceId(),
                'index' => (int) $index,
                'name' => $condition->getName(),
                'toolTip' => $condition->getToolTip(),
                'summary' => $condition->getSummary(),
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function buildCommon(string $locale): array
    {
        $dateFormat = $this->resolveDateFormat();

        return [
            'locale' => $locale,
            'date_format' => $dateFormat,
            'available_coupons' => $this->availableCoupons(),
            'available_conditions' => $this->availableConditions(),
            'countries' => $this->countryChoices(),
            'shipping_modules' => $this->shippingModuleChoices(),
            'per_customer_choices' => [
                ['value' => 1, 'label' => $this->translator->trans('Per customer')],
                ['value' => 0, 'label' => $this->translator->trans('Overall')],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function defaultData(string $locale): array
    {
        return [
            'code' => '',
            'title' => '',
            'type' => '',
            'shortDescription' => '',
            'description' => '',
            'isEnabled' => true,
            'startDate' => '',
            'expirationDate' => '',
            'isAvailableOnSpecialOffers' => false,
            'isCumulative' => false,
            'isRemovingPostage' => false,
            'maxUsage' => -1,
            'perCustomerUsageCount' => 1,
            'freeShippingForCountries' => [0],
            'freeShippingForModules' => [0],
            'locale' => $locale,
        ];
    }

    /** @return array<string, mixed> */
    private function dataFromModel(Coupon $coupon, string $locale): array
    {
        $countries = [];
        foreach ($coupon->getFreeShippingForCountries() as $row) {
            \assert($row instanceof CouponCountry);
            $countries[] = (int) $row->getCountryId();
        }
        $modules = [];
        foreach ($coupon->getFreeShippingForModules() as $row) {
            \assert($row instanceof CouponModule);
            $modules[] = (int) $row->getModuleId();
        }

        $dateFormat = $this->resolveDateFormat();

        return [
            'code' => (string) $coupon->getCode(),
            'title' => (string) $coupon->getTitle(),
            'type' => (string) $coupon->getType(),
            'shortDescription' => (string) $coupon->getShortDescription(),
            'description' => (string) $coupon->getDescription(),
            'isEnabled' => (bool) $coupon->getIsEnabled(),
            'startDate' => $coupon->getStartDate() === null ? '' : (string) $coupon->getStartDate($dateFormat),
            'expirationDate' => (string) $coupon->getExpirationDate($dateFormat),
            'isAvailableOnSpecialOffers' => (bool) $coupon->getIsAvailableOnSpecialOffers(),
            'isCumulative' => (bool) $coupon->getIsCumulative(),
            'isRemovingPostage' => (bool) $coupon->getIsRemovingPostage(),
            'maxUsage' => (int) $coupon->getMaxUsage(),
            'perCustomerUsageCount' => $coupon->getPerCustomerUsageCount() ? 1 : 0,
            'freeShippingForCountries' => $countries === [] ? [0] : $countries,
            'freeShippingForModules' => $modules === [] ? [0] : $modules,
            'locale' => $locale,
        ];
    }

    /**
     * @return list<array{serviceId: string, name: string, toolTip: string}>
     */
    private function availableCoupons(): array
    {
        $items = [];
        foreach ($this->couponManager->getAvailableCoupons() as $coupon) {
            \assert($coupon instanceof CouponInterface);
            $items[] = [
                'serviceId' => $coupon->getServiceId(),
                'name' => $coupon->getName(),
                'toolTip' => $coupon->getToolTip(),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{serviceId: string, name: string, toolTip: string}>
     */
    private function availableConditions(): array
    {
        $items = [];
        foreach ($this->couponManager->getAvailableConditions() as $condition) {
            \assert($condition instanceof ConditionInterface);
            $items[] = [
                'serviceId' => $condition->getServiceId(),
                'name' => $condition->getName(),
                'toolTip' => $condition->getToolTip(),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function countryChoices(): array
    {
        $items = [['value' => 0, 'label' => $this->translator->trans('All countries')]];
        foreach (CountryQuery::create()->find() as $country) {
            $items[] = ['value' => (int) $country->getId(), 'label' => (string) $country->getTitle()];
        }

        usort($items, static fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $items;
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function shippingModuleChoices(): array
    {
        $items = [['value' => 0, 'label' => $this->translator->trans('All shipping methods')]];
        $modules = ModuleQuery::create()
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE)
            ->find();
        foreach ($modules as $module) {
            $items[] = ['value' => (int) $module->getId(), 'label' => (string) $module->getTitle()];
        }

        usort($items, static fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $items;
    }

    private function resolveDateFormat(): string
    {
        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return $defaultLang?->getDatetimeFormat() ?? 'Y-m-d H:i:s';
    }
}
