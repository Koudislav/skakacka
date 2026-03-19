<?php

declare(strict_types=1);

namespace App\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Nette\Security\Passwords;

class UserRepository {

	public const USERS_TABLE = 'users';

	public const ERROR_EMAIL_NOT_VERIFIED = 2;

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

		if ($user->email_verified_at === null) {
			throw new AuthenticationException('Email není ověřen. Zkontrolujte svou emailovou schránku pro ověřovací odkaz.', self::ERROR_EMAIL_NOT_VERIFIED);
		}

		if ($this->passwords->needsRehash($user->password_hash)) {
			$user->update(['password_hash' => $this->passwords->hash($password)]);
		}

		return $user;
	}

	public function findAll() {
		return $this->db->table(self::USERS_TABLE);
	}

	public function getById(int $userId): ?ActiveRow {
		return $this->db->table(self::USERS_TABLE)
			->get($userId) ?: null;
	}

	public function getByEmail(string $email): ?ActiveRow {
		return $this->db->table(self::USERS_TABLE)
			->where('email', $email)
			->fetch() ?: null;
	}

	public function verifyPasswordById(int $userId, string $password): bool {
		$user = $this->getById($userId);

		if (!$user) {
			return false;
		}

		return $this->passwords->verify($password, $user->password_hash);
	}

	public function setPassword(int $userId, string $password): void {
		$user = $this->getById($userId);

		if ($user) {
			$user->update(['password_hash' => $this->passwords->hash($password)]);
		}
	}

	public function updateUser(int $userId, \stdClass $values): void {
		$user = $this->getById($userId);
		if ($user) {
			$data = [];
			if (!empty($values->email) && $user->email !== $values->email) {
				$data['email'] = $values->email;
				$data['email_verified_at'] = null;
				$data['email_verification_token'] = null;
				$data['email_verification_expires_at'] = null;
			}
			if (isset($values->role)) {
				$data['role'] = $values->role;
			}
			if (isset($values->is_active)) {
				$data['is_active'] = $values->is_active ? 1 : 0;
			}
			if (!empty($data)) {
				$user->update($data);
			}
		}
	}

	public function createUser(\stdClass $values): ActiveRow {
		return $this->db->table(self::USERS_TABLE)->insert([
			'email' => $values->email,
			'password_hash' => $this->passwords->hash($values->password),
			'role' => $values->role,
			'is_active' => $values->is_active ? 1 : 0,
		]);
	}

	public function generateEmailVerification(int $userId): string {
		$token = bin2hex(random_bytes(32)); // 64 znaků
		$expiresAt = (new \DateTime('+24 hours'))->format('Y-m-d H:i:s');

		$this->db->table(self::USERS_TABLE)
			->where('id', $userId)
			->update([
				'email_verification_token' => $token,
				'email_verification_expires_at' => $expiresAt,
				'email_verified_at' => null,
			]);
		return $token;
	}

	public function getByVerificationToken(string $token): ?ActiveRow {
		return $this->db->table(self::USERS_TABLE)
			->where('email_verification_token', $token)
			->fetch() ?: null;
	}

	public function markEmailAsVerified(int $userId): void {
		$this->db->table(self::USERS_TABLE)
			->where('id', $userId)
			->update([
				'email_verified_at' => new \DateTime(),
				'email_verification_token' => null,
				'email_verification_expires_at' => null,
			]);
	}

	public function update(int $userId, array $data): void {
		$this->db->table(self::USERS_TABLE)
			->where('id', $userId)
			->update($data);
	}

}
