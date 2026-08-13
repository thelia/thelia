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

namespace Thelia\Install\Generator;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Tools\Version\Version;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

/**
 * Renders `setup/insert.sql` from `setup/insert.sql.tpl`.
 *
 * The template holds the whole seed with the wording of every locale left to
 * an `intl()` call, resolved against the files of `setup/I18n` in the `install`
 * domain. It is a Twig template because that is the engine Thelia 3 renders
 * with; the Smarty one the generator used to need left with Thelia 2.
 */
class SeedSqlGenerator
{
    private const TEMPLATE = 'insert.sql.tpl';

    private const OUTPUT = 'insert.sql';

    private readonly string $setupDirectory;

    private ?ConnectionInterface $connection = null;

    public function __construct(
        private readonly Translator $translator,
        ?string $setupDirectory = null,
    ) {
        $setupDirectory ??= \defined('THELIA_SETUP_DIRECTORY') ? THELIA_SETUP_DIRECTORY : '';

        $this->setupDirectory = rtrim($setupDirectory, \DIRECTORY_SEPARATOR);
    }

    /**
     * @return list<string> the locales `setup/I18n` ships, sorted
     */
    public function getAvailableLocales(): array
    {
        $locales = [];

        foreach (glob($this->setupDirectory.\DIRECTORY_SEPARATOR.'I18n'.\DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
            $locales[] = basename($file, '.php');
        }

        sort($locales);

        return $locales;
    }

    /**
     * The locales of the languages the seed creates.
     *
     * Seeding the wording of a language the shop does not have would bloat the
     * seed for nothing: a language added later is served by the listener that
     * replays `setup/I18n` on creation.
     *
     * @return list<string> sorted, as the i18n blocks of the template expect
     */
    public function getSeededLocales(): array
    {
        $template = $this->readTemplate();

        if (1 !== preg_match('/INSERT INTO `lang`.*?;/s', $template, $block)) {
            throw new \RuntimeException(\sprintf('%s seeds no language.', self::TEMPLATE));
        }

        preg_match_all("/^\(\d+, '[^']*', '[^']*', '([^']*)'/m", $block[0], $matches);

        $locales = array_unique($matches[1]);
        sort($locales);

        return $locales;
    }

    public function getOutputPath(): string
    {
        return $this->setupDirectory.\DIRECTORY_SEPARATOR.self::OUTPUT;
    }

    /**
     * @param list<string>|null $locales the locales to seed, the seeded languages by default
     */
    public function generate(?array $locales = null): string
    {
        $availableLocales = $this->getAvailableLocales();

        if ([] === $availableLocales) {
            throw new \RuntimeException(\sprintf('No translation file found in %s.', $this->setupDirectory.\DIRECTORY_SEPARATOR.'I18n'));
        }

        $locales ??= $this->getSeededLocales();
        $unknownLocales = array_diff($locales, $availableLocales);

        if ([] !== $unknownLocales || [] === $locales) {
            throw new \InvalidArgumentException(\sprintf('Unknown locale(s): %s. Available locales: %s', implode(', ', $unknownLocales), implode(', ', $availableLocales)));
        }

        foreach ($locales as $locale) {
            $this->translator->addResource(
                'php',
                $this->setupDirectory.\DIRECTORY_SEPARATOR.'I18n'.\DIRECTORY_SEPARATOR.$locale.'.php',
                $locale,
                'install',
            );
        }

        return $this->createTwig()
            ->createTemplate($this->readTemplate())
            ->render(['locales' => array_values($locales)] + Version::parse());
    }

    private function readTemplate(): string
    {
        $path = $this->setupDirectory.\DIRECTORY_SEPARATOR.self::TEMPLATE;
        $template = file_get_contents($path);

        if (false === $template) {
            throw new \RuntimeException(\sprintf('Cannot read %s.', $path));
        }

        return $template;
    }

    private function createTwig(): Environment
    {
        // The seed is SQL: escaping is the connection's job below, and the HTML
        // escaper Twig applies by default would corrupt every quoted wording.
        $twig = new Environment(new ArrayLoader(), ['autoescape' => false]);

        $twig->addFunction(new TwigFunction('intl', $this->translate(...)));

        return $twig;
    }

    /**
     * Resolves one wording of the template, quoted for the seed.
     *
     * @param bool $useDefault whether the key stands in for a missing translation
     */
    private function translate(string $key, string $locale, bool $useDefault = false): string
    {
        if ('' === $key) {
            throw new \RuntimeException(\sprintf('The seed template asks for an empty key in %s.', $locale));
        }

        $translation = $this->translator->trans($key, [], 'install', $locale, $useDefault);

        if ('' === $translation || '0' === $translation) {
            return 'NULL';
        }

        return $this->getConnection()->quote($translation);
    }

    private function getConnection(): ConnectionInterface
    {
        return $this->connection ??= Propel::getConnection(ProductTableMap::DATABASE_NAME);
    }
}
