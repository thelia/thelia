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

namespace Thelia\Core\Translation;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Translator as BaseTranslator;
use Thelia\Core\HttpFoundation\Request;

class Translator extends BaseTranslator
{
    public const GLOBAL_FALLBACK_DOMAIN = 'global';

    public const GLOBAL_FALLBACK_KEY = '%s.%s';

    protected static self $instance;

    public function __construct(protected RequestStack $requestStack)
    {
        // Allow singleton style calls once intanciated.
        // For this to work, the Translator service has to be instanciated very early. This is done manually
        // in TheliaHttpKernel, by calling $this->container->get('thelia.translator');
        parent::__construct('');
        self::$instance = $this;
    }

    /**
     * Return this class instance, only once instanciated.
     *
     * @return Translator the instance
     *
     * @throws \RuntimeException if the class has not been instanciated
     */
    public static function getInstance(): self
    {
        if (!self::$instance instanceof \Thelia\Core\Translation\Translator) {
            throw new \RuntimeException('Translator instance is not initialized.');
        }

        return self::$instance;
    }

    public function getLocale(): string
    {
        $currentRequest = $this->requestStack->getCurrentRequest();

        if ($currentRequest instanceof Request) {
            $session = $currentRequest->getSession();

            return $session->getLang()->getLocale();
        }

        return parent::getLocale();
    }

    public function trans(
        ?string $id,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null,
        $returnDefaultIfNotAvailable = true,
        $useFallback = true,
    ): string {
        $domain ??= 'core';

        if (null === $locale) {
            $locale = $this->getLocale();
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        // global translations
        if ($useFallback) {
            $fallbackId = \sprintf(self::GLOBAL_FALLBACK_KEY, $domain, (string) $id);

            if ($this->catalogues[$locale]->has($fallbackId, self::GLOBAL_FALLBACK_DOMAIN)) {
                return parent::trans($fallbackId, $parameters, self::GLOBAL_FALLBACK_DOMAIN, $locale);
            }

            if ($this->catalogues[$locale]->has($id, self::GLOBAL_FALLBACK_DOMAIN)) {
                // global translations
                return parent::trans($id, $parameters, self::GLOBAL_FALLBACK_DOMAIN, $locale);
            }
        }

        if ($this->catalogues[$locale]->has((string) $id, $domain)) {
            return parent::trans($id, $parameters, $domain, $locale);
        }

        if ($returnDefaultIfNotAvailable) {
            return strtr($id, $parameters);
        }

        return '';
    }
}
