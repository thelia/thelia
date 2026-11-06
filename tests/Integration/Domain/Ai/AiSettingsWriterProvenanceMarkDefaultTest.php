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

use AiProbeModule\ProbeWriter;
use Thelia\Domain\Ai\Provider\AiBaseUrlValidator;
use Thelia\Domain\Ai\Repository\AiUsageSettingRepository;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Service\AiSettingsWriter;
use Thelia\Domain\Ai\Service\AiUsageRegistry;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Domain\Webhook\WebhookUrlValidator;
use Thelia\Test\IntegrationTestCase;

final class AiSettingsWriterProvenanceMarkDefaultTest extends IntegrationTestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once \dirname(__DIR__, 3).'/fixtures/ai/ProbeModule/ProbeWriter.php';
    }

    public function testASettingSavedWithoutAProvenanceMarkValueStoresTheOffDefault(): void
    {
        $writer = new AiSettingsWriter(
            new AiKeyVault(new AiKeyCipher('provenance-default-secret')),
            new AiBaseUrlValidator(new WebhookUrlValidator()),
            new AiUsageRegistry(['aiprobe.writing' => ProbeWriter::class], new AiUsageSettingRepository(), new AiSettings()),
            new AiSettings(),
        );

        $setting = $writer->saveUsageSetting(null, 'aiprobe.writing');

        self::assertFalse((bool) $setting->getProvenanceMark(), 'the provenance mark is off by default (schema DEFAULT 0, API processor ?? false)');
    }
}
