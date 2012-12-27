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

namespace Thelia\Log;

abstract class AbstractTlogDestination {

    //Tableau de TlogDestinationConfig paramétrant la destination
    protected $_configs;

    //Tableau des lignes de logs stockés avant utilisation par ecrire()
    protected $_logs;

    // Vaudra true si on est dans le back office.
    protected $flag_back_office = false;
    
    protected $configModel;

    public function __construct() {
        $this->_configs = array();
        $this->_logs = array();

		// Initialiser les variables de configuration
         $this->_configs = $this->get_configs();

         // Appliquer la configuration
         $this->configurer();
    }

    //Affecte une valeur à une configuration de la destination
    public function set_config($nom, $valeur) {
        foreach($this->_configs as $config) {
            if($config->nom == $nom) {
                $config->valeur = $valeur;
                // Appliquer les changements
                $this->configurer($config);

                return true;
            }
        }
        return false;
    }
    
    public function setConfigModel($configModel)
    {
        $this->configModel = $configModel;
    }
    
    public function getConfigModel()
    {
        return $this->configModel;
    }

    //Récupère la valeur affectée à une configuration de la destination
    public function get_config($nom) {
        foreach($this->_configs as $config) {
            if($config->nom == $nom) {
                return $config->valeur;
            }
        }
        return false;
    }

    public function get_configs() {
        return $this->_configs;
    }

    public function mode_back_office($bool) {
            $this->flag_back_office = $bool;
    }

    //Ajoute une ligne de logs à la destination
    public function ajouter($string) {
        $this->_logs[] = $string;
    }

    protected function inserer_apres_body(&$res, $logdata) {

            $match = array();

            if (preg_match("/(<body[^>]*>)/i", $res, $match)) {
                    $res = str_replace($match[0], $match[0] . "\n" . $logdata, $res);
            }
            else {
                    $res = $logdata . $res;
            }
    }

    // Demande à la destination de se configurer pour être prête
    // a fonctionner. Si $config est != false, celà indique
    // que seul le paramètre de configuration indiqué a été modifié.
    protected function configurer($config = false) {
            // Cette methode doit etre surchargée si nécessaire.
    }

    //Lance l'écriture de tous les logs par la destination
    //$res : contenu de la page html
    abstract public function ecrire(&$res);

    // Retourne le titre de cette destination, tel qu'affiché dans le menu de selection
    abstract public function get_titre();

    // Retourne une brève description de la destination
    abstract public function get_description();
}