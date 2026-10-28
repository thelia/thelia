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

use Thelia\Domain\Ai\Catalog\AiModelCatalog;
use Thelia\Domain\Ai\Catalog\AiModelSpecification;
use Thelia\Model\AiConnection;
use Thelia\Model\AiModel;
use Thelia\Model\AiModelQuery;
use Thelia\Test\IntegrationTestCase;

final class AiModelCatalogTest extends IntegrationTestCase
{
    public function testTheEmbeddedListPricesAndDescribesModelsWithoutAnyRowInTheDatabase(): void
    {
        self::assertSame(0, AiModelQuery::create()->count(), 'the catalogue is not copied to the database');

        $model = $this->catalog()->find('anthropic', 'claude-haiku-4-5');

        self::assertInstanceOf(AiModelSpecification::class, $model);
        self::assertSame('1.00', $model->inputPrice);
        self::assertSame('5.00', $model->outputPrice);
        self::assertSame('0.10', $model->cacheReadPrice);
        self::assertSame('USD', $model->currency);
        self::assertSame(200000, $model->contextWindow);
        self::assertTrue($model->supportsTools);
        self::assertTrue($model->supportsStructuredOutput);
        self::assertSame(AiModelSpecification::SOURCE_CATALOG, $model->source);
        self::assertNull($model->connectionId);
    }

    public function testTheShippedListHasNoFineTunedModelAndNoModelPastItsDeprecationDate(): void
    {
        $data = json_decode((string) file_get_contents(\dirname(__DIR__, 4).'/core/lib/Thelia/Domain/Ai/Resources/model-catalog.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertNotEmpty($data['models']);
        self::assertSame('MIT', $data['license']);

        foreach ($data['models'] as $model) {
            self::assertStringStartsNotWith('ft:', $model['model']);
            self::assertTrue(null === $model['deprecation_date'] || $model['deprecation_date'] >= '2026-10-28', $model['model']);
        }
    }

    public function testAnUnknownModelIsNull(): void
    {
        self::assertNull($this->catalog()->find('mistral', 'not-a-model'));
        self::assertSame([], $this->catalog()->models('albert'));
    }

    public function testARowOverridesThePriceOfAModelOfTheList(): void
    {
        $this->row('anthropic', 'claude-haiku-4-5', input: '0.500000');

        $model = $this->catalog()->find('anthropic', 'claude-haiku-4-5');

        self::assertSame('0.500000', $model?->inputPrice);
        self::assertSame(AiModelSpecification::SOURCE_MANUAL, $model->source);
    }

    public function testARowAddsAModelTheListDoesNotKnow(): void
    {
        $this->row('mistral', 'ministral-private-preview', input: '0.020000', output: '0.020000');

        self::assertSame('0.020000', $this->catalog()->find('mistral', 'ministral-private-preview')?->outputPrice);
        self::assertArrayHasKey('ministral-private-preview', $this->catalog()->models('mistral'));
    }

    public function testAnInactiveRowHidesAModelOfTheList(): void
    {
        $this->row('anthropic', 'claude-haiku-4-5', active: false);

        self::assertNull($this->catalog()->find('anthropic', 'claude-haiku-4-5'));
    }

    public function testAModelOfAConnectionIsSeenOnlyThroughThatConnection(): void
    {
        $lan = $this->connection('cat-lan');
        $other = $this->connection('cat-other');
        $this->row('ollama', 'my-finetune', input: '0.000000', connectionId: $lan->getId());
        $this->row('ollama', 'llama3', input: '0.000000', connectionId: $lan->getId());

        self::assertNotNull($this->catalog()->find('ollama', 'my-finetune', $lan->getId()));
        self::assertNull($this->catalog()->find('ollama', 'my-finetune', $other->getId()));
        self::assertNull($this->catalog()->find('ollama', 'my-finetune'));
    }

    public function testTheRowOfAConnectionWinsOverTheRowOfTheProvider(): void
    {
        $lan = $this->connection('cat-lan');
        $this->row('openai', 'gpt-5.1-mini-probe', input: '1.000000');
        $this->row('openai', 'gpt-5.1-mini-probe', input: '0.250000', connectionId: $lan->getId());

        self::assertSame('0.250000', $this->catalog()->find('openai', 'gpt-5.1-mini-probe', $lan->getId())?->inputPrice);
        self::assertSame('1.000000', $this->catalog()->find('openai', 'gpt-5.1-mini-probe')?->inputPrice);
    }

    private function catalog(): AiModelCatalog
    {
        return new AiModelCatalog();
    }

    private function connection(string $code): AiConnection
    {
        $connection = (new AiConnection())->setCode($code)->setTitle($code)->setProvider('ollama')->setJurisdiction('eu');
        $connection->save();

        return $connection;
    }

    private function row(string $provider, string $name, string $input = '0.000000', string $output = '0.000000', bool $active = true, ?int $connectionId = null): void
    {
        (new AiModel())
            ->setProvider($provider)
            ->setName($name)
            ->setConnectionId($connectionId)
            ->setInputPrice($input)
            ->setOutputPrice($output)
            ->setActive($active)
            ->setSource(AiModelSpecification::SOURCE_MANUAL)
            ->save();
    }
}
