<?php

class PaydoFailPageModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();
		// Browser redirects are informational only; verified IPN controls the order status.
		$this->setTemplate('module:paydo/views/templates/front/failPage.tpl');
	}
}
