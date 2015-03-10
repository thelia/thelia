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

    /** @var \PDO  */
    protected $con;

    protected function configure()
    {
        $this
            ->setName("generate:sql")
            ->setDescription("Generate SQL files (insert.sql, update*.sql)")
            ->addOption(
                "locale",
                null,
                InputOption::VALUE_OPTIONAL,
                "generate only for only specific locales (separated by a ,) : fr_FR,es_ES or es_ES"
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $container->set("request", new Request());
        $container->get("request")->setSession(new Session(new MockArraySessionStorage()));
        $container->enterScope("request");

        $this->translator = $container->get('thelia.translator');
        $this->parser = $container->get('thelia.parser');

        $this->con = Propel::getConnection(ProductTableMap::DATABASE_NAME);

        // load translations
        $finder = Finder::create()
            ->name('*.php')
            ->depth(0)
            ->in(THELIA_SETUP_DIRECTORY . 'I18n');

        $locales = [];

        // limit to only some locale(s)
        $localesToKeep = $input->getOption("locale");
        if (!empty($localesToKeep)) {
            $localesToKeep = explode(',', $localesToKeep);
        } else {
            $localesToKeep = null;
        }

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $locale = $file->getBasename('.php');
            if (empty($localesToKeep) || in_array($locale, $localesToKeep)) {
                $locales[] = $locale;
                $this->translator->addResource(
                    'php',
                    $file->getRealPath(),
                    $locale,
                    'install'
                );
            }
        }

        // replace the default intl function
        $this->parser->unregisterPlugin('function', 'intl');
        $this->parser->registerPlugin('function', 'intl', [$this, 'translate']);

        // Main insert.sql file
        $content = file_get_contents(THELIA_SETUP_DIRECTORY . 'insert.sql.tpl');
        $this->parser->assign("locales", $locales);
        $content = $this->parser->renderString($content, [], false);

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
            $filename = $file->getBasename();

            $content = file_get_contents($file->getRealPath());
            $content = $this->parser->renderString($content, [], false);

            // Main sql file
            $destination = THELIA_SETUP_DIRECTORY . 'update' . DS . 'sql' . DS . $file->getBasename('.tpl');
            if (false === file_put_contents($destination, $content)) {
                $output->writeln("Can't write file " . $destination);
            } else {
                $output->writeln("File " . $destination . ' generated successfully.');
            }
        }
    }

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
