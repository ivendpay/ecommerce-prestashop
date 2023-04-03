{*
* 2007-2023 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
<section>
<link rel="stylesheet" href="/modules/ivendpay/views/css/ivendpay.css?v=4" type="text/css" media="all">

  <div class="pop pop-choose-currency">
      <div id="step-2">
          <br>
          <fieldset>
            <h2><strong>{l s='Choose option' mod='ivendpay'}</strong></h2>

            {if $listCoins|count > 6}
              <div id="search-crypto-ivendpay-div">
                  <input id="search-crypto-ivendpay" placeholder="{l s='Search' mod='ivendpay'}" type="text" autocomplete="off">
              </div>
            {/if}

            <div id="listCoins" class="form__radios">
                {foreach $listCoins as $key => $coin}
                    {if $coin['id'] != 'bpay-usdt'}
                    <div class="form__radio {if $key > 6} hidden-coins {/if}" style="{if $key > 6} display: none {/if}" data-coin="{$coin['id']|escape:'htmlall':'UTF-8'}">
                        <label for="{$coin['id']|escape:'htmlall':'UTF-8'}">
                            <img src="https://content.ivendpay.com/pic/{$coin['id']|escape:'htmlall':'UTF-8'}.png">
                            <span>{$coin['name']|escape:'htmlall':'UTF-8'}</span>
                        </label>
                        <input id="{$coin['id']|escape:'htmlall':'UTF-8'}" name="crypto-method" type="radio" value="{$coin['id']|escape:'htmlall':'UTF-8'}">
                    </div>
                    {/if}
                {/foreach}

                {foreach $listCoins as $coin}
                    {if $coin['id'] == 'bpay-usdt'}
                        {if $listCoins|count > 1}
                        <h2 class="or-pay-with"><strong>{l s='or pay with' mod='ivendpay'}</strong></h2>
                        {/if}
                        <div class="form__radio bpay" data-coin="{$coin['id']|escape:'htmlall':'UTF-8'}">
                            <label for="{$coin['id']|escape:'htmlall':'UTF-8'}">
                                <img src="https://content.ivendpay.com/pic/{$coin['id']|escape:'htmlall':'UTF-8'}.png">
                                <span>{$coin['name']|escape:'htmlall':'UTF-8'}</span>
                            </label>
                            <input id="{$coin['id']|escape:'htmlall':'UTF-8'}" name="crypto-method" type="radio" value="{$coin['id']|escape:'htmlall':'UTF-8'}">
                        </div>
                    {/if}
                {/foreach}
            </div>
          </fieldset>

          {if $listCoins|count > 6}
              <a href="javascript:void(0)" class="button button--full showMoreCoinsIvendPay" data-action="show">{l s='Show More' mod='ivendpay'}</a>
              <a href="javascript:void(0)" style="display: none" class="button button--full showMoreCoinsIvendPay">{l s='Hide' mod='ivendpay'}</a>
              <br>
          {/if}
    </div>
  </div>

    <script src="/modules/ivendpay/views/js/ivendpay.js?v=7"></script>
</section>
