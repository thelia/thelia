<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Core\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Admin;
use Thelia\Model\LangQuery;

class SecurityContextTest extends TestCase
{
    /** @var Session */
    protected $session;

    /** @var SecurityContext */
    protected $securityContext;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());

        $request = new Request();
        $request->setSession($this->session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->securityContext = new SecurityContext($requestStack);
    }

    public function testDeletedAdminNoLongerHasAValidSession(): void
    {
        $admin = new Admin();
        $admin
            ->setFirstname('thelia')
            ->setLastname('thelia')
            ->setLogin(uniqid('security_context_test'))
            ->setEmail(uniqid('security_context_test').'@example.com')
            ->setPassword('azerty')
            ->setLocale(LangQuery::create()->findOne()->getLocale())
        ;
        $admin->save();

        $this->securityContext->setAdminUser($admin);

        $this->assertTrue($this->securityContext->hasAdminUser());

        $admin->delete();

        // Simulate the next request: the session admin is unserialized afresh,
        // exactly as the session storage does between two requests.
        $staleAdmin = unserialize(serialize($this->session->getAdminUser()));
        $this->assertInstanceOf(Admin::class, $staleAdmin);
        $this->session->setAdminUser($staleAdmin);

        $this->assertNull($this->securityContext->getAdminUser());
        $this->assertFalse($this->securityContext->hasAdminUser());
    }
}
