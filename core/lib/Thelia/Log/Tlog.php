<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Log;

use Exception;
use UnexpectedValueException;
use Psr\Log\LoggerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;

/**
 * Thelia Logger.
 *
 * Allow to define different level and output.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * @deprecated use Psr\Log\LoggerInterface from Symfony instead
 */
class Tlog implements LoggerInterface
{
    // Nom des variables de configuration
    public const VAR_LEVEL = 'tlog_level';

    public const VAR_DESTINATIONS = 'tlog_destinations';

    public const VAR_PREFIXE = 'tlog_prefix';

    public const VAR_FILES = 'tlog_files';

    public const VAR_IP = 'tlog_ip';

    public const VAR_SHOW_REDIRECT = 'tlog_show_redirect';

    // all level of trace
    public const DEBUG = 100;

    public const INFO = 200;

    public const NOTICE = 300;

    public const WARNING = 400;

    public const ERROR = 500;

    public const CRITICAL = 600;

    public const ALERT = 700;

    public const EMERGENCY = 800;

    public const MUET = \PHP_INT_MAX;

    protected $levels = [
        100 => 'DEBUG',
        200 => 'INFO',
        300 => 'NOTICE',
        400 => 'WARNING',
        500 => 'ERROR',
        600 => 'CRITICAL',
        700 => 'ALERT',
        800 => 'EMERGENCY',
    ];

    // default values
    public const DEFAULT_LEVEL = self::ERROR;

    public const DEFAUT_DESTINATIONS = "Thelia\Log\Destination\TlogDestinationRotatingFile";

    public const DEFAUT_PREFIXE = '#INDEX: #LEVEL [#FILE:#FUNCTION()] {#LINE} #DATE #HOUR: ';

    public const DEFAUT_FILES = '*';

    public const DEFAUT_IP = '';

    public const DEFAUT_SHOW_REDIRECT = 0;

    /**
     * @var \Thelia\Log\Tlog
     */
    private static $instance = false;

    /**
     * @var array containing class of destination handler
     */
    protected $destinations = [];

    protected $mode_back_office = false;

    protected $level = self::ERROR;

    protected $prefix = '';

    protected $files = [];

    protected $all_files = false;

    protected $show_redirect = false;

    private int $linecount = 0;

    protected $done = false;

    // directories where are the Destinations Files
    public $dir_destinations = [];

    private function __construct()
    {
    }

    /**
     * @return \Thelia\Log\Tlog
     */
    public static function getInstance()
    {
        if (self::$instance == false) {
            self::$instance = new self();

            // On doit placer les initialisations à ce level pour pouvoir
            // utiliser la classe Tlog dans les classes de base (Cnx, BaseObj, etc.)
            // Les placer dans le constructeur provoquerait une boucle
            self::$instance->init();
        }

        return self::$instance;
    }

    /**
     * Create a new Tlog instance, that could be configured without interfering with the "main" instance.
     *
     * @return Tlog a new Tlog instance
     */
    public static function getNewInstance(): self
    {
        $instance = new self();

        $instance->init();

        return $instance;
    }

    /**
     * initialize default configuration.
     */
    protected function init(): void
    {
        $this->setLevel(ConfigQuery::read(self::VAR_LEVEL, self::DEFAULT_LEVEL));

        $this->dir_destinations = [
                __DIR__.DS.'Destination',
                THELIA_LOCAL_DIR.'tlog'.DS.'destinations',
        ];

        $this->setPrefix(ConfigQuery::read(self::VAR_PREFIXE, self::DEFAUT_PREFIXE));
        $this->setFiles(ConfigQuery::read(self::VAR_FILES, self::DEFAUT_FILES));
        $this->setIp(ConfigQuery::read(self::VAR_IP, self::DEFAUT_IP));
        $this->setDestinations(ConfigQuery::read(self::VAR_DESTINATIONS, self::DEFAUT_DESTINATIONS));
        $this->setShowRedirect(ConfigQuery::read(self::VAR_SHOW_REDIRECT, self::DEFAUT_SHOW_REDIRECT));

        // Au cas ou il y aurait un exit() quelque part dans le code.
        register_shutdown_function([$this, 'writeOnExit']);
    }

    // Configuration
    // -------------

    public function setDestinations(string $destinations): static
    {
        if ($destinations !== '' && $destinations !== '0') {
            $this->destinations = [];

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
     * change the debug level. Use Tlog constant : \Thelia\Log\Tlog::DEBUG set level to Debug.
     *
     * @param int $level
     */
    public function setLevel($level): static
    {
        $this->level = $level;

        return $this;
    }

    public function setPrefix($prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function setFiles($files): static
    {
        $this->files = explode(';', (string) $files);

        $this->all_files = \in_array('*', $this->files);

        return $this;
    }

    public function setIp($ips): static
    {
        // isset($_SERVER['REMOTE_ADDR']) if we are in cli mode
        if (!empty($ips) && isset($_SERVER['REMOTE_ADDR']) && !\in_array($_SERVER['REMOTE_ADDR'], explode(';', (string) $ips))) {
            $this->level = self::MUET;
        }

        return $this;
    }

    public function setShowRedirect($bool): static
    {
        $this->show_redirect = $bool;

        return $this;
    }

    // Configuration d'une destination
    public function setConfig($destination, $param, $valeur): static
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
     * @param string $message
     */
    public function debug($message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Alias of debug method. With this method you can put all parameter you want.
     *
     * ex : Tlog::getInstance()->addDebug($arg1, $arg2, $arg3);
     */
    public function addDebug(...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::DEBUG, $arg);
        }
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     */
    public function info($message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Alias of info method. With this method you can put all parameter you want.
     *
     * ex : Tlog::getInstance()->addInfo($arg1, $arg2, $arg3);
     */
    public function addInfo(...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::INFO, $arg);
        }
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     */
    public function notice($message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Alias of notice method. With this method you can put all parameter you want.
     *
     * ex : Tlog::getInstance()->addNotice($arg1, $arg2, $arg3);
     */
    public function addNotice(...$args): void
    {
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
     * @param string $message
     */
    public function warning($message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Alias of warning method. With this method you can put all parameter you want.
     *
     * ex : Tlog::getInstance()->addWarning($arg1, $arg2, $arg3);
     */
    public function addWarning(...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::WARNING, $arg);
        }
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     */
    public function error($message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Alias of error method. With this method you can put all parameter you want.
     *
     * ex : Tlog::getInstance()->addError($arg1, $arg2, $arg3);
     */
    public function addError(...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::ERROR, $arg);
        }
    }

    /**
     * @see error()
     */
    public function err($message, array $context = []): void
    {
        $this->error($message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     */
    public function critical($message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Alias of critical method. With this method you can put all parameter you want.
     *
     * ex : Tlog::getInstance()->addCritical($arg1, $arg2, $arg3);
     */
    public function addCritical(...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::CRITICAL, $arg);
        }
    }

    /**
     * @see critical()
     */
    public function crit($message, array $context = []): void
    {
        $this->critical($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     */
    public function alert($message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * Alias of alert method. With this method you can put all parameter you want.
     *
     * ex : Tlog::getInstance()->addAlert($arg1, $arg2, $arg3);
     */
    public function addAlert(...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::ALERT, $arg);
        }
    }

    /**
     * System is unusable.
     *
     * @param string $message
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Alias of emergency method. With this method you can put all parameter you want.
     *
     * ex : Tlog::getInstance()->addEmergency($arg1, $arg2, $arg3);
     */
    public function addEmergency(...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::EMERGENCY, $arg);
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string $message
     */
    public function log($level, $message, array $context = []): void
    {
        if ($this->level > $level || \array_key_exists($level, $this->levels) === false) {
            return;
        }

        $this->out($this->levels[$level], $message, $context);
    }

    /**
     * final end method. Write log for each destination handler.
     *
     * @param string $res
     */
    public function write(&$res): void
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
    public function writeOnExit(): void
    {
        // Si les infos de debug n'ont pas été ecrites, le faire maintenant
        if ($this->done === false) {
            $res = '';

            $this->write($res);

            echo $res;
        }
    }

    public function showRedirect($url): bool
    {
        if ($this->level != self::MUET && $this->show_redirect) {
            echo '
<html>
<head><title>'.Translator::getInstance()->trans('Redirecting ...')."</title></head>
<body>
<a href=\"{$url}\">".Translator::getInstance()->trans('Redirecting to %url', ['%url' => $url]).'</a>
</body>
</html>
';

            return true;
        }

        return false;
    }

    /**
     * check if level is activated and control if current file is activated.
     *
     * @param int $level
     */
    public function isActivated($level): bool
    {
        if ($this->level <= $level) {
            $origin = $this->findOrigin();

            $file = basename((string) $origin['file']);

            if ($this->isActivedFile($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * check if $file is in authorized files.
     *
     */
    public function isActivedFile(string $file): bool
    {
        return ($this->all_files || \in_array($file, $this->files)) && !\in_array('!' . $file, $this->files);
    }

    /* -- Methodes privees ---------------------------------------- */
    /**
     * @return mixed[]
     */
    private function findOrigin(): array
    {
        $origin = [];

        if (\function_exists('debug_backtrace')) {
            $trace = debug_backtrace();
            $prevHop = null;
            // make a downsearch to identify the caller
            $hop = array_pop($trace);

            while ($hop !== null) {
                if (isset($hop['class'])) {
                    // we are sometimes in functions = no class available: avoid php warning here
                    $className = $hop['class'];

                    if (!empty($className) && ($className == ltrim(self::class, '\\') || strtolower(get_parent_class($className)) === ltrim(self::class, '\\'))) {
                        $origin['line'] = $hop['line'];
                        $origin['file'] = $hop['file'];
                        break;
                    }
                }

                $prevHop = $hop;
                $hop = array_pop($trace);
            }

            $origin['class'] = $prevHop['class'] ?? 'main';

            if (isset($prevHop['function'])
                && $prevHop['function'] !== 'include'
                && $prevHop['function'] !== 'include_once'
                && $prevHop['function'] !== 'require'
                && $prevHop['function'] !== 'require_once') {
                $origin['function'] = $prevHop['function'];
            } else {
                $origin['function'] = 'main';
            }
        }

        return $origin;
    }

    protected function interpolate($message, array $context = []): string
    {
        // build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{'.$key.'}'] = $val;
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    private function out($level, $message, array $context = []): void
    {
        $text = '';

        if ($message instanceof Exception) {
            $text = $message->getMessage()."\n".$message->getTraceAsString();
        } elseif (\is_scalar($message) === false) {
            $text = print_r($message, 1);
        } else {
            $text = $message;
        }

        $text = $this->interpolate($text, $context);

        $origin = $this->findOrigin();

        $file = basename((string) $origin['file']);

        if ($this->isActivedFile($file)) {
            $function = $origin['function'];
            $line = $origin['line'];

            $prefix = str_replace(
                ['#INDEX', '#LEVEL', '#FILE', '#FUNCTION', '#LINE', '#DATE', '#HOUR'],
                [1 + $this->linecount, $level, $file, $function, $line, date('Y-m-d'), date('G:i:s')],
                $this->prefix
            );

            $trace = $prefix.$text;

            foreach ($this->destinations as $dest) {
                $dest->add($trace);
            }

            ++$this->linecount;
        }
    }

    /**
     * @param type  $destinations
     * @param array $actives      array containing classes instanceof AbstractTlogDestination
     */
    protected function loadDestinations(array &$destinations, array $actives = null): void
    {
        foreach ($actives as $active) {
            if (class_exists($active)) {
                $class = new $active();

                if (!$class instanceof AbstractTlogDestination) {
                    throw new UnexpectedValueException($active." must extends Thelia\Tlog\AbstractTlogDestination");
                }

                $destinations[$active] = $class;
            }
        }
    }
}
