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

namespace Thelia\Domain\Ai\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;

/**
 * Translates the texts of the AI layer in the domain `core`, the one the console, the API and a mail render
 * read. The locale is the one asked for, else the one of the visitor of the current request, else the one of
 * the default language of the shop: no request is assumed (console, mail rendering, queue worker).
 *
 * @internal
 */
final readonly class AiTextTranslator
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    /**
     * @param array<string, string> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $locale = null): string
    {
        return Translator::getInstance()->trans($id, $parameters, 'core', $this->locale($locale));
    }

    public function locale(?string $locale = null): string
    {
        if (null !== $locale && '' !== $locale) {
            return $locale;
        }

        $request = $this->requestStack->getMainRequest();

        if ($request instanceof Request && $request->hasSession()) {
            $sessionLocale = $request->getSession()->getLang()?->getLocale();

            if (null !== $sessionLocale && '' !== $sessionLocale) {
                return $sessionLocale;
            }
        }

        return Lang::getDefaultLanguage()->getLocale();
    }
}
