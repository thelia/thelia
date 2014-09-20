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

namespace Thelia\Core\Translation;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Translation\Translator as BaseTranslator;

class Translator extends BaseTranslator
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    protected static $instance = null;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        // Allow singleton style calls once intanciated.
        // For this to work, the Translator service has to be instanciated very early. This is done manually
        // in TheliaHttpKernel, by calling $this->container->get('thelia.translator');
        parent::__construct(null);
        self::$instance = $this;
    }

    /**
     * Return this class instance, only once instanciated.
     *
     * @throws \RuntimeException                   if the class has not been instanciated.
     * @return \Thelia\Core\Translation\Translator the instance.
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            throw new \RuntimeException("Translator instance is not initialized.");
        }
        return self::$instance;
    }

    public function getLocale()
    {
        if ($this->container->isScopeActive('request') && $this->container->has('request')) {
            return $this->container->get('request')->getSession()->getLang()->getLocale();
        }

        return $this->locale;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function trans($id, array $parameters = array(), $domain = 'core', $locale = null, $return_default_if_not_available = true)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        if ($this->catalogues[$locale]->has((string) $id, $domain)) {
            return parent::trans($id, $parameters, $domain, $locale);
        } else {
            if ($return_default_if_not_available) {
                return strtr($id, $parameters);
            } else {
                return '';
            }
        }
    }
}
