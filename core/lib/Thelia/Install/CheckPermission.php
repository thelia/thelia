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

namespace Thelia\Install;

use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Translation\Translator;

/**
 * Class CheckPermission
 *
 * Take care of integration tests (files permissions)
 *
 * @package Thelia\Install
 * @author  Manuel Raynaud <manu@raynaud.io>
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CheckPermission extends BaseInstall
{
    const DIR_CONF =            'local/config';
    const DIR_LOG  =            'log';
    const DIR_CACHE =           'cache';
    const DIR_WEB =             'web';
    const DIR_SESSION =         'local/session';
    const DIR_MEDIA =           'local/media';

    /** @var array Directory needed to be writable */
    protected $directoriesToBeWritable = array(
        self::DIR_CONF,
        self::DIR_LOG,
        self::DIR_CACHE,
        self::DIR_WEB,
        self::DIR_SESSION,
        self::DIR_MEDIA
    );

    /** @var array Minimum server configuration necessary */
    protected $minServerConfigurationNecessary = array(
        'memory_limit' => 134217728,
        'post_max_size' => 20971520,
        'upload_max_filesize' => 2097152
    );

    protected $extensions = array(
        'curl',
        'fileinfo',
        'gd',
        'intl',
        'openssl',
        'pdo_mysql',
        'dom',
        'calendar'
    );

    protected $validationMessages = array();

    /** @var bool If permissions are OK */
    protected $isValid = true;

    /** @var TranslatorInterface Translator Service */
    protected $translator = null;

    /**
     * Constructor
     *
     * @param bool       $verifyInstall If verify install
     * @param Translator $translator    Translator Service
     *                                  necessary for install wizard
     */
    public function __construct($verifyInstall = true, Translator $translator = null)
    {
        $this->translator = $translator;

        $this->validationMessages['php_version'] =  array(
            'text' => $this->getI18nPhpVersionText('5.4', phpversion(), true),
            'hint' =>  $this->getI18nPhpVersionHint(),
            'status' => true
        );

        foreach ($this->directoriesToBeWritable as $directory) {
            $this->validationMessages[$directory] =  array(
                'text' => '',
                'hint' =>  '',
                'status' => true
            );
        }
        foreach ($this->minServerConfigurationNecessary as $key => $value) {
            $this->validationMessages[$key] =  array(
                'text' => '',
                'hint' =>  $this->getI18nConfigHint(),
                'status' => true
            );
        }

        foreach ($this->extensions as $extension) {
            $this->validationMessages[$extension] = array(
                'text' => '',
                'hint' => $this->getI18nExtensionHint(),
                'status' => true,
            );
        }

        parent::__construct($verifyInstall);
    }

    /**
     * Perform file permission check
     *
     * @return bool
     */
    public function exec()
    {
        if (version_compare(phpversion(), '5.4', '<')) {
            $this->validationMessages['php_version']['text'] = $this->getI18nPhpVersionText('5.4', phpversion(), false);
            $this->validationMessages['php_version']['status'] = false;
            $this->validationMessages['php_version']['hint'] = $this->getI18nPhpVersionHint();
        }

        foreach ($this->directoriesToBeWritable as $directory) {
            $fullDirectory = THELIA_ROOT . $directory;
            $this->validationMessages[$directory]['text'] = $this->getI18nDirectoryText($fullDirectory, true);
            if (is_writable($fullDirectory) === false) {
                if (!$this->makeDirectoryWritable($fullDirectory)) {
                    $this->isValid = false;
                    $this->validationMessages[$directory]['status'] = false;
                    $this->validationMessages[$directory]['text'] = $this->getI18nDirectoryText($fullDirectory, false);
                }
            }
        }

        foreach ($this->minServerConfigurationNecessary as $key => $value) {
            $this->validationMessages[$key]['text'] = $this->getI18nConfigText($key, $this->formatBytes($value), ini_get($key), true);
            if (!$this->verifyServerMemoryValues($key, $value)) {
                $this->isValid = false;
                $this->validationMessages[$key]['status'] = false;
                $this->validationMessages[$key]['text'] = $this->getI18nConfigText($key, $this->formatBytes($value), ini_get($key), false);
                ;
            }
        }

        foreach ($this->extensions as $extension) {
            $this->validationMessages[$extension]['text'] = $this->getI18nExtensionText($extension, true);
            if (false === extension_loaded($extension)) {
                $this->isValid = false;
                $this->validationMessages[$extension]['status'] = false;
                $this->validationMessages[$extension]['text'] = $this->getI18nExtensionText($extension, false);
            }
        }

        return $this->isValid;
    }

    /**
     * Get validation messages
     *
     * @return array
     */
    public function getValidationMessages()
    {
        return $this->validationMessages;
    }

    /**
     * Make a directory writable (recursively)
     *
     * @param string $directory path to directory
     *
     * @return bool
     */
    protected function makeDirectoryWritable($directory)
    {
        return (is_writable(THELIA_ROOT . $directory) === true);
    }

    /**
     * Get Translated text about the directory state
     *
     * @param string $directory Directory being checked
     * @param bool   $isValid   If directory permission is valid
     *
     * @return string
     */
    protected function getI18nDirectoryText($directory, $isValid)
    {
        if ($this->translator !== null) {
            if ($isValid) {
                $sentence = 'The directory %directory% is writable';
            } else {
                $sentence = 'The directory %directory% is not writable';
            }

            $translatedText = $this->translator->trans(
                $sentence,
                array(
                    '%directory%' => $directory
                )
            );
        } else {
            $translatedText = sprintf('The directory %s should be writable', $directory);
        }

        return $translatedText;
    }

    protected function getI18nExtensionText($extension, $isValid)
    {
        if ($isValid) {
            $sentence = '%extension% php extension is loaded.';
        } else {
            $sentence = '%extension% php extension is not loaded.';
        }

        return $this->translator->trans($sentence, array(
            '%extension%' => $extension
        ));
    }

    /**
     * Get Translated text about the directory state
     * Not usable with CLI
     *
     * @param string $key           .ini file key
     * @param string $expectedValue Expected server value
     * @param string $currentValue  Actual server value
     * @param bool   $isValid       If server configuration is valid
     *
     * @return string
     */
    protected function getI18nConfigText($key, $expectedValue, $currentValue, $isValid)
    {
        if ($isValid) {
            $sentence = 'The PHP "%key%" configuration value (currently %currentValue%) is correct (%expectedValue% required).';
        } else {
            $sentence = 'The PHP "%key%" configuration value (currently %currentValue%) is below minimal requirements to run Thelia2 (%expectedValue% required).';
        }

        $translatedText = $this->translator->trans(
            $sentence,
            array(
                '%key%' => $key,
                '%expectedValue%' => $expectedValue,
                '%currentValue%' => $currentValue,
            ),
            'install-wizard'
        );

        return $translatedText;
    }

    protected function getI18nExtensionHint()
    {
        return $this->translator->trans('This PHP extension should be installed and loaded.');
    }

    /**
     * Get Translated hint about the config requirement issue
     *
     * @return string
     */
    protected function getI18nConfigHint()
    {
        $sentence = 'Change this value in the php.ini configuration file.';
        $translatedText = $this->translator->trans(
            $sentence
        );

        return $translatedText;
    }

    /**
     * Get Translated hint about the PHP version requirement issue
     *
     * @param string $expectedValue
     * @param string $currentValue
     * @param bool   $isValid
     *
     * @return string
     */
    protected function getI18nPhpVersionText($expectedValue, $currentValue, $isValid)
    {
        if ($this->translator !== null) {
            if ($isValid) {
                $sentence = 'PHP version %currentValue% matches the minimum required (PHP %expectedValue%).';
            } else {
                $sentence = 'The installer detected PHP version %currentValue%, but Thelia 2 requires PHP %expectedValue% or newer.';
            }

            $translatedText = $this->translator->trans(
                $sentence,
                array(
                    '%expectedValue%' => $expectedValue,
                    '%currentValue%' => $currentValue,
                )
            );
        } else {
            $translatedText = sprintf('Thelia requires PHP %s or newer (%s currently).', $expectedValue, $currentValue);
        }

        return $translatedText;
    }

    /**
     * Get Translated hint about the config requirement issue
     *
     * @return string
     */
    protected function getI18nPhpVersionHint()
    {
        $sentence = 'You should upgrade the installed PHP version to continue Thelia 2 installation.';
        $translatedText = $this->translator->trans(
            $sentence,
            array()
        );

        return $translatedText;
    }

    /**
     * Check if a server memory value is met or not
     *
     * @param string $key                   .ini file key
     * @param int    $necessaryValueInBytes Expected value in bytes
     *
     * @return bool
     */
    protected function verifyServerMemoryValues($key, $necessaryValueInBytes)
    {
        $serverValueInBytes = $this->returnBytes(ini_get($key));

        if ($serverValueInBytes == -1) {
            return true;
        }

        return ($serverValueInBytes >= $necessaryValueInBytes);
    }

    /**
     * Return bytes from memory .ini value
     *
     * @param string $val .ini value
     *
     * @return int
     */
    protected function returnBytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        // Do not add breaks in the switch below
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val = (int)$val*1024;
                // no break
            case 'm':
                $val = (int)$val*1024;
                // no break
            case 'k':
                $val = (int)$val*1024;
        }

        return $val;
    }

    /**
     * Convert bytes to readable string
     *
     * @param int $bytes     bytes
     * @param int $precision conversion precision
     *
     * @return string
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $base = log($bytes) / log(1024);
        $suffixes = array('', 'k', 'M', 'G', 'T');

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }
}
