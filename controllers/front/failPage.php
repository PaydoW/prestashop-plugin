<?php

class PaydoFailPageModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        $this->setTemplate('module:paydo/views/templates/front/failPage.tpl');
    }
}
