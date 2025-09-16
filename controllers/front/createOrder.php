<?php
class PaydoCreateOrderModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	public function postProcess()
	{
		$cartId = (int)Tools::getValue('cart_id');
		if (!$cartId) Tools::redirect($this->context->link->getPageLink('index', true));

		$cart = new Cart($cartId);
		if (!Validate::isLoadedObject($cart)) Tools::redirect($this->context->link->getPageLink('index', true));

		$customer = new Customer((int)$cart->id_customer);
		if (!Validate::isLoadedObject($customer)) Tools::redirect($this->context->link->getPageLink('index', true));
		$secureKey = (string)$customer->secure_key;

		$keyFromUrl = (string)Tools::getValue('key');
		if ($keyFromUrl && $keyFromUrl !== $secureKey) {
			Tools::redirect($this->context->link->getModuleLink('paydo', 'failPage', ['cart_id' => $cartId], true));
		}

		$module = Module::getInstanceByName('paydo');
		if (!$module) Tools::redirect($this->context->link->getModuleLink('paydo', 'failPage', ['cart_id' => $cartId], true));

		$pendingState = (int)Configuration::get('PS_OS_PAYDO_PENDING_STATE') ?: (int)Configuration::get('PS_OS_PREPARATION');

		if (!$cart->orderExists()) {
			$module->validateOrder(
				$cartId,
				$pendingState,
				(float)$cart->getOrderTotal(true, Cart::BOTH),
				$module->displayName,
				null,
				null,
				(int)$cart->id_currency,
				false,
				$secureKey
			);
			$orderId = (int)$module->currentOrder;
		} else {
			$orderId = (int)Order::getIdByCartId($cartId);
		}

		if (!$orderId) {
			$orderId = (int)Db::getInstance()->getValue('SELECT id_order FROM '._DB_PREFIX_.'orders WHERE id_cart='.(int)$cartId);
		}

		$idModule = (int)$module->id;

		if ($orderId && $idModule) {
			$url = $this->context->link->getPageLink(
				'order-confirmation',
				true,
				null,
				http_build_query([
					'id_cart'   => (int)$cartId,
					'id_order'  => (int)$orderId,
					'id_module' => (int)$idModule,
					'key'	   => $secureKey,
				])
			);
			Tools::redirect($url);
		} else {
			Tools::redirect($this->context->link->getModuleLink('paydo', 'failPage', ['cart_id' => $cartId], true));
		}
	}
}
