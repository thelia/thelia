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

namespace Thelia\Type;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class JsonType extends BaseType
{
    public function getType(): string
    {
        return 'Json type';
    }

    public function isValid($value): bool
    {
        json_decode((string) $value, true);

        return json_last_error() == \JSON_ERROR_NONE;
    }

    public function getFormattedValue($value)
    {
        return $this->isValid($value) ? json_decode((string) $value, true) : null;
    }

    public function getFormOptions(): array
    {
        return [];
    }
}
