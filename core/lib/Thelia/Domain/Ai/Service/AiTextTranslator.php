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

use Thelia\Core\Translation\Translator;
use Thelia\Domain\Localization\Service\LangService;

/**
 * Translates the texts of the AI layer in the domain `core`, the one the console, the API and a mail render
 * read. The locale is the one asked for, else the one {@see LangService} gives for the current request (the
 * language of the back-office in the back-office, the one of the visitor on the storefront), else the one of the
 * default language of the shop: no request is assumed (console, mail rendering, queue worker).
 *
 * @internal
 */
final readonly class AiTextTranslator
{
    public function __construct(private LangService $langService)
    {
    }

    /**
     * @param array<string, string> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $locale = null): string
    {
        return Translator::getInstance()->trans($id, $parameters, 'core', $this->locale($locale));
    }

    /**
     * A text a module declared, such as the purpose of its usage: looked up in the domain of the module first
     * (its `I18n/<locale>.php`, the module code in lower case), then in `core`, else the text as written.
     */
    public function transFromModule(string $id, string $module, ?string $locale = null): string
    {
        $locale = $this->locale($locale);
        $translated = Translator::getInstance()->trans($id, [], strtolower($module), $locale, false);

        return '' !== $translated ? $translated : $this->trans($id, [], $locale);
    }

    public function locale(?string $locale = null): string
    {
        if (null !== $locale && '' !== $locale) {
            return $locale;
        }

        return (string) $this->langService->getLocale();
    }
}
