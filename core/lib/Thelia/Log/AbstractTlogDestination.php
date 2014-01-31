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

namespace Thelia\Log;

abstract class AbstractTlogDestination
{
    //Tableau de TlogDestinationConfig paramétrant la destination
    protected $_configs = array();

    //Tableau des lignes de logs stockés avant utilisation par ecrire()
    protected $_logs = array();

    public function __construct()
    {
        // Initialiser les variables de configuration
        $this->_configs = $this->getConfigs();

         // Appliquer la configuration
         $this->configure();
    }

    //Affecte une valeur à une configuration de la destination
    public function setConfig($name, $value, $apply_changes = true)
    {
        foreach ($this->_configs as $config) {
            if ($config->getName() == $name) {
                $config->setValue($value);

                // Appliquer les changements
                if ($apply_changes) $this->configure();

                return true;
            }
        }

        return false;
    }

    //Récupère la valeur affectée à une configuration de la destination
    public function getConfig($name, $default = false)
    {
        foreach ($this->_configs as $config) {
            if ($config->getName() == $name) {
                return $config->getValue();
            }
        }

        return $default;
    }

    public function getConfigs()
    {
        return $this->_configs;
    }

    //Ajoute une ligne de logs à la destination
    public function add($string)
    {
        $this->_logs[] = $string;
    }

    protected function insertAfterBody(&$res, $logdata)
    {
        $match = array();

        if (preg_match("/(<body[^>]*>)/i", $res, $match)) {
                $res = str_replace($match[0], $match[0] . "\n" . $logdata, $res);
        }
    }

    // Demande à la destination de se configurer pour être prête
    // a fonctionner. Si $config est != false, celà indique
    // que seul le paramètre de configuration indiqué a été modifié.
    protected function configure()
    {
        // Cette methode doit etre surchargée si nécessaire.
    }

    //Lance l'écriture de tous les logs par la destination
    //$res : contenu de la page html
    abstract public function write(&$res);

    // Retourne le titre de cette destination, tel qu'affiché dans le menu de selection
    abstract public function getTitle();

    // Retourne une brève description de la destination
    abstract public function getDescription();
}
