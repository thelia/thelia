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

namespace Thelia\Domain\Ai\Provider;

use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Platform;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thelia\Domain\Ai\AiProvider;

/**
 * Mistral (La Plateforme).
 *
 * @internal
 */
final class MistralAdapter extends AbstractPlatformAdapter
{
    public function supports(AiProvider $provider): bool
    {
        return AiProvider::Mistral === $provider;
    }

    protected function platform(AiTarget $target, #[\SensitiveParameter] ?string $apiKey, string $baseUrl, HttpClientInterface $httpClient): Platform
    {
        return Factory::createPlatform((string) $apiKey, $httpClient, baseUrl: $baseUrl);
    }

    protected function model(AiTarget $target, array $capabilities): Model
    {
        return new Mistral($target->model->name, $capabilities);
    }
}
