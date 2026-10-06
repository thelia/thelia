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

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Symfony\Component\Serializer\Attribute\Groups;
use Thelia\Api\State\Provider\DeliveryDateAvailabilityProvider;

/**
 * The days, and the slots of each day, a carrier offers from today on.
 *
 * Every day between the first and the last one offered is listed, closed and full days
 * included, so that a client can draw a calendar without computing a single date: the days
 * are calendar days of the shop, written Y-m-d, and the hours are local, written H:i. A slot
 * says whether it can still be taken, never how many orders it holds.
 *
 * Read again when the buyer is about to choose: slots fill while a page is open, and the
 * checkout judges the choice on what the shop computes at that moment, not on this answer.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/front/delivery_modules/{moduleId}/delivery_dates',
            uriVariables: ['moduleId'],
            openapi: new Operation(
                summary: 'List the delivery days and slots a carrier offers',
                parameters: [
                    new Parameter(name: 'moduleId', in: 'path', required: true, schema: ['type' => 'integer']),
                    new Parameter(name: 'locale', in: 'query', required: false, description: 'Language of the slot titles, the shop language by default.', schema: ['type' => 'string']),
                ],
            ),
            provider: DeliveryDateAvailabilityProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
)]
class DeliveryDateAvailability
{
    public const GROUP_FRONT_READ = 'front:delivery_date_availability:read';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_FRONT_READ])]
    public int $moduleId;

    #[ApiProperty(description: 'none, date (the buyer picks a day) or slot (the buyer picks a slot of a day).')]
    #[Groups([self::GROUP_FRONT_READ])]
    public string $choiceMode = 'none';

    /**
     * @var list<array{date: string, open: bool, available: bool, slots: list<array{id: int, title: ?string, start: string, end: string, available: bool}>}>
     */
    #[ApiProperty(
        description: 'Every day of the window in order. open: the carrier delivers that day; available: it can still be picked (for slots, one of them is left).',
        example: [['date' => '2026-10-09', 'open' => true, 'available' => true, 'slots' => [['id' => 4, 'title' => 'Morning', 'start' => '09:00', 'end' => '11:00', 'available' => true]]]],
    )]
    #[Groups([self::GROUP_FRONT_READ])]
    public array $days = [];
}
