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

namespace BackOfficeDefaultTwigBundle\Controller\Configuration;

use BackOfficeDefaultTwigBundle\Service\Admin\AdminAccessChecker;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\HttpFoundation\Session\Session as TheliaSession;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\LangQuery;
use Twig\Environment;

final class TranslationsCustomerTitleController
{
    private const RESOURCE = AdminResources::CONFIG;
    private const ROUTE = 'admin.configuration.translations-customers-title';
    private const TEMPLATE = '@BackOfficeDefaultTwig/configuration/translations/customer-title.html.twig';

    public function __construct(
        private readonly AdminAccessChecker $access,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/admin/configuration/translations-customers-title', name: 'admin.configuration.translations-customers-title', methods: ['GET'])]
    public function defaultAction(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::VIEW)) {
            return $denied;
        }

        $locale = $this->editionLocale($request);
        $titles = [];
        foreach (CustomerTitleQuery::create()->orderByPosition()->find() as $title) {
            $title->setLocale($locale);
            $titles[] = [
                'id' => (int) $title->getId(),
                'short' => (string) $title->getShort(),
                'long' => (string) $title->getLong(),
            ];
        }

        return new Response($this->twig->render(self::TEMPLATE, [
            'titles' => $titles,
            'edit_language_id' => $this->editionLanguageId($request),
            'edit_language_locale' => $locale,
            'available_languages' => $this->languageOptions(),
        ]));
    }

    #[Route('/admin/configuration/translations-customers-title/update', name: 'admin.configuration.translations-customers-title.update', methods: ['POST'])]
    public function updateAction(Request $request): Response
    {
        if ($denied = $this->access->check(self::RESOURCE, [], AccessManager::UPDATE)) {
            return $denied;
        }

        $locale = (string) ($request->request->get('locale') ?? $this->editionLocale($request));
        foreach (CustomerTitleQuery::create()->find() as $title) {
            $shortKey = 'short_title_'.(int) $title->getId();
            $longKey = 'long_title_'.(int) $title->getId();
            $shortValue = $request->request->get($shortKey);
            $longValue = $request->request->get($longKey);
            if ($shortValue === null && $longValue === null) {
                continue;
            }
            $title->setLocale($locale)
                ->setShort((string) ($shortValue ?? ''))
                ->setLong((string) ($longValue ?? ''))
                ->save();
        }

        if ($request->request->get('save_mode') === 'close') {
            return new RedirectResponse('/admin/configuration');
        }

        return new RedirectResponse($this->urls->generate(self::ROUTE));
    }

    /** @return list<array{id: int, title: string, locale: string}> */
    private function languageOptions(): array
    {
        $options = [];
        foreach (LangQuery::create()->orderByPosition()->find() as $lang) {
            $options[] = [
                'id' => (int) $lang->getId(),
                'title' => (string) $lang->getTitle(),
                'locale' => (string) $lang->getLocale(),
            ];
        }

        return $options;
    }

    private function editionLocale(Request $request): string
    {
        $editionId = $request->get('edit_language_id');
        if ($editionId !== null && (int) $editionId > 0) {
            $lang = LangQuery::create()->findPk((int) $editionId);
            if ($lang !== null) {
                return (string) $lang->getLocale();
            }
        }

        $session = $request->getSession();
        if ($session instanceof TheliaSession) {
            return (string) $session->getAdminEditionLang()->getLocale();
        }

        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return (string) ($defaultLang?->getLocale() ?? 'en_US');
    }

    private function editionLanguageId(Request $request): int
    {
        $editionId = $request->get('edit_language_id');
        if ($editionId !== null && (int) $editionId > 0) {
            return (int) $editionId;
        }

        $session = $request->getSession();
        if ($session instanceof TheliaSession) {
            return (int) $session->getAdminEditionLang()->getId();
        }

        $defaultLang = LangQuery::create()->findOneByByDefault(true);

        return (int) ($defaultLang?->getId() ?? 0);
    }
}
