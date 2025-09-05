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

namespace Thelia\Core\HttpFoundation\Controller;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request as SfRequest;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;

#[AutoconfigureTag('controller.argument_value_resolver', attributes: ['priority' => 200])]
final class TheliaRequestValueResolver implements ValueResolverInterface
{
    public function resolve(SfRequest $request, ArgumentMetadata $argument): array
    {
        if ($argument->getType() !== TheliaRequest::class) {
            return [];
        }

        if ($request instanceof TheliaRequest) {
            return [$request];
        }

        return [TheliaRequest::createFromBase($request)];
    }
}
