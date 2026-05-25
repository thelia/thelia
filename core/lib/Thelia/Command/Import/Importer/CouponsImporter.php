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

namespace Thelia\Command\Import\Importer;

use Thelia\Command\Import\AbstractDemoImporter;
use Thelia\Command\Import\DemoImportContext;
use Thelia\Model\Coupon;

final class CouponsImporter extends AbstractDemoImporter
{
    private const TYPE_PERCENT = 'thelia.coupon.type.remove_x_percent';
    private const TYPE_AMOUNT = 'thelia.coupon.type.remove_x_amount';

    public function priority(): int
    {
        return 110;
    }

    public function description(): string
    {
        return 'Coupons';
    }

    public function import(DemoImportContext $context): void
    {
        $nextYear = new \DateTime('+1 year');

        $this->createCoupon($context, 'WELCOME10', self::TYPE_PERCENT, ['percentage' => 10], 'Bienvenue : 10% de réduction', 'Welcome: 10% off', $nextYear, true, false);
        $this->createCoupon($context, 'SUMMER15', self::TYPE_PERCENT, ['percentage' => 15], 'Offre été : 15% de réduction', 'Summer sale: 15% off', $nextYear, true, false);
        $this->createCoupon($context, 'THANKYOU5', self::TYPE_AMOUNT, ['amount' => 5], 'Merci : 5 € offerts', 'Thank you: 5€ off', $nextYear, true, false);
        $this->createCoupon($context, 'FREESHIP', self::TYPE_AMOUNT, ['amount' => 0], 'Livraison offerte', 'Free shipping', $nextYear, true, true);
        $this->createCoupon($context, 'EXPIRED20', self::TYPE_PERCENT, ['percentage' => 20], 'Offre expirée : 20%', 'Expired offer: 20% off', new \DateTime('-1 month'), false, false);
    }

    /**
     * @param array<string, int> $effects
     */
    private function createCoupon(
        DemoImportContext $context,
        string $code,
        string $type,
        array $effects,
        string $titleFr,
        string $titleEn,
        \DateTimeInterface $expiration,
        bool $enabled,
        bool $removePostage,
    ): void {
        $coupon = new Coupon();
        $coupon->setCode($code);
        $coupon->setType($type);
        $coupon->setSerializedEffects(json_encode($effects, \JSON_THROW_ON_ERROR));
        $coupon->setIsEnabled($enabled);
        $coupon->setExpirationDate($expiration);
        $coupon->setMaxUsage(Coupon::UNLIMITED_COUPON_USE);
        $coupon->setIsCumulative(false);
        $coupon->setIsRemovingPostage($removePostage);
        $coupon->setIsAvailableOnSpecialOffers(true);
        $coupon->setIsUsed(false);
        $coupon->setPerCustomerUsageCount(false);
        $coupon->setSerializedConditions('');
        $coupon
            ->setLocale('fr_FR')->setTitle($titleFr)->setShortDescription('')->setDescription('')
            ->setLocale('en_US')->setTitle($titleEn)->setShortDescription('')->setDescription('');
        $coupon->save($context->connection);
    }
}
