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

namespace Thelia\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Domain\Ai\AsAiUsage;

/**
 * Collects the usages of the AI layer that services declare with #[AsAiUsage].
 *
 * The kernel tags every autoconfigured service that carries the attribute (the services of the modules
 * included); this pass reads the attributes of the tagged classes and publishes `usage id => class` as the
 * parameter `thelia.ai.usage_classes`. Two services declaring the same identifier stop the build: the
 * log, the budgets and the settings of the merchant are keyed by it.
 */
final class RegisterAiUsagePass implements CompilerPassInterface
{
    public const string TAG = 'thelia.ai.usage';

    public const string PARAMETER = 'thelia.ai.usage_classes';

    public function process(ContainerBuilder $container): void
    {
        $usageClasses = [];

        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $serviceId) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($serviceId)->getClass() ?? $serviceId);

            if (!\is_string($class) || !class_exists($class)) {
                continue;
            }

            foreach ((new \ReflectionClass($class))->getAttributes(AsAiUsage::class) as $attribute) {
                $usageId = $attribute->newInstance()->id;

                if (isset($usageClasses[$usageId])) {
                    throw new \LogicException(\sprintf('The AI usage "%s" is declared twice, by "%s" and by "%s". Each usage identifier must be unique: prefix it with the code of your module.', $usageId, $usageClasses[$usageId], $class));
                }

                $usageClasses[$usageId] = $class;
            }
        }

        ksort($usageClasses);

        $container->setParameter(self::PARAMETER, $usageClasses);
    }
}
