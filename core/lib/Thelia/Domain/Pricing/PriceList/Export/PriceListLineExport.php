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

namespace Thelia\Domain\Pricing\PriceList\Export;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Model\Admin;
use Thelia\Model\AdminLog;
use Thelia\Model\PriceList;
use Thelia\Model\PriceListItemQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Writes the lines of a price list as CSV, in the columns the import reads (pse_ref, pse_id,
 * min_quantity, price, discount_percent), so a file leaves and comes back. The lines are read by
 * batches of BATCH_SIZE, by primary key, so a list of any size is written with a bounded memory.
 *
 * The export is logged in the administration log: who took the negotiated prices of a list out,
 * and how many lines. Written to a stream the caller owns (a response, a file, php://output).
 */
final readonly class PriceListLineExport
{
    public const int BATCH_SIZE = 500;

    public const array COLUMNS = ['pse_ref', 'pse_id', 'min_quantity', 'price', 'discount_percent'];

    public function __construct(private SecurityContext $securityContext)
    {
    }

    /**
     * @param resource $stream
     *
     * @return int the number of lines written, the header apart
     */
    public function write(PriceList $priceList, $stream): int
    {
        $this->put($stream, self::COLUMNS);

        $written = 0;
        $lastId = 0;

        do {
            $items = PriceListItemQuery::create()
                ->filterByPriceListId($priceList->getId())
                ->filterById($lastId, Criteria::GREATER_THAN)
                ->orderById()
                ->limit(self::BATCH_SIZE)
                ->find();

            $references = $this->references(array_map(static fn ($item): int => $item->getProductSaleElementsId(), iterator_to_array($items, false)));

            foreach ($items as $item) {
                $this->put($stream, [
                    $references[$item->getProductSaleElementsId()] ?? '',
                    (string) $item->getProductSaleElementsId(),
                    (string) $item->getMinQuantity(),
                    $item->getPrice() ?? '',
                    $item->getDiscountPercent() ?? '',
                ]);
                $lastId = $item->getId();
                ++$written;
            }
        } while (self::BATCH_SIZE === \count($items));

        $this->log($priceList, $written);

        return $written;
    }

    /**
     * @param list<int> $saleElementIds
     *
     * @return array<int, string>
     */
    private function references(array $saleElementIds): array
    {
        if ([] === $saleElementIds) {
            return [];
        }

        $references = [];

        /** @var list<array{Id: int|string, Ref: string}> $rows */
        $rows = ProductSaleElementsQuery::create()->filterById($saleElementIds, Criteria::IN)->select(['Id', 'Ref'])->find()->toArray();

        foreach ($rows as $row) {
            $references[(int) $row['Id']] = $row['Ref'];
        }

        return $references;
    }

    /**
     * @param resource     $stream
     * @param list<string> $cells
     */
    private function put($stream, array $cells): void
    {
        if (false === fputcsv($stream, $cells, ';', '"', '')) {
            throw new \RuntimeException('The export could not be written to its stream.');
        }
    }

    private function log(PriceList $priceList, int $lines): void
    {
        $user = $this->securityContext->getAdminUser();

        (new AdminLog())
            ->setAdminLogin($user instanceof UserInterface ? $user->getUsername() : '<no login>')
            ->setAdminFirstname($user instanceof Admin ? $user->getFirstname() : '<no first name>')
            ->setAdminLastname($user instanceof Admin ? $user->getLastname() : '<no last name>')
            ->setResource(AdminResources::PRICING)
            ->setResourceId($priceList->getId())
            ->setAction(AccessManager::VIEW)
            ->setMessage(\sprintf('Price list [%s] exported to CSV, %d line(s)', $priceList->getCode(), $lines))
            ->save();
    }
}
