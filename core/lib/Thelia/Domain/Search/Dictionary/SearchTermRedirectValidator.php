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

namespace Thelia\Domain\Search\Dictionary;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Translation\Translator;
use Thelia\Model\ProductI18nQuery;

final readonly class SearchTermRedirectValidator
{
    public const int COMMON_TERM_THRESHOLD = 20;

    public function __construct(private SearchRedirectTargets $targets)
    {
    }

    public function validate(string $type, ?int $targetId, ?string $url): void
    {
        if (!\in_array($type, SearchRedirectTargetType::ALL, true)) {
            throw new \InvalidArgumentException(self::translate('"%type%" is not a redirection target.', ['%type%' => $type]));
        }

        if (SearchRedirectTargetType::URL === $type) {
            if (null === $url || mb_strlen($url) > 2048 || !SearchRedirectTargets::isInternalPath($url)) {
                throw new \InvalidArgumentException(self::translate('The address must be a path of the shop, starting with a single slash.'));
            }

            return;
        }

        if (null === $this->targets->find($type, $targetId)) {
            throw new \InvalidArgumentException(self::translate('The target of the redirection does not exist.'));
        }
    }

    public function isCommonTerm(string $term, string $locale): bool
    {
        $term = SearchTermNormalizer::normalize($term);

        if ('' === $term) {
            return false;
        }

        $escaped = addcslashes($term, '%_\\');

        return ProductI18nQuery::create()
            ->filterByLocale($locale)
            ->condition('first_word', 'ProductI18n.Title LIKE ?', $escaped.'%')
            ->condition('other_words', 'ProductI18n.Title LIKE ?', '% '.$escaped.'%')
            ->where(['first_word', 'other_words'], Criteria::LOGICAL_OR)
            ->limit(self::COMMON_TERM_THRESHOLD + 1)
            ->count() > self::COMMON_TERM_THRESHOLD;
    }

    /**
     * @param array<string, string> $parameters
     */
    private static function translate(string $message, array $parameters = []): string
    {
        return Translator::getInstance()->trans($message, $parameters, 'core');
    }
}
