<?php

define('PAYDO_TEST_MODE', true);
require_once dirname(__DIR__) . '/classes/PaydoPaymentSelection.php';

function assertSameValue($expected, $actual, $message)
{
	if ($expected !== $actual) {
		fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
		exit(1);
	}
}

$basePayload = ['order' => ['amount' => '1.00', 'currency' => 'EUR', 'id' => '123']];

$standard = PaydoPaymentSelection::resolve(false, false, '999', ['204']);
assertSameValue(true, $standard['valid'], 'Standard mode is always Hosted Page');
assertSameValue(false, array_key_exists('paymentMethod', PaydoPaymentSelection::applyToPayload($basePayload, $standard)), 'Standard mode omits paymentMethod');

$hosted = PaydoPaymentSelection::resolve(true, true, 'hosted', ['204']);
assertSameValue(true, $hosted['valid'], 'Enabled Hosted Page is accepted');
assertSameValue(false, array_key_exists('paymentMethod', PaydoPaymentSelection::applyToPayload($basePayload, $hosted)), 'Hosted Page omits paymentMethod');

$direct = PaydoPaymentSelection::resolve(true, true, '204', ['204']);
$directPayload = PaydoPaymentSelection::applyToPayload($basePayload, $direct);
assertSameValue(true, $direct['valid'], 'Allowlisted direct method is accepted');
assertSameValue(204, $directPayload['paymentMethod'], 'Direct paymentMethod is an integer');

$spoofed = PaydoPaymentSelection::resolve(true, true, '999', ['204']);
assertSameValue(false, $spoofed['valid'], 'Spoofed method is rejected');
assertSameValue(false, PaydoPaymentSelection::resolve(true, false, 'hosted', ['204'])['valid'], 'Disabled Hosted Page is rejected');
assertSameValue(false, PaydoPaymentSelection::resolve(true, false, '0', ['0'])['valid'], 'Zero is never accepted as a method');

$expectedSignature = hash('sha256', '1.00:EUR:123:secret');
assertSameValue($expectedSignature, PaydoPaymentSelection::signature('123', '1.00', 'EUR', 'secret'), 'Signature uses the exact JSON amount string');
assertSameValue(false, $expectedSignature === PaydoPaymentSelection::signature('123', '1.0000', 'EUR', 'secret'), 'Amount formatting changes the signature');

echo "PayDo payment selection tests passed.\n";
