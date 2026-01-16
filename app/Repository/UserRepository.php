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
		$user = $this->db->table(self::USERS_TABLE)
			->where('email', $email)
			->fetch();

		if (!$user || !$this->passwords->verify($password, $user->password_hash)) {
			throw new AuthenticationException('Neplatné uživatelské jméno nebo heslo.');
		}

		if ($user->is_active !== 1) {
			throw new AuthenticationException('Uživatel není aktivní.');
		}

		if ($this->passwords->needsRehash($user->password_hash)) {
			$user->update(['password_hash' => $this->passwords->hash($password)]);
		}

		return $user;
	}

	public function findAll() {
		return $this->db->table(self::USERS_TABLE);
	}

	public function getUserById(int $userId): ?ActiveRow {
		return $this->db->table(self::USERS_TABLE)
			->get($userId) ?: null;
	}

	public function verifyPasswordById(int $userId, string $password): bool {
		$user = $this->getUserById($userId);

		if (!$user) {
			return false;
		}

		return $this->passwords->verify($password, $user->password_hash);
	}

	public function setPassword(int $userId, string $password): void {
		$user = $this->getUserById($userId);

		if ($user) {
			$user->update(['password_hash' => $this->passwords->hash($password)]);
		}
	}

	public function updateUser(int $userId, \stdClass $values): void {
		$user = $this->getUserById($userId);

		if ($user) {
			$data = ['email' => $values->email];
			if (isset($values->role)) {
				$data['role'] = $values->role;
			}
			if (isset($values->is_active)) {
				$data['is_active'] = $values->is_active ? 1 : 0;
			}
			$user->update($data);
		}
	}

	public function createUser(\stdClass $values): void {
		$this->db->table(self::USERS_TABLE)->insert([
			'email' => $values->email,
			'password_hash' => $this->passwords->hash($values->password),
			'role' => $values->role,
			'is_active' => $values->is_active ? 1 : 0,
		]);
	}

}
