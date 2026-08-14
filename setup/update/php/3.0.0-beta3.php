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

$pdo = $database->getConnection();

// Legal identifiers used to be typed in a single free-text key, `store_business_id`.
// 3.0.0-beta3 gives each of them its own key, so the value has to be routed: most shops
// typed a SIRET there, the others some free legal wording. `store_business_id` itself is
// left untouched, both as a safety net and because the PDF templates fall back to it.
//
// Nothing is ever overwritten, so replaying this script changes nothing.

$businessId = $pdo
    ->query("SELECT `value` FROM `config` WHERE `name` = 'store_business_id'")
    ->fetchColumn();

if (!is_string($businessId) || trim($businessId) === '') {
    return;
}

// Same checksum as BackOfficeDefaultTwigBundle\Form\Configuration\ConfigStoreType::isValidSiret().
// Duplicated on purpose: update scripts are plain PHP and run outside the back-office bundle.
$isValidSiret = static function (string $siret): bool {
    if (preg_match('/^[0-9]{14}$/', $siret) !== 1) {
        return false;
    }

    // La Poste establishments are numbered outside the Luhn scheme, only their length holds.
    if (str_starts_with($siret, '356000000')) {
        return true;
    }

    $checksum = 0;
    foreach (str_split(strrev($siret)) as $rank => $character) {
        $digit = (int) $character;

        if ($rank % 2 === 1) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }

        $checksum += $digit;
    }

    return $checksum % 10 === 0;
};

$digits = (string) preg_replace('/\D/', '', $businessId);

[$targetKey, $targetValue] = $isValidSiret($digits)
    ? ['store_siret', $digits]
    : ['store_legal_mentions', mb_substr($businessId, 0, 500)];

$pdo
    ->prepare(
        "UPDATE `config` SET `value` = :value, `updated_at` = NOW()
         WHERE `name` = :name AND (`value` IS NULL OR `value` = '')",
    )
    ->execute(['value' => $targetValue, 'name' => $targetKey]);
