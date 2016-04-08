
UPGRADE FROM 2.2 to 2.3
=======================


EventDispatcher
----------------

 * The `getDispatcher()` and `getName()` methods from `Symfony\Component\EventDispatcher\Event`
   are deprecated, the event dispatcher instance and event name can be received in the listener call instead.

    Before:

    ```php
    use Symfony\Component\EventDispatcher\Event;

    class Foo
    {
        public function myFooListener(Event $event)
        {
            $dispatcher = $event->getDispatcher();
            $eventName = $event->getName();
            $dispatcher->dispatch('log', $event);

            // ... more code
       }
    }
    ```

    After:

    ```php
    use Symfony\Component\EventDispatcher\Event;
    use Symfony\Component\EventDispatcher\EventDispatcherInterface;

    class MyListenerClass
    {
        public function myListenerMethod(Event $event, $eventName, EventDispatcherInterface $dispatcher)
        {
            $dispatcher->dispatch('log', $event);

            // ... more code
        }
    }
    ```

    While this above is sufficient for most uses, **if your module must be compatible with versions less than 2.3, or if your module uses multiple EventDispatcher instances,** you might need to specifically inject a known instance of the `EventDispatcher` into your listeners. This could be done using constructor or setter injection as follows:

    ```php
    use Symfony\Component\EventDispatcher\EventDispatcherInterface;

    class MyListenerClass
    {
        protected $dispatcher = null;

        public function __construct(EventDispatcherInterface $dispatcher)
        {
            $this->dispatcher = $dispatcher;
        }
    }
    ```

Request and RequestStack
----------------

 * The `Request` service are deprecated, you must now use the `RequestStack` service.

    ##### In your loops
    The way to recover the request does not change.

    To get the current request

    ```php
    class MyLoopClass extends BaseLoop implements PropelSearchLoopInterface
    {
        public function buildModelCriteria()
        {
            // Get the current request
            $request = $this->getCurrentRequest();
            // Or
            $request = $this->requestStack->getCurrentRequest();

            // ... more code
        }
    }
    ```

    ##### In your controllers
    It's not recommended to use `getRequest()` and `getSession()`, the Request instance can be received in the action method parameters.
    However, the `getRequest()` method returns the current request.
    **Warning !!** This is not compatible with Thelia 2.0, because it uses Symfony 2.2

    To get the current request

    ```php
    use Thelia\Core\HttpFoundation\Request;

    class MyControllerClass extends ...
    {
        public function MyActionMethod(Request $request, $query_parameters ...)
        {
            $session = $request->getSession();
            // ... more code
        }
    }
    ```

Container Scopes
----------------

 * The "container scopes" concept no longer exists in Thelia 2.3.
    For backward compatibility, the attributes `scope` is automatically removed of the xml configuration files.  
    **Warning !!** The attributes `scope` are always needed for your modules compatible with Thelia < 2.3.  
    [See the Symfony documentation for more information](http://symfony.com/doc/2.8/cookbook/service_container/scopes.html)


Unit Test
----------------

 * The `SecurityContext`, `ParserContext`, `TokenProvider`, `TheliaFormFactory`, `TaxEngine` services are no longer dependent on "Request", but "RequestSTack".  
    This may break your unit tests.

For more information about the upgrade from Symfony 2.3 to Symfony 2.8
----------------

[Upgrade from Symfony 2.3 to 2.4](https://github.com/symfony/symfony/blob/2.8/UPGRADE-2.4.md)  
[Upgrade from Symfony 2.4 to 2.5](https://github.com/symfony/symfony/blob/2.8/UPGRADE-2.5.md)  
[Upgrade from Symfony 2.5 to 2.6](https://github.com/symfony/symfony/blob/2.8/UPGRADE-2.6.md)  
[Upgrade from Symfony 2.6 to 2.7](https://github.com/symfony/symfony/blob/2.8/UPGRADE-2.7.md)  
[Upgrade from Symfony 2.7 to 2.8](https://github.com/symfony/symfony/blob/2.8/UPGRADE-2.8.md)  
[Upgrade from Symfony 2.8 to 3.0](https://github.com/symfony/symfony/blob/2.8/UPGRADE-3.0.md)  
