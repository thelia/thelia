<?php
use Thelia\Core\Security\Token\UsernamePasswordToken;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\Security\Authentication\UsernamePasswordAuthenticator;
use Thelia\Core\Security\User\UserProvider\CustomerUserProvider;
use Thelia\Core\Security\Encoder\PasswordHashEncoder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AuthenticationProcessor {

	private $container;

	public function __construct(ContainerInterface $container) {
		$this->container = $container;
	}

	public function createToken(Request $request) {

		$context = $request->get('_context');

		try {
			$securityContext = $this->container->get("security.$context");

			$token = new UsernamePasswordToken(
				$request->get('_username'),
				$request->get('_password')
			);

			$securityContext->setToken($token);
		}
		catch (\Exception $ex) {
			// Nothing to do
		}
	}
}