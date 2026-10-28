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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        // Seconds a provider may stay silent before the call is given up: a model thinks for a while before its first token.
        ->set('thelia.ai.http_timeout', 60)
        // Seconds a whole call may last, a streamed answer included.
        ->set('thelia.ai.http_max_duration', 300)
        // The models of the moderation guardrails (docs.mistral.ai/capabilities/guardrailing, developers.openai.com/api/docs/guides/moderation).
        ->set('thelia.ai.moderation.mistral_model', 'mistral-moderation-2603')
        ->set('thelia.ai.moderation.openai_model', 'omni-moderation-latest')
        // The categories of Mistral that stop a call: the safety ones. `health`, `financial`, `law` and `pii` are advice
        // categories (a customer who gives an address is not an offender): they are noted in the verdict, never blocking.
        // A category a later version of the model adds is not blocking until it is listed here.
        ->set('thelia.ai.moderation.mistral_blocking_categories', ['sexual', 'hate_and_discrimination', 'violence_and_threats', 'dangerous', 'criminal', 'selfharm', 'jailbreaking']);
};
