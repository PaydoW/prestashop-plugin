<?php

class PaydoValidationModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	public function postProcess()
	{
		$cart = $this->context->cart;
		$cartId = (int) $cart->id;
		if (!$this->module->active || !$cartId || !$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice) {
			Tools::redirect($this->context->link->getPageLink('order', true, null, ['step' => 1]));
		}

		$customer = new Customer((int) $cart->id_customer);
		if (!Validate::isLoadedObject($customer)) {
			Tools::redirect($this->context->link->getPageLink('order', true, null, ['step' => 1]));
		}

		$selection = $this->module->resolvePaymentSelection(Tools::getValue('paydo_method'));
		if (empty($selection['valid'])) {
			$this->redirectToCheckoutWithError($this->module->l('The selected PayDo payment method is unavailable. Please choose another method.'));
		}

		$orderId = $this->getOrCreateOrderId($cart, $customer);
		if (!$orderId) {
			$this->redirectToFailure($cartId);
		}

		$language = strtolower(substr((string) (Configuration::get('PAYDO_LANGUAGE') ?: 'en'), 0, 2));
		if (!preg_match('/^[a-z]{2}$/', $language)) {
			$language = 'en';
		}

		$existing = $this->module->getPaydoTransactionByOrderId($orderId);
		if (!$existing) {
			$existing = $this->module->getPaydoTransactionByCartId($cartId);
		}
		if ($existing && $this->isValidInvoiceId($existing['invoice_id'])) {
			$terminalState = isset($existing['transaction_state']) ? (int) $existing['transaction_state'] : 0;
			if ((int) $existing['invoice_status'] === 1 || in_array($terminalState, [3, 5], true)) {
				Tools::redirect($this->context->link->getModuleLink($this->module->name, 'createOrder', [], true));
			}
			$this->redirectToPaydoCheckout($language, $existing['invoice_id']);
		}

		$amount = number_format((float) $cart->getOrderTotal(true, Cart::BOTH), 2, '.', '');
		$currencyData = Currency::getCurrency((int) $cart->id_currency);
		$currency = isset($currencyData['iso_code']) ? strtoupper((string) $currencyData['iso_code']) : '';
		if (!preg_match('/^[A-Z]{3}$/', $currency)) {
			$this->redirectToFailure($cartId);
		}

		$items = [];
		foreach ($cart->getProducts() as $product) {
			$items[] = [
				'id' => (string) $product['id_product'],
				'name' => (string) $product['name'],
				'price' => number_format((float) $product['price_wt'], 2, '.', ''),
				'quantity' => (int) $product['cart_quantity'],
			];
		}

		$address = new Address((int) $cart->id_address_delivery);
		$request = [
			'publicKey' => trim((string) Configuration::get('PAYDO_PUBLIC_KEY')),
			'order' => [
				'id' => (string) $orderId,
				'amount' => $amount,
				'currency' => $currency,
				'description' => 'Payment order #' . $orderId,
				'items' => $items,
			],
			'payer' => [
				'email' => (string) $customer->email,
				'name' => trim((string) $customer->firstname . ' ' . (string) $customer->lastname),
				'phone' => Validate::isLoadedObject($address) ? (string) ($address->phone ?: $address->phone_mobile) : '',
				'extraFields' => ['date_of_birth' => (string) ($customer->birthday ?: '')],
			],
			'productUrl' => $this->context->link->getPageLink('index', true),
			'resultUrl' => $this->context->link->getModuleLink($this->module->name, 'createOrder', [], true),
			'failPath' => $this->context->link->getModuleLink($this->module->name, 'failPage', [], true),
			'signature' => PaydoPaymentSelection::signature((string) $orderId, $amount, $currency, (string) Configuration::get('PAYDO_SECRET_KEY')),
			'language' => $language,
		];

		$request = PaydoPaymentSelection::applyToPayload($request, $selection);

		$invoiceId = $this->createPaydoInvoice($request);
		if (!$invoiceId) {
			$this->redirectToFailure($cartId);
		}

		if (!$this->module->savePaydoInvoice($cartId, $orderId, $invoiceId, (string) $selection['mode'], $amount, $currency)) {
			PrestaShopLogger::addLog('[Paydo] Invoice storage failed for order ' . (int) $orderId, 3);
			$this->redirectToFailure($cartId);
		}

		$this->redirectToPaydoCheckout($language, $invoiceId);
	}

	private function createPaydoInvoice(array $request)
	{
		if (!function_exists('curl_init')) {
			PrestaShopLogger::addLog('[Paydo] PHP cURL is unavailable during invoice creation', 3);
			return false;
		}

		$payload = json_encode($request);
		if (!is_string($payload)) {
			return false;
		}

		$ch = curl_init('https://api.paydo.com/v3/invoices/create');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/json']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$body = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$transportFailed = curl_errno($ch) !== 0;
		curl_close($ch);

		if ($transportFailed || $httpCode < 200 || $httpCode >= 300 || !is_string($body)) {
			PrestaShopLogger::addLog(sprintf('[Paydo] Invoice creation failed (HTTP %d)', $httpCode), 3);
			return false;
		}

		$response = json_decode($body, true);
		$invoiceId = is_array($response) && isset($response['data']) ? $response['data'] : null;
		return $this->isValidInvoiceId($invoiceId) ? (string) $invoiceId : false;
	}

	private function getOrCreateOrderId(Cart $cart, Customer $customer)
	{
		$cartId = (int) $cart->id;
		$orderId = (int) Order::getIdByCartId($cartId);
		if ($orderId) {
			return $orderId;
		}

		$pendingState = (int) Configuration::get('PS_OS_PAYDO_PENDING_STATE');
		if (!$pendingState) {
			$pendingState = (int) Configuration::get('PS_OS_PREPARATION');
		}

		if (!$cart->orderExists()) {
			$this->module->validateOrder($cartId, $pendingState, (float) $cart->getOrderTotal(true, Cart::BOTH), $this->module->displayName, null, null, (int) $cart->id_currency, false, (string) $customer->secure_key);
		}

		$orderId = (int) $this->module->currentOrder;
		return $orderId ?: (int) Order::getIdByCartId($cartId);
	}

	private function redirectToCheckoutWithError($message)
	{
		$this->context->cookie->paydo_payment_error = (string) $message;
		$this->context->cookie->write();
		Tools::redirect($this->context->link->getPageLink('order', true, null, ['step' => 3]));
	}

	private function redirectToFailure($cartId)
	{
		Tools::redirect($this->context->link->getModuleLink($this->module->name, 'failPage', ['cart_id' => (int) $cartId], true));
	}

	private function redirectToPaydoCheckout($language, $invoiceId)
	{
		Tools::redirect('https://checkout.paydo.com/' . rawurlencode($language) . '/payment/invoice-preprocessing/' . rawurlencode($invoiceId));
	}

	private function isValidInvoiceId($invoiceId)
	{
		return is_string($invoiceId) && (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $invoiceId);
	}
}
