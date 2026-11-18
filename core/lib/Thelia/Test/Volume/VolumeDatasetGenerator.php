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

use Propel\Runtime\Connection\ConnectionInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Domain\Catalog\Product\Identifier\Gtin;
use Thelia\Model\OrderStatus;

/**
 * Writes a volume dataset: a category tree, a catalogue with combinations, features, images,
 * translations and prices, customers with an address, and an order history. A test tool for
 * measuring how the shop holds with a large catalogue, not an import for merchants.
 *
 * Rows are written with multi-row INSERT statements and identifiers given by the dataset, without
 * the events of the models: an update event rewrites every field, and a hundred thousand products
 * saved one by one take hours. Every random choice comes from one generator seeded by the options,
 * every date from the base date of the options, so the same options on the same starting database
 * write the same rows.
 */
final class VolumeDatasetGenerator
{
    /**
     * Password of every generated customer, "volume-dataset", hashed once: a hash computed at each
     * run would be salted differently, and two datasets would differ by it.
     */
    public const CUSTOMER_PASSWORD_HASH = '$2y$10$n0XjgFvtHp8Hdy.B8sKqce.ytxEQtl3jHVYDxASa.Kqt8yNSo3L6C';

    public const CUSTOMER_PASSWORD = 'volume-dataset';

    public const IMAGE_FILE_PATTERN = 'volume-dataset-%d.jpg';

    private const VAT_RATE = 0.2;

    private const SIZES = ['S', 'M', 'L', 'XL'];

    private const COLOURS = [
        'en' => ['Black', 'White', 'Grey', 'Blue', 'Green', 'Red', 'Yellow', 'Beige'],
        'fr' => ['Noir', 'Blanc', 'Gris', 'Bleu', 'Vert', 'Rouge', 'Jaune', 'Beige'],
    ];

    private const ORIGINS = [
        'en' => ['France', 'Italy', 'Spain', 'Portugal', 'Germany', 'Poland', 'Denmark', 'Belgium'],
        'fr' => ['France', 'Italie', 'Espagne', 'Portugal', 'Allemagne', 'Pologne', 'Danemark', 'Belgique'],
    ];

    private const WARRANTIES = [
        'en' => ['1 year', '2 years', '3 years', '5 years'],
        'fr' => ['1 an', '2 ans', '3 ans', '5 ans'],
    ];

    private Randomizer $random;

    private VolumeDatasetManifest $manifest;

    private BatchInserter $inserter;

    private VolumeDatasetOptions $options;

    private VolumeDatasetReferences $references;

    private int $baseTimestamp;

    /** @var array<string, true> */
    private array $usedUrls = [];

    private int $templateId = 0;

    /** @var array{size: int, colour: int, sizes: list<int>, colours: list<int>} */
    private array $attributes = ['size' => 0, 'colour' => 0, 'sizes' => [], 'colours' => []];

    /** @var array<string, array{id: int, values: list<int>}> */
    private array $features = [];

    /** @var list<int> values written by the last attribute or feature */
    private array $lastValueIds = [];

    /** @var list<int> */
    private array $leafCategoryIds = [];

    /** @var list<string> */
    private array $imageFiles = [];

    /** @var list<array{ref: string, pseId: int, pseRef: string, price: string, title: string}> */
    private array $catalogue = [];

    /** @var list<array{id: int, titleId: int, firstname: string, lastname: string, address1: string, zipcode: string, city: string}> */
    private array $customers = [];

    /** @var (callable(string, int, int): void)|null */
    private $progress;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $mediaDirectory,
        private readonly string $imageSourceDirectory,
        private readonly int $batchSize = BatchInserter::DEFAULT_BATCH_SIZE,
    ) {
    }

    /**
     * @param (callable(string, int, int): void)|null $progress called with a step, the rows done and the rows planned
     */
    public function generate(VolumeDatasetOptions $options, ?callable $progress = null): VolumeDatasetManifest
    {
        if (null !== VolumeDatasetManifest::load($this->connection)) {
            throw new \RuntimeException('A volume dataset is already in this database: purge it before generating another one.');
        }

        $this->options = $options;
        $this->progress = $progress;
        $this->references = VolumeDatasetReferences::resolve($this->connection, $options);
        $this->random = new Randomizer(new Mt19937($options->seed));
        $this->inserter = new BatchInserter($this->connection, $this->batchSize);
        $this->manifest = new VolumeDatasetManifest();
        $this->manifest->recordOptions($options->toArray());
        $this->baseTimestamp = (new \DateTimeImmutable($options->baseDate, new \DateTimeZone('UTC')))->getTimestamp();
        $this->usedUrls = $this->existingUrls();

        // The marker goes in first: a generation stopped half-way still leaves what the purge needs.
        $this->manifest->save($this->connection, $this->date(0));

        $this->timed('structure', $this->writeStructure(...));
        $this->timed('categories', $this->writeCategories(...));
        $this->timed('images', $this->copyImages(...));
        $this->timed('products', $this->writeProducts(...));
        $this->timed('customers', $this->writeCustomers(...));
        $this->timed('orders', $this->writeOrders(...));

        $this->manifest->save($this->connection, $this->date(0));

        return $this->manifest;
    }

    /**
     * @param callable(): void $step
     */
    private function timed(string $name, callable $step): void
    {
        $start = hrtime(true);
        $step();
        $this->manifest->recordDuration($name, (hrtime(true) - $start) / 1e9);
        $this->manifest->save($this->connection, $this->date(0));
    }

    private function writeStructure(): void
    {
        $now = $this->date(800 * 86400);
        $locales = $this->options->locales;

        $this->templateId = $this->nextId('template');
        $this->inserter->insert('template', ['id', 'created_at', 'updated_at'], [[$this->templateId, $now, $now]]);
        $this->inserter->insert('template_i18n', ['id', 'locale', 'name'], array_map(
            fn (string $locale): array => [$this->templateId, $locale, 'Volume dataset'],
            $locales,
        ));

        $this->attributes['size'] = $this->writeAttribute(1, ['en' => 'Size', 'fr' => 'Taille'], ['en' => self::SIZES, 'fr' => self::SIZES], $now);
        $this->attributes['sizes'] = $this->lastValueIds;
        $this->attributes['colour'] = $this->writeAttribute(2, ['en' => 'Colour', 'fr' => 'Couleur'], self::COLOURS, $now);
        $this->attributes['colours'] = $this->lastValueIds;

        $materials = ['en' => VolumeDatasetVocabulary::ADJECTIVES['en'], 'fr' => VolumeDatasetVocabulary::ADJECTIVES['fr']];
        foreach (['material' => [['en' => 'Material', 'fr' => 'Matière'], $materials], 'origin' => [['en' => 'Origin', 'fr' => 'Origine'], self::ORIGINS], 'warranty' => [['en' => 'Warranty', 'fr' => 'Garantie'], self::WARRANTIES]] as $code => [$titles, $values]) {
            $id = $this->writeFeature(\count($this->features) + 1, $titles, $values, $now);
            $this->features[$code] = ['id' => $id, 'values' => $this->lastValueIds];
        }
    }

    /**
     * @param array{en: string, fr: string}             $titles
     * @param array{en: list<string>, fr: list<string>} $values
     */
    private function writeAttribute(int $position, array $titles, array $values, string $now): int
    {
        $id = $this->nextId('attribute');
        $this->inserter->insert('attribute', ['id', 'position', 'created_at', 'updated_at'], [[$id, $position, $now, $now]]);
        $this->inserter->insert('attribute_i18n', ['id', 'locale', 'title'], $this->i18nRows($id, $titles));
        $this->inserter->insert('attribute_template', ['id', 'attribute_id', 'template_id', 'position', 'created_at', 'updated_at'], [
            [$this->nextId('attribute_template'), $id, $this->templateId, $position, $now, $now],
        ]);

        $avRows = $avI18nRows = [];
        $this->lastValueIds = [];
        foreach (array_keys($values['en']) as $index) {
            $avId = $this->nextId('attribute_av');
            $this->lastValueIds[] = $avId;
            $avRows[] = [$avId, $id, $index + 1, $now, $now];
            array_push($avI18nRows, ...$this->i18nRows($avId, ['en' => $values['en'][$index], 'fr' => $values['fr'][$index]]));
        }
        $this->inserter->insert('attribute_av', ['id', 'attribute_id', 'position', 'created_at', 'updated_at'], $avRows);
        $this->inserter->insert('attribute_av_i18n', ['id', 'locale', 'title'], $avI18nRows);

        return $id;
    }

    /**
     * @param array{en: string, fr: string}             $titles
     * @param array{en: list<string>, fr: list<string>} $values
     */
    private function writeFeature(int $position, array $titles, array $values, string $now): int
    {
        $id = $this->nextId('feature');
        $this->inserter->insert('feature', ['id', 'visible', 'position', 'created_at', 'updated_at'], [[$id, 1, $position, $now, $now]]);
        $this->inserter->insert('feature_i18n', ['id', 'locale', 'title'], $this->i18nRows($id, $titles));
        $this->inserter->insert('feature_template', ['id', 'feature_id', 'template_id', 'position', 'created_at', 'updated_at'], [
            [$this->nextId('feature_template'), $id, $this->templateId, $position, $now, $now],
        ]);

        $avRows = $avI18nRows = [];
        $this->lastValueIds = [];
        foreach (array_keys($values['en']) as $index) {
            $avId = $this->nextId('feature_av');
            $this->lastValueIds[] = $avId;
            $avRows[] = [$avId, $id, $index + 1, $now, $now];
            array_push($avI18nRows, ...$this->i18nRows($avId, ['en' => $values['en'][$index], 'fr' => $values['fr'][$index]]));
        }
        $this->inserter->insert('feature_av', ['id', 'feature_id', 'position', 'created_at', 'updated_at'], $avRows);
        $this->inserter->insert('feature_av_i18n', ['id', 'locale', 'title'], $avI18nRows);

        return $id;
    }

    private function writeCategories(): void
    {
        $now = $this->date(700 * 86400);
        $parents = [[0, '']];
        $planned = $this->options->categoryCount();
        $done = 0;

        for ($level = 1; $level <= $this->options->categoryDepth; ++$level) {
            $categoryRows = $i18nRows = $urlRows = $children = [];
            foreach ($parents as [$parentId, $parentPath]) {
                for ($rank = 1; $rank <= $this->options->categoriesPerLevel; ++$rank) {
                    $id = $this->nextId('category');
                    $path = '' === $parentPath ? (string) $rank : $parentPath.'.'.$rank;
                    $word = $this->random->getInt(0, \count(VolumeDatasetVocabulary::CATEGORY_WORDS['en']) - 1);
                    $categoryRows[] = [$id, $parentId, 1, $rank, $this->templateId, $now, $now, 1, $now];

                    $titles = [];
                    foreach ($this->options->locales as $locale) {
                        $titles[$locale] = VolumeDatasetVocabulary::categoryTitle($locale, $word, $path);
                        $i18nRows[] = [$id, $locale, $titles[$locale], VolumeDatasetVocabulary::text($locale, [$word, $rank]), null];
                    }
                    array_push($urlRows, ...$this->urlRows('category', $id, $titles, $now));
                    $children[] = [$id, $path];
                }
            }

            $this->inserter->insert('category', ['id', 'parent', 'visible', 'position', 'default_template_id', 'created_at', 'updated_at', 'version', 'version_created_at'], $categoryRows);
            $this->inserter->insert('category_i18n', ['id', 'locale', 'title', 'description', 'chapo'], $i18nRows);
            $this->inserter->insert('rewriting_url', ['id', 'url', 'view', 'view_id', 'view_locale', 'redirected', 'created_at', 'updated_at'], $urlRows);
            $done += \count($categoryRows);
            $this->report('categories', $done, $planned);
            $parents = $children;
        }

        $this->leafCategoryIds = array_map(static fn (array $leaf): int => $leaf[0], $parents);
    }

    /**
     * A few source images, copied once and shared by every product: a hundred thousand copies
     * would weigh gigabytes. The cost is an image cache warmer than a real shop's.
     */
    private function copyImages(): void
    {
        $sources = glob(rtrim($this->imageSourceDirectory, '/').'/PROD*-1.jpg') ?: [];
        sort($sources);
        if ([] === $sources) {
            throw new \RuntimeException(\sprintf('No source image in %s.', $this->imageSourceDirectory));
        }

        $filesystem = new Filesystem();
        for ($index = 1; $index <= $this->options->images; ++$index) {
            $file = \sprintf(self::IMAGE_FILE_PATTERN, $index);
            $filesystem->copy($sources[($index - 1) % \count($sources)], rtrim($this->mediaDirectory, '/').'/images/product/'.$file, true);
            $this->manifest->recordFile('images/product/'.$file);
            $this->imageFiles[] = $file;
        }
    }

    private function writeProducts(): void
    {
        $leafCount = \count($this->leafCategoryIds);
        $positionsByCategory = [];
        $total = $this->options->products;
        $nounCount = \count(VolumeDatasetVocabulary::NOUNS['en']);
        $adjectiveCount = \count(VolumeDatasetVocabulary::ADJECTIVES['en']);
        $defaultLocale = $this->options->locales[0];

        for ($start = 1; $start <= $total; $start += $this->batchSize) {
            $rows = array_fill_keys(['product', 'product_i18n', 'product_category', 'pse', 'price', 'combination', 'feature', 'image', 'image_i18n', 'url'], []);
            $end = min($total, $start + $this->batchSize - 1);

            for ($number = $start; $number <= $end; ++$number) {
                $id = $this->nextId('product');
                $ref = \sprintf('%sP%07d', VolumeDatasetManifest::REF_PREFIX, $number);
                // The newest product is the last one, one every ten minutes before the base date.
                $created = $this->date(($total - $number + 1) * 600);
                $noun = $this->random->getInt(0, $nounCount - 1);
                $adjective = $this->random->getInt(0, $adjectiveCount - 1);
                $rows['product'][] = [$id, $this->references->taxRuleId, $ref, 1, $number, $this->templateId, $created, $created, 1, $created];

                $titles = [];
                foreach ($this->options->locales as $locale) {
                    $titles[$locale] = VolumeDatasetVocabulary::productTitle($locale, $noun, $adjective, $number);
                    $rows['product_i18n'][] = [
                        $id,
                        $locale,
                        $titles[$locale],
                        VolumeDatasetVocabulary::text($locale, [$noun, $adjective]),
                        VolumeDatasetVocabulary::text($locale, [$noun, $adjective + 1, $number, $noun + 2]),
                    ];
                }
                array_push($rows['url'], ...$this->urlRows('product', $id, $titles, $created));

                // Squaring a uniform draw crowds the products in the first leaves: a real
                // catalogue has a few large categories, and the measure needs one.
                $draw = $this->random->getInt(0, 999_999) / 1_000_000;
                $categoryIds = [$this->leafCategoryIds[min($leafCount - 1, (int) floor($leafCount * $draw * $draw))]];
                if ($leafCount > 1 && 1 === $this->random->getInt(1, 5)) {
                    $second = $this->leafCategoryIds[$this->random->getInt(0, $leafCount - 1)];
                    if ($second !== $categoryIds[0]) {
                        $categoryIds[] = $second;
                    }
                }
                foreach ($categoryIds as $rank => $categoryId) {
                    $positionsByCategory[$categoryId] = ($positionsByCategory[$categoryId] ?? 0) + 1;
                    $rows['product_category'][] = [$id, $categoryId, 0 === $rank ? 1 : 0, $positionsByCategory[$categoryId], $created, $created];
                }

                $default = $this->writeSaleElements($rows, $id, $ref, $created);
                $this->catalogue[] = ['ref' => $ref, 'pseId' => $default['id'], 'pseRef' => $default['ref'], 'price' => $default['price'], 'title' => $titles[$defaultLocale]];

                $this->writeProductFeatures($rows, $id, $adjective, $created);
                $this->writeProductImages($rows, $id, $titles, $created);
            }

            $this->inserter->insert('product', ['id', 'tax_rule_id', 'ref', 'visible', 'position', 'template_id', 'created_at', 'updated_at', 'version', 'version_created_at'], $rows['product']);
            $this->inserter->insert('product_i18n', ['id', 'locale', 'title', 'chapo', 'description'], $rows['product_i18n']);
            $this->inserter->insert('product_category', ['product_id', 'category_id', 'default_category', 'position', 'created_at', 'updated_at'], $rows['product_category']);
            $this->inserter->insert('product_sale_elements', ['id', 'product_id', 'ref', 'quantity', 'position', 'visible', 'promo', 'newness', 'weight', 'is_default', 'ean_code', 'created_at', 'updated_at'], $rows['pse']);
            $this->inserter->insert('product_price', ['product_sale_elements_id', 'currency_id', 'price', 'promo_price', 'from_default_currency', 'created_at', 'updated_at'], $rows['price']);
            $this->inserter->insert('attribute_combination', ['attribute_id', 'attribute_av_id', 'product_sale_elements_id', 'position', 'created_at', 'updated_at'], $rows['combination']);
            $this->inserter->insert('feature_product', ['id', 'product_id', 'feature_id', 'feature_av_id', 'is_free_text', 'position', 'created_at', 'updated_at'], $rows['feature']);
            $this->inserter->insert('product_image', ['id', 'product_id', 'visible', 'decorative', 'position', 'created_at', 'updated_at'], $rows['image']);
            $this->inserter->insert('product_image_i18n', ['id', 'locale', 'file', 'title', 'alt'], $rows['image_i18n']);
            $this->inserter->insert('rewriting_url', ['id', 'url', 'view', 'view_id', 'view_locale', 'redirected', 'created_at', 'updated_at'], $rows['url']);

            $this->report('products', $end, $total);
        }
    }

    /**
     * One combination without attribute, or two to four sizes in a colour, the first one being
     * the default.
     *
     * @param array<string, list<list<string|int|float|null>>> $rows
     *
     * @return array{id: int, ref: string, price: string}
     */
    private function writeSaleElements(array &$rows, int $productId, string $productRef, string $created): array
    {
        $variants = [1, 1, 2, 3, 4][$this->random->getInt(0, 4)];
        $basePrice = $this->random->getInt(500, 50_000);
        $promo = 1 === $this->random->getInt(1, 10);
        $colour = $this->attributes['colours'][$this->random->getInt(0, \count($this->attributes['colours']) - 1)];
        $default = null;

        for ($rank = 0; $rank < $variants; ++$rank) {
            $pseId = $this->nextId('product_sale_elements');
            $pseRef = $productRef.'-'.$rank;
            $cents = $basePrice + $rank * 500;
            $digits = \sprintf('200%09d', $pseId);
            $rows['pse'][] = [
                $pseId, $productId, $pseRef, $this->random->getInt(0, 100), $rank + 1, 1, $promo ? 1 : 0,
                1 === $this->random->getInt(1, 10) ? 1 : 0, \sprintf('%.2f', $this->random->getInt(10, 3000) / 100),
                0 === $rank ? 1 : 0, $digits.Gtin::checkDigitOf($digits), $created, $created,
            ];

            foreach ($this->references->currencyIdsByCode as $currencyId) {
                // Another currency is a tenth dearer: enough for two prices to differ.
                $currencyCents = $currencyId === $this->references->defaultCurrencyId ? $cents : intdiv($cents * 11, 10);
                $rows['price'][] = [$pseId, $currencyId, $this->money($currencyCents), $this->money($promo ? intdiv($currencyCents * 8, 10) : 0), 0, $created, $created];
            }

            if ($variants > 1) {
                $rows['combination'][] = [$this->attributes['size'], $this->attributes['sizes'][$rank], $pseId, 1, $created, $created];
                $rows['combination'][] = [$this->attributes['colour'], $colour, $pseId, 2, $created, $created];
            }

            $default ??= ['id' => $pseId, 'ref' => $pseRef, 'price' => $this->money($cents)];
        }

        return $default;
    }

    /**
     * @param array<string, list<list<string|int|float|null>>> $rows
     */
    private function writeProductFeatures(array &$rows, int $productId, int $adjective, string $created): void
    {
        // Every product has a material, seven in ten an origin, one in two a warranty.
        $chances = ['origin' => 7, 'warranty' => 5];
        $position = 0;
        foreach ($this->features as $code => $feature) {
            if (isset($chances[$code]) && $this->random->getInt(1, 10) > $chances[$code]) {
                continue;
            }
            $value = 'material' === $code
                ? $feature['values'][$adjective % \count($feature['values'])]
                : $feature['values'][$this->random->getInt(0, \count($feature['values']) - 1)];
            $rows['feature'][] = [$this->nextId('feature_product'), $productId, $feature['id'], $value, 0, ++$position, $created, $created];
        }
    }

    /**
     * @param array<string, list<list<string|int|float|null>>> $rows
     * @param array<string, string>                            $titles
     */
    private function writeProductImages(array &$rows, int $productId, array $titles, string $created): void
    {
        $count = $this->random->getInt(1, 3);
        for ($position = 1; $position <= $count; ++$position) {
            $imageId = $this->nextId('product_image');
            $file = $this->imageFiles[$this->random->getInt(0, \count($this->imageFiles) - 1)];
            $rows['image'][] = [$imageId, $productId, 1, 0, $position, $created, $created];
            foreach ($titles as $locale => $title) {
                $rows['image_i18n'][] = [$imageId, $locale, $file, $title, $title.' '.$position];
            }
        }
    }

    private function writeCustomers(): void
    {
        $total = $this->options->customers;
        $langId = $this->references->langIdsByLocale[$this->options->locales[0]];
        $titleIds = $this->references->customerTitleIds;

        for ($start = 1; $start <= $total; $start += $this->batchSize) {
            $customerRows = $addressRows = [];
            $end = min($total, $start + $this->batchSize - 1);

            for ($number = $start; $number <= $end; ++$number) {
                $id = $this->nextId('customer');
                $created = $this->date(($total - $number + 1) * 900 + 400 * 86400);
                $titleId = $titleIds[$this->random->getInt(0, \count($titleIds) - 1)];
                $firstname = VolumeDatasetVocabulary::FIRSTNAMES[$this->random->getInt(0, \count(VolumeDatasetVocabulary::FIRSTNAMES) - 1)];
                $lastname = VolumeDatasetVocabulary::LASTNAMES[$this->random->getInt(0, \count(VolumeDatasetVocabulary::LASTNAMES) - 1)];
                [$zipcode, $city] = VolumeDatasetVocabulary::CITIES[$this->random->getInt(0, \count(VolumeDatasetVocabulary::CITIES) - 1)];
                $address1 = \sprintf('%d %s', $this->random->getInt(1, 120), VolumeDatasetVocabulary::STREETS[$this->random->getInt(0, \count(VolumeDatasetVocabulary::STREETS) - 1)]);

                // example.com is reserved for examples (RFC 2606): no mail can reach a real person.
                $customerRows[] = [
                    $id, $titleId, $langId, \sprintf('%sC%07d', VolumeDatasetManifest::REF_PREFIX, $number), $firstname, $lastname,
                    \sprintf('volume-customer-%d@example.com', $number), self::CUSTOMER_PASSWORD_HASH, 'PASSWORD_BCRYPT',
                    0, '0', 1, 0, $this->references->customerGroupId, $created, $created, 1, $created,
                ];
                $addressRows[] = [
                    $this->nextId('address'), 'Main address', $id, $titleId, $firstname, $lastname, $address1, '', '',
                    $zipcode, $city, $this->references->countryId, \sprintf('06%08d', $number), 1, $created, $created,
                ];
                $this->customers[] = ['id' => $id, 'titleId' => $titleId, 'firstname' => $firstname, 'lastname' => $lastname, 'address1' => $address1, 'zipcode' => $zipcode, 'city' => $city];
            }

            $this->inserter->insert('customer', ['id', 'title_id', 'lang_id', 'ref', 'firstname', 'lastname', 'email', 'password', 'algo', 'reseller', 'discount', 'enable', 'is_guest', 'customer_group_id', 'created_at', 'updated_at', 'version', 'version_created_at'], $customerRows);
            $this->inserter->insert('address', ['id', 'label', 'customer_id', 'title_id', 'firstname', 'lastname', 'address1', 'address2', 'address3', 'zipcode', 'city', 'country_id', 'phone', 'is_default', 'created_at', 'updated_at'], $addressRows);
            $this->report('customers', $end, $total);
        }
    }

    private function writeOrders(): void
    {
        $total = $this->options->orders;
        if (0 === $total) {
            return;
        }

        $customerCount = \count($this->customers);
        $productCount = \count($this->catalogue);
        $langId = $this->references->langIdsByLocale[$this->options->locales[0]];
        $currencyId = $this->references->defaultCurrencyId;
        $statuses = $this->references->orderStatusIdsByCode;
        $yearInSeconds = 365 * 86400;

        for ($start = 1; $start <= $total; $start += $this->batchSize) {
            $rows = array_fill_keys(['cart', 'address', 'order', 'line', 'tax'], []);
            $end = min($total, $start + $this->batchSize - 1);

            for ($number = $start; $number <= $end; ++$number) {
                // Spread over the year before the base date, the newest order being the last one.
                $age = intdiv(($total - $number) * $yearInSeconds, $total) + $this->random->getInt(0, 3599);
                $created = $this->date($age);
                $draw = $this->random->getInt(0, 999_999) / 1_000_000;
                // Squared like the categories: a few customers order a lot.
                $customer = $this->customers[min($customerCount - 1, (int) floor($customerCount * $draw * $draw))];
                $statusCode = $this->statusFor($age);

                $cartId = $this->nextId('cart');
                $rows['cart'][] = [$cartId, 'volume-cart-'.$cartId, $customer['id'], $currencyId, $created, $created];

                $addressIds = [];
                foreach (['invoice', 'delivery'] as $unused) {
                    $addressIds[] = $addressId = $this->nextId('order_address');
                    $rows['address'][] = [$addressId, $customer['titleId'], $customer['firstname'], $customer['lastname'], $customer['address1'], '', '', $customer['zipcode'], $customer['city'], $this->references->countryId, $created, $created];
                }

                $orderId = $this->nextId('order');
                $paid = \in_array($statusCode, [OrderStatus::CODE_PAID, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT, OrderStatus::CODE_REFUNDED], true);
                $rows['order'][] = [
                    $orderId, \sprintf('%sO%07d', VolumeDatasetManifest::REF_PREFIX, $number), $customer['id'], $addressIds[0], $addressIds[1],
                    $paid ? $created : null, $currencyId, 1, '0', '9.900000', '0', $this->references->paymentModuleId, $this->references->deliveryModuleId,
                    $statuses[$statusCode], $langId, $cartId, $created, $created, 1, $created,
                ];

                $lineCount = $this->random->getInt(1, 4);
                for ($line = 0; $line < $lineCount; ++$line) {
                    $item = $this->catalogue[$this->random->getInt(0, $productCount - 1)];
                    $lineId = $this->nextId('order_product');
                    $rows['line'][] = [$lineId, $orderId, $item['ref'], $item['pseRef'], $item['pseId'], $item['title'], $this->random->getInt(1, 3), $item['price'], '0', 0, 0, 'VAT 20%', $created, $created];
                    $rows['tax'][] = [$this->nextId('order_product_tax'), $lineId, 'VAT 20%', $this->money((int) round((float) $item['price'] * 100 * self::VAT_RATE)), '0', $created, $created];
                }
            }

            $this->inserter->insert('cart', ['id', 'token', 'customer_id', 'currency_id', 'created_at', 'updated_at'], $rows['cart']);
            $this->inserter->insert('order_address', ['id', 'customer_title_id', 'firstname', 'lastname', 'address1', 'address2', 'address3', 'zipcode', 'city', 'country_id', 'created_at', 'updated_at'], $rows['address']);
            $this->inserter->insert('order', ['id', 'ref', 'customer_id', 'invoice_order_address_id', 'delivery_order_address_id', 'invoice_date', 'currency_id', 'currency_rate', 'discount', 'postage', 'postage_tax', 'payment_module_id', 'delivery_module_id', 'status_id', 'lang_id', 'cart_id', 'created_at', 'updated_at', 'version', 'version_created_at'], $rows['order']);
            $this->inserter->insert('order_product', ['id', 'order_id', 'product_ref', 'product_sale_elements_ref', 'product_sale_elements_id', 'title', 'quantity', 'price', 'promo_price', 'was_new', 'was_in_promo', 'tax_rule_title', 'created_at', 'updated_at'], $rows['line']);
            $this->inserter->insert('order_product_tax', ['id', 'order_product_id', 'title', 'amount', 'promo_amount', 'created_at', 'updated_at'], $rows['tax']);
            $this->report('orders', $end, $total);
        }
    }

    private function statusFor(int $age): string
    {
        $draw = $this->random->getInt(1, 100);
        if ($age < 3 * 86400) {
            return match (true) {
                $draw <= 30 => OrderStatus::CODE_NOT_PAID,
                $draw <= 70 => OrderStatus::CODE_PAID,
                default => OrderStatus::CODE_PROCESSING,
            };
        }

        return match (true) {
            $draw <= 8 => OrderStatus::CODE_CANCELED,
            $draw <= 10 && isset($this->references->orderStatusIdsByCode[OrderStatus::CODE_REFUNDED]) => OrderStatus::CODE_REFUNDED,
            default => OrderStatus::CODE_SENT,
        };
    }

    /**
     * @param array<string, string> $titlesByLocale
     *
     * @return list<list<string|int|null>>
     */
    private function urlRows(string $view, int $id, array $titlesByLocale, string $created): array
    {
        $rows = [];
        foreach ($titlesByLocale as $locale => $title) {
            $base = VolumeDatasetVocabulary::slug($title);
            $url = $base.'.html';
            // Locales written with the same words share a title: the next one gets its language.
            if (isset($this->usedUrls[$url])) {
                $url = $base.'-'.strtolower(substr($locale, 0, 2)).'.html';
            }
            if (isset($this->usedUrls[$url])) {
                $url = $base.'-'.$view.'-'.$id.'-'.strtolower($locale).'.html';
            }
            $this->usedUrls[$url] = true;
            $rows[] = [$this->nextId('rewriting_url'), $url, $view, (string) $id, $locale, null, $created, $created];
        }

        return $rows;
    }

    /**
     * @param array{en: string, fr: string} $texts
     *
     * @return list<list<string|int>>
     */
    private function i18nRows(int $id, array $texts): array
    {
        return array_map(
            static fn (string $locale): array => [$id, $locale, $texts[VolumeDatasetVocabulary::language($locale)]],
            $this->options->locales,
        );
    }

    /**
     * @return array<string, true>
     */
    private function existingUrls(): array
    {
        $statement = $this->connection->prepare('SELECT url FROM rewriting_url');
        $statement->execute();

        return array_fill_keys($statement->fetchAll(\PDO::FETCH_COLUMN), true);
    }

    private function nextId(string $table): int
    {
        return $this->manifest->nextId($this->connection, $table);
    }

    private function date(int $secondsBeforeBase): string
    {
        return gmdate('Y-m-d H:i:s', $this->baseTimestamp - $secondsBeforeBase);
    }

    private function money(int $cents): string
    {
        return \sprintf('%d.%02d0000', intdiv($cents, 100), $cents % 100);
    }

    private function report(string $step, int $done, int $planned): void
    {
        if (null !== $this->progress) {
            ($this->progress)($step, $done, $planned);
        }
    }
}
