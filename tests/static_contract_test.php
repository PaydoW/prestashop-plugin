<?php

function contractAssert($condition, $message)
{
	if (!$condition) {
		fwrite(STDERR, "FAIL: {$message}\n");
		exit(1);
	}
}

$root = dirname(__DIR__);
$phpFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($iterator as $file) {
	if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getPathname(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR) === false) {
		$phpFiles[] = $file->getPathname();
	}
}

$source = '';
foreach ($phpFiles as $file) {
	$source .= file_get_contents($file) . "\n";
}

contractAssert(strpos($source, 'api.paydo.com/v1') === false, 'No PayDo API v1 endpoint may remain');
contractAssert(strpos($source, 'https://api.paydo.com/v3/invoices/create') !== false, 'Invoice creation uses v3');
contractAssert(strpos($source, 'https://api.paydo.com/v3/invoices/') !== false, 'Invoice verification uses v3');
contractAssert(strpos($source, 'available-for-application/') !== false, 'Method synchronization endpoint is present');

$validation = file_get_contents($root . '/controllers/front/validation.php');
contractAssert(strpos($validation, "['paymentMethod'] = null") === false, 'Hosted Page never sends paymentMethod null');
contractAssert(strpos($validation, "'key' =>") === false, 'Result URL does not expose a customer order key');

$failure = file_get_contents($root . '/controllers/front/failPage.php');
contractAssert(strpos($failure, 'changeIdOrderState') === false, 'Browser failure redirect cannot change order status');

$module = file_get_contents($root . '/paydo.php');
contractAssert(strpos($module, 'UNIQUE KEY `uniq_order_id`') !== false, 'Order IDs have a unique index');
contractAssert(strpos($module, 'UNIQUE KEY `uniq_invoice_id`') !== false, 'Invoice IDs have a unique index');
contractAssert(strpos($module, "can('edit', 'AdminModulessf')") !== false, 'PrestaShop 8 and 9 module edit permission is checked');
contractAssert(strpos($module, '$this->context->smarty->fetch(\'module:paydo/views/templates/hook/payment_options.tpl\')') !== false, 'Checkout form uses the active PrestaShop Smarty context');
contractAssert(strpos($module, 'clearCompiledTemplate($template)') !== false, 'Checkout template compilation is invalidated after settings and upgrades');

$upgrade = file_get_contents($root . '/upgrade/upgrade-2.2.0.php');
contractAssert(strpos($upgrade, 'upgrade_module_2_2_0') !== false, 'Upgrade entry point matches module version 2.2.0');

$checkoutTemplate = file_get_contents($root . '/views/templates/hook/payment_options.tpl');
contractAssert(strpos($checkoutTemplate, 'name="paydo_method"') !== false, 'Checkout renders a PayDo method selector');
contractAssert(strpos($checkoutTemplate, "Choose PayDo method:") !== false, 'Checkout method selector matches the WooCommerce wording');

$configurationTemplate = file_get_contents($root . '/views/templates/hook/getContent.tpl');
contractAssert(strpos($configurationTemplate, 'name="token"') === false, 'Configuration form does not collide with the Symfony route token');
contractAssert(strpos($module, "'jwtToken' => Configuration::get('PAYDO_JWT_TOKEN')") !== false, 'Saved JWT token is assigned to the admin form');
contractAssert(strpos($configurationTemplate, '{$jwtToken|escape:') !== false, 'Saved JWT token is visible and escaped in the admin form');

echo "PayDo static contract tests passed.\n";
