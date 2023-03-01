<?php

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

class JwtListener implements EventSubscriberInterface
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof ActiveRecordInterface) {
            return;
        }

        $payload = $event->getData();
        $payload['type'] = $user::class;

        $event->setData($payload);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_CREATED => 'onJWTCreated'
        ];
    }
}
