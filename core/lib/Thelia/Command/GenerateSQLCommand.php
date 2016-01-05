<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Command;

use Imagine\Exception\RuntimeException;
use Propel\Runtime\Propel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Tools\Version\Version;
use TheliaSmarty\Template\SmartyParser;

/**
 * Class GenerateSQLCommand
 * @package Thelia\Command
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class GenerateSQLCommand extends ContainerAwareCommand
{
    /** @var Translator $translator */
    protected $translator = null;

    /** @var SmartyParser $parser */
    protected $parser = null;

    /** @var \PDO */
    protected $con;

    /** @var array */
    protected $locales;

    protected function configure()
    {
        $this
            ->setName("generate:sql")
            ->setDescription("Generate SQL files (insert.sql, update*.sql)")
            ->addOption(
                "locales",
                null,
                InputOption::VALUE_OPTIONAL,
                "generate only for only specific locales (separated by a ,) : fr_FR,es_ES or es_ES"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($input);

        // Main insert.sql file
        $content = file_get_contents(THELIA_SETUP_DIRECTORY . 'insert.sql.tpl');
        $version = Version::parse();
        $content = $this->parser->renderString($content, $version, false);

        if (false === file_put_contents(THELIA_SETUP_DIRECTORY . 'insert.sql', $content)) {
            $output->writeln("Can't write file " . THELIA_SETUP_DIRECTORY . 'insert.sql');
        } else {
            $output->writeln("File " . THELIA_SETUP_DIRECTORY . 'insert.sql generated successfully.');
        }

        // sql update files
        $finder = Finder::create()
            ->name('*.tpl')
            ->depth(0)
            ->in(THELIA_SETUP_DIRECTORY . 'update' . DS . 'tpl');

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            $content = $this->parser->renderString($content, [], false);

            $destination = THELIA_SETUP_DIRECTORY . 'update' . DS . 'sql' . DS . $file->getBasename('.tpl');

            if (false === file_put_contents($destination, $content)) {
                $output->writeln("Can't write file " . $destination);
            } else {
                $output->writeln("File " . $destination . ' generated successfully.');
            }
        }
    }

    protected function init(InputInterface $input)
    {
        $container = $this->getContainer();

        $container->set("request", new Request());
        $container->get("request")->setSession(new Session(new MockArraySessionStorage()));

        $this->translator = $container->get('thelia.translator');
        $this->parser = $container->get('thelia.parser');

        $this->con = Propel::getConnection(ProductTableMap::DATABASE_NAME);

        $this->initLocales($input);

        $this->initParser();
    }

    /**
     * @param InputInterface $input
     * @return array
     */
    protected function initLocales(InputInterface $input)
    {
        $this->locales = [];
        $availableLocales = [];

        $finder = Finder::create()
            ->name('*.php')
            ->depth(0)
            ->sortByName()
            ->in(THELIA_SETUP_DIRECTORY . 'I18n');

        // limit to only some locale(s)
        $localesToKeep = $input->getOption("locales");
        if (!empty($localesToKeep)) {
            $localesToKeep = explode(',', $localesToKeep);
        } else {
            $localesToKeep = null;
        }

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $locale = $file->getBasename('.php');
            $availableLocales[] = $locale;

            if (empty($localesToKeep) || in_array($locale, $localesToKeep)) {
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
                    "You should at least generate sql for one locale. Available locales : %s",
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
    protected function initParser()
    {
        $this->parser->unregisterPlugin('function', 'intl');
        $this->parser->registerPlugin('function', 'intl', [$this, 'translate']);
        $this->parser->assign("locales", $this->locales);
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
     * @param $params
     * @param $smarty
     * @return string
     */
    public function translate($params, $smarty)
    {
        $translation = '';

        if (empty($params["l"])) {
            throw new RuntimeException('Translation Error. Key is empty.');
        } elseif (empty($params["locale"])) {
            throw new RuntimeException('Translation Error. Locale is empty.');
        } else {
            $inString = (0 !== intval($params["in_string"]));
            $useDefault = (0 !== intval($params["use_default"]));

            $translation = $this->translator->trans(
                $params["l"],
                [],
                'install',
                $params["locale"],
                $useDefault
            );

            if (empty($translation)) {
                $translation = ($inString) ? '' : "NULL";
            } else {
                $translation = $this->con->quote($translation);
                // remove quote
                if ($inString) {
                    $translation = substr($translation, 1, -1);
                }
            }
        }

        return $translation;
    }
}
