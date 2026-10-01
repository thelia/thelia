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

namespace Thelia\Tests\Integration\Install;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Consent;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Map\ConfigTableMap;
use Thelia\Model\Map\ConsentTableMap;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements 3.2.0.sql runs for the content slots: the header setting an updated shop
 * gets, which reproduces the header the theme showed until then (folder 2, content 1), and
 * the terms and conditions moved onto their consent.
 */
final class ContentSlotsMigrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A shop on 3.1 has no header setting yet.
        $this->connection()->exec("DELETE FROM `config` WHERE `name` = 'header_menu_items'");
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        ConfigQuery::resetCache();
    }

    public function testAShopWithFolderTwoAndContentOneKeepsThemInItsHeader(): void
    {
        $this->givenFolderTwo(true);
        $this->givenContentOne(true);

        $this->runMigration();

        self::assertSame('folder:2,content:1', $this->storedSetting('header_menu_items'));
    }

    public function testAShopWithoutFolderTwoOnlyKeepsContentOne(): void
    {
        $this->givenFolderTwo(false);
        $this->givenContentOne(true);

        $this->runMigration();

        self::assertSame('content:1', $this->storedSetting('header_menu_items'));
    }

    public function testAShopWithNeitherGetsAnEmptyHeader(): void
    {
        $this->givenFolderTwo(false);
        $this->givenContentOne(false);

        $this->runMigration();

        self::assertSame('', $this->storedSetting('header_menu_items'));
    }

    public function testAHeaderTheShopAlreadySetIsKeptAndTheScriptReplays(): void
    {
        $this->givenFolderTwo(true);
        $this->givenContentOne(true);
        $this->connection()->exec("INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES ('header_menu_items', 'content:7', 0, 0, NOW(), NOW())");

        $this->runMigration();
        $this->runMigration();

        self::assertSame('content:7', $this->storedSetting('header_menu_items'));
    }

    public function testTheTermsNamedOnlyByTheSettingMoveOntoTheConsent(): void
    {
        $terms = $this->content();
        $this->givenTermsSetting((string) $terms);
        $this->givenConsentContent(null);

        $this->runMigration();

        self::assertSame($terms, $this->storedConsentContent());
    }

    public function testAConsentThatAlreadyNamesItsTermsKeepsThem(): void
    {
        $chosen = $this->content();
        $legacy = $this->content();
        $this->givenTermsSetting((string) $legacy);
        $this->givenConsentContent($chosen);

        $this->runMigration();

        self::assertSame($chosen, $this->storedConsentContent());
    }

    public function testAnEmptyOrDanglingSettingLeavesTheConsentWithoutContent(): void
    {
        $this->givenConsentContent(null);

        $this->givenTermsSetting('');
        $this->runMigration();
        self::assertNull($this->storedConsentContent());

        $this->givenTermsSetting((string) ($this->content() + 1000));
        $this->runMigration();
        self::assertNull($this->storedConsentContent());
    }

    private function givenFolderTwo(bool $exists): void
    {
        $this->connection()->exec('DELETE FROM `folder` WHERE `id` = 2');

        if ($exists) {
            $this->connection()->exec('INSERT INTO `folder` (`id`, `parent`, `visible`, `position`, `created_at`, `updated_at`) VALUES (2, 0, 1, 1, NOW(), NOW())');
        }
    }

    private function givenContentOne(bool $exists): void
    {
        $this->connection()->exec('DELETE FROM `content` WHERE `id` = 1');

        if ($exists) {
            $this->connection()->exec('INSERT INTO `content` (`id`, `visible`, `position`, `created_at`, `updated_at`) VALUES (1, 1, 1, NOW(), NOW())');
        }
    }

    private function givenTermsSetting(string $value): void
    {
        ConfigQuery::write('terms_conditions_content_id', $value);
    }

    private function givenConsentContent(?int $contentId): void
    {
        $this->termsConsent()->setContentId($contentId)->save();
    }

    private function content(): int
    {
        $fixtures = new FixtureFactory($this->getPropelConnection());

        return $fixtures->content($fixtures->folder())->getId();
    }

    private function termsConsent(): Consent
    {
        $consent = ConsentQuery::create()->findOneByCode(Consent::CODE_TERMS_AND_CONDITIONS);
        self::assertNotNull($consent, 'the seed creates the terms and conditions consent');

        return $consent;
    }

    private function storedSetting(string $name): ?string
    {
        ConfigTableMap::clearInstancePool();

        return ConfigQuery::create()->findOneByName($name)?->getStoredValue();
    }

    private function storedConsentContent(): ?int
    {
        ConsentTableMap::clearInstancePool();

        return $this->termsConsent()->getContentId();
    }

    private function runMigration(): void
    {
        foreach ($this->migrationStatements() as $statement) {
            $this->connection()->exec($statement);
        }
    }

    private function connection(): ConnectionInterface
    {
        return $this->getPropelConnection();
    }

    /**
     * @return list<string>
     */
    private function migrationStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.2.0.sql');

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (str_contains($sql, '@header_menu_items') || str_contains($sql, '@terms_content_id')) {
                $statements[] = $sql;
            }
        }

        self::assertCount(4, $statements, 'The 3.2.0 script does not set up the content slots.');

        return $statements;
    }
}
