<?php
if (!defined('_PS_VERSION_'))
    exit;

class Billplz extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    protected $api_key;
    protected $collection_id;
    protected $x_signature;

    public function __construct()
    {
        $this->name = 'billplz';
        $this->tab = 'payments_gateways';
        $this->version = '3.2.0';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6.99.99');
        //$this->limited_countries = array('my');
        $this->author = 'BILLPLZ SDN BHD';
        $this->controllers = array('return', 'validation');
        $this->is_eu_compatible = 0;

        $this->currencies = true;
        $this->currencies_mode = 'radio';

        $config = Configuration::getMultiple(array(
            'BILLPLZ_IS_STAGING',
            'BILLPLZ_API_KEY',
            'BILLPLZ_COLLECTION_ID',
            'BILLPLZ_X_SIGNATURE'));

        if (!empty($config['BILLPLZ_IS_STAGING'])) {
            $this->is_staging = $config['BILLPLZ_IS_STAGING'];
        }
        
        if (!empty($config['BILLPLZ_API_KEY'])) {
            $this->api_key = $config['BILLPLZ_API_KEY'];
        }

        if (!empty($config['BILLPLZ_COLLECTION_ID'])) {
            $this->collection_id = $config['BILLPLZ_COLLECTION_ID'];
        }

        if (!empty($config['BILLPLZ_X_SIGNATURE'])) {
            $this->x_signature = $config['BILLPLZ_X_SIGNATURE'];
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Billplz');
        $this->description = $this->l('Accept payments using Billplz.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        
        if (!isset($this->is_staging) || !isset($this->api_key) || !isset($this->collection_id) || !isset($this->x_signature)) {
            $this->warning = $this->l('API Key, Collection ID and X Signature Key must be configured before using this module.');
        }

        if (!count(Currency::checkPaymentCurrencies($this->id)))
            $this->warning = $this->l('No currency has been set for this module.');
    }

    public function install()
    {
        Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'billplz` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `cart_id` int(11) NOT NULL,
                `bill_id` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `bill_id` (`bill_id`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;'
        );

        if (!parent::install() || !$this->registerHook('paymentReturn') || !$this->registerHook('payment') || !$this->installOrderState()) {
            return false;
        }
        return true;

    }

    public function installOrderState()
    {
        if (!Configuration::get('BILLPLZ_OS_WAITING') || !Validate::isLoadedObject(new OrderState(Configuration::get('BILLPLZ_OS_WAITING')))) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Awaiting for Billplz payment';
            }

            $order_state->send_email = false;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            if ($order_state->add()) {
                $source = _PS_MODULE_DIR_ . 'billplz/logo.png';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int) $order_state->id . '.png';
                copy($source, $destination);
            }

            Configuration::updateValue('BILLPLZ_OS_WAITING', (int) $order_state->id);
        }
        return true;
    }

    public function uninstall()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'billplz`;');

        if (!Configuration::deleteByName('BILLPLZ_IS_STAGING')
            || !Configuration::deleteByName('BILLPLZ_API_KEY')
            || !Configuration::deleteByName('BILLPLZ_COLLECTION_ID')
            || !Configuration::deleteByName('BILLPLZ_X_SIGNATURE')
            || !Configuration::deleteByName('BILLPLZ_OS_WAITING')
            || !parent::uninstall()) {
            return false;
        }

        return true;
    }

    /**
     * Validate the form submitted in configuration setting
     * 
     */
    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('BILLPLZ_IS_STAGING')) {
                $this->_postErrors[] = $this->l('Billplz mode are required.');
            } elseif (!Tools::getValue('BILLPLZ_API_KEY')) {
                $this->_postErrors[] = $this->l('API Key are required.');
            } elseif (!Tools::getValue('BILLPLZ_COLLECTION_ID')) {
                $this->_postErrors[] = $this->l('Collection ID is required.');
            } elseif (!Tools::getValue('BILLPLZ_X_SIGNATURE')) {
                $this->_postErrors[] = $this->l('X Signature Key is required.');
            }
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('BILLPLZ_IS_STAGING', Tools::getValue('BILLPLZ_IS_STAGING'));
            Configuration::updateValue('BILLPLZ_API_KEY', Tools::getValue('BILLPLZ_API_KEY'));
            Configuration::updateValue('BILLPLZ_COLLECTION_ID', Tools::getValue('BILLPLZ_COLLECTION_ID'));
            Configuration::updateValue('BILLPLZ_X_SIGNATURE', Tools::getValue('BILLPLZ_X_SIGNATURE'));
        }
        $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->renderForm();
        return $this->_html;
    }

    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }
        
        if (!$this->checkCurrency($params['cart'])){
            return;
        }

        $cart = $this->context->cart;

        $this->smarty->assign(array(
            'cart_id' => $cart->id,
            'logo_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/logo.png',
            'payment_logo_url' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/images/payment_logo.png',
            'process_url' => $this->context->link->getModuleLink($this->name, 'process', array(), true),
        ));
 
        return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
    }
    
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = Currency::getPaymentCurrencies($this->id);

        if (is_array($currencies_module))
            foreach ($currencies_module as $currency_module)
                if ($currency_order->id == $currency_module['id_currency'])
                    return true;
        return false;
    }

    public function renderForm()
    {
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'radio',
                    'label' => $this->l('Account Type'),
                    'name' => 'BILLPLZ_IS_STAGING',
                    'values' => array(
                            array(
                                'id' => 'production',
                                'label' => $this->l('Production'),
                                'value' => 'no'
                            ),
                            array(
                              'id' => 'sandbox',
                              'label' => $this->l('Sandbox'),
                              'value' => 'yes'
                            )
                        ),
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('API Secret Key'),
                    'name' => 'BILLPLZ_API_KEY',
                    'desc' => $this->l('It can be from Production or Staging. It can be retrieved from Billplz Account Settings page.'),
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Collection ID'),
                    'name' => 'BILLPLZ_COLLECTION_ID',
                    'desc' => $this->l('Enter your chosen specific Billing Collection ID. It can be retrieved from Billplz Billing page.'),
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('X Signature Key'),
                    'name' => 'BILLPLZ_X_SIGNATURE',
                    'desc' => $this->l('It can be from Production or Staging. It can be retrieved from Billplz Account Settings page.'),
                    'required' => true
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?: 0;
        $this->fields_form = array();
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues()
        );

        return $helper->generateForm($fields_form);
    }

    public function getConfigFieldsValues()
    {
        return array(
            'BILLPLZ_IS_STAGING' => Tools::getValue('BILLPLZ_IS_STAGING', Configuration::get('BILLPLZ_IS_STAGING')),
            'BILLPLZ_API_KEY' => Tools::getValue('BILLPLZ_API_KEY', Configuration::get('BILLPLZ_API_KEY')),
            'BILLPLZ_COLLECTION_ID' => Tools::getValue('BILLPLZ_COLLECTION_ID', Configuration::get('BILLPLZ_COLLECTION_ID')),
            'BILLPLZ_X_SIGNATURE' => Tools::getValue('BILLPLZ_X_SIGNATURE', Configuration::get('BILLPLZ_X_SIGNATURE')),
        );
    }
}
