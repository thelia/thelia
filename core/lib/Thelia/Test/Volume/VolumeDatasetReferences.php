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
use Thelia\Model\OrderStatus;

/**
 * What a volume dataset links to and does not create: the languages, currencies, tax rule,
 * country, customer titles and group, order statuses and the payment and delivery modules
 * of the shop it is generated in.
 */
final readonly class VolumeDatasetReferences
{
    /**
     * @param array<string, int> $langIdsByLocale
     * @param array<string, int> $currencyIdsByCode
     * @param list<int>          $customerTitleIds
     * @param array<string, int> $orderStatusIdsByCode
     */
    public function __construct(
        public array $langIdsByLocale,
        public array $currencyIdsByCode,
        public int $defaultCurrencyId,
        public int $taxRuleId,
        public int $countryId,
        public array $customerTitleIds,
        public int $customerGroupId,
        public array $orderStatusIdsByCode,
        public ?int $paymentModuleId,
        public ?int $deliveryModuleId,
    ) {
    }

    public static function resolve(ConnectionInterface $connection, VolumeDatasetOptions $options): self
    {
        $langIdsByLocale = [];
        foreach ($options->locales as $locale) {
            $langIdsByLocale[$locale] = self::requiredId(
                $connection,
                'SELECT id FROM lang WHERE locale = ? ORDER BY id LIMIT 1',
                [$locale],
                \sprintf('No language with the locale %s in this shop.', $locale),
            );
        }

        $currencyIdsByCode = [];
        foreach ($options->currencyCodes as $code) {
            $currencyIdsByCode[$code] = self::requiredId(
                $connection,
                'SELECT id FROM currency WHERE code = ? ORDER BY id LIMIT 1',
                [$code],
                \sprintf('No currency with the code %s in this shop.', $code),
            );
        }

        $defaultCurrencyId = self::requiredId($connection, 'SELECT id FROM currency ORDER BY by_default DESC, id LIMIT 1', [], 'This shop has no currency.');
        if (!\in_array($defaultCurrencyId, $currencyIdsByCode, true)) {
            throw new \InvalidArgumentException('The currencies of the dataset must include the default currency of the shop.');
        }

        $orderStatusIdsByCode = [];
        foreach (self::column($connection, 'SELECT code, id FROM order_status ORDER BY id') as $row) {
            $orderStatusIdsByCode[(string) $row[0]] = (int) $row[1];
        }
        foreach ([OrderStatus::CODE_NOT_PAID, OrderStatus::CODE_PAID, OrderStatus::CODE_PROCESSING, OrderStatus::CODE_SENT, OrderStatus::CODE_CANCELED] as $code) {
            if (!isset($orderStatusIdsByCode[$code])) {
                throw new \RuntimeException(\sprintf('The order status %s is missing.', $code));
            }
        }

        $customerTitleIds = array_map(
            static fn (array $row): int => (int) $row[0],
            self::column($connection, 'SELECT id FROM customer_title ORDER BY id'),
        );
        if ([] === $customerTitleIds) {
            throw new \RuntimeException('This shop has no customer title.');
        }

        return new self(
            langIdsByLocale: $langIdsByLocale,
            currencyIdsByCode: $currencyIdsByCode,
            defaultCurrencyId: $defaultCurrencyId,
            taxRuleId: self::requiredId($connection, 'SELECT id FROM tax_rule ORDER BY is_default DESC, id LIMIT 1', [], 'This shop has no tax rule.'),
            countryId: self::requiredId($connection, 'SELECT id FROM country ORDER BY by_default DESC, id LIMIT 1', [], 'This shop has no country.'),
            customerTitleIds: $customerTitleIds,
            customerGroupId: self::requiredId($connection, 'SELECT id FROM customer_group ORDER BY is_default DESC, id LIMIT 1', [], 'This shop has no customer group.'),
            orderStatusIdsByCode: $orderStatusIdsByCode,
            paymentModuleId: self::optionalId($connection, 'SELECT id FROM module WHERE activate = 1 AND type = 3 ORDER BY code = ? DESC, position, id LIMIT 1', ['Cheque']),
            deliveryModuleId: self::optionalId($connection, 'SELECT id FROM module WHERE activate = 1 AND type = 2 ORDER BY code = ? DESC, position, id LIMIT 1', ['CustomDelivery']),
        );
    }

    /**
     * @param list<string|int> $parameters
     */
    private static function requiredId(ConnectionInterface $connection, string $sql, array $parameters, string $message): int
    {
        return self::optionalId($connection, $sql, $parameters) ?? throw new \RuntimeException($message);
    }

    /**
     * @param list<string|int> $parameters
     */
    private static function optionalId(ConnectionInterface $connection, string $sql, array $parameters): ?int
    {
        $statement = $connection->prepare($sql);
        $statement->execute($parameters);
        // fetch() rather than fetchColumn(): Propel's statement wrapper turns "no row" into an
        // empty string, which an (int) cast would read as the identifier 0.
        $row = $statement->fetch(\PDO::FETCH_NUM);

        return \is_array($row) && null !== $row[0] ? (int) $row[0] : null;
    }

    /**
     * @return list<list<string|int|null>>
     */
    private static function column(ConnectionInterface $connection, string $sql): array
    {
        $statement = $connection->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_NUM);
    }
}
