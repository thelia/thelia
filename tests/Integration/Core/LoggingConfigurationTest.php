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

namespace Thelia\Tests\Integration\Core;

use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Thelia\Test\IntegrationTestCase;

/**
 * config/packages/monolog.yaml of this repository sends the main handler
 * through a "nested" stream of its own. That is what the shop wrote, so that
 * is what has to happen: the rotating file the core would have written
 * through, had the shop said nothing, must not be in the chain at all.
 */
final class LoggingConfigurationTest extends IntegrationTestCase
{
    #[Test]
    public function theHandlerTheShopNamedIsTheOneThatWrites(): void
    {
        $token = 'logging-configuration-probe-'.bin2hex(random_bytes(6));

        /** @var LoggerInterface $logger */
        $logger = $this->getService('logger');
        $logger->error($token);

        self::assertStringContainsString(
            $token,
            $this->read($this->fileTheShopNamed()),
            'The stream named in config/packages/monolog.yaml must be the one the main handler writes through.',
        );
        self::assertStringNotContainsString(
            $token,
            $this->read($this->fileTheCoreWouldHaveNamed()),
            'The default the core offers a shop that writes nothing must not be added to a shop that wrote its own.',
        );
    }

    private function fileTheShopNamed(): string
    {
        return THELIA_LOG_DIR.'test.log';
    }

    private function fileTheCoreWouldHaveNamed(): string
    {
        return THELIA_LOG_DIR.'test-'.date('Y-m-d').'.log';
    }

    private function read(string $file): string
    {
        return is_file($file) ? (string) file_get_contents($file) : '';
    }
}
