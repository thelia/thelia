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

namespace Thelia\Tests\Integration\Domain\Ai;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Ai\AiProvider;
use Thelia\Domain\Ai\Exception\InvalidAiArgumentException;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Provider\AiHttpClientFactory;
use Thelia\Domain\Ai\Provider\AiProviderAdapters;
use Thelia\Domain\Ai\Provider\AiTargetResolver;
use Thelia\Domain\Ai\Provider\AnthropicAdapter;
use Thelia\Domain\Ai\Provider\MistralAdapter;
use Thelia\Domain\Ai\Provider\OllamaAdapter;
use Thelia\Domain\Ai\Provider\OpenAiAdapter;
use Thelia\Domain\Ai\Provider\OpenAiCompatibleAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Test\IntegrationTestCase;

/**
 * What the kernel builds from the classes of the layer, as the gateway will receive it.
 */
final class AiProviderWiringTest extends IntegrationTestCase
{
    /** @return iterable<string, array{AiProvider, class-string}> */
    public static function adapters(): iterable
    {
        yield 'mistral' => [AiProvider::Mistral, MistralAdapter::class];
        yield 'anthropic' => [AiProvider::Anthropic, AnthropicAdapter::class];
        yield 'openai' => [AiProvider::OpenAi, OpenAiAdapter::class];
        yield 'ollama' => [AiProvider::Ollama, OllamaAdapter::class];
        yield 'compatible' => [AiProvider::OpenAiCompatible, OpenAiCompatibleAdapter::class];
        yield 'scaleway' => [AiProvider::Scaleway, OpenAiCompatibleAdapter::class];
        yield 'ovh' => [AiProvider::OvhCloud, OpenAiCompatibleAdapter::class];
        yield 'albert' => [AiProvider::Albert, OpenAiCompatibleAdapter::class];
        yield 'thelia ai' => [AiProvider::TheliaAi, OpenAiCompatibleAdapter::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('adapters')]
    public function testEveryProviderOfTheContractHasItsAdapter(AiProvider $provider, string $class): void
    {
        self::assertInstanceOf($class, $this->getService(AiProviderAdapters::class)->for($provider));
    }

    public function testEveryProviderOfTheContractIsCovered(): void
    {
        $covered = array_map(static fn (array $row): AiProvider => $row[0], iterator_to_array(self::adapters(), false));

        self::assertEqualsCanonicalizing(AiProvider::cases(), $covered);
    }

    public function testTheResolverAndTheClientFactoryComeWithTheirParameters(): void
    {
        self::assertInstanceOf(AiTargetResolver::class, $this->getService(AiTargetResolver::class));
        self::assertInstanceOf(AiHttpClientFactory::class, $this->getService(AiHttpClientFactory::class));
        self::assertSame(60, static::getContainer()->getParameter('thelia.ai.http_timeout'));
        self::assertSame(300, static::getContainer()->getParameter('thelia.ai.http_max_duration'));
    }

    public function testTheValidatorReadsTheFlagOfTheAiLayerAndNotTheOneOfTheWebhooks(): void
    {
        $parameter = new \ReflectionParameter([AiBaseUrlValidator::class, '__construct'], 'allowInsecure');
        $attributes = $parameter->getAttributes(Autowire::class);

        self::assertCount(1, $attributes);
        self::assertSame('%thelia.ai.allow_insecure_urls%', $attributes[0]->newInstance()->value);
        self::assertTrue(static::getContainer()->hasParameter('thelia.ai.allow_insecure_urls'));
    }

    public function testPlainHttpToAPublicAddressIsOffUnlessTheFlagOfTheAiLayerAllowsIt(): void
    {
        self::assertFalse(static::getContainer()->getParameter('thelia.ai.allow_insecure_urls'));

        $this->expectException(InvalidAiArgumentException::class);

        $this->getService(AiBaseUrlValidator::class)->validate('http://93.184.216.34/v1', false);
    }
}
