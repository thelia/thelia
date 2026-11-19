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

namespace Thelia\Domain\Search\EventListener;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Search\Dictionary\SearchRedirectTargetType;
use Thelia\Domain\Search\Dictionary\SearchTermNormalizer;
use Thelia\Domain\Search\Dictionary\SearchTermRedirectValidator;
use Thelia\Domain\Search\Event\SearchSynonymWriteEvent;
use Thelia\Domain\Search\Event\SearchTermRedirectWriteEvent;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\SearchSynonym;
use Thelia\Model\SearchSynonymQuery;
use Thelia\Model\SearchTermRedirect;
use Thelia\Model\SearchTermRedirectQuery;

final readonly class SearchDictionaryWriteListener
{
    public const int MAX_SYNONYMS = 50;

    public function __construct(private SearchTermRedirectValidator $redirectValidator)
    {
    }

    #[AsEventListener(event: TheliaEvents::SEARCH_SYNONYM_CREATE)]
    #[AsEventListener(event: TheliaEvents::SEARCH_SYNONYM_UPDATE)]
    public function saveSynonym(SearchSynonymWriteEvent $event): void
    {
        $synonym = $event->getSynonym() ?? new SearchSynonym();
        $lang = $this->langOf($event->getLangId());
        $term = $this->termOf($event->getTerm());
        $equivalents = array_values(array_filter(
            SearchTermNormalizer::normalizeAll($event->getSynonyms()),
            static fn (string $equivalent): bool => SearchTermNormalizer::key($equivalent) !== SearchTermNormalizer::key($term),
        ));

        if ([] === $equivalents) {
            throw new \InvalidArgumentException(self::translate('Give at least one equivalent term, other than the term itself.'));
        }

        if (\count($equivalents) > self::MAX_SYNONYMS) {
            throw new \InvalidArgumentException(self::translate('A term has %max% equivalent terms at most.', ['%max%' => (string) self::MAX_SYNONYMS]));
        }

        foreach ($equivalents as $equivalent) {
            if (mb_strlen($equivalent) > 255) {
                throw new \InvalidArgumentException(self::translate('An equivalent term holds 255 characters at most.'));
            }
        }

        $duplicate = SearchSynonymQuery::create()
            ->filterByLangId($lang->getId())
            ->filterByTerm($term)
            ->_if(!$synonym->isNew())
                ->filterById($synonym->getId(), Criteria::NOT_EQUAL)
            ->_endif()
            ->exists();

        if ($duplicate) {
            throw new \InvalidArgumentException(self::translate('The term "%term%" already has synonyms in this language.', ['%term%' => $term]));
        }

        $synonym
            ->setLangId($lang->getId())
            ->setTerm($term)
            ->setSynonyms(implode("\n", $equivalents))
            ->setBidirectional($event->isBidirectional() ? 1 : 0)
            ->setActive($event->isActive() ? 1 : 0)
            ->save();

        $event->setSynonym($synonym);
    }

    #[AsEventListener(event: TheliaEvents::SEARCH_SYNONYM_DELETE)]
    public function deleteSynonym(SearchSynonymWriteEvent $event): void
    {
        $this->synonymOf($event)->delete();
    }

    #[AsEventListener(event: TheliaEvents::SEARCH_SYNONYM_TOGGLE)]
    public function toggleSynonym(SearchSynonymWriteEvent $event): void
    {
        $synonym = $this->synonymOf($event);
        $synonym->setActive(1 === $synonym->getActive() ? 0 : 1)->save();
    }

    #[AsEventListener(event: TheliaEvents::SEARCH_TERM_REDIRECT_CREATE)]
    #[AsEventListener(event: TheliaEvents::SEARCH_TERM_REDIRECT_UPDATE)]
    public function saveRedirect(SearchTermRedirectWriteEvent $event): void
    {
        $redirect = $event->getRedirect() ?? new SearchTermRedirect();
        $lang = $this->langOf($event->getLangId());
        $term = $this->termOf($event->getTerm());
        $type = $event->getTargetType();
        $isUrl = SearchRedirectTargetType::URL === $type;
        $targetId = $isUrl ? null : $event->getTargetId();
        $url = $isUrl ? trim((string) $event->getUrl()) : null;

        $this->redirectValidator->validate($type, $targetId, $url);

        $duplicate = SearchTermRedirectQuery::create()
            ->filterByLangId($lang->getId())
            ->filterByTerm($term)
            ->_if(!$redirect->isNew())
                ->filterById($redirect->getId(), Criteria::NOT_EQUAL)
            ->_endif()
            ->exists();

        if ($duplicate) {
            throw new \InvalidArgumentException(self::translate('The term "%term%" is already redirected in this language.', ['%term%' => $term]));
        }

        $redirect
            ->setLangId($lang->getId())
            ->setTerm($term)
            ->setTargetType($type)
            ->setTargetId($targetId)
            ->setUrl($url)
            ->setActive($event->isActive() ? 1 : 0)
            ->save();

        $event
            ->setRedirect($redirect)
            ->setCommonTerm($event->isCommonTermChecked() && $this->redirectValidator->isCommonTerm($term, (string) $lang->getLocale()));
    }

    #[AsEventListener(event: TheliaEvents::SEARCH_TERM_REDIRECT_DELETE)]
    public function deleteRedirect(SearchTermRedirectWriteEvent $event): void
    {
        $this->redirectOf($event)->delete();
    }

    #[AsEventListener(event: TheliaEvents::SEARCH_TERM_REDIRECT_TOGGLE)]
    public function toggleRedirect(SearchTermRedirectWriteEvent $event): void
    {
        $redirect = $this->redirectOf($event);

        if (0 === $redirect->getActive()) {
            $this->redirectValidator->validate((string) $redirect->getTargetType(), $redirect->getTargetId(), $redirect->getUrl());
        }

        $redirect->setActive(1 === $redirect->getActive() ? 0 : 1)->save();
    }

    private function langOf(int $langId): Lang
    {
        return LangQuery::create()->findPk($langId)
            ?? throw new \InvalidArgumentException(self::translate('The language does not exist.'));
    }

    private function termOf(string $term): string
    {
        $term = SearchTermNormalizer::normalize($term);

        if ('' === $term || mb_strlen($term) > 255) {
            throw new \InvalidArgumentException(self::translate('The term is required and holds 255 characters at most.'));
        }

        return $term;
    }

    private function synonymOf(SearchSynonymWriteEvent $event): SearchSynonym
    {
        return $event->getSynonym() ?? throw new \LogicException(self::translate('The synonym is missing.'));
    }

    private function redirectOf(SearchTermRedirectWriteEvent $event): SearchTermRedirect
    {
        return $event->getRedirect() ?? throw new \LogicException(self::translate('The redirection is missing.'));
    }

    /**
     * @param array<string, string> $parameters
     */
    private static function translate(string $message, array $parameters = []): string
    {
        return Translator::getInstance()->trans($message, $parameters, 'core');
    }
}
