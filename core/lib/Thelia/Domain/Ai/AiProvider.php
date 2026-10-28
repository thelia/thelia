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

namespace Thelia\Domain\Ai;

/**
 * The providers a connection can reach. Its value is also the key of AiRequest::$providerOptions.
 */
enum AiProvider: string
{
    case Mistral = 'mistral';
    case Anthropic = 'anthropic';
    case OpenAi = 'openai';
    /** Any endpoint that speaks the OpenAI API. */
    case OpenAiCompatible = 'openai_compatible';
    case Scaleway = 'scaleway';
    case OvhCloud = 'ovhcloud';
    /** The model service of the French State (DINUM). */
    case Albert = 'albert';
    /** A self-hosted Ollama server. */
    case Ollama = 'ollama';
    /** The preset connection to the gateway Thelia operates. */
    case TheliaAi = 'thelia_ai';

    /**
     * Where the provider processes the data when reached at this base URL, or null when it depends on where
     * the endpoint runs: the administrator declares it on the connection. OpenAI is outside the EU unless the
     * project uses its European endpoint `https://eu.api.openai.com`.
     */
    public function defaultJurisdiction(?string $baseUrl = null): ?AiJurisdiction
    {
        return match ($this) {
            self::Mistral, self::Scaleway, self::OvhCloud, self::Albert, self::TheliaAi => AiJurisdiction::EuropeanUnion,
            self::OpenAi => 'eu.api.openai.com' === parse_url((string) $baseUrl, \PHP_URL_HOST)
                ? AiJurisdiction::EuropeanUnion
                : AiJurisdiction::OutsideEuropeanUnion,
            self::Anthropic => AiJurisdiction::OutsideEuropeanUnion,
            self::OpenAiCompatible, self::Ollama => null,
        };
    }
}
