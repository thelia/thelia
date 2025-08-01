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

namespace Thelia\Install;

use Symfony\Component\Translation\Translator;

/**
 * Class CheckPermission.
 *
 * Take care of integration tests (files permissions)
 *
 * @author  Manuel Raynaud <manu@raynaud.io>
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CheckPermission extends BaseInstall
{
    public const DIR_CONF = 'vendor/thelia/config';
    public const DIR_VAR = 'var';
    public const DIR_WEB = 'public';
    public const DIR_MEDIA = 'local/media';

    /** @var array Directory needed to be writable */
    protected array $directoriesToBeWritable = [
        self::DIR_CONF,
        self::DIR_VAR,
        self::DIR_WEB,
        self::DIR_MEDIA,
    ];

    /** @var array Minimum server configuration necessary */
    protected array $minServerConfigurationNecessary = [
        'memory_limit' => 134217728,
        'post_max_size' => 20971520,
        'upload_max_filesize' => 2097152,
    ];

    protected $phpExpectedVerions = [
        'min' => '7.2',
        'max' => '8.0',
    ];
    protected $extensions = [
        'curl',
        'fileinfo',
        'gd',
        'intl',
        'openssl',
        'pdo_mysql',
        'dom',
        'zip',
    ];
    protected $validationMessages = [];

    /** @var bool If permissions are OK */
    protected bool $isValid = true;

    /**
     * Constructor.
     *
     * @param bool            $verifyInstall If verify install
     * @param Translator|null $translator    Translator Service necessary for install wizard
     */
    public function __construct(bool $verifyInstall = true, protected ?Translator $translator = null)
    {
        $this->validationMessages['php_version'] = [
            'text' => $this->getI18nPhpVersionText(\PHP_VERSION, true),
            'hint' => $this->getI18nPhpVersionHint(),
            'status' => true,
        ];

        foreach ($this->directoriesToBeWritable as $directory) {
            $this->validationMessages[$directory] = [
                'text' => '',
                'hint' => '',
                'status' => true,
            ];
        }

        foreach (array_keys($this->minServerConfigurationNecessary) as $key) {
            $this->validationMessages[$key] = [
                'text' => '',
                'hint' => $this->getI18nConfigHint(),
                'status' => true,
            ];
        }

        foreach ($this->extensions as $extension) {
            $this->validationMessages[$extension] = [
                'text' => '',
                'hint' => $this->getI18nExtensionHint(),
                'status' => true,
            ];
        }

        parent::__construct($verifyInstall);
    }

    /**
     * Perform file permission check.
     */
    public function exec(): bool
    {
        $currentVersion = substr(\PHP_VERSION, 0, strrpos(\PHP_VERSION, '.'));

        if (!version_compare($currentVersion, $this->phpExpectedVerions['min'], '>=') && version_compare($currentVersion, $this->phpExpectedVerions['max'], '<=')) {
            $this->isValid = false;
            $this->validationMessages['php_version']['text'] = $this->getI18nPhpVersionText(\PHP_VERSION, false);
            $this->validationMessages['php_version']['status'] = false;
            $this->validationMessages['php_version']['hint'] = $this->getI18nPhpVersionHint();
        }

        foreach ($this->directoriesToBeWritable as $directory) {
            $fullDirectory = THELIA_ROOT.$directory;
            $this->validationMessages[$directory]['text'] = $this->getI18nDirectoryText($fullDirectory, true);

            if (false === is_writable($fullDirectory) && !$this->makeDirectoryWritable($fullDirectory)) {
                $this->isValid = false;
                $this->validationMessages[$directory]['status'] = false;
                $this->validationMessages[$directory]['text'] = $this->getI18nDirectoryText($fullDirectory, false);
            }
        }

        foreach ($this->minServerConfigurationNecessary as $key => $value) {
            $this->validationMessages[$key]['text'] = $this->getI18nConfigText($key, $this->formatBytes($value), \ini_get($key), true);

            if (!$this->verifyServerMemoryValues($key, $value)) {
                $this->isValid = false;
                $this->validationMessages[$key]['status'] = false;
                $this->validationMessages[$key]['text'] = $this->getI18nConfigText($key, $this->formatBytes($value), \ini_get($key), false);
            }
        }

        foreach ($this->extensions as $extension) {
            $this->validationMessages[$extension]['text'] = $this->getI18nExtensionText($extension, true);

            if (false === \extension_loaded($extension)) {
                $this->isValid = false;
                $this->validationMessages[$extension]['status'] = false;
                $this->validationMessages[$extension]['text'] = $this->getI18nExtensionText($extension, false);
            }
        }

        return $this->isValid;
    }

    /**
     * Get validation messages.
     */
    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }

    /**
     * Make a directory writable (recursively).
     *
     * @param string $directory path to directory
     */
    protected function makeDirectoryWritable(string $directory): bool
    {
        return is_writable(THELIA_ROOT.$directory);
    }

    /**
     * Get Translated text about the directory state.
     *
     * @param string $directory Directory being checked
     * @param bool   $isValid   If directory permission is valid
     */
    protected function getI18nDirectoryText(string $directory, bool $isValid): string
    {
        $sentence = $isValid ? 'The directory %directory% is writable' : 'The directory %directory% is not writable';

        return $this->formatString($sentence, ['%directory%' => $directory]);
    }

    protected function getI18nExtensionText($extension, $isValid): string
    {
        $sentence = $isValid ? '%extension% php extension is loaded.' : '%extension% php extension is not loaded.';

        return $this->formatString($sentence, ['%extension%' => $extension]);
    }

    /**
     * Get Translated text about the directory state
     * Not usable with CLI.
     *
     * @param string $key           .ini file key
     * @param string $expectedValue Expected server value
     * @param string $currentValue  Actual server value
     * @param bool   $isValid       If server configuration is valid
     */
    protected function getI18nConfigText(string $key, string $expectedValue, string $currentValue, bool $isValid): string
    {
        $sentence = $isValid ? 'The PHP "%key%" configuration value (currently %currentValue%) is correct (%expectedValue% required).'
            : 'The PHP "%key%" configuration value (currently %currentValue%) is below minimal requirements to run Thelia2 (%expectedValue% required).';

        return $this->formatString(
            $sentence,
            [
                '%key%' => $key,
                '%expectedValue%' => $expectedValue,
                '%currentValue%' => $currentValue,
            ],
        );
    }

    protected function getI18nExtensionHint(): string
    {
        return $this->formatString('This PHP extension should be installed and loaded.');
    }

    /**
     * Get Translated hint about the config requirement issue.
     */
    protected function getI18nConfigHint(): string
    {
        return $this->formatString('Change this value in the php.ini configuration file.', []);
    }

    /**
     * Get Translated hint about the PHP version requirement issue.
     */
    protected function getI18nPhpVersionText(string $currentValue, bool $isValid): string
    {
        $sentence = $isValid ? 'PHP version %currentValue% matches the version required (>= %minExpectedValue% <= %maxExpectedValue%).'
            : 'The installer detected PHP version %currentValue%, but Thelia 2 requires PHP between %minExpectedValue% and %maxExpectedValue%.';

        return $this->formatString(
            $sentence,
            [
                '%minExpectedValue%' => $this->phpExpectedVerions['min'],
                '%maxExpectedValue%' => $this->phpExpectedVerions['max'],
                '%currentValue%' => $currentValue,
            ],
        );
    }

    /**
     * Get Translated hint about the config requirement issue.
     */
    protected function getI18nPhpVersionHint(): string
    {
        return $this->formatString('You should change the installed PHP version to continue Thelia 2 installation.', []);
    }

    /**
     * Check if a server memory value is met or not.
     *
     * @param string $key                   .ini file key
     * @param int    $necessaryValueInBytes Expected value in bytes
     */
    protected function verifyServerMemoryValues(string $key, int $necessaryValueInBytes): bool
    {
        $serverValueInBytes = $this->returnBytes(\ini_get($key));
        if (-1 === $serverValueInBytes) {
            return true;
        }

        return $serverValueInBytes >= $necessaryValueInBytes;
    }

    /**
     * Return bytes from memory .ini value.
     *
     * @param string $val .ini value
     */
    protected function returnBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[\strlen($val) - 1]);

        // Do not add breaks in the switch below
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val = (int) $val * 1024;
                // no break
            case 'm':
                $val = (int) $val * 1024;
                // no break
            case 'k':
                $val = (int) $val * 1024;
        }

        return (int) $val;
    }

    /**
     * Convert bytes to readable string.
     *
     * @param int $bytes     bytes
     * @param int $precision conversion precision
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $base = log($bytes) / log(1024);
        $suffixes = ['', 'k', 'M', 'G', 'T'];

        return round(1024 ** ($base - floor($base)), $precision).$suffixes[floor($base)];
    }

    protected function formatString(string $string, array $parameters = []): string
    {
        if ($this->translator instanceof Translator) {
            return $this->translator->trans(
                $string,
                $parameters,
                'install-wizard',
            );
        }

        return strtr($string, $parameters);
    }
}
