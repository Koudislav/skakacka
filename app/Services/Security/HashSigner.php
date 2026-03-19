<?php

declare(strict_types=1);

namespace App\Services\Security;

class HashSigner {

	public function __construct(
		private string $secret
	) {}

	public function sign(string $data): string {
		return hash_hmac('sha256', $data, $this->secret);
	}

	public function verify(string $data, string $token): bool {
		return hash_equals($this->sign($data), $token);
	}

}
