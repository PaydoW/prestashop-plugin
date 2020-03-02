{if isset($confirmation)}
    <div class="alert alert-success">{l s='Settings updated' mod='paydo'}</div>
{/if}
<fieldset>
    <h2>{l s='PayDo configuration' mod='paydo'}</h2>
    <div class="panel">
        <form id="data" action="" method="post">
            <div class="form-group clearfix">
                <label class="col-lg-3">{l s='Enable PayDo payments' mod='paydo'}</label>
                <div class="col-lg-9">
                    <img src="../img/admin/enabled.gif" alt="" />
                    <input type="radio" id="enablePayments_1" name="enablePayments" value="1" {if $enablePayments eq '1'}checked{/if}/>
                    <label class="t" for="enablePayments_1">{l s='Yes' mod='paydo'}</label>
                    <img src="../img/admin/disabled.gif" alt="" />
                    <input type="radio" id="enablePayments_0" name="enablePayments" value="0" {if $enablePayments eq '0' || empty($enablePayments)}checked{/if}/>
                    <label class="t" for="enablePayments_0">{l s='No' mod='paydo'}</label>
                </div>
            </div>
            <div class="form-group clearfix">
                <label class="col-lg-3">{l s='Display Name' mod='paydo'}</label>
                <div class="col-lg-9">
                    <input type="text" id="displayName" name="displayName" value="{Configuration::get('PAYDO_NAME')}" placeholder="{l s='Payment method displayed name' mod='paydo'}"/>
                </div>
            </div>
            <div class="form-group clearfix">
                <label class="col-lg-3">{l s='Description' mod='paydo'}</label>
                <div class="col-lg-9">
                    <input type="text" id="description" name="description" value="{Configuration::get('DESCRIPTION')}" placeholder="{l s='Payment method description' mod='paydo'}"/>
                </div>
            </div>
            <div class="form-group clearfix">
                <label class="col-lg-3">{l s='Public Key' mod='paydo'}</label>
                <div class="col-lg-9">
                    <input type="text" id="publicKey" name="publicKey" value="{Configuration::get('PAYDO_PUBLIC_KEY')}" placeholder="{l s='Issued in project settings' mod='paydo'}"/>
                </div>
            </div>
            <div class="form-group clearfix">
                <label class="col-lg-3">{l s='Secret Key' mod='paydo'}</label>
                <div class="col-lg-9">
                    <input type="text" id="secretKey" name="secretKey" value="{Configuration::get('PAYDO_SECRET_KEY')}" placeholder="{l s='Issued in project settings' mod='paydo'}"/>
                </div>
            </div>
            <div class="form-group clearfix">
                <label class="col-lg-3">{l s='JWT Token' mod='paydo'}</label>
                <div class="col-lg-9">
                    <input type="text" id="jwtToken" name="jwtToken" value="{Configuration::get('JWT_TOKEN')}" placeholder="{l s='Issued in project settings' mod='paydo'}"/>
                </div>
            </div>
            <div class="form-group clearfix">
                <label class="col-lg-3">{l s='Direct Payment' mod='paydo'}</label>
                <div class="col-lg-9">
                    <select name="directPay">
                        <option label="Non Direct" value="Non Direct">Non direct</option>
                        {foreach from=$directMethods item=method}
                            <option label="{$method}" value="{$method}" {if $directPay eq $method}selected{/if}>{$method}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
            <div class="form-group clearfix">
                <label class="col-lg-3">{l s='Payment form language' mod='paydo'}</label>
                <div class="col-lg-9">
                    <select name="language" form="data">
                        <option value="en" {if $language eq 'en'}selected{/if}>{l s='English' mod='paydo'}</option>
                        <option value="ru" {if $language eq 'ru'}selected{/if}>{l s='Russian' mod='paydo'}</option>
                    </select>
                </div>
            </div>
            <div class="panel-footer">
                <input class="btn btn-default pull-right" type="submit" name="pc_form" value="{l s='Save' mod='paydo'}" />
            </div>
        </form>
    </div>
</fieldset>