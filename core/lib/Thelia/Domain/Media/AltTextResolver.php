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

namespace Thelia\Domain\Media;

final readonly class AltTextResolver
{
    // A decorative image is published with an empty alt attribute (WCAG 1.1.1); any
    // other image reads its alt text, and the title until one has been written.
    public function resolve(?string $alt, bool $decorative, ?string $title): string
    {
        if ($decorative) {
            return '';
        }

        if (null !== $alt && '' !== trim($alt)) {
            return $alt;
        }

        return $title ?? '';
    }
}
