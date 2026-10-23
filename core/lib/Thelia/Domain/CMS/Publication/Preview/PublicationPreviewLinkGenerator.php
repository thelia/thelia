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

namespace Thelia\Domain\CMS\Publication\Preview;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the link a back-office user previews a draft with, and shares with someone
 * who has no back-office account.
 *
 * The link is signed with the application secret and carries its expiry: whoever holds
 * it sees the row until then, without signing in. The lifetime is a container parameter,
 * `thelia.publication.preview_lifetime_days`, seven days by default.
 */
final readonly class PublicationPreviewLinkGenerator
{
    public function __construct(
        private UriSigner $uriSigner,
        private UrlGeneratorInterface $urlGenerator,
        private ClockInterface $clock,
        #[Autowire(param: 'thelia.publication.preview_lifetime_days')]
        private int $lifetimeDays = 7,
    ) {
    }

    public function generate(PublicationPreview $preview): PublicationPreviewLink
    {
        $expiresAt = $this->clock->now()->modify(\sprintf('+%d days', max(1, $this->lifetimeDays)));

        $parameters = ['type' => $preview->type, 'id' => $preview->id];

        if (null !== $preview->locale) {
            // Not a route parameter: it travels as a query parameter, signed with the rest.
            $parameters['locale'] = $preview->locale;
        }

        $url = $this->urlGenerator->generate(PublicationPreview::ROUTE, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);

        return new PublicationPreviewLink($this->uriSigner->sign($url, $expiresAt), \DateTimeImmutable::createFromInterface($expiresAt));
    }
}
