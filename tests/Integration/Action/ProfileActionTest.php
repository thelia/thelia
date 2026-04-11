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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Profile\ProfileEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ProfileQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class ProfileActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsProfileWithUniqueCode(): void
    {
        $event = new ProfileEvent();
        $event
            ->setCode('test-manager')
            ->setLocale('en_US')
            ->setTitle('Test Manager')
            ->setChapo('')
            ->setDescription('')
            ->setPostscriptum('');

        $this->dispatch($event, TheliaEvents::PROFILE_CREATE);

        $profile = $event->getProfile();
        self::assertNotNull($profile);
        self::assertSame('test-manager', $profile->getCode());
        self::assertSame('Test Manager', $profile->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesI18nFields(): void
    {
        $profile = $this->factory->profile(['code' => 'tmp-editor', 'title' => 'Old']);

        $event = new ProfileEvent($profile);
        $event
            ->setId($profile->getId())
            ->setCode('tmp-editor')
            ->setLocale('en_US')
            ->setTitle('Updated')
            ->setChapo('')
            ->setDescription('')
            ->setPostscriptum('');

        $this->dispatch($event, TheliaEvents::PROFILE_UPDATE);

        self::assertSame(
            'Updated',
            ProfileQuery::create()->findPk($profile->getId())->setLocale('en_US')->getTitle(),
        );
    }

    public function testDeleteRemovesProfile(): void
    {
        $profile = $this->factory->profile(['code' => 'tmp-delete']);
        $profileId = $profile->getId();

        $event = new ProfileEvent($profile);
        $event->setId($profileId);

        $this->dispatch($event, TheliaEvents::PROFILE_DELETE);

        self::assertNull(ProfileQuery::create()->findPk($profileId));
    }
}
