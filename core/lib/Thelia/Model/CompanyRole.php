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

namespace Thelia\Model;

use Thelia\Model\Base\CompanyRole as BaseCompanyRole;

class CompanyRole extends BaseCompanyRole
{
    /**
     * The permissions the role grants.
     *
     * @return list<string>
     */
    public function getPermissionList(): array
    {
        return self::decodePermissions((string) $this->getPermissions());
    }

    /**
     * What the stored JSON list grants; a value that is not a list of strings grants nothing.
     *
     * @return list<string>
     */
    public static function decodePermissions(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!\is_array($decoded) || !array_is_list($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, is_string(...)));
    }
}
