<?php

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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Tools\Version\Version;
use TheliaSmarty\Template\SmartyParser;

/**
 * Class GenerateSQLCommand.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class GenerateSQLCommand extends ContainerAwareCommand
{
    /** @var Translator */
    protected $translator;

    /** @var SmartyParser */
    protected $parser;

    /** @var \PDO */
    protected $con;

    /** @var array */
    protected $locales;

    protected function configure(): void
    {
        $this
            ->setName('generate:sql')
            ->setDescription('Generate setup/insert.sql from setup/insert.sql.tpl')
            ->addOption(
                'locales',
                null,
                InputOption::VALUE_OPTIONAL,
                'generate only for only specific locales (separated by a ,) : fr_FR,es_ES or es_ES. '
                .'Defaults to the locales of the languages the seed creates.'
            )
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'report whether setup/insert.sql is what the template produces, without writing it'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->init($input);

        $seed = THELIA_SETUP_DIRECTORY.'insert.sql';

        $content = file_get_contents($seed.'.tpl');
        $version = Version::parse();
        $content = $this->parser->renderString($content, $version, false);

        if ($input->getOption('check')) {
            if ($content === file_get_contents($seed)) {
                $output->writeln('File '.$seed.' is up to date.');

                return 0;
            }

            $output->writeln('<error>'.$seed.' is not what insert.sql.tpl and setup/I18n produce. Run: php Thelia generate:sql</error>');

            return 1;
        }

        if (false === file_put_contents($seed, $content)) {
            $output->writeln("Can't write file ".$seed);

            return 1;
        }

        $output->writeln('File '.$seed.' generated successfully.');

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
        if (!empty($localesToKeep)) {
            $localesToKeep = explode(',', $localesToKeep);
        } else {
            $localesToKeep = $this->getSeededLocales();
        }

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $locale = $file->getBasename('.php');
            $availableLocales[] = $locale;

            if (\in_array($locale, $localesToKeep, true)) {
                $this->locales[] = $locale;
                $this->translator->addResource(
                    'php',
                    $file->getRealPath(),
                    $locale,
                    'install'
                );
            }
        }

        if (empty($this->locales)) {
            throw new \RuntimeException(
                sprintf(
                    'You should at least generate sql for one locale. Available locales : %s',
                    implode(', ', $availableLocales)
                )
            );
        }
    }

    /**
     * The locales of the languages the seed creates, read from the `lang` block
     * of the template.
     *
     * Seeding the wording of a language the shop does not have would only bloat
     * the generated file, and it is what the shipped `insert.sql` holds: taking
     * every file of `setup/I18n` instead makes a plain run rewrite it.
     *
     * @return string[]
     */
    protected function getSeededLocales()
    {
        $template = file_get_contents(THELIA_SETUP_DIRECTORY.'insert.sql.tpl');

        if (1 !== preg_match('/INSERT INTO `lang`.*?;/s', $template, $langBlock)) {
            throw new \RuntimeException('insert.sql.tpl seeds no language.');
        }

        preg_match_all("/^\(\d+, '[^']*', '[^']*', '([^']*)'/m", $langBlock[0], $matches);

        return $matches[1];
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
        $this->parser->registerPlugin('function', 'intl', [$this, 'translate']);
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
        $inString = (0 !== (int) ($params['in_string'] ?? 0));
        $useDefault = (0 !== (int) ($params['use_default'] ?? 0));

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
