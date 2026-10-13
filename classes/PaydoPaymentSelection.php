<?php

if (!defined('_PS_VERSION_') && !defined('PAYDO_TEST_MODE')) {
	exit;
}

final class PaydoPaymentSelection
{
	const HOSTED = 'hosted';

	/**
	 * Resolve and validate the checkout value without trusting browser input.
	 *
	 * @param bool $methodsMode
	 * @param bool $hostedPageEnabled
	 * @param mixed $selectedValue
	 * @param array $enabledMethodIds
	 *
	 * @return array{valid: bool, mode: string|null, payment_method: int|null}
	 */
	public static function resolve($methodsMode, $hostedPageEnabled, $selectedValue, array $enabledMethodIds)
	{
		if (!$methodsMode) {
			return [
				'valid' => true,
				'mode' => self::HOSTED,
				'payment_method' => null,
			];
		}

		$selectedValue = is_scalar($selectedValue) ? trim((string) $selectedValue) : '';
		if ($selectedValue === self::HOSTED) {
			return [
				'valid' => (bool) $hostedPageEnabled,
				'mode' => self::HOSTED,
				'payment_method' => null,
			];
		}

		$enabledMethodIds = array_values(array_unique(array_map('strval', $enabledMethodIds)));
		if (
			$selectedValue === ''
			|| !ctype_digit($selectedValue)
			|| (int) $selectedValue <= 0
			|| !in_array($selectedValue, $enabledMethodIds, true)
		) {
			return [
				'valid' => false,
				'mode' => null,
				'payment_method' => null,
			];
		}

		return [
			'valid' => true,
			'mode' => $selectedValue,
			'payment_method' => (int) $selectedValue,
		];
	}

	/**
	 * Generate a signature from the exact amount and currency strings sent in JSON.
	 *
	 * @param string $orderId
	 * @param string $amount
	 * @param string $currency
	 * @param string $secretKey
	 *
	 * @return string
	 */
	public static function signature($orderId, $amount, $currency, $secretKey)
	{
		return hash('sha256', implode(':', [
			(string) $amount,
			(string) $currency,
			(string) $orderId,
			(string) $secretKey,
		]));
	}

	/**
	 * Add paymentMethod only for a validated direct-redirection selection.
	 *
	 * @param array $payload
	 * @param array $selection
	 *
	 * @return array
	 */
	public static function applyToPayload(array $payload, array $selection)
	{
		if (isset($selection['payment_method']) && is_int($selection['payment_method']) && $selection['payment_method'] > 0) {
			$payload['paymentMethod'] = $selection['payment_method'];
		}

		return $payload;
	}
}
