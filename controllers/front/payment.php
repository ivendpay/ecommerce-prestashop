<?php
/**
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
 *  @author    ivendPay http://ivendpay.com
 *  @copyright 2007-2023 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

class IvendpayPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        // Call parent init content method
        parent::initContent();
    }

    public function postProcess()
    {
        $cart = $this->context->cart;
        $select_coin = Tools::getValue('select_coin', null);

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active || empty($select_coin)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'ivendpay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'payment'));
        }

        $cart_id = $this->context->cart->id;
        $secure_key = Context::getContext()->customer->secure_key;

        $this->module->validateOrder(
            $cart_id,
            Configuration::get('PS_OS_IVENDPAY_INIT'),
            $this->context->cart->getOrderTotal(true),
            $this->module->displayName,
            NULL,
            NULL,
            1,
            false,
            $secure_key
        );

        $order = new Order (Order::getOrderByCartId((int)$cart_id));
        $order_id = $this->module->currentOrder;

        $currencies = $this->module->getCurrency($cart->id_currency);
        $currentCurrency = null;
        foreach ($currencies as $currency) {
            if ($currency['id_currency'] == $cart->id_currency) {
                $currentCurrency = $currency;
                break;
            }
        }

        $data = $this->createInvoiceCurl([
            "currency"      => pSQL($select_coin),
            "amount_fiat"   => $this->context->cart->getOrderTotal(true),
            "currency_fiat" => $currentCurrency['iso_code'],
        ]);

        if (! empty($data['payment_url'])) {
            $this->sqlCreateInvoice($data['invoice'], $order_id);

            $this->context->smarty->assign([
                'params' => $_REQUEST,
                'tokenUrl' => $data['payment_url'],
            ]);

            Tools::redirect($data['payment_url']);
        } else {
            $this->context->smarty->assign([
                'errors' => $data['errors'],
            ]);
            $this->setTemplate('module:ivendpay/views/templates/front/payment_req_fail.tpl');
            $order->setCurrentState(Configuration::get('PS_OS_IVENDPAY_FAILED'));
            $order->save();
        }
    }

    private function sqlCreateInvoice($invoice, $order_id)
    {
        if (!Db::getInstance()->insert('ivendpay_payment_order', [
            'invoice' => $invoice,
            'order_id' => (int) $order_id,
        ])) {
            return false;
        }

        return true;
    }

    private function createInvoiceCurl($dataValue) {
        $apiKey = Configuration::get('IVENDPAY_API_KEY');

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://gate.ivendpay.com/api/v3/create",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($dataValue),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "X-API-KEY: " . $apiKey
            ]
        ]);

        $response = json_decode(curl_exec($curl), true);

        if (empty($response['data'][0]['payment_url']) || empty($response['data'][0]['invoice'])) {
            return false;
        }

        return $response['data'][0];
    }
}
