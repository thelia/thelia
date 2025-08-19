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

use App\Kernel;
use Symfony\Component\HttpFoundation\Request as SfRequest;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;

require dirname(__DIR__).'/vendor/autoload_runtime.php';

SfRequest::setFactory(
    static fn (
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null,
    ) => new TheliaRequest($query, $request, $attributes, $cookies, $files, $server, $content)
);

return static function (array $context): Kernel {
    $thelia = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $request = TheliaRequest::createFromGlobals();
    $response = $thelia->handle($request);
    $response->send();
    $thelia->terminate($request, $response);

    return $thelia;
};
