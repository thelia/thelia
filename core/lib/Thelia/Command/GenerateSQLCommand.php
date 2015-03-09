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
use PDO;
use Propel\Runtime\Propel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
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

    protected $con;

    protected function configure()
    {
        $this
            ->setName("generate:sql")
            ->setDescription("Generate SQL files (insert.sql, update*.sql)");
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

        $finder = Finder::create()
            ->name('*.php')
            ->depth(0)
            ->in(THELIA_SETUP_DIRECTORY . 'I18n');

        $locales = [];

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $locale = $file->getBasename('.php');
            $locales[] = $locale;
            $this->translator->addResource(
                'php',
                $file->getRealPath(),
                $locale,
                'install'
            );
        }

        $this->parser->unregisterPlugin('function', 'intl');
        $this->parser->registerPlugin('function', 'intl', [$this, 'translate']);

        $content = file_get_contents(THELIA_SETUP_DIRECTORY . 'insert.sql.tpl');
        $this->parser->assign("locales", $locales);
        $content = $this->parser->renderString($content, [], false);

        // Main sql file
        if (false === file_put_contents(THELIA_SETUP_DIRECTORY . 'insert.sql', $content)) {
            $output->writeln("Can't write file " . THELIA_SETUP_DIRECTORY . 'insert.sql');
        } else {
            $output->writeln("File " . THELIA_SETUP_DIRECTORY . 'insert.sql generated successfully.');
        }

        // sql update file
        $finder = Finder::create()
            ->name('*.tpl')
            ->depth(0)
            ->in(THELIA_SETUP_DIRECTORY . 'update' . DS . 'tpl');

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            $filename = $file->getBasename();

            $output->writeln("Processing file " . $filename);

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
