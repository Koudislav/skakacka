<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;

class UserRepository {

	public const USERS_TABLE = 'users';

	public function __construct(
		private Explorer $db,
		private Passwords $passwords,
	) {}

	public function authenticate(string $email, string $password): ActiveRow {
		bdump($this->db->query('SELECT 1'));
		// bdump($this->passwords->hash($password), 'hashed password for debug');
		// die;
		$user = $this->db->table(self::USERS_TABLE)
			->where('email', $email)
			->fetch();

		if (!$user || !$this->passwords->verify($password, $user->password_hash)) {
			throw new AuthenticationException('Neplatné uživatelské jméno nebo heslo.');
		}

		if ($this->passwords->needsRehash($user->password_hash)) {
			$user->update(['password_hash' => $this->passwords->hash($password)]);
		}

		return $user;
	}

}
