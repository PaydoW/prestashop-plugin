<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
	exit;
}

require_once __DIR__ . '/classes/PaydoPaymentSelection.php';

class Paydo extends PaymentModule
{
	protected $logger;
	protected $translator;

	public function __construct()
	{
		$this->name = 'paydo';
		$this->tab = 'payments_gateways';
		$this->version = '2.2.0';
		$this->author = 'PAYDO';
		$this->controllers = ['payment', 'validation', 'failPage', 'callback', 'createOrder'];
		$this->currencies = true;
		$this->currencies_mode = 'checkbox';
		$this->bootstrap = true;
		$this->displayName = 'Paydo';
		$this->description = 'Make payments via Paydo';
		$this->confirmUninstall = 'Are you sure you want to uninstall this module?';
		$this->ps_versions_compliancy = ['min' => '8.0.0'];

		parent::__construct();
	}

	public function install()
	{
		return parent::install()
			&& $this->registerHook('PaymentOptions')
			&& $this->registerHook('PaymentReturn')
			&& $this->installOrderState()
			&& $this->installDb()
			&& $this->installConfigurationDefaults();
	}

	public function uninstall()
	{
		return $this->uninstallDb()
			&& parent::uninstall();
	}

	public function processConfiguration()
	{
		if (!Tools::isSubmit('pc_form') && !Tools::isSubmit('paydo_sync_methods')) {
			return;
		}

		if (!$this->isAuthorizedAdminRequest()) {
			$this->context->smarty->assign('configurationError', $this->l('You are not authorized to change PayDo settings.'));
			return;
		}

		if (Tools::isSubmit('pc_form')) {
			$methodsMode = (bool) Tools::getValue('methodsMode');
			$hostedPageEnabled = (bool) Tools::getValue('hostedPageEnabled');
			$enabledMethodIds = $this->filterEnabledMethodIds(Tools::getValue('enabledMethods', []));

			if ($methodsMode && !$hostedPageEnabled && !$enabledMethodIds) {
				$this->context->smarty->assign(
					'configurationError',
					$this->l('Enable PayDo Hosted Page or at least one synchronized payment method.')
				);
				return;
			}

			Configuration::updateValue('PAYDO_ENABLE', (int) Tools::getValue('enablePayments'));
			Configuration::updateValue('PAYDO_NAME', trim(strip_tags((string) Tools::getValue('displayName'))));
			Configuration::updateValue('DESCRIPTION', trim(strip_tags((string) Tools::getValue('description'))));
			Configuration::updateValue('PAYDO_PUBLIC_KEY', trim((string) Tools::getValue('publicKey')));
			Configuration::updateValue('PAYDO_METHODS_MODE', (int) $methodsMode);
			Configuration::updateValue('PAYDO_HOSTED_PAGE_ENABLED', (int) $hostedPageEnabled);
			Configuration::updateValue('PAYDO_PROJECT_ID', trim((string) Tools::getValue('projectId')));
			Configuration::updateValue('PAYDO_ENABLED_METHODS', json_encode($enabledMethodIds));

			$secretKey = trim((string) Tools::getValue('secretKey'));
			if ($secretKey !== '') {
				Configuration::updateValue('PAYDO_SECRET_KEY', $secretKey);
			}

			$jwtToken = trim((string) Tools::getValue('jwtToken'));
			if ($jwtToken !== '') {
				Configuration::updateValue('PAYDO_JWT_TOKEN', $jwtToken);
			}

			$this->clearCheckoutTemplateCache();

			$this->context->smarty->assign('confirmation', 'ok');
			return;
		}

		if (Tools::isSubmit('paydo_sync_methods')) {
			$projectId = trim((string) Tools::getValue('projectId'));
			if ($projectId === '') {
				$projectId = trim((string) Configuration::get('PAYDO_PROJECT_ID'));
			}

			$jwtToken = trim((string) Tools::getValue('jwtToken'));
			if ($jwtToken === '') {
				$jwtToken = trim((string) Configuration::get('PAYDO_JWT_TOKEN'));
			}

			Configuration::updateValue('PAYDO_PROJECT_ID', $projectId);
			if (trim((string) Tools::getValue('jwtToken')) !== '') {
				Configuration::updateValue('PAYDO_JWT_TOKEN', $jwtToken);
			}

			$result = $this->syncPaymentMethods($projectId, $jwtToken);
			if ($result['success']) {
				$this->clearCheckoutTemplateCache();
				$this->context->smarty->assign('methodSyncSuccess', sprintf(
					$this->l('%d payment methods synchronized.'),
					$result['count']
				));
			} else {
				$this->context->smarty->assign('methodSyncError', $result['message']);
			}
		}
	}

	public function assignConfiguration()
	{
		$methods = $this->getAvailablePaymentMethods();
		$enabledMethodIds = $this->getEnabledPaymentMethodIds();
		$enabledLookup = array_fill_keys($enabledMethodIds, true);

		foreach ($methods as &$method) {
			$method['enabled'] = isset($enabledLookup[(string) $method['identifier']]);
		}
		unset($method);

		$this->context->smarty->assign([
			'enablePayments' => (int) Configuration::get('PAYDO_ENABLE'),
			'displayName' => Configuration::get('PAYDO_NAME'),
			'description' => Configuration::get('DESCRIPTION'),
			'publicKey' => Configuration::get('PAYDO_PUBLIC_KEY'),
			'secretKey' => '',
			'hasSecretKey' => trim((string) Configuration::get('PAYDO_SECRET_KEY')) !== '',
			'methodsMode' => (int) Configuration::get('PAYDO_METHODS_MODE'),
			'hostedPageEnabled' => $this->isHostedPageEnabled(),
			'projectId' => Configuration::get('PAYDO_PROJECT_ID'),
			'jwtToken' => Configuration::get('PAYDO_JWT_TOKEN'),
			'hasJwtToken' => trim((string) Configuration::get('PAYDO_JWT_TOKEN')) !== '',
			'paymentMethods' => $methods,
			'hasEnabledMethods' => (bool) $this->getEnabledPaymentMethods(),
			'ipnUrl' => $this->context->link->getModuleLink($this->name, 'callback', [], true),
		]);
	}

	public function getContent()
	{
		$this->processConfiguration();
		$this->assignConfiguration();
		return $this->fetch('module:paydo/views/templates/hook/getContent.tpl');
	}

	public function hookPaymentOptions()
	{
		if (!$this->active || !(int) Configuration::get('PAYDO_ENABLE')) {
			return;
		}

		$methodsMode = (bool) Configuration::get('PAYDO_METHODS_MODE');
		$hostedPageEnabled = $this->isHostedPageEnabled();
		$paymentMethods = $this->getEnabledPaymentMethods();
		if ($methodsMode && !$hostedPageEnabled && !$paymentMethods) {
			return;
		}

		if (!empty($this->context->cookie->paydo_payment_error)) {
			$this->context->controller->errors[] = (string) $this->context->cookie->paydo_payment_error;
			unset($this->context->cookie->paydo_payment_error);
		}

		$formAction = $this->context->link->getModuleLink($this->name, 'validation', [], true);
		$this->context->smarty->assign([
			'action' => $formAction,
			'methodsMode' => $methodsMode,
			'hostedPageEnabled' => $hostedPageEnabled,
			'paymentMethods' => $paymentMethods,
			'description' => Configuration::get('DESCRIPTION'),
		]);
		$paymentForm = $this->context->smarty->fetch('module:paydo/views/templates/hook/payment_options.tpl');

		$newOption = new PaymentOption();
		$newOption->setModuleName($this->name)
			->setCallToActionText(Configuration::get('PAYDO_NAME') ?: $this->displayName)
			->setAction($formAction)
			->setForm($paymentForm);

		return [$newOption];
	}

	/**
	 * Return payment methods currently enabled by the merchant.
	 *
	 * @return array
	 */
	public function getEnabledPaymentMethods()
	{
		$available = $this->getAvailablePaymentMethods();
		$enabledLookup = array_fill_keys($this->getEnabledPaymentMethodIds(), true);

		return array_values(array_filter($available, static function ($method) use ($enabledLookup) {
			return isset($enabledLookup[(string) $method['identifier']]);
		}));
	}

	/**
	 * Check whether a method identifier is allowed for checkout.
	 *
	 * @param string $identifier
	 *
	 * @return bool
	 */
	public function isPaymentMethodEnabled($identifier)
	{
		foreach ($this->getEnabledPaymentMethods() as $method) {
			if ((string) $method['identifier'] === (string) $identifier) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Hosted Page remains enabled by default for backward compatibility.
	 *
	 * @return bool
	 */
	public function isHostedPageEnabled()
	{
		$value = Configuration::get('PAYDO_HOSTED_PAGE_ENABLED');

		return $value === false ? true : (bool) $value;
	}

	/**
	 * Resolve the checkout value against the server-side allowlist.
	 *
	 * @param mixed $selectedValue
	 *
	 * @return array
	 */
	public function resolvePaymentSelection($selectedValue)
	{
		return PaydoPaymentSelection::resolve(
			(bool) Configuration::get('PAYDO_METHODS_MODE'),
			$this->isHostedPageEnabled(),
			$selectedValue,
			$this->getEnabledPaymentMethodIds()
		);
	}

	/**
	 * @return array
	 */
	private function getAvailablePaymentMethods()
	{
		$methods = json_decode((string) Configuration::get('PAYDO_AVAILABLE_METHODS'), true);
		if (!is_array($methods)) {
			return [];
		}

		$normalized = [];
		foreach ($methods as $method) {
			if (!is_array($method) || !isset($method['identifier'], $method['title'])) {
				continue;
			}

			$identifier = (string) $method['identifier'];
			$title = trim((string) $method['title']);
			if (!ctype_digit($identifier) || (int) $identifier <= 0 || $title === '') {
				continue;
			}

			$logo = isset($method['logo']) ? trim((string) $method['logo']) : '';
			if ($logo !== '' && !$this->isSafeRemoteUrl($logo)) {
				$logo = '';
			}

			$normalized[] = [
				'identifier' => $identifier,
				'title' => $title,
				'logo' => $logo,
			];
		}

		return $normalized;
	}

	/**
	 * @return array
	 */
	public function getEnabledPaymentMethodIds()
	{
		$identifiers = json_decode((string) Configuration::get('PAYDO_ENABLED_METHODS'), true);

		if (!is_array($identifiers)) {
			return [];
		}

		$normalized = [];
		foreach ($identifiers as $identifier) {
			if (!is_scalar($identifier) || !ctype_digit((string) $identifier) || (int) $identifier <= 0) {
				continue;
			}
			$normalized[] = (string) $identifier;
		}

		return array_values(array_unique($normalized));
	}

	/**
	 * Require an authenticated employee with module edit permission.
	 *
	 * The Symfony module manager validates its route CSRF token before invoking
	 * the module configuration page.
	 *
	 * @return bool
	 */
	private function isAuthorizedAdminRequest()
	{
		$employee = isset($this->context->employee) ? $this->context->employee : null;
		if (!Validate::isLoadedObject($employee)) {
			return false;
		}

		// PrestaShop 8 and 9 serve module configuration through the Symfony
		// AdminModulessf controller. AdminModules is retained as a fallback for
		// installations still using the legacy controller.
		if (method_exists($employee, 'can') && $employee->can('edit', 'AdminModulessf')) {
			return true;
		}

		$tabId = (int) Tab::getIdFromClassName('AdminModules');
		if ($tabId <= 0) {
			return false;
		}
		$access = Profile::getProfileAccess((int) $employee->id_profile, $tabId);

		return is_array($access) && !empty($access['edit']);
	}

	/**
	 * Only keep method identifiers present in the latest PayDo synchronization.
	 *
	 * @param mixed $identifiers
	 *
	 * @return array
	 */
	private function filterEnabledMethodIds($identifiers)
	{
		if (!is_array($identifiers)) {
			return [];
		}

		$availableLookup = [];
		foreach ($this->getAvailablePaymentMethods() as $method) {
			$availableLookup[(string) $method['identifier']] = true;
		}

		$filtered = [];
		foreach ($identifiers as $identifier) {
			if (!is_scalar($identifier)) {
				continue;
			}
			$identifier = (string) $identifier;
			if (isset($availableLookup[$identifier])) {
				$filtered[] = $identifier;
			}
		}

		return array_values(array_unique($filtered));
	}

	/**
	 * Fetch Internet Acquiring methods available for the configured PayDo project.
	 *
	 * @param string $projectId
	 * @param string $jwtToken
	 *
	 * @return array
	 */
	private function syncPaymentMethods($projectId, $jwtToken)
	{
		if ($projectId === '' || $jwtToken === '') {
			return [
				'success' => false,
				'message' => $this->l('Fill in Project ID and JWT Token before synchronization.'),
			];
		}

		if (!function_exists('curl_init')) {
			return [
				'success' => false,
				'message' => $this->l('The PHP cURL extension is required to synchronize payment methods.'),
			];
		}

		if (preg_match('/[\r\n]/', $jwtToken)) {
			return [
				'success' => false,
				'message' => $this->l('JWT Token contains invalid characters.'),
			];
		}

		$url = 'https://api.paydo.com/v3/instrument-settings/payment-methods/available-for-application/'
			. rawurlencode($projectId);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPGET, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Authorization: Bearer ' . $jwtToken,
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$body = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($curlError || !is_string($body)) {
			PrestaShopLogger::addLog(sprintf('[Paydo] Payment methods synchronization transport failure (HTTP %d)', $httpCode));
			return [
				'success' => false,
				'message' => $this->l('Could not connect to PayDo. Please try again.'),
			];
		}

		if ($httpCode !== 200) {
			return [
				'success' => false,
				'message' => $this->getSyncHttpErrorMessage($httpCode),
			];
		}

		$response = json_decode($body, true);
		if (!is_array($response)) {
			return [
				'success' => false,
				'message' => $this->l('PayDo returned an invalid JSON response.'),
			];
		}

		$rows = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
		$methods = [];

		foreach ($rows as $row) {
			if (!isset($row['paymentMethod']) || !is_array($row['paymentMethod'])) {
				continue;
			}

			$method = $row['paymentMethod'];
			$identifier = isset($method['identifier']) ? (string) $method['identifier'] : '';
			$title = isset($method['title']) ? trim((string) $method['title']) : '';

			$isEnabled = isset($method['isEnabled'])
				&& filter_var($method['isEnabled'], FILTER_VALIDATE_BOOLEAN);
			if (!$isEnabled || !ctype_digit($identifier) || (int) $identifier <= 0 || $title === '') {
				continue;
			}

			$logo = isset($method['logo']) ? trim((string) $method['logo']) : '';
			if ($logo !== '' && !$this->isSafeRemoteUrl($logo)) {
				$logo = '';
			}

			$methods[$identifier] = [
				'identifier' => $identifier,
				'title' => $title,
				'logo' => $logo,
			];
		}

		if (!$methods) {
			return [
				'success' => false,
				'message' => $this->l('PayDo returned no enabled payment methods for this project.'),
			];
		}

		uasort($methods, static function ($left, $right) {
			return strnatcasecmp((string) $left['title'], (string) $right['title']);
		});

		Configuration::updateValue('PAYDO_AVAILABLE_METHODS', json_encode($methods));
		Configuration::updateValue(
			'PAYDO_ENABLED_METHODS',
			json_encode($this->filterEnabledMethodIds($this->getEnabledPaymentMethodIds()))
		);

		return [
			'success' => true,
			'count' => count($methods),
		];
	}

	/**
	 * @param int $httpCode
	 *
	 * @return string
	 */
	private function getSyncHttpErrorMessage($httpCode)
	{
		switch ((int) $httpCode) {
			case 401:
				return $this->l('PayDo rejected the JWT Token (HTTP 401).');
			case 403:
				return $this->l('The JWT Token has no access to this PayDo project (HTTP 403).');
			case 404:
				return $this->l('The PayDo project was not found (HTTP 404).');
			case 422:
				return $this->l('PayDo rejected the synchronization request (HTTP 422).');
			default:
				if ($httpCode >= 500) {
					return $this->l('PayDo is temporarily unavailable. Please try again later.');
				}

				return sprintf($this->l('PayDo API request failed (HTTP %d).'), (int) $httpCode);
		}
	}

	/**
	 * @param string $url
	 *
	 * @return bool
	 */
	private function isSafeRemoteUrl($url)
	{
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			return false;
		}

		$scheme = parse_url($url, PHP_URL_SCHEME);

		return in_array(strtolower((string) $scheme), ['http', 'https'], true);
	}

	private function installOrderState()
	{
		$configurationKey = 'PS_OS_PAYDO_PENDING_STATE';
		if (Configuration::getGlobalValue($configurationKey)) {
			return true;
		}

		$orderState = new OrderState();
		$orderState->module_name = $this->name;
		$orderState->color = '#D3D3D3';
		$orderState->logable = false;
		$orderState->paid = false;
		$orderState->invoice = false;
		$orderState->shipped = false;
		$orderState->delivery = false;
		$orderState->pdf_delivery = false;
		$orderState->pdf_invoice = false;
		$orderState->send_email = false;
		$orderState->hidden = false;
		$orderState->unremovable = true;
		$orderState->template = '';
		$orderState->deleted = false;

		$orderState->name = [];
		foreach (Language::getLanguages(false) as $language) {
			$orderState->name[(int) $language['id_lang']] = 'Pending Payment';
		}

		if (!$orderState->add()) {
			return false;
		}

		Configuration::updateGlobalValue($configurationKey, (int) $orderState->id);
		return true;
	}

	public function hookPaymentReturn($params)
	{
		if (!$this->active) {
			return;
		}
		return $this->fetch('module:paydo/views/templates/hook/payment_return.tpl');
	}

	public function upgradeTo220()
	{
		$upgraded = $this->installConfigurationDefaults()
			&& $this->installDb();

		if ($upgraded) {
			$this->clearCheckoutTemplateCache();
		}

		return $upgraded;
	}

	/**
	 * Drop only the compiled/cached checkout fragment after configuration or
	 * module upgrades. Production shops commonly keep Smarty compilation off,
	 * so otherwise a newly uploaded payment_options.tpl can remain stale.
	 */
	private function clearCheckoutTemplateCache()
	{
		$template = 'module:' . $this->name . '/views/templates/hook/payment_options.tpl';
		$smarty = isset($this->context->smarty) ? $this->context->smarty : null;

		if (!is_object($smarty)) {
			return;
		}

		if (method_exists($smarty, 'clearCompiledTemplate')) {
			$smarty->clearCompiledTemplate($template);
		}

		if (method_exists($smarty, 'clearCache')) {
			$smarty->clearCache($template);
		}
	}

	private function installConfigurationDefaults()
	{
		$defaults = [
			'PAYDO_METHODS_MODE' => 0,
			'PAYDO_HOSTED_PAGE_ENABLED' => 1,
			'PAYDO_AVAILABLE_METHODS' => '[]',
			'PAYDO_ENABLED_METHODS' => '[]',
		];

		foreach ($defaults as $key => $value) {
			if (Configuration::get($key) === false && !Configuration::updateValue($key, $value)) {
				return false;
			}
		}

		return true;
	}

	private function installDb()
	{
		$sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "paydo_order_transactions` (
			`id_paydo_order_transaction` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			`order_id` INT UNSIGNED NOT NULL,
			`cart_id` INT UNSIGNED NOT NULL,
			`transaction_id` VARCHAR(255) DEFAULT NULL,
			`invoice_id` VARCHAR(255) NOT NULL,
			`selected_method` VARCHAR(32) NOT NULL DEFAULT 'hosted',
			`amount` DECIMAL(20,8) NOT NULL DEFAULT 0,
			`currency` VARCHAR(3) NOT NULL DEFAULT '',
			`invoice_status` SMALLINT DEFAULT NULL,
			`transaction_state` SMALLINT DEFAULT NULL,
			`verification_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			`last_verification_at` DATETIME DEFAULT NULL,
			`created_at` DATETIME NOT NULL,
			`updated_at` DATETIME NOT NULL,
			UNIQUE KEY `uniq_cart_id` (`cart_id`),
			UNIQUE KEY `uniq_order_id` (`order_id`),
			UNIQUE KEY `uniq_invoice_id` (`invoice_id`(64)),
			KEY `idx_transaction_id` (`transaction_id`),
			KEY `idx_invoice_status` (`invoice_status`)
		) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8mb4;";

		if (!Db::getInstance()->execute($sql)) {
			return false;
		}

		return $this->upgradeDbSchema();
	}

	private function upgradeDbSchema()
	{
		$table = _DB_PREFIX_ . 'paydo_order_transactions';
		$columns = [
			'selected_method' => "VARCHAR(32) NOT NULL DEFAULT 'hosted' AFTER `invoice_id`",
			'amount' => "DECIMAL(20,8) NOT NULL DEFAULT 0 AFTER `selected_method`",
			'currency' => "VARCHAR(3) NOT NULL DEFAULT '' AFTER `amount`",
			'invoice_status' => "SMALLINT DEFAULT NULL AFTER `currency`",
			'transaction_state' => "SMALLINT DEFAULT NULL AFTER `invoice_status`",
			'verification_attempts' => "SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `transaction_state`",
			'last_verification_at' => "DATETIME DEFAULT NULL AFTER `verification_attempts`",
		];

		foreach ($columns as $column => $definition) {
			if (!$this->databaseColumnExists($table, $column)) {
				$sql = 'ALTER TABLE `' . bqSQL($table) . '` ADD `' . bqSQL($column) . '` ' . $definition;
				if (!Db::getInstance()->execute($sql)) {
					return false;
				}
			}
		}

		Db::getInstance()->execute(
			'UPDATE `' . bqSQL($table) . '` transaction_row
			INNER JOIN `' . _DB_PREFIX_ . 'orders` orders_row ON orders_row.id_cart = transaction_row.cart_id
			INNER JOIN `' . _DB_PREFIX_ . 'currency` currency_row ON currency_row.id_currency = orders_row.id_currency
			SET transaction_row.order_id = orders_row.id_order,
				transaction_row.amount = orders_row.total_paid,
				transaction_row.currency = currency_row.iso_code
			WHERE transaction_row.order_id = 0 OR LENGTH(transaction_row.currency) = 0'
		);

		$indexes = [
			'uniq_order_id' => 'UNIQUE KEY `uniq_order_id` (`order_id`)',
			'uniq_invoice_id' => 'UNIQUE KEY `uniq_invoice_id` (`invoice_id`(64))',
			'idx_invoice_status' => 'KEY `idx_invoice_status` (`invoice_status`)',
		];

		foreach ($indexes as $index => $definition) {
			if (!$this->databaseIndexExists($table, $index)) {
				if (!Db::getInstance()->execute('ALTER TABLE `' . bqSQL($table) . '` ADD ' . $definition)) {
					return false;
				}
			}
		}

		return true;
	}

	private function databaseColumnExists($table, $column)
	{
		$rows = Db::getInstance()->executeS(
			'SHOW COLUMNS FROM `' . bqSQL($table) . '` LIKE \'' . pSQL($column) . '\''
		);

		return !empty($rows);
	}

	private function databaseIndexExists($table, $index)
	{
		$rows = Db::getInstance()->executeS(
			'SHOW INDEX FROM `' . bqSQL($table) . '` WHERE Key_name = \'' . pSQL($index) . '\''
		);

		return !empty($rows);
	}

	public function getPaydoTransactionByOrderId($orderId)
	{
		return Db::getInstance()->getRow(
			'SELECT * FROM `' . _DB_PREFIX_ . 'paydo_order_transactions` WHERE order_id = ' . (int) $orderId
		);
	}

	public function getPaydoTransactionByCartId($cartId)
	{
		return Db::getInstance()->getRow(
			'SELECT * FROM `' . _DB_PREFIX_ . 'paydo_order_transactions` WHERE cart_id = ' . (int) $cartId
		);
	}

	public function savePaydoInvoice($cartId, $orderId, $invoiceId, $selectedMethod, $amount, $currency)
	{
		$existing = $this->getPaydoTransactionByOrderId($orderId);
		if (!$existing) {
			$existing = $this->getPaydoTransactionByCartId($cartId);
		}

		if ($existing && !empty($existing['invoice_id']) && (string) $existing['invoice_id'] !== (string) $invoiceId) {
			return false;
		}

		$data = [
			'cart_id' => (int) $cartId,
			'order_id' => (int) $orderId,
			'invoice_id' => pSQL($invoiceId),
			'selected_method' => pSQL($selectedMethod),
			'amount' => (string) $amount,
			'currency' => pSQL(strtoupper((string) $currency)),
			'invoice_status' => 0,
			'updated_at' => date('Y-m-d H:i:s'),
		];

		if ($existing) {
			return Db::getInstance()->update(
				'paydo_order_transactions',
				$data,
				'id_paydo_order_transaction = ' . (int) $existing['id_paydo_order_transaction']
			);
		}

		$data['created_at'] = date('Y-m-d H:i:s');

		return Db::getInstance()->insert('paydo_order_transactions', $data);
	}

	public function updatePaydoTransaction($orderId, array $values)
	{
		$allowed = [
			'transaction_id', 'invoice_status', 'transaction_state',
			'verification_attempts', 'last_verification_at',
		];
		$data = [];
		foreach ($values as $key => $value) {
			if (!in_array($key, $allowed, true)) {
				continue;
			}
			$data[$key] = $key === 'transaction_id' ? pSQL((string) $value) : $value;
		}

		if (!$data) {
			return false;
		}

		$data['updated_at'] = date('Y-m-d H:i:s');

		return Db::getInstance()->update(
			'paydo_order_transactions',
			$data,
			'order_id = ' . (int) $orderId
		);
	}

	private function uninstallDb()
	{
		$sql = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "paydo_order_transactions`;";
		return Db::getInstance()->execute($sql);
	}
}
