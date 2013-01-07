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

use Thelia\Model\Config;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 
 * Thelia Logger
 * 
 * Allow to define different level and output.
 * 
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class Tlog Implements TlogInterface
{
    // Nom des variables de configuration
    const VAR_LEVEL 		= "tlog_level";
    const VAR_DESTINATIONS 	= "tlog_destinations";
    const VAR_PREFIXE 		= "tlog_prefix";
    const VAR_FILES 		= "tlog_files";
    const VAR_IP                = "tlog_ip";
    const VAR_SHOW_REDIRECT     = "tlog_show_redirect";

    // all level of trace
    const TRACE                 = 1;
    const DEBUG                 = 2;
    const WARNING               = 3;
    const INFO                  = 4;
    const ERROR                 = 5;
    const FATAL                 = 6;
    const MUET                  = PHP_INT_MAX;

    // default values
    const DEFAULT_LEVEL 	= self::DEBUG;
    const DEFAUT_DESTINATIONS   = "Thelia\Log\Destination\TlogDestinationFile";
    const DEFAUT_PREFIXE 	= "#NUM: #NIVEAU [#FICHIER:#FONCTION()] {#LIGNE} #DATE #HEURE: ";
    const DEFAUT_FILES 		= "*";
    const DEFAUT_IP 		= "";
    const DEFAUT_SHOW_REDIRECT  = 0;

    private static $instance = false;

    protected $destinations = array();

    protected $mode_back_office = false;
    protected $level = self::MUET;
    protected $prefixe = "";
    protected $files = array();
    protected $all_files = false;
    protected $show_redirect = false;

    private $linecount = 0;

    protected static $ecrire_effectue = false;

    // directories where are the Destinations Files
    public $dir_destinations = array();
    
    protected $container;
    
    /**
     * 
     * @param type $container
     * 
     * public function __construct(ContainerBuilder $container)
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        
        $this->init();
    }

//    public static function instance() {
//            if (self::$instance == false) {
//                    self::$instance = new Tlog();
//
//                    // On doit placer les initialisations à ce level pour pouvoir
//                    // utiliser la classe Tlog dans les classes de base (Cnx, BaseObj, etc.)
//                    // Les placer dans le constructeur provoquerait une boucle
//                    self::$instance->init();
//            }
//
//            return self::$instance;
//    }

    protected function init() {
            $config = $this->container->get("model.config");
        
            $level = $config->read(self::VAR_LEVEL, self::DEFAULT_LEVEL);

            $this->set_level($level);

            $this->dir_destinations = array(
                    __DIR__.'/Destination'
                    //, __DIR__.'/../client/tlog/destinations'
            );

            $this->set_prefixe($config->read(self::VAR_PREFIXE, self::DEFAUT_PREFIXE));
            $this->set_files($config->read(self::VAR_FILES, self::DEFAUT_FILES));
            $this->set_ip($config->read(self::VAR_IP, self::DEFAUT_IP));
            $this->set_destinations($config->read(self::VAR_DESTINATIONS, self::DEFAUT_DESTINATIONS));
            $this->set_show_redirect($config->read(self::VAR_SHOW_REDIRECT, self::DEFAUT_SHOW_REDIRECT));

            // Au cas ou il y aurait un exit() quelque part dans le code.
            register_shutdown_function(array($this, 'ecrire_si_exit'));
    }

    // Configuration
    // -------------

    public function set_destinations($destinations) {
            if (! empty($destinations)) {

                    $this->destinations = array();

                    $classes_destinations = explode(';', $destinations);
                    $this->loadDestinations($this->destinations, $classes_destinations);
            }
    }

    public function set_level($level) {
            $this->level = $level;
    }

    public function set_prefixe($prefixe) {

            $this->prefixe = $prefixe;
    }

    public function set_files($files) {
            $this->files = explode(";", $files);

            $this->all_files = in_array('*', $this->files);
    }

    public function set_ip($ips) {
            if (! empty($ips) && ! in_array($_SERVER['REMOTE_ADDR'], explode(";", $ips))) $this->level = self::MUET;
    }

    public function set_show_redirect($bool) {
            $this->show_redirect = $bool;
    }

    // Configuration d'une destination
    public function set_config($destination, $param, $valeur) {

            if (isset($this->destinations[$destination])) {
                    $this->destinations[$destination]->set_config($param, $valeur);
            }
    }

    // Configuration d'une destination
    public function get_config($destination, $param) {

            if (isset($this->destinations[$destination])) {
                    return $this->destinations[$destination]->get_config($param);
            }

            return false;
    }

    // Methodes d'accès aux traces
    // ---------------------------

    public function trace() {
        if ($this->level > self::TRACE)
            return;

        $args = func_get_args();

        $this->out("TRACE", $args);
    }

    public function debug() {
        if ($this->level > self::DEBUG)
            return;

        $args = func_get_args();

        $this->out("DEBUG", $args);
    }

    public function info() {
        if ($this->level > self::INFO)
            return;

        $args = func_get_args();

        $this->out("INFO", $args);
    }

    public function warning() {
        if ($this->level > self::WARNING)
            return;

        $args = func_get_args();

        $this->out("WARNING", $args);
    }

    public function error() {
        if ($this->level > self::ERROR)
            return;

        $args = func_get_args();

        $this->out("ERREUR", $args);
    }

    public function fatal() {
        if ($this->level > self::FATAL)
            return;

        $args = func_get_args();

        $this->out("FATAL", $args);
    }

    // Mode back office
    public static function mode_back_office($booleen) {

            foreach(Tlog::instance()->destinations as $dest) {
                    $dest->mode_back_office($booleen);
            }
    }

    // Ecriture finale
    public function ecrire(&$res) {

            self::$ecrire_effectue = true;

            // Muet ? On ne fait rien
            if ($this->level == self::MUET) return;

            foreach($this->destinations as $dest) {
                    $dest->ecrire($res);
            }
    }

    public function ecrire_si_exit() {
        // Si les infos de debug n'ont pas été ecrites, le faire maintenant
        if (self::$ecrire_effectue === false) {

                $res = "";

                $this->ecrire($res);

                echo $res;
        }
    }

    public function afficher_redirections($url) {

        if ($this->level != self::MUET && $this->show_redirect) {
                echo "
<html>
<head><title>Redirection...</title></head>
<body>
<a href=\"$url\">Redirection vers $url</a>
</body>
</html>
        ";

                return true;
        }
        else {
                return false;
        }
    }

    // Permet de déterminer si la trace est active, en prenant en compte
    // le level et le filtrage par fichier
    public function active($level) {

        if ($this->level <= $level) {

            $origine = $this->trouver_origine();

            $file = basename($origine['file']);

            if ($this->is_file_active($file)) {
                    return true;
            }
        }

        return false;
    }

    public function is_file_active($file) {
        return ($this->all_files || in_array($file, $this->files)) && ! in_array("!$file", $this->files);
    }

    /* -- Methodes privees ---------------------------------------- */

    // Adapté de LoggerLoginEvent dans log4php
    private function trouver_origine() {

        $origine = array();

        if(function_exists('debug_backtrace')) {

            $trace = debug_backtrace();
            $prevHop = null;
            // make a downsearch to identify the caller
            $hop = array_pop($trace);
            
            while($hop !== null) {
                if(isset($hop['class'])) {
                    // we are sometimes in functions = no class available: avoid php warning here
                    $className = $hop['class'];

                    if (! empty($className) and ($className == ltrim($this->container->getParameter("logger.class"),'\\') or strtolower(get_parent_class($className)) == ltrim($this->container->getParameter("logger.class"),'\\'))) {
                            $origine['line'] = $hop['line'];
                            $origine['file'] = $hop['file'];
                            break;
                    }
                }
                $prevHop = $hop;
                $hop = array_pop($trace);
            }

            $origine['class'] = isset($prevHop['class']) ? $prevHop['class'] : 'main';

            if(isset($prevHop['function']) and
                $prevHop['function'] !== 'include' and
                $prevHop['function'] !== 'include_once' and
                $prevHop['function'] !== 'require' and
                $prevHop['function'] !== 'require_once') {

                $origine['function'] = $prevHop['function'];
            } else {
                $origine['function'] = 'main';
            }
        }

        return $origine;
    }

    private function out($level, $tabargs) {

        $text = '';

        foreach ($tabargs as $arg) {
            $text .= is_scalar($arg) ? $arg : print_r($arg, true);
        }

        $origine = $this->trouver_origine();

        $file = basename($origine['file']);

        if ($this->is_file_active($file)) {

            $function = $origine['function'];
            $line = $origine['line'];

            $prefixe = str_replace(
                array("#NUM", "#NIVEAU", "#FICHIER", "#FONCTION", "#LIGNE", "#DATE", "#HEURE"),
                array(1+$this->linecount, $level, $file, $function, $line, date("Y-m-d"), date("G:i:s")),
                $this->prefixe
            );

            $trace = $prefixe . $text;

            foreach($this->destinations as $dest) {
                $dest->ajouter($trace);
            }

            $this->linecount++;
        }
    }
    
    /**
     * 
     * @param type $destinations
     * @param array $actives array containing classes instanceof AbstractTlogDestination
     */
    protected function loadDestinations(&$destinations, array $actives = NULL) {
        
        foreach($actives as $active)
        {
            if(class_exists($active))
            {
                $class = new $active($this->container->get('model.config'));
                
                if(!$class instanceof AbstractTlogDestination)
                {
                    throw new \UnexpectedValueException($active." must extends Thelia\Tlog\AbstractTlogDestination");
                }
                
                $destinations[$active] = $class;
            }
        }
    }
}