<?php

class PaydoCallbackModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();
		$this->callbackRequest();
		$this->setTemplate('module:paydo/views/templates/front/callback.tpl');
	}

	private function callbackRequest()
	{
		$rawData = file_get_contents('php://input');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$this->respondBadRequest();
		}

		$callback = json_decode($rawData, false);
		if (!$callback) {
			$this->respondBadRequest();
		}

		if (!isset($callback->transaction->order->id, $callback->invoice->id)) {
			$this->respondBadRequest();
		}

		$cart_id = (int) $callback->transaction->order->id;
		$invoice_id = (string) $callback->invoice->id;

		$order_id = (int) Order::getIdByCartId($cart_id);
		if (!$order_id) {
			$this->respondBadRequest();
		}

		$order = new Order($order_id);
		if (!Validate::isLoadedObject($order)) {
			$this->respondBadRequest();
		}

		if ((string) $order->module !== 'paydo') {
			$this->respondForbidden();
		}

		$stored_invoice_id = $this->getStoredInvoiceIdByCartId($cart_id);
		if (!$stored_invoice_id) {
			$this->respondForbidden();
		}

		if ((string) $stored_invoice_id !== (string) $invoice_id) {
			$this->respondForbidden();
		}

		$invoice_data = $this->getPaydoInvoice($invoice_id);
		if (!$invoice_data) {
			$this->respondForbidden();
		}

		if (!$this->isInvoiceValidForOrder($invoice_data, $order, $cart_id, $invoice_id)) {
			$this->respondForbidden();
		}

		$paid_status = (int) Configuration::get('PS_OS_PAYMENT');
		$failed_status = (int) Configuration::get('PS_OS_ERROR');
		$pending_status = (int) Configuration::get('PS_OS_PAYDO_PENDING_STATE');

		$current_state = (int) $order->current_state;
		$history = new OrderHistory();
		$history->id_order = $order_id;

		$invoice_status = isset($invoice_data['status']) ? (int) $invoice_data['status'] : -1;

		switch ($invoice_status) {
			case 1: // paid
				if ($current_state !== $paid_status) {
					$history->changeIdOrderState($paid_status, $order_id);
					$history->addWithemail();
					$order->setCurrentState($paid_status);
					$order->save();
				}
				break;

			case 0: // new / unpaid
				if ($current_state !== $pending_status) {
					$history->changeIdOrderState($pending_status, $order_id);
				}
				break;

			default:
				$this->respondBadRequest();
		}

		header('HTTP/1.1 200 OK');
		exit;
	}

	private function getStoredInvoiceIdByCartId($cart_id)
	{
		$sql = 'SELECT invoice_id
			FROM `' . _DB_PREFIX_ . 'paydo_order_transactions`
			WHERE cart_id = ' . (int) $cart_id;

		$invoice_id = Db::getInstance()->getValue($sql);

		return $invoice_id ? (string) $invoice_id : false;
	}

	private function getPaydoInvoice($invoice_id)
	{
		if (!$invoice_id) {
			return false;
		}

		$url = 'https://api.paydo.com/v1/invoices/' . rawurlencode($invoice_id);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPGET, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$result = curl_exec($ch);
		$http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);

		curl_close($ch);

		if ($curl_error || $http_code !== 200 || !$result) {
			return false;
		}

		$response = json_decode($result, true);

		if (!is_array($response) || !isset($response['data']) || !is_array($response['data'])) {
			return false;
		}

		return $response['data'];
	}

	private function isInvoiceValidForOrder(array $invoice_data, Order $order, $cart_id, $invoice_id)
	{
		if (!isset($invoice_data['identifier'], $invoice_data['status'])) {
			return false;
		}

		if ((string) $invoice_data['identifier'] !== (string) $invoice_id) {
			return false;
		}

		if (isset($invoice_data['orderIdentifier']) && (string) $invoice_data['orderIdentifier'] !== (string) $cart_id) {
			return false;
		}

		if (isset($invoice_data['amount'])) {
			$order_amount = (float) $order->total_paid;
			$invoice_amount = (float) $invoice_data['amount'];

			if (abs($order_amount - $invoice_amount) > 0.01) {
				return false;
			}
		}

		if (isset($invoice_data['currency'])) {
			$currency = new Currency((int) $order->id_currency);

			if (!Validate::isLoadedObject($currency)) {
				return false;
			}

			if (strtoupper((string) $invoice_data['currency']) !== strtoupper((string) $currency->iso_code)) {
				return false;
			}
		}

		return true;
	}

	private function respondBadRequest()
	{
		header('HTTP/1.1 400 Bad Request');
		exit;
	}

	private function respondForbidden()
	{
		header('HTTP/1.1 403 Forbidden');
		exit;
	}
}
