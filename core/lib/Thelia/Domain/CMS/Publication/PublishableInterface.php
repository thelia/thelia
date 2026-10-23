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

namespace Thelia\Domain\CMS\Publication;

use Propel\Runtime\Connection\ConnectionInterface;

/**
 * What the publication service needs of a row that can be published: the contents and
 * the folders, whose generated models already carry these methods.
 */
interface PublishableInterface
{
    public function getId(): ?int;

    public function getVisible(): ?int;

    public function setVisible(?int $v = null): static;

    public function getPublishAt(?string $format = null): string|\DateTimeInterface|null;

    public function setPublishAt(string|int|\DateTimeInterface|null $v = null): static;

    public function getUnpublishAt(?string $format = null): string|\DateTimeInterface|null;

    public function setUnpublishAt(string|int|\DateTimeInterface|null $v = null): static;

    public function save(?ConnectionInterface $con = null): int;
}
