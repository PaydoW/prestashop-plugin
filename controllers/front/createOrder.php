<?php

class PaydoCreateOrderModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	public function postProcess()
	{
		$orderId = (int) Tools::getValue('id_order');

		if (!$orderId) {
			Tools::redirect($this->context->link->getPageLink('index', true));
		}

		$order = new Order($orderId);
		if (!Validate::isLoadedObject($order)) {
			Tools::redirect($this->context->link->getPageLink('index', true));
		}

		$cartId = (int) $order->id_cart;
		$cart = new Cart($cartId);
		if (!Validate::isLoadedObject($cart)) {
			Tools::redirect($this->context->link->getPageLink('index', true));
		}

		$customer = new Customer((int) $order->id_customer);
		if (!Validate::isLoadedObject($customer)) {
			Tools::redirect($this->context->link->getPageLink('index', true));
		}

		$secureKey = (string) $customer->secure_key;

		$keyFromUrl = (string) Tools::getValue('key');
		if ($keyFromUrl && $keyFromUrl !== $secureKey) {
			Tools::redirect($this->context->link->getModuleLink('paydo', 'failPage', ['cart_id' => $cartId], true));
		}

		$module = Module::getInstanceByName('paydo');
		if (!$module) {
			Tools::redirect($this->context->link->getModuleLink('paydo', 'failPage', ['cart_id' => $cartId], true));
		}

		if ($orderId) {
			$this->updateStoredOrderId($cartId, $orderId);
		}

		$idModule = (int) $module->id;

		if ($orderId && $idModule) {
			$url = $this->context->link->getPageLink(
				'order-confirmation',
				true,
				null,
				http_build_query([
					'id_cart' => (int) $cartId,
					'id_order' => (int) $orderId,
					'id_module' => (int) $idModule,
					'key' => $secureKey,
				])
			);

			Tools::redirect($url);
		} else {
			Tools::redirect($this->context->link->getModuleLink('paydo', 'failPage', ['cart_id' => $cartId], true));
		}
	}

	private function updateStoredOrderId($cart_id, $order_id)
	{
		if (!$cart_id || !$order_id) {
			return false;
		}

		return Db::getInstance()->update(
			'paydo_order_transactions',
			[
				'order_id' => (int) $order_id,
				'updated_at' => date('Y-m-d H:i:s'),
			],
			'cart_id = ' . (int) $cart_id
		);
	}
}
