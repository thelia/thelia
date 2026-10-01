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

namespace Thelia\Api\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;

/**
 * The shape of the lines a buyer sends: a reference and a quantity each, and the
 * sale element picked for a reference several of them share. Nothing else is
 * taken, so a price or a title sent along is refused rather than ignored.
 *
 * Shared by the quick order and the purchase lists, which receive the same lines.
 * Whether an empty list is acceptable is left to the field using it.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ReferenceQuantityRows extends Compound
{
    /**
     * The groups are handed down to every nested constraint. Left to the compound
     * alone, they would stop at its first level: the constraints inside All and
     * Collection keep the Default group, and are skipped by any other group.
     *
     * @var list<string>|null
     */
    private ?array $nestedGroups;

    /**
     * @param list<string>|null $groups
     */
    public function __construct(?array $groups = null, mixed $payload = null)
    {
        $this->nestedGroups = $groups;

        parent::__construct(null, $groups, $payload);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<\Symfony\Component\Validator\Constraint>
     */
    protected function getConstraints(array $options): array
    {
        $groups = $this->nestedGroups;

        return [
            new Assert\Count(max: ReferenceQuantityLines::MAX_LINES, groups: $groups),
            new Assert\All(
                constraints: [
                    new Assert\Collection(
                        fields: [
                            'reference' => [
                                new Assert\NotBlank(groups: $groups),
                                new Assert\Type('string', groups: $groups),
                                new Assert\Length(max: ReferenceQuantityLines::MAX_REFERENCE_LENGTH, groups: $groups),
                            ],
                            'quantity' => [
                                new Assert\NotNull(groups: $groups),
                                new Assert\Type('int', groups: $groups),
                                new Assert\Positive(groups: $groups),
                                new Assert\LessThanOrEqual(ReferenceQuantityLines::MAX_QUANTITY, groups: $groups),
                            ],
                            'productSaleElementsId' => new Assert\Optional(
                                [new Assert\Type('int', groups: $groups), new Assert\Positive(groups: $groups)],
                                groups: $groups,
                            ),
                        ],
                        groups: $groups,
                    ),
                ],
                groups: $groups,
            ),
        ];
    }
}
