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

namespace Thelia\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;

/**
 * List (and optionally remove) local translation overrides whose base key no longer exists.
 *
 * The local override layer (local/I18n/{locale}.php, the "global" fallback domain) survives a
 * git push of the code. When a base string is removed from the code, an override that still
 * references it becomes orphaned. This command reports those orphans and, with --force, prunes
 * them. It never touches the versioned base translation files.
 */
#[AsCommand(name: 'i18n:prune-overrides', description: 'List or remove local translation overrides whose base string no longer exists')]
class I18nPruneOverridesCommand extends ContainerAwareCommand
{
    public function __construct(
        private readonly Translator $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Restrict to a single locale, e.g. fr_FR (default: every override file found)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Actually remove the orphaned overrides (default: dry-run, only report)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $directory = THELIA_LOCAL_DIR.'I18n';

        if (!is_dir($directory)) {
            $io->warning(\sprintf('No local override directory found at %s. Nothing to prune.', $directory));

            return Command::SUCCESS;
        }

        $force = (bool) $input->getOption('force');
        $onlyLocale = $input->getOption('locale');

        $files = glob($directory.\DIRECTORY_SEPARATOR.'*.php') ?: [];
        $totalOrphans = 0;
        $totalPruned = 0;

        foreach ($files as $file) {
            $locale = basename($file, '.php');

            if (null !== $onlyLocale && $onlyLocale !== $locale) {
                continue;
            }

            $overrides = require $file;

            if (!\is_array($overrides)) {
                continue;
            }

            $orphans = $this->findOrphans($overrides, $locale);

            if ([] === $orphans['global'] && [] === $orphans['domains']) {
                continue;
            }

            $count = \count($orphans['global']);
            foreach ($orphans['domains'] as $keys) {
                $count += \count($keys);
            }
            $totalOrphans += $count;

            $io->section(\sprintf('%s — %d orphaned override(s)', $locale, $count));

            $rows = [];
            foreach ($orphans['global'] as $text) {
                $rows[] = ['(global)', $text];
            }
            foreach ($orphans['domains'] as $domain => $keys) {
                foreach ($keys as $text) {
                    $rows[] = [$domain, $text];
                }
            }
            $io->table(['Domain', 'Source string'], $rows);

            if ($force) {
                $cleaned = $this->removeOrphans($overrides, $orphans);
                $this->writeOverrideFile($file, $cleaned);
                $totalPruned += $count;
            }
        }

        if (0 === $totalOrphans) {
            $io->success('No orphaned override found.');

            return Command::SUCCESS;
        }

        if ($force) {
            $this->getDispatcher()->dispatch(
                new CacheEvent($this->getContainer()->getParameter('kernel.cache_dir')),
                TheliaEvents::CACHE_CLEAR,
            );
            $io->success(\sprintf('Pruned %d orphaned override(s).', $totalPruned));

            return Command::SUCCESS;
        }

        $io->note(\sprintf('%d orphaned override(s) found (dry-run). Re-run with --force to remove them.', $totalOrphans));
        $io->writeln('An override is reported when its source string is absent from the base catalogues loaded for that locale. Review before forcing, especially on partially translated locales.');

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array{global: list<string>, domains: array<string, list<string>>}
     */
    private function findOrphans(array $overrides, string $locale): array
    {
        $catalogue = $this->translator->getCatalogue($locale);
        $baseDomains = array_diff($catalogue->getDomains(), [Translator::GLOBAL_FALLBACK_DOMAIN]);

        $result = ['global' => [], 'domains' => []];

        foreach ($overrides as $key => $value) {
            if (\is_array($value)) {
                // Domain bucket: local/I18n stores per-resource overrides as [$domain => [$text => $translation]].
                foreach (array_keys($value) as $text) {
                    if (!$catalogue->has((string) $text, (string) $key)) {
                        $result['domains'][(string) $key][] = (string) $text;
                    }
                }

                continue;
            }

            // Global override: a blanket fallback keyed by the source string. Orphaned only when
            // the string exists in no base domain at all.
            $existsSomewhere = false;
            foreach ($baseDomains as $domain) {
                if ($catalogue->has((string) $key, $domain)) {
                    $existsSomewhere = true;
                    break;
                }
            }

            if (!$existsSomewhere) {
                $result['global'][] = (string) $key;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed>                                              $overrides
     * @param array{global: list<string>, domains: array<string, list<string>>} $orphans
     *
     * @return array<string, mixed>
     */
    private function removeOrphans(array $overrides, array $orphans): array
    {
        $globalOrphans = array_flip($orphans['global']);

        foreach ($overrides as $key => $value) {
            if (\is_array($value)) {
                if (isset($orphans['domains'][$key])) {
                    foreach ($orphans['domains'][$key] as $text) {
                        unset($overrides[$key][$text]);
                    }

                    if ([] === $overrides[$key]) {
                        unset($overrides[$key]);
                    }
                }

                continue;
            }

            if (isset($globalOrphans[$key])) {
                unset($overrides[$key]);
            }
        }

        return $overrides;
    }

    /**
     * Rewrite the override file, mirroring the format produced by the back-office write listener
     * (Thelia\Action\Translation::writeFallbackFile): flat strings for global fallbacks, nested
     * arrays for per-domain overrides.
     *
     * @param array<string, mixed> $translations
     */
    private function writeOverrideFile(string $file, array $translations): void
    {
        $fp = fopen($file, 'w');

        if (false === $fp) {
            throw new \RuntimeException(\sprintf('Failed to open translation file %s for writing.', $file));
        }

        fwrite($fp, '<'."?php\n\n");
        fwrite($fp, "return [\n");

        ksort($translations);

        foreach ($translations as $key => $value) {
            if (\is_array($value)) {
                $escapedKey = str_replace("'", "\\'", (string) $key);
                fwrite($fp, \sprintf("    '%s' => [\n", $escapedKey));
                ksort($value);

                foreach ($value as $subKey => $subText) {
                    $escapedSubKey = str_replace("'", "\\'", (string) $subKey);
                    $translation = str_replace("'", "\\'", (string) $subText);
                    fwrite($fp, \sprintf("        '%s' => '%s',\n", $escapedSubKey, $translation));
                }

                fwrite($fp, "    ],\n");

                continue;
            }

            $escapedKey = str_replace("'", "\\'", (string) $key);
            $translation = str_replace("'", "\\'", (string) $value);
            fwrite($fp, \sprintf("    '%s' => '%s',\n", $escapedKey, $translation));
        }

        fwrite($fp, "];\n");

        fclose($fp);
    }
}
