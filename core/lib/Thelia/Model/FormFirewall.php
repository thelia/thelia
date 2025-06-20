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

use Thelia\Model\Base\FormFirewall as BaseFormFirewall;

class FormFirewall extends BaseFormFirewall
{
    public function resetAttempts()
    {
        $this->setAttempts(0)->save();

        return $this;
    }

    public function incrementAttempts()
    {
        $this->setAttempts(
            $this->getAttempts() + 1
        );

        $this->save();

        return $this;
    }
}
