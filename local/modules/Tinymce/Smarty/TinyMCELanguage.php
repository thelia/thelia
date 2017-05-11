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

namespace Tinymce\Smarty;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\Lang;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

/**
 * Class TinyMCELanguage
 * @package Tinymce\Smarty
 */
class TinyMCELanguage extends AbstractSmartyPlugin
{
    /** @var  string $locale */
    private $locale;

    public function __construct(Request $request)
    {
        if ($request->getSession() != null) {
            $this->locale = $request->getSession()->getLang()->getLocale();
        } else {
            $this->locale = Lang::getDefaultLanguage()->getLocale();
        }
    }

    public function guessTinyMCELanguage($params, \Smarty_Internal_Template $template)
    {
        // Find TinyMCE available languages
        $finder = new Finder();

        $files = $finder->in(__DIR__.DS."..".DS."Resources".DS.'js'.DS.'tinymce'.DS.'langs')->sortByName();

        $miniLocale = substr($this->locale, 0, 2);

        // Find the best matching language
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $lang = str_replace('.js', '', $file->getFilename());

            if ($lang == $this->locale || $lang == $miniLocale) {
                return $lang;
            }
        }

        return '';
    }

    /**
     * Define the various smarty plugins hendled by this class
     *
     * @return array an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'tinymce_lang', $this, 'guessTinyMCELanguage'),
        );
    }
}
