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

namespace Thelia\Log\Destination;

use Thelia\Log\AbstractTlogDestination;
use Thelia\Log\TlogDestinationConfig;
use Thelia\Core\Translation\Translator;

class TlogDestinationFile extends AbstractTlogDestination
{
    // Nom des variables de configuration
    // ----------------------------------
    const VAR_PATH_FILE = "tlog_destinationfile_path";
    const TLOG_DEFAULT_NAME = "log-thelia.txt";

    const VAR_MODE = "tlog_destinationfile_mode";
    const VALEUR_MODE_DEFAULT = "A";

    const VAR_MAX_FILE_SIZE_KB = "tlog_destinationfile_max_file_size";
    const MAX_FILE_SIZE_KB_DEFAULT = 1024; // 1 Mb

    protected $path_defaut = false;
    protected $fh = false;

    public function __construct()
    {
        $this->path_defaut = THELIA_ROOT . "log" . DS . self::TLOG_DEFAULT_NAME;
        parent::__construct();
    }

    public function configure()
    {
        $file_path = $this->getConfig(self::VAR_PATH_FILE);
        $mode = strtolower($this->getConfig(self::VAR_MODE, self::VALEUR_MODE_DEFAULT)) == 'a' ? 'a' : 'w';

        if (! empty($file_path)) {
            if (! is_file($file_path)) {
                $dir = dirname($file_path);
                if (! is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                touch($file_path);
                chmod($file_path, 0666);
            }

            if ($this->fh) @fclose($this->fh);

            if (filesize($file_path) > 1024 * $this->getConfig(self::VAR_MAX_FILE_SIZE_KB, self::MAX_FILE_SIZE_KB_DEFAULT)) {

                $idx = 1;

                do {
                    $file_path_bk = "$file_path.$idx";

                    $idx++;

                } while (file_exists($file_path_bk));

                @rename($file_path, $file_path_bk);

                @touch($file_path);
                @chmod($file_path, 0666);
            }

            $this->fh = fopen($file_path, $mode);
        }
    }

    public function getTitle()
    {
            return Translator::getInstance()->trans('Text File');
    }

    public function getDescription()
    {
            return Translator::getInstance()->trans('Store logs into text file');
    }

    public function getConfigs()
    {
        return array(
            new TlogDestinationConfig(
                self::VAR_PATH_FILE,
                'Absolute file path',
                'You should enter an abolute file path. The base directory of your Thelia installation is '.THELIA_ROOT,
                $this->path_defaut,
                TlogDestinationConfig::TYPE_TEXTFIELD
            ),
            new TlogDestinationConfig(
                self::VAR_MODE,
                'File opening mode (A or E)',
                'Enter E to empty this file for each request, or A to always append logs. Consider resetting the file from time to time',
                self::VALEUR_MODE_DEFAULT,
                TlogDestinationConfig::TYPE_TEXTFIELD
            ),
            new TlogDestinationConfig(
                self::VAR_MAX_FILE_SIZE_KB,
                'Maximum log file size, in Kb',
                'When this size if exeeded, a backup copy of the file is made, and a new log file is opened. As the file size check is performed only at the beginning of a request, the file size may be bigger thant this limit. Note: 1 Mb = 1024 Kb',
                self::MAX_FILE_SIZE_KB_DEFAULT,
                TlogDestinationConfig::TYPE_TEXTFIELD
            )
        );
    }

    public function add($texte)
    {
        if ($this->fh) {
            fwrite($this->fh, $texte."\n");
        }
    }

    public function write(&$res)
    {
        if ($this->fh) @fclose($this->fh);

        $this->fh = false;
    }
}
