<?php

class PaydoCreateOrderModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	public function initContent()
	{
		parent::initContent();

		// The browser redirect is informational only. Order state is changed exclusively by verified IPN.
		$this->setTemplate('module:paydo/views/templates/front/result.tpl');
	}
}
