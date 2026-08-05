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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Service\DataAccess\DataAccessService;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\LogsInAsAdmin;

/**
 * A product translated only in the store's default language (en_US in the
 * test fixtures) currently comes back from `resources()` with `i18ns` left
 * as a locale-keyed map (`['en_US' => [...]]`) instead of the flat shape the
 * front expects, when the current session locale (fr_FR) has no translation
 * row. ResourceService::formatI18ns() ignores the BO's own
 * "default_lang_without_translation" setting (Configuration > Languages),
 * even though that setting already governs the same fallback for the legacy
 * loops and for attr()/theme_hook() (see ModelCriteriaTools::getFrontEndI18n).
 */
final class ResourceServiceI18nFallbackTest extends IntegrationTestCase
{
    use LogsInAsAdmin;

    private DataAccessService $dataAccess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataAccess = static::getContainer()->get(DataAccessService::class);
    }

    protected function tearDown(): void
    {
        // ConfigQuery caches values in a static array that the transaction
        // rollback does not clear; restore the store default explicitly so
        // a strict-mode test never leaks into whichever test runs next in
        // the same PHPUnit process.
        $this->configureLangBehavior(Lang::REPLACE_BY_DEFAULT_LANGUAGE);
        parent::tearDown();
    }

    public function testMissingTranslationFallsBackToDefaultLanguageByDefault(): void
    {
        $product = $this->createProductTranslatedOnlyInDefaultLanguage();
        $this->setSessionLocale('fr_FR');

        $result = $this->dataAccess->resources('/api/front/products/'.$product->getId());

        self::assertIsArray($result);
        self::assertSame('Default locale title', $result['i18ns']['title'] ?? null);
    }

    public function testMissingTranslationStaysEmptyWhenStrictBehaviorIsConfigured(): void
    {
        $this->configureLangBehavior(Lang::STRICTLY_USE_REQUESTED_LANGUAGE);

        $product = $this->createProductTranslatedOnlyInDefaultLanguage();
        $this->setSessionLocale('fr_FR');

        $result = $this->dataAccess->resources('/api/front/products/'.$product->getId());

        self::assertIsArray($result);
        self::assertArrayNotHasKey('title', $result['i18ns']);
    }

    public function testAdminRouteNeverFallsBackRegardlessOfSetting(): void
    {
        $product = $this->createProductTranslatedOnlyInDefaultLanguage();
        $this->setSessionLocale('fr_FR');
        $this->loginAsAdminInSession();

        $result = $this->dataAccess->resources('/api/admin/products/'.$product->getId());

        self::assertIsArray($result);
        self::assertArrayNotHasKey('title', $result['i18ns']);
    }

    private function createProductTranslatedOnlyInDefaultLanguage(): Product
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        // en_US is the store default language (setup/insert.sql.tpl); fr_FR is
        // active but intentionally left without a translation row.
        $product->setLocale('en_US')->setTitle('Default locale title')->save($connection);

        return $product;
    }

    private function setSessionLocale(string $locale): void
    {
        $lang = LangQuery::create()->findOneByLocale($locale);
        static::getContainer()->get('request_stack')->getCurrentRequest()->getSession()->setLang($lang);
    }

    /**
     * ConfigQuery::write() alone is not enough here: ConfigQuery::read() falls
     * back to the DB (and re-applies `$model->getValue() ?: $default`) whenever
     * its process-wide $booted flag is false, which it still is at this point
     * in the integration test kernel (TheliaBundle::boot() never primed it).
     * Since '0' is falsy in PHP, that `?:` silently discards a deliberately
     * configured "0" and restores the default — a latent bug in
     * ConfigQuery::read(), reported to the dev, left unfixed here as
     * out of scope. Priming the cache directly sidesteps it for this test.
     */
    private function configureLangBehavior(int $behavior): void
    {
        ConfigQuery::write('default_lang_without_translation', $behavior);
        ConfigQuery::initCache(['default_lang_without_translation' => (string) $behavior]);
    }
}
