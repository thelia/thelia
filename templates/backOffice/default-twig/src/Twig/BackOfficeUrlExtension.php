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

namespace BackOfficeDefaultTwigBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\HttpFoundation\Session\Session as TheliaSession;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Tools\TokenProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BackOfficeUrlExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenProvider $tokens,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('token_url', $this->tokenUrl(...)),
            new TwigFunction('assignToken', $this->assignToken(...)),
            new TwigFunction('bo_languages', $this->boLanguages(...)),
            new TwigFunction('bo_current_language', $this->boCurrentLanguage(...)),
        ];
    }

    /** @return list<Lang> */
    public function boLanguages(): array
    {
        return array_values(iterator_to_array(
            LangQuery::create()->orderByPosition()->find(),
        ));
    }

    public function boCurrentLanguage(): Lang
    {
        $session = $this->requestStack->getMainRequest()?->getSession();

        if ($session instanceof TheliaSession) {
            return $session->getAdminLang();
        }

        return Lang::getDefaultLanguage();
    }

    /**
     * @param array<string, scalar> $parameters
     */
    public function tokenUrl(string $route, array $parameters = []): string
    {
        $url = $this->urls->generate($route, $parameters);
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'_token='.$this->assignToken();
    }

    public function assignToken(): string
    {
        return $this->tokens->assignToken() ?? '';
    }
}
