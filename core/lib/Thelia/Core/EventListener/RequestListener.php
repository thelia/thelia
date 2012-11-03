<?php

namespace Thelia\Core\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RequestListener implements EventSubscriberInterface
{
   protected $container;

   public function __construct(ContainerInterface $container)
   {
       $this->container = $container;
   }

   public function onKernelRequest(GetResponseEvent $event)
   {
   }

   public static function getSubscribedEvents()
   {
       return array(
        KernelEvents::REQUEST => array('onKernelRequest', 30)
       );
   }
}
