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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateService;
use Thelia\Core\Template\TheliaTemplateHelper;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The email template of a shop lives in the `active-mail-template` config row: that is
 * what the seed creates and what every database carries since 2.0.0-beta2. The row is
 * spelled out here on purpose — the code side is free to rename its constant, the stored
 * name is not.
 *
 * `template:set email <name>` writes TemplateDefinition::CONFIG_NAMES['email'], and the
 * mailer, the hook parser and the template path resolver read the active email template.
 * Both ends have to land on that row, or the command changes a variable nothing looks up
 * and the shop keeps sending the emails of the template it was told to leave.
 */
final class ActiveEmailTemplateConfigTest extends IntegrationTestCase
{
    private const STORED_CONFIG_NAME = 'active-mail-template';
    private const ANOTHER_TEMPLATE = 'an-email-template-of-its-own';

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheCommandWritesTheRowTheShopStoresItsEmailTemplateIn(): void
    {
        ConfigQuery::write(TemplateDefinition::CONFIG_NAMES[TemplateDefinition::EMAIL_SUBDIR], self::ANOTHER_TEMPLATE);

        self::assertSame(self::ANOTHER_TEMPLATE, ConfigQuery::read(self::STORED_CONFIG_NAME));
    }

    public function testTheTemplateHelperReadsTheStoredEmailTemplate(): void
    {
        $templateHelper = $this->getService(TheliaTemplateHelper::class);
        $activeTemplate = new TemplateDefinition(
            ConfigQuery::read(self::STORED_CONFIG_NAME, 'default'),
            TemplateDefinition::EMAIL,
        );

        self::assertTrue($templateHelper->isActive($activeTemplate));

        ConfigQuery::write(self::STORED_CONFIG_NAME, self::ANOTHER_TEMPLATE);

        self::assertFalse($templateHelper->isActive($activeTemplate));
    }

    public function testTheEmailTemplatePathFollowsTheStoredEmailTemplate(): void
    {
        ConfigQuery::write(self::STORED_CONFIG_NAME, self::ANOTHER_TEMPLATE);

        self::assertSame(
            THELIA_TEMPLATE_DIR.TemplateDefinition::EMAIL_SUBDIR.\DIRECTORY_SEPARATOR.self::ANOTHER_TEMPLATE,
            TemplateService::getTemplateAbsolutePathByType(TemplateDefinition::EMAIL_SUBDIR),
        );
    }
}
