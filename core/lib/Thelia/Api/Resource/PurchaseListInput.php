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
use Thelia\Domain\CustomerList\PurchaseListFacade;

/**
 * What a buyer sends to create, rename or copy a purchase list. Each operation
 * validates its own group: a title is required except on a copy, and lines are
 * only taken on a creation, a list saved from a cart or an order holding the lines
 * of its source.
 */
final class PurchaseListInput
{
    #[ApiProperty(description: 'The title of the list, at most 255 characters. Optional on a copy, which keeps the title of its source.', example: 'Monthly restock')]
    #[Assert\NotBlank(groups: [PurchaseList::VALIDATION_CREATE, PurchaseList::VALIDATION_FROM_SOURCE, PurchaseList::VALIDATION_RENAME])]
    #[Assert\Length(max: PurchaseListFacade::MAX_TITLE_LENGTH, groups: [PurchaseList::VALIDATION_CREATE, PurchaseList::VALIDATION_FROM_SOURCE, PurchaseList::VALIDATION_RENAME, PurchaseList::VALIDATION_DUPLICATE])]
    #[Groups([PurchaseList::GROUP_FRONT_WRITE])]
    public ?string $title = null;

    /**
     * @var list<array{reference: string, quantity: int, productSaleElementsId?: int|null}>|null
     */
    #[ApiProperty(
        description: 'On a creation only: the first lines of the list, at most 500, in the format of `POST /front/account/quick-order/resolve`.',
        example: [['reference' => 'TSHIRT-01', 'quantity' => 3]],
    )]
    #[ReferenceQuantityRows(groups: [PurchaseList::VALIDATION_CREATE])]
    #[Assert\IsNull(groups: [PurchaseList::VALIDATION_FROM_SOURCE, PurchaseList::VALIDATION_RENAME, PurchaseList::VALIDATION_DUPLICATE], message: 'Lines are only taken when a list is created.')]
    #[Groups([PurchaseList::GROUP_FRONT_WRITE])]
    public ?array $lines = null;
}
