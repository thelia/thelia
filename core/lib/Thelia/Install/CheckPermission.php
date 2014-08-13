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
use Thelia\Core\Translation\Translator;

/**
 * Class CheckPermission
 *
 * Take care of integration tests (files permissions)
 *
 * @package Thelia\Install
 * @author  Manuel Raynaud <mraynaud@openstudio.fr>
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CheckPermission extends BaseInstall
{

    const DIR_CONF =            'local/config';
    const DIR_LOG  =            'log';
    const DIR_CACHE =           'cache';
    const DIR_WEB =             'web';
    const DIR_SESSION =         'local/session';

    /** @var array Directory needed to be writable */
    protected $directoriesToBeWritable = array(
        self::DIR_CONF,
        self::DIR_LOG,
        self::DIR_CACHE,
        self::DIR_WEB,
        self::DIR_SESSION,
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
        'mcrypt',
        'pdo_mysql',
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
            $this->validationMessages['php_version'] = $this->getI18nPhpVersionText('5.4', phpversion(), false);
        }

        foreach ($this->directoriesToBeWritable as $directory) {
            $fullDirectory = THELIA_ROOT . $directory;
            $this->validationMessages[$directory]['text'] = $this->getI18nDirectoryText($fullDirectory, true);
            if (is_writable($fullDirectory) === false) {
                if (!$this->makeDirectoryWritable($fullDirectory)) {
                    $this->isValid = false;
                    $this->validationMessages[$directory]['status'] = false;
                    $this->validationMessages[$directory]['text'] = $this->getI18nDirectoryText($fullDirectory, false);
                    $this->validationMessages[$directory]['hint'] = $this->getI18nDirectoryHint($fullDirectory);
                }
            }
        }

        foreach ($this->minServerConfigurationNecessary as $key => $value) {
            $this->validationMessages[$key]['text'] = $this->getI18nConfigText($key, $this->formatBytes($value), ini_get($key), true);
            if (!$this->verifyServerMemoryValues($key, $value)) {
                $this->isValid = false;
                $this->validationMessages[$key]['status'] = false;
                $this->validationMessages[$key]['text'] = $this->getI18nConfigText($key, $this->formatBytes($value), ini_get($key), false);;
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
                $sentence = 'Your directory %directory% is writable';
            } else {
                $sentence = 'Your directory %directory% is not writable';
            }

            $translatedText = $this->translator->trans(
                $sentence,
                array(
                    '%directory%' => $directory
                )
            );
        } else {
            $translatedText = sprintf('Your directory %s needs to be writable', $directory);
        }

        return $translatedText;
    }

    protected function getI18nExtensionText($extension, $isValid)
    {
        if ($isValid) {
            $sentence = '%extension% php extension is loaded';
        } else {
            $sentence = '%extension% php extension is not loaded';
        }

        return $this->translator->trans($sentence, array(
            '%extension%' => $extension
        ));
    }

    /**
     * Get Translated hint about the directory state
     *
     * @param string $directory Directory being checked
     *
     * @return string
     */
    protected function getI18nDirectoryHint($directory)
    {
        if ($this->translator !== null) {
            $sentence = 'chmod 777 %directory% on your server with admin rights could help';
            $translatedText = $this->translator->trans(
                $sentence,
                array(
                    '%directory%' => $directory
                ),
                'install-wizard'
            );
        } else {
            $translatedText = sprintf('chmod 777 %s on your server with admin rights could help', $directory);
        }

        return $translatedText;
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
            $sentence = 'Your %key% server configuration (currently %currentValue%) is well enough to run Thelia2 (%expectedValue% needed)';
        } else {
            $sentence = 'Your %key% server configuration (currently %currentValue%) is not sufficient enough in order to run Thelia2 (%expectedValue% needed)';
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
        return $this->translator->trans('This extension must be installed and loaded');
    }

    /**
     * Get Translated hint about the config requirement issue
     *
     * @return string
     */
    protected function getI18nConfigHint()
    {
        $sentence = 'Modifying this value on your server php.ini file with admin rights could help';
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
                $sentence = 'Your PHP version %currentValue% is well enough to run Thelia2 (%expectedValue% needed)';
            } else {
                $sentence = 'Your PHP version %currentValue% is not sufficient enough to run Thelia2 (%expectedValue% needed)';
            }

            $translatedText = $this->translator->trans(
                $sentence,
                array(
                    '%expectedValue%' => $expectedValue,
                    '%currentValue%' => $currentValue,
                )
            );
        } else {
            $translatedText = sprintf('Thelia needs at least PHP %s (%s currently)', $expectedValue, $currentValue);
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
        $sentence = 'Upgrading your version of PHP with admin rights could help';
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
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
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
