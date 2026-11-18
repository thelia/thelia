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

namespace Thelia\Test\Volume;

/**
 * Fixed word lists the texts of a volume dataset are built from. A search on one of the nouns
 * hits a predictable share of the catalogue, which is what the measure of the search page needs.
 * French has its own words; every other locale is written with the English ones.
 */
final class VolumeDatasetVocabulary
{
    public const NOUNS = [
        'en' => ['Chair', 'Table', 'Lamp', 'Shelf', 'Sofa', 'Rug', 'Mirror', 'Vase', 'Stool', 'Desk', 'Bench', 'Cabinet', 'Clock', 'Basket', 'Cushion', 'Bed', 'Dresser', 'Frame', 'Candle', 'Plate'],
        'fr' => ['Chaise', 'Table', 'Lampe', 'Étagère', 'Canapé', 'Tapis', 'Miroir', 'Vase', 'Tabouret', 'Bureau', 'Banc', 'Armoire', 'Horloge', 'Panier', 'Coussin', 'Lit', 'Commode', 'Cadre', 'Bougie', 'Assiette'],
    ];

    public const ADJECTIVES = [
        'en' => ['Oak', 'Walnut', 'Linen', 'Brass', 'Velvet', 'Rattan', 'Marble', 'Ceramic', 'Steel', 'Wool', 'Bamboo', 'Glass'],
        'fr' => ['chêne', 'noyer', 'lin', 'laiton', 'velours', 'rotin', 'marbre', 'céramique', 'acier', 'laine', 'bambou', 'verre'],
    ];

    public const CATEGORY_WORDS = [
        'en' => ['Living room', 'Bedroom', 'Kitchen', 'Office', 'Garden', 'Bathroom', 'Kids', 'Lighting', 'Decoration', 'Storage', 'Textile', 'Outdoor'],
        'fr' => ['Salon', 'Chambre', 'Cuisine', 'Bureau', 'Jardin', 'Salle de bain', 'Enfants', 'Luminaires', 'Décoration', 'Rangement', 'Textile', 'Extérieur'],
    ];

    public const FIRSTNAMES = ['Alice', 'Bruno', 'Chloé', 'David', 'Emma', 'Farid', 'Gaëlle', 'Hugo', 'Inès', 'Jules', 'Karim', 'Léa', 'Marc', 'Nina', 'Omar', 'Paul', 'Rose', 'Sami', 'Théo', 'Zoé'];

    public const LASTNAMES = ['Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau', 'Simon', 'Laurent', 'Lefebvre', 'Michel', 'Garcia', 'Roux'];

    public const CITIES = [['75011', 'Paris'], ['69003', 'Lyon'], ['13006', 'Marseille'], ['31000', 'Toulouse'], ['33000', 'Bordeaux'], ['44000', 'Nantes'], ['59000', 'Lille'], ['63000', 'Clermont-Ferrand'], ['67000', 'Strasbourg'], ['35000', 'Rennes']];

    public const STREETS = ['rue de la Paix', 'avenue des Tilleuls', 'boulevard Victor Hugo', 'place du Marché', 'chemin des Vignes', 'rue des Écoles', 'allée des Pins', 'quai de la Loire'];

    private const SENTENCES = [
        'en' => [
            'Made to last, with a finish that ages well.',
            'Designed in a small workshop and assembled by hand.',
            'Fits a small flat as well as a large house.',
            'Easy to clean and to move from one room to another.',
            'Delivered ready to use, with nothing to assemble.',
            'Each piece is checked before it leaves the workshop.',
        ],
        'fr' => [
            'Fabriqué pour durer, avec une finition qui vieillit bien.',
            'Dessiné dans un petit atelier et assemblé à la main.',
            'Convient à un petit appartement comme à une grande maison.',
            'Facile à nettoyer et à déplacer d’une pièce à l’autre.',
            'Livré prêt à l’emploi, sans montage.',
            'Chaque pièce est contrôlée avant de quitter l’atelier.',
        ],
    ];

    public static function language(string $locale): string
    {
        return str_starts_with($locale, 'fr') ? 'fr' : 'en';
    }

    public static function productTitle(string $locale, int $noun, int $adjective, int $number): string
    {
        $language = self::language($locale);
        $nounWord = self::NOUNS[$language][$noun % \count(self::NOUNS[$language])];
        $adjectiveWord = self::ADJECTIVES[$language][$adjective % \count(self::ADJECTIVES[$language])];

        return 'fr' === $language
            ? \sprintf('%s %s %d', $nounWord, $adjectiveWord, $number)
            : \sprintf('%s %s %d', $adjectiveWord, strtolower($nounWord), $number);
    }

    public static function categoryTitle(string $locale, int $word, string $path): string
    {
        $language = self::language($locale);

        return \sprintf('%s %s', self::CATEGORY_WORDS[$language][$word % \count(self::CATEGORY_WORDS[$language])], $path);
    }

    /**
     * @param list<int> $picks indexes of the sentences, in order
     */
    public static function text(string $locale, array $picks): string
    {
        $sentences = self::SENTENCES[self::language($locale)];

        return implode(' ', array_map(static fn (int $pick): string => $sentences[$pick % \count($sentences)], $picks));
    }

    public static function slug(string $text): string
    {
        // A fixed table rather than iconv: its transliteration depends on the C library, and two
        // machines would not write the same addresses.
        $ascii = strtr($text, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'É' => 'E', 'à' => 'a', 'â' => 'a', 'ç' => 'c', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u']);
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii));

        return trim($slug, '-');
    }
}
