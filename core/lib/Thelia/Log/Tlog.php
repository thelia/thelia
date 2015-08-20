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
namespace Thelia\Log;

use Thelia\Model\ConfigQuery;
use Psr\Log\LoggerInterface;
use Thelia\Core\Translation\Translator;

/**
 *
 * Thelia Logger
 *
 * Allow to define different level and output.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class Tlog implements LoggerInterface
{
    // Nom des variables de configuration
    const VAR_LEVEL        = "tlog_level";
    const VAR_DESTINATIONS    = "tlog_destinations";
    const VAR_PREFIXE        = "tlog_prefix";
    const VAR_FILES        = "tlog_files";
    const VAR_IP                = "tlog_ip";
    const VAR_SHOW_REDIRECT     = "tlog_show_redirect";

    // all level of trace
    const DEBUG                 = 100;
    const INFO                  = 200;
    const NOTICE                = 300;
    const WARNING               = 400;
    const ERROR                 = 500;
    const CRITICAL              = 600;
    const ALERT                 = 700;
    const EMERGENCY             = 800;
    const MUET                  = PHP_INT_MAX;

    protected $levels = array(
        100 => "DEBUG",
        200 => "INFO",
        300 => "NOTICE",
        400 => "WARNING",
        500 => "ERROR",
        600 => "CRITICAL",
        700 => "ALERT",
        800 => "EMERGENCY"
    );

    // default values
    const DEFAULT_LEVEL         = self::ERROR;
    const DEFAUT_DESTINATIONS   = "Thelia\Log\Destination\TlogDestinationRotatingFile";
    const DEFAUT_PREFIXE    = "#INDEX: #LEVEL [#FILE:#FUNCTION()] {#LINE} #DATE #HOUR: ";
    const DEFAUT_FILES        = "*";
    const DEFAUT_IP        = "";
    const DEFAUT_SHOW_REDIRECT  = 0;

    /**
     *
     * @var \Thelia\Log\Tlog
     */
    private static $instance = false;

    /**
     *
     * @var array containing class of destination handler
     */
    protected $destinations = array();

    protected $mode_back_office = false;
    protected $level = self::MUET;
    protected $prefix = "";
    protected $files = array();
    protected $all_files = false;
    protected $show_redirect = false;

    private $linecount = 0;

    protected $done = false;

    // directories where are the Destinations Files
    public $dir_destinations = array();

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     *
     * @return \Thelia\Log\Tlog
     */
    public static function getInstance()
    {
        if (self::$instance == false) {
            self::$instance = new Tlog();

            // On doit placer les initialisations à ce level pour pouvoir
            // utiliser la classe Tlog dans les classes de base (Cnx, BaseObj, etc.)
            // Les placer dans le constructeur provoquerait une boucle
            self::$instance->init();
        }

        return self::$instance;
    }

    /**
     * Create a new Tlog instance, that could be configured without interfering with the "main" instance
     *
     * @return Tlog a new Tlog instance.
     */
    public static function getNewInstance()
    {
        $instance = new Tlog();

        $instance->init();

        return $instance;
    }

    /**
     * initialize default configuration
     */
    protected function init()
    {
        $this->setLevel(ConfigQuery::read(self::VAR_LEVEL, self::DEFAULT_LEVEL));

        $this->dir_destinations = array(
                __DIR__.DS.'Destination',
                THELIA_LOCAL_DIR.'tlog'.DS.'destinations'
        );

        $this->setPrefix(ConfigQuery::read(self::VAR_PREFIXE, self::DEFAUT_PREFIXE));
        $this->setFiles(ConfigQuery::read(self::VAR_FILES, self::DEFAUT_FILES));
        $this->setIp(ConfigQuery::read(self::VAR_IP, self::DEFAUT_IP));
        $this->setDestinations(ConfigQuery::read(self::VAR_DESTINATIONS, self::DEFAUT_DESTINATIONS));
        $this->setShowRedirect(ConfigQuery::read(self::VAR_SHOW_REDIRECT, self::DEFAUT_SHOW_REDIRECT));

        // Au cas ou il y aurait un exit() quelque part dans le code.
        register_shutdown_function(array($this, 'writeOnExit'));
    }

    // Configuration
    // -------------

    /**
     *
     * @param string $destinations
     */
    public function setDestinations($destinations)
    {
        if (! empty($destinations)) {
            $this->destinations = array();

            $classes_destinations = explode(';', $destinations);

            $this->loadDestinations($this->destinations, $classes_destinations);
        }

        return $this;
    }

    /**
     * Return the directories where destinations classes should be searched.
     *
     * @return array of directories
     */
    public function getDestinationsDirectories()
    {
        return $this->dir_destinations;
    }

    /**
     *
     * change the debug level. Use Tlog constant : \Thelia\Log\Tlog::DEBUG set level to Debug
     *
     * @param int $level
     */
    public function setLevel($level)
    {
        $this->level = $level;

        return $this;
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function setFiles($files)
    {
        $this->files = explode(";", $files);

        $this->all_files = in_array('*', $this->files);

        return $this;
    }

    public function setIp($ips)
    {
        // isset($_SERVER['REMOTE_ADDR']) if we are in cli mode
        if (! empty($ips) && isset($_SERVER['REMOTE_ADDR']) && ! in_array($_SERVER['REMOTE_ADDR'], explode(";", $ips))) {
            $this->level = self::MUET;
        }
        return $this;
    }

    public function setShowRedirect($bool)
    {
        $this->show_redirect = $bool;

        return $this;
    }

    // Configuration d'une destination
    public function setConfig($destination, $param, $valeur)
    {
        if (isset($this->destinations[$destination])) {
            $this->destinations[$destination]->setConfig($param, $valeur);
        }

        return $this;
    }

    // Configuration d'une destination
    public function getConfig($destination, $param)
    {
        if (isset($this->destinations[$destination])) {
            return $this->destinations[$destination]->getConfig($param);
        }

        return false;
    }

    // Methodes d'accès aux traces
    // ---------------------------

    /**
     * Detailed debug information.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function debug($message, array $context = array())
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     *
     * Alias of debug method. With this method you can put all parameter you want
     *
     * ex : Tlog::getInstance()->addDebug($arg1, $arg2, $arg3);
     *
     */
    public function addDebug()
    {
        $args = func_get_args();

        foreach ($args as $arg) {
            $this->log(self::DEBUG, $arg);
        }
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function info($message, array $context = array())
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     *
     * Alias of info method. With this method you can put all parameter you want
     *
     * ex : Tlog::getInstance()->addInfo($arg1, $arg2, $arg3);
     *
     */
    public function addInfo()
    {
        $args = func_get_args();

        foreach ($args as $arg) {
            $this->log(self::INFO, $arg);
        }
    }

    /**
     * Normal but significant events.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function notice($message, array $context = array())
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     *
     * Alias of notice method. With this method you can put all parameter you want
     *
     * ex : Tlog::getInstance()->addNotice($arg1, $arg2, $arg3);
     *
     */
    public function addNotice()
    {
        $args = func_get_args();

        foreach ($args as $arg) {
            $this->log(self::NOTICE, $arg);
        }
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function warning($message, array $context = array())
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     *
     * Alias of warning method. With this method you can put all parameter you want
     *
     * ex : Tlog::getInstance()->addWarning($arg1, $arg2, $arg3);
     *
     */
    public function addWarning()
    {
        $args = func_get_args();

        foreach ($args as $arg) {
            $this->log(self::WARNING, $arg);
        }
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function error($message, array $context = array())
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     *
     * Alias of error method. With this method you can put all parameter you want
     *
     * ex : Tlog::getInstance()->addError($arg1, $arg2, $arg3);
     *
     */
    public function addError()
    {
        $args = func_get_args();

        foreach ($args as $arg) {
            $this->log(self::ERROR, $arg);
        }
    }

    /**
     *
     * @see error()
     */
    public function err($message, array $context = array())
    {
        $this->error($message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function critical($message, array $context = array())
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     *
     * Alias of critical method. With this method you can put all parameter you want
     *
     * ex : Tlog::getInstance()->addCritical($arg1, $arg2, $arg3);
     *
     */
    public function addCritical()
    {
        $args = func_get_args();

        foreach ($args as $arg) {
            $this->log(self::CRITICAL, $arg);
        }
    }

    /**
     *
     * @see critical()
     */
    public function crit($message, array $context = array())
    {
        $this->critical($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function alert($message, array $context = array())
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     *
     * Alias of alert method. With this method you can put all parameter you want
     *
     * ex : Tlog::getInstance()->addAlert($arg1, $arg2, $arg3);
     *
     */
    public function addAlert()
    {
        $args = func_get_args();

        foreach ($args as $arg) {
            $this->log(self::ALERT, $arg);
        }
    }

    /**
     * System is unusable.
     *
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function emergency($message, array $context = array())
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     *
     * Alias of emergency method. With this method you can put all parameter you want
     *
     * ex : Tlog::getInstance()->addEmergency($arg1, $arg2, $arg3);
     *
     */
    public function addEmergency()
    {
        $args = func_get_args();

        foreach ($args as $arg) {
            $this->log(self::EMERGENCY, $arg);
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param  mixed  $level
     * @param  string $message
     * @param  array  $context
     * @return null
     */
    public function log($level, $message, array $context = array())
    {
        if ($this->level > $level || array_key_exists($level, $this->levels) === false) {
            return;
        }

        $this->out($this->levels[$level], $message, $context);
    }

    /**
     *
     * final end method. Write log for each destination handler
     *
     * @param  string $res
     * @return void
     */
    public function write(&$res)
    {
        $this->done = true;

        // Muet ? On ne fait rien
        if ($this->level == self::MUET) {
            return;
        }

        foreach ($this->destinations as $dest) {
            $dest->write($res);
        }
    }

    /**
     * @see write()
     */
    public function writeOnExit()
    {
        // Si les infos de debug n'ont pas été ecrites, le faire maintenant
        if ($this->done === false) {
            $res = "";

            $this->write($res);

            echo $res;
        }
    }

    public function showRedirect($url)
    {
        if ($this->level != self::MUET && $this->show_redirect) {
            echo "
<html>
<head><title>".Translator::getInstance()->trans('Redirecting ...')."</title></head>
<body>
<a href=\"$url\">".Translator::getInstance()->trans('Redirecting to %url', array('%url' => $url))."</a>
</body>
</html>
";

            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * check if level is activated and control if current file is activated
     *
     * @param  int     $level
     * @return boolean
     */
    public function isActivated($level)
    {
        if ($this->level <= $level) {
            $origin = $this->findOrigin();

            $file = basename($origin['file']);

            if ($this->isActivedFile($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     *
     * check if $file is in authorized files
     *
     * @param  string  $file
     * @return boolean
     */
    public function isActivedFile($file)
    {
        return ($this->all_files || in_array($file, $this->files)) && ! in_array("!$file", $this->files);
    }

    /* -- Methodes privees ---------------------------------------- */

    private function findOrigin()
    {
        $origin = array();

        if (function_exists('debug_backtrace')) {
            $trace = debug_backtrace();
            $prevHop = null;
            // make a downsearch to identify the caller
            $hop = array_pop($trace);

            while ($hop !== null) {
                if (isset($hop['class'])) {
                    // we are sometimes in functions = no class available: avoid php warning here
                    $className = $hop['class'];

                    if (! empty($className) && ($className == ltrim(__CLASS__, '\\') || strtolower(get_parent_class($className)) == ltrim(__CLASS__, '\\'))) {
                        $origin['line'] = $hop['line'];
                        $origin['file'] = $hop['file'];
                        break;
                    }
                }
                $prevHop = $hop;
                $hop = array_pop($trace);
            }

            $origin['class'] = isset($prevHop['class']) ? $prevHop['class'] : 'main';

            if (isset($prevHop['function']) &&
                $prevHop['function'] !== 'include' &&
                $prevHop['function'] !== 'include_once' &&
                $prevHop['function'] !== 'require' &&
                $prevHop['function'] !== 'require_once') {
                $origin['function'] = $prevHop['function'];
            } else {
                $origin['function'] = 'main';
            }
        }

        return $origin;
    }

    protected function interpolate($message, array $context = array())
    {
        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $val;
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    private function out($level, $message, array $context = array())
    {
        $text = '';

        if ($message instanceof \Exception) {
            $text = $message->getMessage()."\n".$message->getTraceAsString();
        } elseif (is_scalar($message) === false) {
            $text = print_r($message, 1);
        } else {
            $text = $message;
        }

        $text = $this->interpolate($text, $context);

        $origin = $this->findOrigin();

        $file = basename($origin['file']);

        if ($this->isActivedFile($file)) {
            $function = $origin['function'];
            $line = $origin['line'];

            $prefix = str_replace(
                array("#INDEX", "#LEVEL", "#FILE", "#FUNCTION", "#LINE", "#DATE", "#HOUR"),
                array(1+$this->linecount, $level, $file, $function, $line, date("Y-m-d"), date("G:i:s")),
                $this->prefix
            );

            $trace = $prefix . $text;

            foreach ($this->destinations as $dest) {
                $dest->add($trace);
            }

            $this->linecount++;
        }
    }

    /**
     *
     * @param type  $destinations
     * @param array $actives      array containing classes instanceof AbstractTlogDestination
     */
    protected function loadDestinations(&$destinations, array $actives = null)
    {
        foreach ($actives as $active) {
            if (class_exists($active)) {
                $class = new $active();

                if (!$class instanceof AbstractTlogDestination) {
                    throw new \UnexpectedValueException($active." must extends Thelia\Tlog\AbstractTlogDestination");
                }

                $destinations[$active] = $class;
            }
        }
    }
}
