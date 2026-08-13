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

namespace Thelia\Tests\Unit\Install\I18n;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Install\I18n\SeedTranslationCatalog;

final class SeedTranslationCatalogTest extends TestCase
{
    private string $directory;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir().'/thelia-seed-catalog-'.uniqid('', true);

        $this->filesystem->mkdir($this->directory);

        $this->writeCatalog('en_US', [
            'Taïwan' => 'Taiwan',
            'Taiwan' => 'Taiwan',
            'Poland' => 'Poland',
            'France' => 'France',
            'Untranslated everywhere' => '',
        ]);

        $this->writeCatalog('pl_PL', [
            'Taïwan' => 'Tajwan',
            'Poland' => 'Polska',
        ]);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testOnlyReportsTheShippedLocales(): void
    {
        $catalog = new SeedTranslationCatalog($this->directory);

        self::assertTrue($catalog->hasLocale('pl_PL'));
        self::assertFalse($catalog->hasLocale('xx_XX'));
    }

    public function testTranslatesAKeyIntoTheTargetLocale(): void
    {
        $catalog = new SeedTranslationCatalog($this->directory);

        self::assertSame('Polska', $catalog->translate('Poland', 'pl_PL'));
    }

    public function testReportsAMissingTranslationAsNull(): void
    {
        $catalog = new SeedTranslationCatalog($this->directory);

        self::assertNull($catalog->translate('France', 'pl_PL'));
        self::assertNull($catalog->translate('Untranslated everywhere', 'en_US'));
    }

    public function testRecoversTheKeyASeededValueComesFrom(): void
    {
        $catalog = new SeedTranslationCatalog($this->directory);

        // `Taïwan` and `Taiwan` both seed the English value `Taiwan`. The entry
        // that translates to itself is the one the value has to map back to,
        // otherwise the Polish translation of an unrelated key would be picked.
        self::assertSame('Taiwan', $catalog->getKeyForTranslation('Taiwan', 'en_US'));
        self::assertSame('Poland', $catalog->getKeyForTranslation('Poland', 'en_US'));
        self::assertNull($catalog->getKeyForTranslation('Never seeded', 'en_US'));
    }

    public function testRecoversTheKeysOfTheShippedEnglishCatalog(): void
    {
        $catalog = new SeedTranslationCatalog(\dirname(__DIR__, 4).'/setup/I18n');

        // `en_US.php` is not a plain identity map. The seed writes `Taiwan`
        // into `country_i18n` although the key is `Taïwan`, and the subject of
        // the account confirmation mail keeps a `%store` placeholder in its
        // key only. Taking those values for keys finds no translation at all.
        self::assertNull($catalog->translate('Taiwan', 'fr_FR'));
        self::assertSame('Taïwan', $catalog->getKeyForTranslation('Taiwan', 'en_US'));
        self::assertSame('Taïwan', $catalog->translate('Taïwan', 'fr_FR'));

        $seededSubject = 'Confirm your {{ config("store_name") }} account';

        self::assertNull($catalog->translate($seededSubject, 'fr_FR'));
        self::assertSame(
            'Confirmez la création de votre compte {{ config("store_name") }}',
            $catalog->translate(
                (string) $catalog->getKeyForTranslation($seededSubject, 'en_US'),
                'fr_FR',
            ),
        );
    }

    /**
     * @param array<string, string> $translations
     */
    private function writeCatalog(string $locale, array $translations): void
    {
        $this->filesystem->dumpFile(
            $this->directory.'/'.$locale.'.php',
            '<?php return '.var_export($translations, true).';',
        );
    }
}
