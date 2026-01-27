<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Security\Authenticator;
use Nette\Security\SimpleIdentity;
use App\Repository\UserRepository;

class UserAuthenticator implements Authenticator {

	public function __construct(
		private UserRepository $userRepository
	) {}

	public function authenticate(string $email, string $password): SimpleIdentity {
		$user = $this->userRepository->authenticate($email, $password);
		return $this->createIdentity($user);
	}

	public function createIdentity($user): SimpleIdentity {
		if ($user->role === 'owner') {
			$roles = ['admin', 'superadmin', 'owner'];
		} else {
			$roles = [$user->role];
		}
		return new SimpleIdentity($user->id, $roles, $user);
	}

}
