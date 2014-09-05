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

namespace Thelia\Core\Template;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Model\ConfigQuery;
use Thelia\Log\Tlog;
use Thelia\Core\Translation\Translator;

class TemplateHelper
{
    const WALK_MODE_PHP = 'php';
    const WALK_MODE_TEMPLATE = 'tpl';

    /**
     * This is a singleton

     */
    private static $instance = null;

    private function __construct() {}

    public static function getInstance()
    {
        if (self::$instance == null) self::$instance = new TemplateHelper();
        return self::$instance;
    }

    /**
     * @return TemplateDefinition
     */
    public function getActiveMailTemplate()
    {
        return new TemplateDefinition(
                ConfigQuery::read('active-mail-template', 'default'),
                TemplateDefinition::EMAIL
        );
    }

    /**
     * Check if a template definition is the current active template
     *
     * @param  TemplateDefinition $tplDefinition
     * @return bool               true is the given template is the active template
     */
    public function isActive(TemplateDefinition $tplDefinition)
    {
        switch ($tplDefinition->getType()) {
            case TemplateDefinition::FRONT_OFFICE:
                $tplVar = 'active-front-template';
                break;
             case TemplateDefinition::BACK_OFFICE:
                 $tplVar = 'active-admin-template';
                 break;
            case TemplateDefinition::PDF:
                 $tplVar = 'active-pdf-template';
                 break;
            case TemplateDefinition::EMAIL:
                $tplVar = 'active-mail-template';
                break;
        }

        return $tplDefinition->getName() == ConfigQuery::read($tplVar, 'default');
    }
    /**
     * @return TemplateDefinition
     */
    public function getActivePdfTemplate()
    {
        return new TemplateDefinition(
                ConfigQuery::read('active-pdf-template', 'default'),
                TemplateDefinition::PDF
        );
    }

    /**
     * @return TemplateDefinition
     */
    public function getActiveAdminTemplate()
    {
        return new TemplateDefinition(
                ConfigQuery::read('active-admin-template', 'default'),
                TemplateDefinition::BACK_OFFICE
        );
    }

    /**
     * @return TemplateDefinition
     */
    public function getActiveFrontTemplate()
    {
        return new TemplateDefinition(
                ConfigQuery::read('active-front-template', 'default'),
                TemplateDefinition::FRONT_OFFICE
        );
    }

    /**
     * Returns an array which contains all standard template definitions
     */
    public function getStandardTemplateDefinitions()
    {
        return array(
                $this->getActiveFrontTemplate(),
                $this->getActiveAdminTemplate(),
                $this->getActivePdfTemplate(),
                $this->getActiveMailTemplate(),
        );
    }

    /**
     * Return a list of existing templates for a given template type
     *
     * @param  int                  $templateType the template type
     * @param string the template base (module or core, default to core).
     * @return TemplateDefinition[] of \Thelia\Core\Template\TemplateDefinition
     */
    public function getList($templateType, $base = THELIA_TEMPLATE_DIR)
    {
        $list = $exclude = array();

        $tplIterator = TemplateDefinition::getStandardTemplatesSubdirsIterator();

        foreach ($tplIterator as $type => $subdir) {

            if ($templateType == $type) {

                $baseDir = rtrim($base, DS).DS.$subdir;

                try {
                    // Every subdir of the basedir is supposed to be a template.
                    $di = new \DirectoryIterator($baseDir);

                    /** @var \DirectoryIterator $file */
                    foreach ($di as $file) {
                        // Ignore 'dot' elements
                        if ($file->isDot() || ! $file->isDir()) continue;

                        // Ignore reserved directory names
                        if (in_array($file->getFilename(), $exclude)) continue;

                        $list[] = new TemplateDefinition($file->getFilename(), $templateType);
                    }
                } catch (\UnexpectedValueException $ex) {
                    // Ignore the exception and continue
                }
            }
        }

        return $list;
    }

    protected function normalizePath($path)
    {
        $path = str_replace(
            str_replace('\\', '/', THELIA_ROOT),
            '',
            str_replace('\\', '/', realpath($path))
        );

        return ltrim($path, '/');
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
     * @param  string                              $directory     the path to the directory to examine
     * @param  string                              $walkMode      type of file scanning: WALK_MODE_PHP or WALK_MODE_TEMPLATE
     * @param  \Thelia\Core\Translation\Translator $translator    the current translator
     * @param  string                              $currentLocale the current locale
     * @param  string                              $domain        the translation domain (fontoffice, backoffice, module, etc...)
     * @param  array                               $strings       the list of strings
     * @throws \InvalidArgumentException           if $walkMode contains an invalid value
     * @return number                              the total number of translatable texts
     */
    public function walkDir($directory, $walkMode, Translator $translator, $currentLocale, $domain, &$strings)
    {
        $num_texts = 0;

        if ($walkMode == self::WALK_MODE_PHP) {
            $prefix = '\-\>[\s]*trans[\s]*\([\s]*';

            $allowed_exts = array('php');
        } elseif ($walkMode == self::WALK_MODE_TEMPLATE) {
            $prefix = '\{intl(?:.*?)l=[\s]*';

            $allowed_exts = array('html', 'tpl', 'xml', 'txt');
        } else {
            throw new \InvalidArgumentException(
                    Translator::getInstance()->trans('Invalid value for walkMode parameter: %value', array('%value' => $walkMode))
            );
        }

        try {

            Tlog::getInstance()->debug("Walking in $directory, in mode $walkMode");

            /** @var \DirectoryIterator $fileInfo */
            foreach (new \DirectoryIterator($directory) as $fileInfo) {

                if ($fileInfo->isDot()) continue;

                if ($fileInfo->isDir()) $num_texts += $this->walkDir($fileInfo->getPathName(), $walkMode, $translator, $currentLocale, $domain, $strings);

                if ($fileInfo->isFile()) {

                    $ext = $fileInfo->getExtension();

                    if (in_array($ext, $allowed_exts)) {

                        if ($content = file_get_contents($fileInfo->getPathName())) {

                            $short_path = $this->normalizePath($fileInfo->getPathName());

                            Tlog::getInstance()->debug("Examining file $short_path\n");

                            $matches = array();

                            if (preg_match_all('/'.$prefix.'((?<![\\\\])[\'"])((?:.(?!(?<![\\\\])\1))*.?)\1/ms', $content, $matches)) {

                                Tlog::getInstance()->debug("Strings found: ", $matches[2]);

                                $idx = 0;

                                foreach ($matches[2] as $match) {

                                    $hash = md5($match);

                                    if (isset($strings[$hash])) {
                                        if (! in_array($short_path, $strings[$hash]['files'])) {
                                            $strings[$hash]['files'][] = $short_path;
                                        }
                                    } else {
                                        $num_texts++;

                                        // remove \' (or \"), that will prevent the translator to work properly, as
                                        // "abc \def\" ghi" will be passed as abc "def" ghi to the translator.

                                        $quote = $matches[1][$idx];

                                        $match = str_replace("\\$quote", $quote, $match);

                                        $strings[$hash] = array(
                                                'files'   => array($short_path),
                                                'text'  => $match,
                                                'translation' => $translator->trans($match, array(), $domain, $currentLocale, false),
                                                'dollar'  => strstr($match, '$') !== false
                                        );
                                    }

                                    $idx++;
                                }
                            }
                        }
                    }
                }
            }

            return $num_texts;

        } catch (\UnexpectedValueException $ex) {
            // Directory does not exists => ignore/
        }
    }


    public function writeTranslation($file, $texts, $translations, $createIfNotExists = false)
    {
        $fs = new Filesystem();

        if (! $fs->exists($file) && true === $createIfNotExists) {

            $dir = dirname($file);

            if (! $fs->exists($file)) {
                $fs->mkdir($dir);
            }
        }

        if ($fp = @fopen($file, 'w')) {

            fwrite($fp, '<' . "?php\n\n");
            fwrite($fp, "return array(\n");

            // Sort keys alphabetically while keeping index
            asort($texts);

            foreach ($texts as $key => $text) {
                // Write only defined (not empty) translations
                if (! empty($translations[$key])) {
                    $text = str_replace("'", "\'", $text);

                    $translation = str_replace("'", "\'", $translations[$key]);

                    fwrite($fp, sprintf("    '%s' => '%s',\n", $text, $translation));
                }
            }

            fwrite($fp, ");\n");

            @fclose($fp);
        } else {
            throw new \RuntimeException(
                Translator::getInstance()->trans(
                    'Failed to open translation file %file. Please be sure that this file is writable by your Web server',
                    array('%file' => $file)
                )
            );
        }
    }
}
