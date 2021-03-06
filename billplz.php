<?php
if (!defined('_PS_VERSION_'))
    exit;

class Billplz extends PaymentModule
{

    public function __construct()
    {
        $this->name = 'billplz';
        $this->tab = 'payments_gateways';
        $this->version = '3.0.4';
        $this->author = 'Wan @ Billplz';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6.99.99');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array('BILLPLZ_APIKEY', 'BILLPLZ_X_SIGNATURE_KEY', 'BILLPLZ_COLLECTIONID'));
        if (isset($config['BILLPLZ_APIKEY']))
            $this->api_key = $config['BILLPLZ_APIKEY'];
        if (isset($config['BILLPLZ_X_SIGNATURE_KEY']))
            $this->x_signature = $config['BILLPLZ_X_SIGNATURE_KEY'];
        if (isset($config['BILLPLZ_COLLECTIONID']))
            $this->collection_id = $config['BILLPLZ_COLLECTIONID'];

        parent::__construct();

        $this->displayName = $this->l('Billplz Payment Gateway');
        $this->description = $this->l('Fair Payment Software. Accept FPX payment.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        
        if (!count(Currency::checkPaymentCurrencies($this->id)))
			$this->warning = $this->l('No currency has been set for this module.');

        if (!Configuration::get('Billplz'))
            $this->warning = $this->l('No name provided');
        if (!isset($this->api_key))
            $this->warning = $this->l('You need to set Billplz API Secret Key!');
        if (!isset($this->x_signature))
            $this->warning = $this->l('You need to set Billplz X Signature Key!');
    }

    public function install()
    {

        // Create tables to store status data to prevent multiple callback
        Db::getInstance()->execute('
				CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'billplz_orders` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
                                `order_id` int(11) NOT NULL,
				`billplz_bills_id` varchar(255) NOT NULL,
				PRIMARY KEY (`id`)
                                ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
				');

        // Pre-set the default values
        Configuration::updateValue('BILLPLZ_BILLNOTIFY', false);

        return parent::install() &&
            Configuration::updateValue('Billplz', 'Billplz MODULE') &&
            $this->registerHook('payment');
    }

    public function uninstall()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'billplz_orders`;');
        return parent::uninstall() &&
            Configuration::deleteByName('BILLPLZ_APIKEY') &&
            Configuration::deleteByName('BILLPLZ_COLLECTIONID') &&
            Configuration::deleteByName('BILLPLZ_X_SIGNATURE_KEY') &&
            Configuration::deleteByName('BILLPLZ_BILLNOTIFY');
    }

    /**
     * Validate the form submitted in configuration setting
     * 
     */
    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('BILLPLZ_APIKEY'))
                $this->_postErrors[] = $this->l('API Key is required');
            if (!Tools::getValue('BILLPLZ_X_SIGNATURE_KEY'))
                $this->_postErrors[] = $this->l('X Signature Key is required.');
        }
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {

            // Update value bila tekan Submit dekat Back Office

            Configuration::updateValue('BILLPLZ_APIKEY', Tools::getValue('BILLPLZ_APIKEY'));
            Configuration::updateValue('BILLPLZ_COLLECTIONID', Tools::getValue('BILLPLZ_COLLECTIONID'));
            Configuration::updateValue('BILLPLZ_X_SIGNATURE_KEY', Tools::getValue('BILLPLZ_X_SIGNATURE_KEY'));
            Configuration::updateValue('BILLPLZ_BILLNOTIFY', Tools::getValue('BILLPLZ_BILLNOTIFY'));
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }
        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        // Get default Language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('API Secret Key'),
                    'name' => 'BILLPLZ_APIKEY',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Collection ID'),
                    'name' => 'BILLPLZ_COLLECTIONID',
                    'size' => 20,
                    'required' => false
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('X Signature Key'),
                    'name' => 'BILLPLZ_X_SIGNATURE_KEY',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Delivery Notification'),
                    'name' => 'BILLPLZ_BILLNOTIFY',
                    'col' => '4',
                    'default_value' => '0',
                    'options' => array(
                        'query' => array(
                            array(
                                'id' => 0,
                                'name' => $this->l('No Notification')
                            ),
                            array(
                                'id' => 1,
                                'name' => $this->l('Email Notification')
                            ),
                            array(
                                'id' => 2,
                                'name' => $this->l('SMS Notification')
                            ),
                            array(
                                'id' => 3,
                                'name' => $this->l('Both Notification')
                            )
                        ),
                        'id' => 'id',
                        'name' => 'name'
                    )
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['BILLPLZ_APIKEY'] = Configuration::get('BILLPLZ_APIKEY');
        $helper->fields_value['BILLPLZ_COLLECTIONID'] = Configuration::get('BILLPLZ_COLLECTIONID');
        $helper->fields_value['BILLPLZ_X_SIGNATURE_KEY'] = Configuration::get('BILLPLZ_X_SIGNATURE_KEY');
        $helper->fields_value['BILLPLZ_BILLNOTIFY'] = Configuration::get('BILLPLZ_BILLNOTIFY');

        return $helper->generateForm($fields_form);
    }

    public function hookPayment($params)
    {

        $cart = $this->context->cart;
        $cart_id = $cart->id;
        
        if (!$this->checkCurrency($params['cart']))
			return;
        
        $customer = new Customer((int) $cart->id_customer);
        $address = new Address(intval($cart->id_address_invoice));

        $amount = number_format($cart->getOrderTotal(true, Cart::BOTH), 2);
        $x_signature = Configuration::get('BILLPLZ_X_SIGNATURE_KEY');

        $raw_string = $cart_id . $amount;
        $filtered_string = preg_replace("/[^a-zA-Z0-9]+/", "", $raw_string);
        $hash = hash_hmac('sha256', $filtered_string, $x_signature);

        $this->smarty->assign(array(
            'cartid' => $cart_id,
            'amount' => $amount,
            'currency' => $this->context->currency->iso_code,
            'proddesc' => $this->getProductDesc($params),
            'name' => $customer->firstname . " " . $customer->lastname,
            'email' => $customer->email,
            'mobile' => ( empty($address->phone) ? $address->phone_mobile : $address->phone ),
            'logoURL' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/images/logo.jpg',
            'logoBillplz' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/logo.png',
            'processurl' => $this->context->link->getModuleLink($this->name, 'process', array(), true),
            'hash' => $hash,
            //'this_path' => $this->_path,
            //'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
        ));
        // payment.tpl should post the data to the Controller (processurl) and the controller send header (Billplz Payment Page) to the user.
        return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookPaymentReturn($params)
    {
        
    }

    public function getProductDesc($params)
    {
        $products = $params['cart']->getProducts(true);

        return $products[0]['name'];
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module))
            foreach ($currencies_module as $currency_module)
                if ($currency_order->id == $currency_module['id_currency'])
                    return true;
        return false;
    }
}
