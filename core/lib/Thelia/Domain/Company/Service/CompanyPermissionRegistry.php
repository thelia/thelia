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

namespace Thelia\Domain\Company\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyPermissionProviderInterface;

/**
 * Every right a company role may hold: the ones of the core, then the ones the modules declare.
 *
 * A module cannot redefine a core code, and the first module to declare a code keeps it.
 */
final class CompanyPermissionRegistry
{
    /** @var array<string, string>|null */
    private ?array $permissions = null;

    /**
     * @param iterable<CompanyPermissionProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('thelia.company.permission_provider')]
        private readonly iterable $providers,
    ) {
    }

    /**
     * @return array<string, string> code => label
     */
    public function all(): array
    {
        if (null === $this->permissions) {
            $permissions = CompanyPermission::labels();

            foreach ($this->providers as $provider) {
                foreach ($provider->permissions() as $code => $label) {
                    $permissions[$code] ??= $label;
                }
            }

            $this->permissions = $permissions;
        }

        return $this->permissions;
    }

    public function isKnown(string $permission): bool
    {
        return \array_key_exists($permission, $this->all());
    }
}
