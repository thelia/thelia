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

namespace Thelia\Tests\Integration\Command;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Command\AiRotateKeysCommand;
use Thelia\Domain\Ai\Vault\AiKeyCipher;
use Thelia\Domain\Ai\Vault\AiKeyVault;
use Thelia\Model\AiConnection;
use Thelia\Model\AiConnectionQuery;
use Thelia\Test\IntegrationTestCase;

final class AiRotateKeysCommandTest extends IntegrationTestCase
{
    private const string OLD_SECRET = 'the-secret-before-the-change';

    /**
     * The command runs its own transaction, and the rollback of a nested one writes nothing back: the test
     * commits for real and removes its rows.
     */
    protected bool $useTransaction = false;

    protected function tearDown(): void
    {
        unset($_SERVER[AiRotateKeysCommand::PREVIOUS_SECRET_VARIABLE]);
        AiConnectionQuery::create()->filterByCode('rot-%', Criteria::LIKE)->delete();

        parent::tearDown();
    }

    public function testEveryKeyIsReEncryptedWithTheCurrentSecret(): void
    {
        $this->connection('rot-a', 'sk-a');
        $this->connection('rot-b', 'sk-b');
        $this->connection('rot-none', null);
        $before = $this->blob('rot-a');

        $tester = $this->rotate([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertNotSame($before, $this->blob('rot-a'));
        self::assertSame('sk-a', $this->vault()->resolveFor('rot-a', $this->blob('rot-a'), null));
        self::assertSame('sk-b', $this->vault()->resolveFor('rot-b', $this->blob('rot-b'), null));
        self::assertNull($this->blob('rot-none'));
        self::assertStringNotContainsString('sk-a', $tester->getDisplay());
    }

    public function testADryRunWritesNothing(): void
    {
        $this->connection('rot-a', 'sk-a');
        $before = $this->blob('rot-a');

        $tester = $this->rotate(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame($before, $this->blob('rot-a'));
        self::assertStringContainsString('Dry run', $tester->getDisplay());
        self::assertStringContainsString('1 key(s)', $tester->getDisplay());
    }

    public function testNothingIsWrittenWhenOneKeyCannotBeOpened(): void
    {
        $this->connection('rot-a', 'sk-a');
        $this->connection('rot-lost', 'sk-lost', 'yet-another-secret');
        $before = $this->blob('rot-a');

        $tester = $this->rotate([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame($before, $this->blob('rot-a'));
        self::assertStringContainsString('rot-lost', $tester->getDisplay());
    }

    public function testAKeyAlreadyOnTheCurrentSecretIsLeftAlone(): void
    {
        $this->connection('rot-current', 'sk-current', $this->currentSecret());
        $before = $this->blob('rot-current');

        $tester = $this->rotate([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame($before, $this->blob('rot-current'));
    }

    public function testThePreviousSecretCanComeFromTheStandardInput(): void
    {
        $this->connection('rot-a', 'sk-a');

        $tester = $this->tester();
        $tester->setInputs([self::OLD_SECRET]);
        $tester->execute(['--stdin' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame('sk-a', $this->vault()->resolveFor('rot-a', $this->blob('rot-a'), null));
    }

    public function testWithoutThePreviousSecretNothingHappens(): void
    {
        $this->connection('rot-a', 'sk-a');
        $before = $this->blob('rot-a');
        unset($_SERVER[AiRotateKeysCommand::PREVIOUS_SECRET_VARIABLE]);

        $tester = $this->tester();
        $tester->execute([]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame($before, $this->blob('rot-a'));
    }

    public function testThePreviousSecretIsNeverAnArgumentOfTheCommand(): void
    {
        $definition = (new Application(self::$kernel))->find('thelia:ai:rotate-keys')->getDefinition();

        self::assertSame([], array_keys($definition->getArguments()));
        self::assertFalse($definition->hasOption('secret'));
        self::assertFalse($definition->hasOption('previous-secret'));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function rotate(array $options): CommandTester
    {
        $_SERVER[AiRotateKeysCommand::PREVIOUS_SECRET_VARIABLE] = self::OLD_SECRET;

        $tester = $this->tester();
        $tester->execute($options);

        return $tester;
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find('thelia:ai:rotate-keys'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        AiConnectionQuery::create()->filterByCode('rot-%', Criteria::LIKE)->delete();
    }

    private function connection(string $code, ?string $plainKey, string $secret = self::OLD_SECRET): void
    {
        (new AiConnection())
            ->setCode($code)
            ->setTitle($code)
            ->setProvider('mistral')
            ->setJurisdiction('eu')
            ->setApiKeyEncrypted(null === $plainKey ? null : (new AiKeyCipher($secret))->encrypt($plainKey))
            ->save();
    }

    private function blob(string $code): ?string
    {
        return AiConnectionQuery::create()->findOneByCode($code)?->getApiKeyEncrypted();
    }

    private function currentSecret(): string
    {
        return (string) self::getContainer()->getParameter('kernel.secret');
    }

    private function vault(): AiKeyVault
    {
        return new AiKeyVault(new AiKeyCipher($this->currentSecret()));
    }
}
