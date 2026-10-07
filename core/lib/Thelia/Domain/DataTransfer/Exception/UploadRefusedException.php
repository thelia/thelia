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

namespace Thelia\Domain\DataTransfer\Exception;

use Thelia\Exception\UserFacingFailure;
use Thelia\Form\Exception\FormValidationException;

/**
 * A file the shop refuses to import, and why: its name, its content, or what an
 * archive would hold once extracted. A form validation error, so a caller that
 * catches those still does.
 */
class UploadRefusedException extends FormValidationException implements UserFacingFailure
{
}
