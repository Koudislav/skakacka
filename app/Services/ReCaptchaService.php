<?php

declare(strict_types=1);

namespace App\Service;

use ReCaptcha\ReCaptcha;
use App\Model\Config;

class ReCaptchaService {

	public function __construct(
		private float $threshold,
		private Config $config,
	) {}

	public function verify(string $token, ?string $ip = null): bool {
		if ($token === '') {
			return false;
		}
		$recaptcha = new ReCaptcha($this->config['recaptcha_secret']);

		$response = $recaptcha->verify($token, $ip);

		if (!$response->isSuccess()) {
			return false;
		}

		$score = $response->getScore();
		return $score !== null && $score >= $this->threshold;
	}

}