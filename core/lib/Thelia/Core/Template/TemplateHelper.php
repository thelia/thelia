<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template;

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
    public function getStandardTemplateDefinitions() {
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
     * @param int $templateType the template type
     * @return An array of \Thelia\Core\Template\TemplateDefinition
     */
    public function getList($templateType) {

        $list = $exclude = array();

        $tplIterator = TemplateDefinition::getStandardTemplatesSubdirsIterator();

        foreach($tplIterator as $type => $subdir) {

            if ($templateType == $type) {

                $baseDir = THELIA_TEMPLATE_DIR.$subdir;

                // Every subdir of the basedir is supposed to be a template.
                $di = new \DirectoryIterator($baseDir);

                foreach ($di as $file) {
                // Ignore 'dot' elements
                if ($file->isDot() || ! $file->isDir()) continue;

                    // Ignore reserved directory names
                    if (in_array($file->getFilename()."/", $exclude)) continue;

                    $list[] = new TemplateDefinition($file->getFilename(), $templateType);
                }

                return $list;
            }
        }
    }

    protected function normalize_path($path)
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
     * @param  array                               $strings       the list of strings
     * @throws \InvalidArgumentException           if $walkMode contains an invalid value
     * @return number                              the total number of translatable texts
     */
    public function walkDir($directory, $walkMode, Translator $translator, $currentLocale, &$strings)
    {
        $num_texts = 0;

        if ($walkMode == self::WALK_MODE_PHP) {
            $prefix = '\-\>[\s]*trans[\s]*\(';

            $allowed_exts = array('php');
        } elseif ($walkMode == self::WALK_MODE_TEMPLATE) {
            $prefix = '\{intl[\s]l=';

            $allowed_exts = array('html', 'tpl', 'xml');
        } else {
            throw new \InvalidArgumentException(
                    Translator::getInstance()->trans('Invalid value for walkMode parameter: %value', array('%value' => $walkMode))
            );
        }

        try {

            Tlog::getInstance()->debug("Walking in $directory, in mode $walkMode");

            foreach (new \DirectoryIterator($directory) as $fileInfo) {

                if ($fileInfo->isDot()) continue;

                if ($fileInfo->isDir()) $num_texts += $this->walkDir($fileInfo->getPathName(), $walkMode, $translator, $currentLocale, $strings);

                if ($fileInfo->isFile()) {

                    $ext = $fileInfo->getExtension();

                    if (in_array($ext, $allowed_exts)) {

                        if ($content = file_get_contents($fileInfo->getPathName())) {

                            $short_path = $this->normalize_path($fileInfo->getPathName());

                            Tlog::getInstance()->debug("Examining file $short_path\n");

                            $matches = array();

                            if (preg_match_all('/'.$prefix.'((?<![\\\\])[\'"])((?:.(?!(?<![\\\\])\1))*.?)\1/ms', $content, $matches)) {

                                Tlog::getInstance()->debug("Strings found: ", $matches[2]);

                                foreach ($matches[2] as $match) {

                                    $hash = md5($match);

                                    if (isset($strings[$hash])) {
                                        if (! in_array($short_path, $strings[$hash]['files'])) {
                                            $strings[$hash]['files'][] = $short_path;
                                        }
                                    } else {
                                        $num_texts++;

                                        // remove \'
                                        $match = str_replace("\\'", "'", $match);

                                        $strings[$hash] = array(
                                                'files'   => array($short_path),
                                                'text'  => $match,
                                                'translation' => $translator->trans($match, array(), 'messages', $currentLocale, false),
                                                'dollar'  => strstr($match, '$') !== false
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $num_texts;

        } catch (\UnexpectedValueException $ex) {
            echo $ex;
        }
    }


    public function write_translation($file, $texts, $translations)
    {
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
