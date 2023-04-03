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

class IvendpayPostbackModuleFrontController extends ModuleFrontController
{
    protected $access_token = '';

    public function __construct($response = array())
    {
        parent::__construct($response);
    }

    public function initContent()
    {
        parent::initContent();
    }

    public function postProcess()
    {
        $type = (string) Tools::getValue('type', null);
        $invoice = Tools::getValue('invoice', null);
        $this->access_token = Configuration::get('IVENDPAY_API_KEY');

        if ($type === 'cancel' && ! empty($invoice)) {
            $row = $this->sqlGetOrderIdFromInvoice($invoice);
            if (! $row) {
                $this->setJsonMessage('error. Invoice not found');
            }

            Tools::redirect('index.php?controller=order-detail&id_order='.$row['order_id']);

        } elseif ($type === 'success' && ! empty($invoice)) {
            $row = $this->sqlGetOrderIdFromInvoice($invoice);
            if (! $row) {
                $this->setJsonMessage('error. Invoice not found');
            }

            $orderSuccess = new Order($row['order_id']);
            if (! $orderSuccess) {
                $this->setJsonMessage('error. Cannot find order');
            }

            if (version_compare(_PS_VERSION_, '1.5', '>=')) {
                $payment = $orderSuccess->getOrderPaymentCollection();
                if (isset($payment[0])) {
                    $payment[0]->transaction_id = pSQL($invoice);
                    $payment[0]->save();
                }
            }

            Tools::redirect('index.php?controller=order-detail&id_order='.$row['order_id']);
        }


        if (! $this->checkHeaderApiKey()) {
            $this->setJsonMessage('error X-API-KEY');
        }

        $findInvoice = false;
        $post_data = [];

        if (empty($_POST)) {
            $this->setJsonMessage('error. Empty $_POST request');
        }

        foreach ($_POST as $key => $value) {
            $key = str_replace([':_', ',_'], [':', ','], $key);
            $post_data = json_decode(html_entity_decode(stripslashes($key)), true);

            if (! empty($post_data['payment_status']) && ! empty($post_data['invoice'])) {
                $findInvoice = true;
                break;
            }
        }

        if (! $findInvoice) {
            $this->setJsonMessage('error. Invoice from request not found');
        }

        $invoice = pSQL($post_data['invoice']);
        $row = $this->sqlGetOrderIdFromInvoice($invoice);

        if (! $row) {
            $this->setJsonMessage('error. Invoice not found');
        }

        $shop_order_id = $row['order_id'];
        $order         = new Order($shop_order_id);

        if (! $order) {
            $this->setJsonMessage('error. Cannot find order');
        }

        $details = $this->get_remote_order_details($invoice);
        if (! $details) {
            $this->setJsonMessage('error. Remote invoice not found');
        }

        $statusPayment = pSQL($details['status']);
        if ($statusPayment === 'TIMEOUT') {
            $order->setCurrentState(Configuration::get('PS_OS_IVENDPAY_FAILED'));
            $order->save();
        } elseif ($statusPayment === 'CANCELED') {
            $order->setCurrentState(Configuration::get('PS_OS_IVENDPAY_CANCELED'));
            $order->save();
        } elseif ($statusPayment === 'PAID') {
            $order->setCurrentState(Configuration::get('PS_OS_IVENDPAY_COMPLETED'));
            $order->save();
        }

        $this->setJsonMessage('Done', 200);
        exit();
    }

    protected function sqlGetOrderIdFromInvoice($invoice)
    {
        $invoice = pSQL($invoice);

        $sql = "SELECT * FROM ". _DB_PREFIX_ ."ivendpay_payment_order WHERE `invoice`='{$invoice}'";
        return Db::getInstance()->getRow($sql);
    }

    protected function checkHeaderApiKey()
    {
        $checkHeaderApiKey = false;
        $headers = apache_request_headers();

        foreach ($headers as $header => $value) {
            if (mb_strtoupper($header) === 'X-API-KEY') {
                if ($value === $this->access_token) {
                    $checkHeaderApiKey = true;
                    break;
                }
            }
        }

        return $checkHeaderApiKey;
    }

    protected function setJsonMessage($msg, $code = 403)
    {
        http_response_code($code);
        echo json_encode([ 'msg' => $msg ]);
        exit();
    }

    private function get_remote_order_details($invoice) {
        $apiKey = $this->access_token;

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://gate.ivendpay.com/api/v3/bill/" . $invoice,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "X-API-KEY: " . $apiKey
            ]
        ]);

        $response = json_decode(curl_exec($curl), true);

        if (empty($response['data'][0]['status'])) {
            return false;
        }

        return $response['data'][0];
    }
}
