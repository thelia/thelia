<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tinymce\Smarty;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\Lang;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

/**
 * Class TinyMCELanguage.
 */
class TinyMCELanguage extends AbstractSmartyPlugin
{
    /** @var string */
    private $locale;

    public function __construct(RequestStack $requestStack)
    {
        $request = $requestStack->getCurrentRequest();
        if (!$request->hasSession() || !$request->getSession()->isStarted()) {
            $this->locale = Lang::getDefaultLanguage()->getLocale();

            return;
        }

        if (null !== $request->getSession()) {
            $this->locale = $request->getSession()->getLang()->getLocale();
        } else {
            $this->locale = Lang::getDefaultLanguage()->getLocale();
        }
    }

    public function guessTinyMCELanguage($params, \Smarty_Internal_Template $template)
    {
        // Find TinyMCE available languages
        $finder = new Finder();

        $files = $finder->in(__DIR__.DS.'..'.DS.'Resources'.DS.'js'.DS.'tinymce'.DS.'langs')->sortByName();

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
     * Define the various smarty plugins hendled by this class.
     *
     * @return array an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'tinymce_lang', $this, 'guessTinyMCELanguage'),
        ];
    }
}
