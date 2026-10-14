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

namespace Thelia\Tests\Support\Api;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Thelia\Api\Resource\Customer;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\ResourceAddonTrait;

/**
 * A module extension of the customer resource with one field the shop writes
 * (`accountManager`, administration only) and one the customer writes (`note`).
 * It records what it was asked to save instead of saving it.
 */
final class RecordingCustomerAddon implements ResourceAddonInterface
{
    use ResourceAddonTrait;

    /** @var list<array<string, mixed>> */
    public static array $saved = [];

    #[Groups([Customer::GROUP_ADMIN_READ_SINGLE, Customer::GROUP_ADMIN_WRITE])]
    public ?string $accountManager = null;

    #[Groups([Customer::GROUP_ADMIN_WRITE, Customer::GROUP_FRONT_READ_SINGLE, Customer::GROUP_FRONT_WRITE])]
    public ?string $note = null;

    private array $received = [];

    public static function getResourceParent(): string
    {
        return Customer::class;
    }

    public static function extendQuery(ModelCriteria $query, ?Operation $operation = null, array $context = []): void
    {
    }

    public function buildFromModel(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        return $this;
    }

    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        $this->received = $data;
        $this->accountManager = $data['accountManager'] ?? null;
        $this->note = $data['note'] ?? null;

        return $this;
    }

    public function doSave(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        self::$saved[] = $this->received;
    }

    public function doDelete(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
    }
}
