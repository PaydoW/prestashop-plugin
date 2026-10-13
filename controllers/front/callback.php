<?php

class PaydoCallbackModuleFrontController extends ModuleFrontController
{
	private const MAX_VERIFICATION_ATTEMPTS = 3;

	public function initContent()
	{
		parent::initContent();
		$this->processCallback();
	}

	private function processCallback()
	{
		if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
			$this->respond(405, 'METHOD_NOT_ALLOWED');
		}

		$payload = json_decode((string) file_get_contents('php://input'), true);
		if (!is_array($payload)) {
			$this->respond(400, 'INVALID_JSON');
		}

		$orderId = isset($payload['transaction']['order']['id']) ? (int) $payload['transaction']['order']['id'] : 0;
		$invoiceId = isset($payload['invoice']['id']) ? trim((string) $payload['invoice']['id']) : '';
		$ipnTxid = $this->extractIpnTransactionId($payload);
		$transactionState = isset($payload['transaction']['state']) && is_numeric($payload['transaction']['state'])
			? (int) $payload['transaction']['state']
			: null;

		if (!$orderId || !$this->isValidInvoiceId($invoiceId)) {
			$this->respond(400, 'INVALID_CALLBACK');
		}

		$order = new Order($orderId);
		if (!Validate::isLoadedObject($order)) {
			$this->respond(404, 'ORDER_NOT_FOUND');
		}
		if ((string) $order->module !== 'paydo') {
			$this->respond(403, 'INVALID_PAYMENT_MODULE');
		}

		$stored = $this->module->getPaydoTransactionByOrderId($orderId);
		if (!$stored || (string) $stored['invoice_id'] !== $invoiceId || (int) $stored['cart_id'] !== (int) $order->id_cart) {
			$this->respond(403, 'INVOICE_MISMATCH');
		}
		if (!empty($stored['transaction_id']) && $ipnTxid !== '' && (string) $stored['transaction_id'] !== $ipnTxid) {
			$this->respond(403, 'TRANSACTION_MISMATCH');
		}

		$invoice = false;
		for ($attempt = 1; $attempt <= self::MAX_VERIFICATION_ATTEMPTS; $attempt++) {
			$currentInvoice = $this->getPaydoInvoice($invoiceId);
			if ($currentInvoice) {
				$invoice = $currentInvoice;
			}
			$this->module->updatePaydoTransaction($orderId, [
				'verification_attempts' => $attempt,
				'last_verification_at' => date('Y-m-d H:i:s'),
			]);

			if ($invoice && isset($invoice['status']) && (int) $invoice['status'] === 1) {
				break;
			}
			if ($attempt < self::MAX_VERIFICATION_ATTEMPTS) {
				usleep($attempt * 500000);
			}
		}

		if (!$invoice) {
			PrestaShopLogger::addLog('[Paydo] Invoice verification temporarily unavailable for order ' . $orderId, 2);
			$this->respond(503, 'VERIFICATION_UNAVAILABLE');
		}

		$validation = $this->validateInvoice($invoice, $order, $stored, $invoiceId, $ipnTxid);
		if (!$validation['valid']) {
			PrestaShopLogger::addLog('[Paydo] Invoice verification rejected for order ' . $orderId . ': ' . $validation['reason'], 3);
			$this->respond(403, 'VERIFICATION_FAILED');
		}

		$invoiceStatus = (int) $invoice['status'];
		$invoiceTxid = $validation['transaction_id'];
		$this->module->updatePaydoTransaction($orderId, [
			'transaction_id' => $invoiceTxid,
			'invoice_status' => $invoiceStatus,
			'transaction_state' => $transactionState,
		]);

		if ($invoiceStatus === 1) {
			$this->markOrderPaid($order);
			$this->respond(200, 'PAID');
		}
		if ($this->hasOrderReachedState((int) $order->id, (int) Configuration::get('PS_OS_PAYMENT'))) {
			$this->respond(200, 'ALREADY_PAID');
		}

		if ($transactionState !== null && in_array($transactionState, [3, 5], true)) {
			$this->changeOrderStateOnce($order, (int) Configuration::get('PS_OS_ERROR'), true);
			$this->respond(200, 'FAILED');
		}

		$this->changeOrderStateOnce($order, (int) Configuration::get('PS_OS_PAYDO_PENDING_STATE'), false);
		PrestaShopLogger::addLog('[Paydo] Payment still pending after bounded invoice verification for order ' . $orderId, 2);
		$this->respond(200, 'PENDING_MANUAL_REVIEW');
	}

	private function getPaydoInvoice($invoiceId)
	{
		if (!function_exists('curl_init')) {
			return false;
		}

		$ch = curl_init('https://api.paydo.com/v3/invoices/' . rawurlencode($invoiceId));
		curl_setopt($ch, CURLOPT_HTTPGET, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 12);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$body = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$transportFailed = curl_errno($ch) !== 0;
		curl_close($ch);

		if ($transportFailed || $httpCode !== 200 || !is_string($body)) {
			return false;
		}

		$response = json_decode($body, true);
		return is_array($response) && isset($response['data']) && is_array($response['data'])
			? $response['data']
			: false;
	}

	private function validateInvoice(array $invoice, Order $order, array $stored, $invoiceId, $ipnTxid)
	{
		foreach (['identifier', 'status', 'orderIdentifier', 'amount', 'currency'] as $requiredField) {
			if (!array_key_exists($requiredField, $invoice)) {
				return ['valid' => false, 'reason' => 'missing_' . $requiredField, 'transaction_id' => ''];
			}
		}

		if ((string) $invoice['identifier'] !== (string) $invoiceId) {
			return ['valid' => false, 'reason' => 'invoice_id', 'transaction_id' => ''];
		}
		if ((string) $invoice['orderIdentifier'] !== (string) $order->id) {
			return ['valid' => false, 'reason' => 'order_id', 'transaction_id' => ''];
		}

		$expectedAmount = number_format((float) $order->total_paid, 4, '.', '');
		$storedAmount = number_format((float) $stored['amount'], 4, '.', '');
		$invoiceAmount = number_format((float) $invoice['amount'], 4, '.', '');
		if ($invoiceAmount !== $expectedAmount || $invoiceAmount !== $storedAmount) {
			return ['valid' => false, 'reason' => 'amount', 'transaction_id' => ''];
		}

		$currency = new Currency((int) $order->id_currency);
		$expectedCurrency = Validate::isLoadedObject($currency) ? strtoupper((string) $currency->iso_code) : '';
		$invoiceCurrency = strtoupper((string) $invoice['currency']);
		$storedCurrency = strtoupper((string) $stored['currency']);
		if ($expectedCurrency === '' || $invoiceCurrency !== $expectedCurrency || $invoiceCurrency !== $storedCurrency) {
			return ['valid' => false, 'reason' => 'currency', 'transaction_id' => ''];
		}

		$invoiceTxid = isset($invoice['transactionIdentifier']) ? trim((string) $invoice['transactionIdentifier']) : '';
		if ($ipnTxid !== '' && $invoiceTxid !== '' && !hash_equals($invoiceTxid, $ipnTxid)) {
			return ['valid' => false, 'reason' => 'transaction_id', 'transaction_id' => ''];
		}
		if ((int) $invoice['status'] === 1 && ($ipnTxid === '' || $invoiceTxid === '')) {
			return ['valid' => false, 'reason' => 'missing_transaction_id', 'transaction_id' => ''];
		}
		if (!empty($stored['transaction_id']) && $invoiceTxid !== '' && !hash_equals((string) $stored['transaction_id'], $invoiceTxid)) {
			return ['valid' => false, 'reason' => 'stored_transaction_id', 'transaction_id' => ''];
		}

		$status = (int) $invoice['status'];
		if (!in_array($status, [0, 1], true)) {
			return ['valid' => false, 'reason' => 'invoice_status', 'transaction_id' => ''];
		}

		$storedTxid = isset($stored['transaction_id']) ? trim((string) $stored['transaction_id']) : '';
		return ['valid' => true, 'reason' => '', 'transaction_id' => $invoiceTxid ?: ($ipnTxid ?: $storedTxid)];
	}

	private function markOrderPaid(Order $order)
	{
		$paidState = (int) Configuration::get('PS_OS_PAYMENT');
		if (!$this->hasOrderReachedState((int) $order->id, $paidState)) {
			$this->changeOrderStateOnce($order, $paidState, true);
		}
	}

	private function hasOrderReachedState($orderId, $stateId)
	{
		if (!(int) $stateId) {
			return false;
		}

		return (bool) Db::getInstance()->getValue(
			'SELECT id_order_history FROM `' . _DB_PREFIX_ . 'order_history`
			WHERE id_order = ' . (int) $orderId . ' AND id_order_state = ' . (int) $stateId
		);
	}

	private function changeOrderStateOnce(Order $order, $stateId, $sendEmail)
	{
		$stateId = (int) $stateId;
		if (!$stateId || (int) $order->current_state === $stateId) {
			return;
		}

		$history = new OrderHistory();
		$history->id_order = (int) $order->id;
		$history->changeIdOrderState($stateId, (int) $order->id);
		if ($sendEmail) {
			$history->addWithemail();
		} else {
			$history->add();
		}
	}

	private function extractIpnTransactionId(array $payload)
	{
		if (isset($payload['transaction']['txid'])) {
			return trim((string) $payload['transaction']['txid']);
		}
		if (isset($payload['invoice']['txid'])) {
			return trim((string) $payload['invoice']['txid']);
		}

		return '';
	}

	private function isValidInvoiceId($invoiceId)
	{
		return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', (string) $invoiceId);
	}

	private function respond($statusCode, $message)
	{
		http_response_code((int) $statusCode);
		header('Content-Type: text/plain; charset=utf-8');
		echo (string) $message;
		exit;
	}
}
