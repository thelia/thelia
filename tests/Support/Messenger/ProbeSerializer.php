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

namespace Thelia\Tests\Support\Messenger;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\DependencyInjection\Compiler\HandledMessageClassesPass;
use Thelia\Messenger\Serializer\AllowedClassesSerializer;

/**
 * The serializer of the shop, letting ProbeMessage through as well: no handler takes
 * it, so the one of the container refuses it.
 */
final class ProbeSerializer
{
    public static function create(ContainerInterface $container): AllowedClassesSerializer
    {
        $inner = $container->get('messenger.transport.symfony_serializer');
        \assert($inner instanceof \Symfony\Component\Messenger\Transport\Serialization\SerializerInterface);
        $handled = $container->getParameter(HandledMessageClassesPass::PARAMETER);
        \assert(\is_array($handled));

        return new AllowedClassesSerializer($inner, [], [...array_map('strval', $handled), ProbeMessage::class]);
    }
}
