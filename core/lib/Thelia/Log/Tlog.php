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

use Psr\Log\LoggerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ConfigQuery;

/**
 * @deprecated use Psr\Log\LoggerInterface instead
 */
class Tlog implements LoggerInterface
{
    public const VAR_LEVEL = 'tlog_level';

    public const VAR_DESTINATIONS = 'tlog_destinations';

    public const VAR_PREFIXE = 'tlog_prefix';

    public const VAR_FILES = 'tlog_files';

    public const VAR_IP = 'tlog_ip';

    public const VAR_SHOW_REDIRECT = 'tlog_show_redirect';

    public const DEBUG = 100;

    public const INFO = 200;

    public const NOTICE = 300;

    public const WARNING = 400;

    public const ERROR = 500;

    public const CRITICAL = 600;

    public const ALERT = 700;

    public const EMERGENCY = 800;

    public const MUET = \PHP_INT_MAX;

    protected array $levels = [
        100 => 'DEBUG',
        200 => 'INFO',
        300 => 'NOTICE',
        400 => 'WARNING',
        500 => 'ERROR',
        600 => 'CRITICAL',
        700 => 'ALERT',
        800 => 'EMERGENCY',
    ];

    public const DEFAULT_LEVEL = self::ERROR;

    public const DEFAUT_DESTINATIONS = "Thelia\Log\Destination\TlogDestinationRotatingFile";

    public const DEFAUT_PREFIXE = '#INDEX: #LEVEL [#FILE:#FUNCTION()] {#LINE} #DATE #HOUR: ';

    public const DEFAUT_FILES = '*';

    public const DEFAUT_IP = '';

    public const DEFAUT_SHOW_REDIRECT = false;

    private static ?self $instance = null;

    protected array $destinations = [];

    protected bool $mode_back_office = false;

    protected int $level = self::ERROR;

    protected string $prefix = '';

    protected array $files = [];

    protected bool $all_files = false;

    protected bool $show_redirect = false;

    private int $linecount = 0;

    protected bool $done = false;

    public array $dir_destinations = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
            self::$instance->init();
        }

        return self::$instance;
    }

    public static function getNewInstance(): self
    {
        $instance = new self();
        $instance->init();

        return $instance;
    }

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
        $this->setShowRedirect((bool) ConfigQuery::read(self::VAR_SHOW_REDIRECT, self::DEFAUT_SHOW_REDIRECT));

        register_shutdown_function([$this, 'writeOnExit']);
    }

    public function setDestinations(string $destinations): static
    {
        if ($destinations !== '' && $destinations !== '0') {
            $this->destinations = [];
            $classes_destinations = explode(';', $destinations);
            $this->loadDestinations($this->destinations, $classes_destinations);
        }

        return $this;
    }

    public function getDestinationsDirectories(): array
    {
        return $this->dir_destinations;
    }

    public function setLevel(mixed $level): static
    {
        $this->level = (int) $level;

        return $this;
    }

    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function setFiles(string $files): static
    {
        $this->files = explode(';', $files);
        $this->all_files = \in_array('*', $this->files);

        return $this;
    }

    public function setIp(string $ips): static
    {
        if ($ips !== '' && $ips !== '0' && isset($_SERVER['REMOTE_ADDR']) && !\in_array($_SERVER['REMOTE_ADDR'], explode(';', $ips))) {
            $this->level = self::MUET;
        }

        return $this;
    }

    public function setShowRedirect(bool $bool): static
    {
        $this->show_redirect = $bool;

        return $this;
    }

    public function setConfig(string $destination, string $param, mixed $valeur): static
    {
        if (isset($this->destinations[$destination])) {
            $this->destinations[$destination]->setConfig($param, $valeur);
        }

        return $this;
    }

    public function getConfig(string $destination, string $param): mixed
    {
        if (isset($this->destinations[$destination])) {
            return $this->destinations[$destination]->getConfig($param);
        }

        return false;
    }

    public function debug($message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    public function addDebug(mixed ...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::DEBUG, $arg);
        }
    }

    public function info($message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function addInfo(mixed ...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::INFO, $arg);
        }
    }

    public function notice($message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function addNotice(mixed ...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::NOTICE, $arg);
        }
    }

    public function warning($message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function addWarning(mixed ...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::WARNING, $arg);
        }
    }

    public function error($message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function addError(mixed ...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::ERROR, $arg);
        }
    }

    public function err($message, array $context = []): void
    {
        $this->error($message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function addCritical(mixed ...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::CRITICAL, $arg);
        }
    }

    public function crit($message, array $context = []): void
    {
        $this->critical($message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function addAlert(mixed ...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::ALERT, $arg);
        }
    }

    public function emergency($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function addEmergency(mixed ...$args): void
    {
        foreach ($args as $arg) {
            $this->log(self::EMERGENCY, $arg);
        }
    }

    public function log(mixed $level, $message, array $context = []): void
    {
        if ($this->level > $level || !\array_key_exists($level, $this->levels)) {
            return;
        }

        $this->out($this->levels[$level], (string) $message, $context);
    }

    public function write(string &$res): void
    {
        $this->done = true;

        if ($this->level === self::MUET) {
            return;
        }

        foreach ($this->destinations as $dest) {
            $dest->write($res);
        }
    }

    public function writeOnExit(): void
    {
        if ($this->done === false) {
            $res = '';
            $this->write($res);
            echo $res;
        }
    }

    public function showRedirect(string $url): bool
    {
        if ($this->level !== self::MUET && $this->show_redirect) {
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

    public function isActivated(int $level): bool
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

    public function isActivedFile(string $file): bool
    {
        return ($this->all_files || \in_array($file, $this->files, true)) && !\in_array('!'.$file, $this->files, true);
    }

    private function findOrigin(): array
    {
        $origin = [];

        if (\function_exists('debug_backtrace')) {
            $trace = debug_backtrace();
            $prevHop = null;
            $hop = array_pop($trace);

            while ($hop !== null) {
                if (isset($hop['class'])) {
                    $className = $hop['class'];
                    $parentClassName = get_parent_class($className) === false ? '' : get_parent_class($className);
                    if (
                        !empty($className)
                        && ($className === ltrim(self::class, '\\')
                        || strtolower($parentClassName) === ltrim(self::class, '\\'))
                    ) {
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

    protected function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{'.$key.'}'] = $val;
        }

        return strtr($message, $replace);
    }

    private function out(string $level, mixed $message, array $context = []): void
    {
        if ($message instanceof \Exception) {
            $text = $message->getMessage()."\n".$message->getTraceAsString();
        } elseif (!\is_scalar($message)) {
            $text = print_r($message, true);
        } else {
            $text = (string) $message;
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
     * @throws \UnexpectedValueException
     */
    protected function loadDestinations(array &$destinations, ?array $actives = null): void
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
