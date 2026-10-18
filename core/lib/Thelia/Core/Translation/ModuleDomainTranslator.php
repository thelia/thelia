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

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lets the framework translator, the one behind Twig's `|trans` filter, reach the
 * catalogues of the Thelia translator.
 *
 * Module and template catalogues (`<module>`, `<module>.fo.<template>`,
 * `<module>.bo.<template>`...) are registered on the Thelia translator only, so a
 * module template rendered by a Twig theme stayed in English whatever the locale.
 * A message the framework catalogue defines is translated by it, as before; any
 * other message of a named domain is looked up in the Thelia translator, then
 * falls back to the framework translator when that one has no entry either.
 */
#[AsDecorator('translator')]
final readonly class ModuleDomainTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface, WarmableInterface
{
    public function __construct(
        #[AutowireDecorated]
        private TranslatorInterface&TranslatorBagInterface&LocaleAwareInterface $inner,
        private Translator $theliaTranslator,
    ) {
    }

    public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        if (null === $id || !$this->isDelegatedDomain($domain) || $this->inner->getCatalogue($locale)->has($id, $domain)) {
            return $this->inner->trans($id, $parameters, $domain, $locale);
        }

        $translated = $this->theliaTranslator->trans(
            $id,
            $parameters,
            $domain,
            $locale ?? $this->inner->getLocale(),
            false,
        );

        return '' !== $translated
            ? $translated
            : $this->inner->trans($id, $parameters, $domain, $locale);
    }

    public function getLocale(): string
    {
        return $this->inner->getLocale();
    }

    public function setLocale(string $locale): void
    {
        $this->inner->setLocale($locale);
    }

    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        return $this->inner->getCatalogue($locale);
    }

    public function getCatalogues(): array
    {
        return $this->inner->getCatalogues();
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($this->inner instanceof WarmableInterface) {
            return $this->inner->warmUp($cacheDir, $buildDir);
        }

        return [];
    }

    /**
     * The theme's own catalogue ('messages', the default domain) and the validator
     * messages stay with the framework translator alone.
     */
    private function isDelegatedDomain(?string $domain): bool
    {
        return null !== $domain && '' !== $domain && 'messages' !== $domain && 'validators' !== $domain;
    }
}
