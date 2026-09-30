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
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Thelia\Api\Validator\ReferenceQuantityRows;

/**
 * The lines a buyer typed or imported: a reference and a quantity each, and the
 * sale element picked for a reference several of them share.
 *
 * Nothing else is taken. Titles, prices and stock come back from the shop, so a
 * price sent here would be a price the buyer chose.
 */
final class QuickOrderInput
{
    /**
     * @var list<array{reference: string, quantity: int, productSaleElementsId?: int|null}>
     */
    #[ApiProperty(
        description: 'The lines to resolve, at most 500. A reference given twice for the same sale element is resolved once, quantities added up.',
        required: true,
        example: [
            ['reference' => 'TSHIRT-01', 'quantity' => 3],
            ['reference' => 'TSHIRT-01', 'quantity' => 2, 'productSaleElementsId' => 412],
            ['reference' => '3760123450012', 'quantity' => 1],
        ],
    )]
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[ReferenceQuantityRows]
    #[Groups([QuickOrder::GROUP_FRONT_WRITE, PurchaseList::GROUP_FRONT_ITEMS_WRITE])]
    public ?array $lines = null;
}
