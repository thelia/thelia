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

namespace Thelia\Action;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\Translation\TranslationEvent;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

/**
 * Class Translation.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Translation extends BaseAction implements EventSubscriberInterface
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function getTranslatableStrings(TranslationEvent $event): void
    {
        $strings = [];
        $stringCount = $this->walkDir(
            $event->getDirectory(),
            $event->getMode(),
            $event->getLocale(),
            $event->getDomain(),
            $strings,
        );

        $event
            ->setTranslatableStrings($strings)
            ->setTranslatableStringCount($stringCount);
    }

    /**
     * Recursively examine files in a directory tree, and extract translatable strings.
     *
     * Returns an array of translatable strings, each item having with the following structure:
     * 'files' an array of file names in which the string appears,
     * 'text' the translatable text
     * 'translation' => the text translation, or an empty string if none available.
     * 'dollar'  => true if the translatable text contains a $
     *
     * @param string $directory     the path to the directory to examine
     * @param string $walkMode      type of file scanning: WALK_MODE_PHP or WALK_MODE_TEMPLATE
     * @param string $currentLocale the current locale
     * @param string $domain        the translation domain (fontoffice, backoffice, module, etc...)
     * @param array  $strings       the list of strings
     *
     * @return number the total number of translatable texts
     *
     * @throws \InvalidArgumentException if $walkMode contains an invalid value
     */
    protected function walkDir(string $directory, string $walkMode, string $currentLocale, string $domain, array &$strings): int|float
    {
        $numTexts = 0;

        if (TranslationEvent::WALK_MODE_PHP === $walkMode) {
            $prefix = '\-\>[\s]*trans[\s]*\([\s]*';

            $allowedExts = ['php'];
        } elseif (TranslationEvent::WALK_MODE_TEMPLATE === $walkMode) {
            $prefix = '\{intl(?:.*?)[\s]l=[\s]*';

            $allowedExts = ['html', 'tpl', 'xml', 'txt'];
        } else {
            throw new \InvalidArgumentException(Translator::getInstance()->trans('Invalid value for walkMode parameter: %value', ['%value' => $walkMode]));
        }

        try {
            Tlog::getInstance()->debug(\sprintf('Walking in %s, in mode %s', $directory, $walkMode));

            /** @var \DirectoryIterator $fileInfo */
            foreach (new \DirectoryIterator($directory) as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }

                if ($fileInfo->isDir()) {
                    $numTexts += $this->walkDir(
                        $fileInfo->getPathName(),
                        $walkMode,
                        $currentLocale,
                        $domain,
                        $strings,
                    );
                }

                if ($fileInfo->isFile()) {
                    $ext = $fileInfo->getExtension();

                    if (\in_array($ext, $allowedExts, true) && $content = file_get_contents($fileInfo->getPathName())) {
                        $short_path = $this->normalizePath($fileInfo->getPathName());
                        Tlog::getInstance()->debug(\sprintf('Examining file %s%s', $short_path, \PHP_EOL));
                        $matches = [];

                        if (preg_match_all(
                            '/'.$prefix.'((?<![\\\\])[\'"])((?:.(?!(?<![\\\\])\1))*.?)*?\1/ms',
                            $content,
                            $matches,
                        )) {
                            Tlog::getInstance()->debug('Strings found: ', $matches[2]);

                            $idx = 0;

                            foreach ($matches[2] as $match) {
                                $hash = md5($match);

                                if (isset($strings[$hash])) {
                                    if (!\in_array($short_path, $strings[$hash]['files'], true)) {
                                        $strings[$hash]['files'][] = $short_path;
                                    }
                                } else {
                                    ++$numTexts;

                                    // remove \' (or \"), that will prevent the translator to work properly, as
                                    // "abc \def\" ghi" will be passed as abc "def" ghi to the translator.

                                    $quote = $matches[1][$idx];

                                    $match = str_replace('\\'.$quote, $quote, $match);

                                    // Ignore empty strings
                                    if ('' === $match) {
                                        continue;
                                    }

                                    $strings[$hash] = [
                                        'files' => [$short_path],
                                        'text' => $match,
                                        'translation' => Translator::getInstance()->trans(
                                            $match,
                                            [],
                                            $domain,
                                            $currentLocale,
                                            false,
                                            false,
                                        ),
                                        'custom_fallback' => Translator::getInstance()->trans(
                                            \sprintf(
                                                Translator::GLOBAL_FALLBACK_KEY,
                                                $domain,
                                                $match,
                                            ),
                                            [],
                                            Translator::GLOBAL_FALLBACK_DOMAIN,
                                            $currentLocale,
                                            false,
                                            false,
                                        ),
                                        'global_fallback' => Translator::getInstance()->trans(
                                            $match,
                                            [],
                                            Translator::GLOBAL_FALLBACK_DOMAIN,
                                            $currentLocale,
                                            false,
                                            false,
                                        ),
                                        'dollar' => str_contains($match, '$'),
                                    ];
                                }

                                ++$idx;
                            }
                        }
                    }
                }
            }
        } catch (\UnexpectedValueException) {
            // Directory does not exists => ignore it.
        }

        return $numTexts;
    }

    public function writeTranslationFile(TranslationEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        // Developer mode writes the versioned I18n/{locale}.php files shipped with the code.
        // It is opt-in: without it, edits only reach the local override layer (writeFallbackFile),
        // so a merchant edit never conflicts with a git push of the base translations.
        if (!$event->isDeveloperMode()) {
            return;
        }

        $file = $event->getTranslationFilePath();

        $fs = new Filesystem();

        if (!$fs->exists($file) && $event->isCreateFileIfNotExists()) {
            $dir = \dirname($file);

            if (!$fs->exists($file)) {
                $fs->mkdir($dir);

                $this->cacheClear($dispatcher);
            }
        }

        $texts = $event->getTranslatableStrings();
        $translations = $event->getTranslatedStrings();

        // Sort keys alphabetically while keeping index
        asort($texts);

        $catalogue = [];

        foreach ($texts as $key => $text) {
            // Write only defined (not empty) translations
            if (!empty($translations[$key])) {
                $catalogue[$text] = $translations[$key];
            }
        }

        if (false === @file_put_contents($file, self::exportCatalogue($catalogue))) {
            throw new \RuntimeException(Translator::getInstance()->trans('Failed to open translation file %file. Please be sure that this file is writable by your Web server', ['%file' => $file]));
        }
    }

    /**
     * The catalogue is written as PHP source and read back with require, so every key and
     * value goes through var_export: it is the only quoting the PHP parser cannot be talked
     * out of, whatever the text holds.
     */
    private static function exportCatalogue(array $catalogue): string
    {
        return '<'."?php\n\nreturn ".var_export($catalogue, true).";\n";
    }

    public function writeFallbackFile(TranslationEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $file = THELIA_LOCAL_DIR.'I18n'.DS.$event->getLocale().'.php';

        $fs = new Filesystem();
        $translations = [];

        if (!$fs->exists($file)) {
            if ($event->isCreateFileIfNotExists()) {
                $dir = \dirname($file);
                $fs->mkdir($dir);

                $this->cacheClear($dispatcher);
            } else {
                throw new \RuntimeException(Translator::getInstance()->trans('Failed to open translation file %file. Please be sure that this file is writable by your Web server', ['%file' => $file]));
            }
        } else {
            /*$loader = new PhpFileLoader();
            $catalogue = $loade     r->load($file);
            $translations = $catalogue->all();
            */
            $translations = require $file;

            if (!\is_array($translations)) {
                $translations = [];
            }
        }

        $texts = $event->getTranslatableStrings();
        $customs = $event->getCustomFallbackStrings();
        $globals = $event->getGlobalFallbackStrings();

        // just reset current translations for this domain to remove strings that do not exist anymore
        $translations[$event->getDomain()] = [];

        foreach ($texts as $key => $text) {
            if (!empty($customs[$key])) {
                $translations[$event->getDomain()][$text] = $customs[$key];
            }

            if (!empty($globals[$key])) {
                $translations[$text] = $globals[$key];
            } else {
                unset($translations[$text]);
            }
        }

        // Write only defined (not empty) translations, keys sorted alphabetically
        $catalogue = [];
        ksort($translations);

        foreach ($translations as $key => $text) {
            if (empty($text)) {
                continue;
            }

            if (\is_array($text)) {
                ksort($text);
            }

            $catalogue[$key] = $text;
        }

        if (false === @file_put_contents($file, self::exportCatalogue($catalogue))) {
            throw new \RuntimeException(Translator::getInstance()->trans('Failed to open translation file %file. Please be sure that this file is writable by your Web server', ['%file' => $file]));
        }
    }

    protected function normalizePath($path): string
    {
        $path = str_replace(
            str_replace('\\', '/', THELIA_ROOT),
            '',
            str_replace('\\', '/', realpath($path)),
        );

        return ltrim($path, '/');
    }

    protected function cacheClear(EventDispatcherInterface $dispatcher): void
    {
        // Only translation files were written; the database schema cannot have moved.
        $cacheEvent = new CacheEvent(
            $this->container->getParameter('kernel.cache_dir'),
            invalidatePropelSchema: false,
        );

        $dispatcher->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::TRANSLATION_GET_STRINGS => ['getTranslatableStrings', 128],
            TheliaEvents::TRANSLATION_WRITE_FILE => [
                ['writeTranslationFile', 128],
                ['writeFallbackFile', 128],
            ],
        ];
    }
}
