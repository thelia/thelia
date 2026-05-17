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

namespace BackOfficeDefaultTwigBundle\UiComponents\DataTable;

final readonly class RowAction
{
    /**
     * @param string                   $kind             One of: 'edit', 'delete', 'view', 'custom'. Drives the default Bootstrap-icon glyph + variant.
     * @param ?string                  $href             Destination URL. Mutually exclusive with $modalTarget — if both null, the action becomes a no-op button.
     * @param ?string                  $modalTarget      CSS selector of a Bootstrap modal to open (e.g. '#delete_dialog'). Adds data-bs-toggle/target.
     * @param ?string                  $grantedAttribute Symfony voter attribute (VIEW/UPDATE/DELETE) required to render the action. Null = no permission gate.
     * @param string|array<string,mixed>|null $grantedSubject   Symfony voter subject (resource code string OR { resource, module } array as supported by AdminVoter).
     * @param array<string, scalar>    $dataAttributes   Extra `data-*` attributes (without the `data-` prefix). Useful to pass `lang-id` etc.
     */
    public function __construct(
        public string $kind,
        public string $label,
        public ?string $href = null,
        public ?string $modalTarget = null,
        public ?string $grantedAttribute = null,
        public string|array|null $grantedSubject = null,
        public array $dataAttributes = [],
    ) {
    }
}
