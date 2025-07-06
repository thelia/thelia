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

use Imagine\Exception\RuntimeException;
use Propel\Runtime\Propel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Tools\Version\Version;

/**
 * Class GenerateSQLCommand.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
#[AsCommand(name: 'generate:sql', description: 'Generate SQL files (insert.sql, update*.sql)')]
class GenerateSQLCommand extends ContainerAwareCommand
{
    /** @var Translator */
    protected $translator;

    protected $parser;

    /** @var \PDO */
    protected $con;

    /** @var array */
    protected $locales;

    protected function configure(): void
    {
        $this
            ->addOption(
                'locales',
                null,
                InputOption::VALUE_OPTIONAL,
                'generate only for only specific locales (separated by a ,) : fr_FR,es_ES or es_ES'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->init($input);

        // Main insert.sql file
        $content = file_get_contents(THELIA_SETUP_DIRECTORY.'insert.sql.tpl');
        $version = Version::parse();
        $content = $this->parser->renderString($content, $version, false);

        if (false === file_put_contents(THELIA_SETUP_DIRECTORY.'insert.sql', $content)) {
            $output->writeln("Can't write file ".THELIA_SETUP_DIRECTORY.'insert.sql');
        } else {
            $output->writeln('File '.THELIA_SETUP_DIRECTORY.'insert.sql generated successfully.');
        }

        // sql update files
        $finder = Finder::create()
            ->name('*.tpl')
            ->depth(0)
            ->in(THELIA_SETUP_DIRECTORY.'update'.DS.'tpl');

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            $content = $this->parser->renderString($content, [], false);

            $destination = THELIA_SETUP_DIRECTORY.'update'.DS.'sql'.DS.$file->getBasename('.tpl');

            if (false === file_put_contents($destination, $content)) {
                $output->writeln("Can't write file ".$destination);
            } else {
                $output->writeln('File '.$destination.' generated successfully.');
            }
        }

        return 0;
    }

    protected function init(InputInterface $input): void
    {
        $this->initRequest();

        $container = $this->getContainer();

        $this->translator = $container->get('thelia.translator');
        $this->parser = $container->get('thelia.parser');

        $this->con = Propel::getConnection(ProductTableMap::DATABASE_NAME);

        $this->initLocales($input);

        $this->initParser();
    }

    protected function initLocales(InputInterface $input): void
    {
        $this->locales = [];
        $availableLocales = [];

        $finder = Finder::create()
            ->name('*.php')
            ->depth(0)
            ->sortByName()
            ->in(THELIA_SETUP_DIRECTORY.'I18n');

        // limit to only some locale(s)
        $localesToKeep = $input->getOption('locales');
        $localesToKeep = empty($localesToKeep) ? null : explode(',', (string) $localesToKeep);

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $locale = $file->getBasename('.php');
            $availableLocales[] = $locale;

            if ($localesToKeep === null || $localesToKeep === [] || \in_array($locale, $localesToKeep)) {
                $this->locales[] = $locale;
                $this->translator->addResource(
                    'php',
                    $file->getRealPath(),
                    $locale,
                    'install'
                );
            }
        }

        if ($this->locales === null || $this->locales === []) {
            throw new \RuntimeException(
                \sprintf(
                    'You should at least generate sql for one locale. Available locales : %s',
                    implode(', ', $availableLocales)
                )
            );
        }
    }

    /**
     * Initialize the smarty parser.
     *
     * The intl function is replaced, and locales are assigned.
     *
     * @throws \SmartyException
     */
    protected function initParser(): void
    {
        $this->parser->unregisterPlugin('function', 'intl');
        $this->parser->registerPlugin('function', 'intl', $this->translate(...));
        $this->parser->assign('locales', $this->locales);
    }

    /**
     * Smarty function that replace the classic `intl` function.
     *
     * The attributes of the function are:
     * - `l`: the key
     * - `locale`: the locale. eg.: fr_FR
     * - `in_string`: set to 1 not add simple quote around the string. (default = 0)
     * - `use_default`: set to 1 to use the `l` string as a fallback. (default = 0)
     *
     * @return string
     */
    public function translate($params, $smarty)
    {
        $translation = '';

        if (empty($params['l'])) {
            throw new RuntimeException('Translation Error. Key is empty.');
        }

        if (empty($params['locale'])) {
            throw new RuntimeException('Translation Error. Locale is empty.');
        }

        $inString = (0 !== (int) $params['in_string']);
        $useDefault = (0 !== (int) $params['use_default']);

        $translation = $this->translator->trans(
            $params['l'],
            [],
            'install',
            $params['locale'],
            $useDefault
        );

        if (empty($translation)) {
            $translation = ($inString) ? '' : 'NULL';
        } else {
            $translation = $this->con->quote($translation);
            // remove quote
            if ($inString) {
                $translation = substr($translation, 1, -1);
            }
        }

        return $translation;
    }
}
