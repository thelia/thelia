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

namespace Thelia\Install;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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

    /** @var array Directory needed to be writable */
    protected $directoriesToBeWritable = array(
        self::DIR_CONF,
        self::DIR_LOG,
        self::DIR_CACHE,
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

        foreach ($this->directoriesToBeWritable as $directory) {
            $this->validationMessages[$directory] =  array(
                'text' => '',
                'hint' =>  '',
                'status' => true
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
        foreach ($this->directoriesToBeWritable as $directory) {
            $fullDirectory = THELIA_ROOT . $directory;
            $this->validationMessages[$directory]['text'] = $this->getI18nText($fullDirectory, true);
            if (is_writable($fullDirectory) === false) {
                if (!$this->makeDirectoryWritable($fullDirectory)) {
                    $this->isValid = false;
                    $this->validationMessages[$directory]['status'] = false;
                    $this->validationMessages[$directory]['text'] = $this->getI18nText($fullDirectory, false);
                    $this->validationMessages[$directory]['hint'] = $this->getI18nHint($fullDirectory);
                }
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
        chmod($directory, 0777);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );
        foreach ($iterator as $item) {
            chmod($item, 0777);
        }

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
    protected function getI18nText($directory, $isValid)
    {
        if ($this->translator !== null) {
            if ($isValid) {
                $sentence = 'Your directory <strong>%directory%</strong> is writable';
            } else {
                $sentence = 'Your directory <strong>%directory%</strong> is not writable';
            }

            $translatedText = $this->translator->trans(
                $sentence,
                array(
                    '%directory%' => $directory
                ),
                'install-wizard'
            );
        } else {
            $translatedText = sprintf('Your directory %s needs to be writable', $directory);
        }

        return $translatedText;
    }

    /**
     * Get Translated hint about the directory state
     *
     * @param string $directory Directory being checked
     *
     * @return string
     */
    protected function getI18nHint($directory)
    {
        if ($this->translator !== null) {
            $sentence = '<span class="label label-primary">chmod 777 %directory%</span> on your server with admin rights could help';
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
}
