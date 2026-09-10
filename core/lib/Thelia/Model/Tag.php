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

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\Tag as BaseTag;

class Tag extends BaseTag
{
    public function preSave(?ConnectionInterface $con = null): bool
    {
        // The unique index on `label` carries the de-duplication, and under
        // utf8mb4_general_ci it already folds case and Latin accents: "vip" and
        // "Salón" collide with "VIP" and "Salon" without a line of PHP. What the
        // collation does not fold is whitespace inside the label, so "VIP  club"
        // and "VIP club" would become two tags. Collapsing it here rather than in
        // a form type keeps the invariant true for the API, the fixtures and the
        // import alike.
        $this->setLabel(self::normalizeLabel($this->getLabel() ?? ''));

        return parent::preSave($con);
    }

    /**
     * Trims the label and reduces every run of whitespace inside it to a single
     * space. Case and accents are deliberately left alone: they are the reader's
     * spelling, and the index compares them as equal anyway.
     */
    public static function normalizeLabel(string $label): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $label));
    }
}
