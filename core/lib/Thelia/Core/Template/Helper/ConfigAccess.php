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

namespace Thelia\Core\Template\Helper;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Thelia\Model\ConfigQuery;

/**
 * Reads a store configuration value, independently of any template engine.
 *
 * This is the engine-agnostic core behind the Smarty {config} plugin: a thin, injectable
 * accessor over ConfigQuery so any parser can read configuration through a small adapter
 * (and so it can be mocked in tests instead of hitting the static query).
 */
#[Autoconfigure(public: true)]
final readonly class ConfigAccess
{
    public function read(string $key, mixed $default = null): mixed
    {
        return ConfigQuery::read($key, $default);
    }
}
