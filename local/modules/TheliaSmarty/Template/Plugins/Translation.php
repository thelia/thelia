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

namespace TheliaSmarty\Template\Plugins;

use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Translation\Translator;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class Translation extends AbstractSmartyPlugin
{
    /** @var Translator */
    protected $translator;

    protected $defaultTranslationDomain = '';
    protected $defaultLocale;

    /** @var array saved default domain and locale, see saveDefaults() */
    protected $savedDefaults = [];

    protected $protectedParams = [
        'l',
        'd',
        'js',
        'locale',
        'default',
        'fallback',
    ];

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Set the default translation domain.
     */
    public function setDefaultTranslationDomain(array $params): void
    {
        $this->defaultTranslationDomain = $this->getParam($params, 'domain');
    }

    /**
     * Set the default locale.
     */
    public function setDefaultLocale(array $params): void
    {
        $this->defaultLocale = $this->getParam($params, 'locale');
    }

    /**
     * Save the current default translation domain and locale, so that a template rendered
     * in the meantime cannot change them for the rest of the current template.
     */
    public function saveDefaults(): void
    {
        $this->savedDefaults[] = [$this->defaultTranslationDomain, $this->defaultLocale];
    }

    /**
     * Restore the default translation domain and locale saved by saveDefaults().
     */
    public function restoreDefaults(): void
    {
        if ([] === $this->savedDefaults) {
            return;
        }

        [$this->defaultTranslationDomain, $this->defaultLocale] = array_pop($this->savedDefaults);
    }

    /**
     * Process translate function.
     */
    public function translate(array $params): string
    {
        // All parameters other than 'l' and 'd' and 'js' are supposed to be variables. Build an array of var => value pairs
        // and pass it to the translator
        $variables = [];

        foreach ($params as $name => $value) {
            if (!\in_array($name, $this->protectedParams)) {
                $variables["%$name"] = $value;
            }
        }

        $string = $this->translator->trans(
            $this->getParam($params, 'l'),
            $variables,
            $this->getParam($params, 'd', $this->defaultTranslationDomain),
            $this->getParam($params, 'locale', $this->defaultLocale),
            $this->getBoolean($this->getParam($params, 'default', true), true),
            $this->getBoolean($this->getParam($params, 'fallback', true), true)
        );

        if ($this->getParam($params, 'js', 0)) {
            $string = preg_replace("/(['\"])/", '\\\$1', $string);
        }

        return $string;
    }

    protected function getBoolean($value, $default = false)
    {
        $val = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        if (null === $val) {
            $val = $default;
        }

        return $val;
    }

    /**
     * Define the various smarty plugins handled by this class.
     *
     * @return \TheliaSmarty\Template\SmartyPluginDescriptor[] an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return [
            new SmartyPluginDescriptor('function', 'intl', $this, 'translate'),
            new SmartyPluginDescriptor('function', 'default_translation_domain', $this, 'setDefaultTranslationDomain'),
            new SmartyPluginDescriptor('function', 'default_locale', $this, 'setDefaultLocale'),
        ];
    }
}
