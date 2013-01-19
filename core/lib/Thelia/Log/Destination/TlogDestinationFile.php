<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Log\Destination;

use Thelia\Log\AbstractTlogDestination;
use Thelia\Log\TlogDestinationConfig;

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
            $this->path_defaut = THELIA_ROOT . "log/" . self::TLOG_DEFAULT_NAME;
            parent::__construct();
    }

    public function configure()
    {
        $file_path = $this->getConfig(self::VAR_PATH_FILE);
        $mode = strtolower($this->getConfig(self::VAR_MODE)) == 'a' ? 'a' : 'w';

        if (! empty($file_path)) {
            if (! is_file($file_path)) {
                    $dir = dirname($file_path);
                    if (! is_dir($dir)) {
                            mkdir($dir, 0777, true);
                    }
            }

            if ($this->fh) @fclose($this->fh);

            $this->fh = fopen($file_path, $mode);
        }
    }

    public function getTitle()
    {
            return "Text File";
    }

    public function getDescription()
    {
            return "Store logs into text file";
    }

    public function getConfigs()
    {
        return array(
            new TlogDestinationConfig(
                self::VAR_PATH_FILE,
                "Chemin du fichier",
                "Attention, vous devez indiquer un chemin absolu.<br />Le répertoire de base de votre Thelia est ".dirname(getcwd()),
                $this->path_defaut,
                TlogDestinationConfig::TYPE_TEXTFIELD
            ),
            new TlogDestinationConfig(
                self::VAR_MODE,
                "Mode d'ouverture (A ou E)",
                "Indiquez E pour ré-initialiser le fichier à chaque requête, A pour ne jamais réinitialiser le fichier. Pensez à le vider de temps en temps !",
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
        if ($this->fh) @fclose($this->fh);

        $this->fh = false;
    }
}
