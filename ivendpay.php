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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ivendpay extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $api;
    protected $api_urls = [];
    protected $coins = [];

    public function __construct()
    {
        $this->name = 'ivendpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'ivendPay';
        $this->controllers = array('payment');
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;
        $this->displayName = $this->l('ivendPay');
        $this->description = $this->l('Pay with ivendPay, secured by ivendPay');
        $this->confirmUninstall = 'Are you sure you want to uninstall this module?';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->is_eu_compatible = 1;

        $config = Configuration::getMultiple(array('IVENDPAY_API_KEY'));
        if (!empty($config['IVENDPAY_API_KEY'])) {
            $this->api = $config['IVENDPAY_API_KEY'];
        }

        parent::__construct();

        if (!isset($this->api)) {
            $this->warning = $this->l('Account details must be configured before using this module.');
        }

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

        // API urls.
        $this->api_urls = [
            'generateOrder' => 'https://gate.ivendpay.com/api/v3/create', // POST
            'listCoins'     => 'https://gate.ivendpay.com/api/v3/coins', // GET
        ];
    }

    public function hookHeader()
    {

    }

    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('paymentOptions') ||
            !$this->registerHook('paymentReturn')) {
            return false;
        }

        if (!$this->installIvendpayOpenOrderState()) {
            return false;
        }

        if (!$this->installIvendpayCompletedOrderState()) {
            return false;
        }

        if (!$this->installIvendpayCanceledOrderState()) {
            return false;
        }

        if (!$this->installIvendpayFailedOrderState()) {
            return false;
        }

        if (!$this->createOrderTable()) {
            return false;
        }

        return true;
    }

    private function createOrderTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `'. _DB_PREFIX_ .'ivendpay_payment_order`(
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `invoice` VARCHAR(255) NOT NULL,        
                `order_id` INT(10) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `invoice` (`invoice`)
            )';

        if (!Db::getInstance()->Execute($sql)) {
            return false;
        }

        return true;
    }

    protected function dropOrderTable()
    {
        $sql = 'DROP TABLE `'. _DB_PREFIX_ .'ivendpay_payment_order`';

        if (!Db::getInstance()->Execute($sql)) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('IVENDPAY_API_KEY')
            || !$this->dropOrderTable()
            || !parent::uninstall())
            return false;
        return true;
    }

    public function getContent()
    {
        if (Tools::isSubmit('submit' . $this->name)) {
            if (Tools::isSubmit('btnSubmit')) {
                if (!Tools::getValue('IVENDPAY_API_KEY'))
                    $this->_postErrors[] = $this->l('Access token is required.');
            }

            if (!count($this->_postErrors)) {
                $configValueApi = (string) Tools::getValue('IVENDPAY_API_KEY');

                // value is ok, update it and display a confirmation message
                Configuration::updateValue('IVENDPAY_API_KEY', $configValueApi);
                $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        $this->_html .= '<br />';

        return $this->_html.$this->displayForm();
    }

    public function displayForm()
    {
      $options = $this->listCoins();

      // Init Fields form array
      $form = [
          'form' => [
              'legend' => [
                  'title' => $this->trans('Settings', [], 'Admin.Global'),
                  'icon' => 'icon-cogs',
              ],
              'input' => [
                  [
                      'type' => 'text',
                      'label' => $this->l('Access token'),
                      //'desc'    => $this->l('Choose options.'),
                      'name' => 'IVENDPAY_API_KEY',
                      'size' => 10,
                      'required' => true,
                  ]
              ],
              'submit' => [
                  'title' => $this->l('Save'),
                  'class' => 'btn btn-default pull-right',
              ],
          ],
      ];

      $helper = new HelperForm();

      // Module, token and currentIndex
      $helper->table = $this->table;
      $helper->name_controller = $this->name;
      $helper->token = Tools::getAdminTokenLite('AdminModules');
      $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
      $helper->submit_action = 'submit' . $this->name;

      // Default language
      $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

      // Load current value into the form
      $helper->fields_value['IVENDPAY_API_KEY'] = Tools::getValue('IVENDPAY_API_KEY', Configuration::get('IVENDPAY_API_KEY'));

      return $helper->generateForm([$form]);
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getPaylinkPaymentOption(),
        ];

        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        echo $params;
    }

    public function getConfigFieldsValues()
    {
        return array(
            'IVENDPAY_API_KEY' => Tools::getValue('IVENDPAY_API_KEY', Configuration::get('IVENDPAY_API_KEY')),
        );
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getPaylinkPaymentOption()
    {
        $options = $this->listCoins();

        $this->context->smarty->assign('listCoins', $options);

        $paylinkOption = new PaymentOption();
        $paylinkOption
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
            ->setInputs([
                [
                    'type'     => 'hidden',
                    'name'     => 'select_coin',
                    'required' => true,
                    'value'    => '',
                ],
            ])
            ->setAdditionalInformation($this->context->smarty->fetch('module:ivendpay/views/templates/front/payment.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment.png'));

        return $paylinkOption;
    }

    private function installIvendpayOpenOrderState()
    {
        if (Configuration::get('PS_OS_IVENDPAY_INIT') < 1) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages(false) as $language) {
                $order_state->name[(int)$language['id_lang']] = 'Payment Requested';
            }
            $order_state->invoice = false;
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->color = "RoyalBlue";
            $order_state->unremovable = true;
            $order_state->hidden = false;
            $order_state->logable = false;
            $order_state->delivery = false;
            $order_state->shipped = false;
            $order_state->paid = false;
            $order_state->deleted = false;
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue("PS_OS_IVENDPAY_INIT", $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }

    private function installIvendpayCompletedOrderState()
    {
        if (Configuration::get('PS_OS_IVENDPAY_COMPLETED') < 1) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages(false) as $language) {
                $order_state->name[(int)$language['id_lang']] = 'Payment Completed';
            }
            $order_state->invoice = true;
            $order_state->send_email = true;
            $order_state->module_name = $this->name;
            $order_state->color = "LimeGreen";
            $order_state->unremovable = true;
            $order_state->hidden = false;
            $order_state->logable = false;
            $order_state->delivery = false;
            $order_state->shipped = false;
            $order_state->paid = true;
            $order_state->deleted = false;
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue("PS_OS_IVENDPAY_COMPLETED", $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }

    private function installIvendpayCanceledOrderState()
    {
        if (Configuration::get('PS_OS_IVENDPAY_CANCELED') < 1) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages(false) as $language) {
                $order_state->name[(int)$language['id_lang']] = 'Payment Canceled';
            }
            $order_state->invoice = false;
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->color = "OrangeRed";
            $order_state->unremovable = true;
            $order_state->hidden = false;
            $order_state->logable = false;
            $order_state->delivery = false;
            $order_state->shipped = false;
            $order_state->paid = false;
            $order_state->deleted = false;
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue("PS_OS_IVENDPAY_CANCELED", $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }

    private function installIvendpayFailedOrderState()
    {
        if (Configuration::get('PS_OS_IVENDPAY_FAILED') < 1) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages(false) as $language) {
                $order_state->name[(int)$language['id_lang']] = 'Payment Failed';
            }
            $order_state->invoice = false;
            $order_state->send_email = false;
            $order_state->module_name = $this->name;
            $order_state->color = "Red";
            $order_state->unremovable = true;
            $order_state->hidden = false;
            $order_state->logable = false;
            $order_state->delivery = false;
            $order_state->shipped = false;
            $order_state->paid = false;
            $order_state->deleted = false;
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue("PS_OS_IVENDPAY_FAILED", $order_state->id);
            } else {
                return false;
            }
        }
        return true;
    }

    protected function listCoins()
    {
        if (! empty($this->coins)) {
            return $this->coins;
        }

        $listCoins = [];

        if (! empty($this->api)) {
            $remoteCoins = $this->get_remote_list_conins();

            if (! empty($remoteCoins['list'])) {
                foreach ($remoteCoins['list'] as $value) {
                    $listCoins[] = [
                        'id' => $value['id'],
                        'name' => $value['name'] .' ('. $value['ticker_name'].')'
                    ];
                }
            }
        }

        $this->coins = $listCoins;

        return $listCoins;
    }

    /**
  	 * Get list coins from remote API.
  	 *
  	 * @since 1.0.0
  	 * @return array|false
  	 */
  	public function get_remote_list_conins()
    {
  		return $this->api_request([], $this->api_urls['listCoins'], 'GET');
  	}

    /**
  	 * API request.
  	 *
  	 * @since 1.0.0
  	 * @param array  $params Query string parameters.
  	 * @param string $url URL.
  	 * @param string $type Request type.
  	 * @return array|false
  	 */
  	public function api_request( $params, $url, $type = 'POST' )
    {
  		$curl = curl_init();

  		curl_setopt_array($curl, [
  			CURLOPT_URL => $url,
  			CURLOPT_RETURNTRANSFER => true,
  			CURLOPT_ENCODING => "",
  			CURLOPT_MAXREDIRS => 10,
  			CURLOPT_TIMEOUT => 30,
  			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  			CURLOPT_CUSTOMREQUEST => $type,
  			CURLOPT_POSTFIELDS => json_encode($params),
  			CURLOPT_HTTPHEADER => [
  				"Content-Type: application/json",
  				"X-API-KEY: " . $this->api
  			],
  		]);

        $raw = curl_exec($curl);

  		$response = json_decode($raw, true);

  		return $response;
  	}

}
