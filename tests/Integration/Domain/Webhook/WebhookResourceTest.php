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

namespace Thelia\Tests\Integration\Domain\Webhook;

use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\ProfileResourceQuery;
use Thelia\Model\ResourceQuery;
use Thelia\Test\IntegrationTestCase;

final class WebhookResourceTest extends IntegrationTestCase
{
    public function testTheWebhookResourceExistsAndIsGrantedToNoProfile(): void
    {
        $resource = ResourceQuery::create()->findOneByCode(AdminResources::WEBHOOK, $this->getPropelConnection());

        self::assertNotNull($resource, 'The install creates the webhook resource.');
        self::assertSame(0, ProfileResourceQuery::create()->filterByResourceId($resource->getId())->count($this->getPropelConnection()));
    }
}
