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

namespace Thelia\Domain\CMS\Publication;

use Thelia\Domain\CMS\Publication\Exception\EmptyContentPublicationException;
use Thelia\Model\Content;
use Thelia\Model\ContentI18nQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * Refuses to put a content online with no body in the default language of the shop.
 *
 * The guard is asked by `Content::preSave()` whenever an existing row goes from hidden
 * to visible, so it covers every path that writes `visible`: the publication events, the
 * old update and toggle events, the facade, the admin API and the scheduled command. A
 * content created visible is not checked: it has nothing yet, and the back office creates
 * drafts. The other languages are not checked either; a back office may warn about them.
 *
 * The body of the default language is read from the translation the row carries in
 * memory when it holds one, since the body being saved along with the flag is not in the
 * database yet, and from the database otherwise.
 */
final readonly class PublicationGuard
{
    public function __construct(
        private EmptyBodyDetector $detector = new EmptyBodyDetector(),
    ) {
    }

    /**
     * @throws EmptyContentPublicationException
     */
    public function assertHasBody(Content $content, ?string $bodyInMemory, string $defaultLocale): void
    {
        $body = $bodyInMemory ?? $this->bodyInDatabase($content, $defaultLocale);

        if ($this->detector->isEmpty($body)) {
            throw EmptyContentPublicationException::forContent((int) $content->getId(), $defaultLocale, self::languageName($defaultLocale));
        }
    }

    public static function defaultLocale(): string
    {
        return (string) Lang::getDefaultLanguage()->getLocale();
    }

    /**
     * The name of the language as the shop writes it ("English"), null when it has none.
     */
    private static function languageName(string $locale): ?string
    {
        $lang = Lang::getDefaultLanguage();

        return $lang->getLocale() === $locale ? $lang->getTitle() : LangQuery::create()->findOneByLocale($locale)?->getTitle();
    }

    private function bodyInDatabase(Content $content, string $locale): ?string
    {
        if ($content->isNew()) {
            return null;
        }

        return ContentI18nQuery::create()
            ->filterById($content->getId())
            ->filterByLocale($locale)
            ->findOne()
            ?->getDescription();
    }
}
