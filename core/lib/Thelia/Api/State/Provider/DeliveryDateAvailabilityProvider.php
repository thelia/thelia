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

namespace Thelia\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Resource\DeliveryDateAvailability;
use Thelia\Domain\Localization\LocalizationFacade;
use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliveryDay;
use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliverySlotOffer;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryDateCalendar;
use Thelia\Model\LangQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Answers the days a carrier offers. A carrier that offers none is answered with an empty
 * window and the choice "none" rather than a 404: the client asked a fair question.
 */
final readonly class DeliveryDateAvailabilityProvider implements ProviderInterface
{
    public function __construct(
        private DeliveryDateCalendar $calendar,
        private LocalizationFacade $localization,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DeliveryDateAvailability
    {
        $module = ModuleQuery::create()
            ->filterById((int) ($uriVariables['moduleId'] ?? 0))
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE)
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->findOne() ?? throw new NotFoundHttpException('No such delivery module.');

        $availability = new DeliveryDateAvailability();
        $availability->moduleId = (int) $module->getId();

        $offer = $this->calendar->offerFor($module, locale: $this->locale());

        if (null === $offer) {
            return $availability;
        }

        $availability->choiceMode = $offer->choiceMode->value;
        $availability->days = array_map(
            static fn (DeliveryDay $day): array => [
                'date' => $day->date->format('Y-m-d'),
                'open' => $day->open,
                'available' => $day->available,
                'slots' => array_map(
                    static fn (DeliverySlotOffer $slot): array => [
                        'id' => $slot->id,
                        'title' => $slot->title,
                        'start' => $slot->startTime,
                        'end' => $slot->endTime,
                        'available' => $slot->available,
                    ],
                    $day->slots,
                ),
            ],
            $offer->days,
        );

        return $availability;
    }

    /**
     * The language asked for when the shop has it, the current one otherwise.
     */
    private function locale(): ?string
    {
        $asked = $this->requestStack->getCurrentRequest()?->query->get('locale');

        if (\is_string($asked) && null !== LangQuery::create()->filterByLocale($asked)->filterByActive(true)->findOne()) {
            return $asked;
        }

        return $this->localization->getCurrentLocale();
    }
}
