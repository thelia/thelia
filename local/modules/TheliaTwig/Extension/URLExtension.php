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

namespace TheliaTwig\Extension;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TheliaTwig\Service\URLService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class URLExtension extends AbstractExtension
{
    public function __construct(
        private readonly URLService $URLService,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('path', [$this, 'path']),
        ];
    }

    public function path(string $routeId, array $parameters = []): string
    {
        $url = '';
        try {
            $url = $this->URLService->generateUrlFunction($routeId, $parameters);
            $checkSymfonyRoutes = $url === '';
        } catch (\Exception) {
            $checkSymfonyRoutes = true;
        }

        return $checkSymfonyRoutes ? $this->urlGenerator->generate($routeId, $parameters) : $url;
    }
}
