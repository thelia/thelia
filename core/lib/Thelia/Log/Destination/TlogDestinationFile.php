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

    protected $path_defaut = false;
    protected $fh = false;

    public function __construct()
    {
        $this->path_defaut = "log" . DS . self::TLOG_DEFAULT_NAME;
        parent::__construct();
    }

    protected function getFilePath()
    {
        $filePath = $this->getConfig(self::VAR_PATH_FILE);

        if (preg_match('/^[a-z]:\\\|^\//i', $filePath) === 0) {
            $filePath = THELIA_ROOT . $filePath;
        }

        return $filePath;
    }

    protected function getOpenMode()
    {
        return strtolower($this->getConfig(self::VAR_MODE, self::VALEUR_MODE_DEFAULT)) == 'a' ? 'a' : 'w';
    }

    public function configure()
    {
        $filePath = $this->getFilePath();
        $mode = $this->getOpenMode();

        if (!empty($filePath)) {
            $this->resolvePath($filePath, $mode);
        }
    }

    protected function resolvePath($filePath, $mode)
    {
        if (! empty($filePath)) {
            if (! is_file($filePath)) {
                $dir = dirname($filePath);
                if (! is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                touch($filePath);
                chmod($filePath, 0666);
            }

            if ($this->fh) {
                @fclose($this->fh);
            }

            $this->fh = fopen($filePath, $mode);
            return true;
        }

        return false;
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
        if ($this->fh) {
            @fclose($this->fh);
        }

        $this->fh = false;
    }
}
