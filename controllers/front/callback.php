<?php
require_once __DIR__ . '/billplz-api.php';

class BillplzCallbackModuleFrontController extends ModuleFrontController
{

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $this->display_column_left = false;
        parent::initContent();

        // Check this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'billplz') {
                $authorized = true;
                break;
            }
        }
        if (!$authorized)
            die();
    }

    public function process()
    {

        $config = Configuration::getMultiple(array('BILLPLZ_APIKEY', 'BILLPLZ_X_SIGNATURE_KEY'));
        if (isset($config['BILLPLZ_APIKEY']))
            $api_key = $config['BILLPLZ_APIKEY'];
        if (isset($config['BILLPLZ_X_SIGNATURE_KEY']))
            $x_signature = $config['BILLPLZ_X_SIGNATURE_KEY'];

        $data = Billplz_API::getCallbackData($x_signature);

        if ($data['paid']) {

            $billplz = new Billplz_API($api_key);
            // Verify and get the Callback Data
            $moreData = $billplz->check_bill($data['id']);

            // Tak boleh guna $this->context sebab ini server side
            $cart = new Cart($moreData['reference_1']);
            
            // Test samada request tersebut valid atau tak
            if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
                die();
            
             // Check wether the bills are in database yet or not
            $sql_query = 'SELECT `billplz_bills_id` FROM `' . _DB_PREFIX_ . 'billplz_orders` WHERE `billplz_bills_id` = "' . $data['id'] . '"';
            $sql_result = Db::getInstance()->getRow($sql_query);

            // Save the to Database
            $this->saveToDB($sql_result, $cart, $moreData);
        } 

        echo "ALL IS WELL";
        exit;
    }

    private function saveToDB($sql_result, $cart, $moreData)
    {

        if (empty($sql_result)) {
            
            /*
             * Get total Paid
             */
            $total = (float) number_format(($moreData['amount'] / 100), 2);

            // Dapatkan currency ID berdasarkan ISO Code
            $currencyid = Currency::getIdByIsoCode($moreData['reference_2']);

            // Load customer data
            $customer = new Customer($cart->id_customer);
           
            if (!Validate::isLoadedObject($customer))
                die();
          
            // Validate order and mark the order as Paid
            $this->module->validateOrder(
                $cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, '(IPN Callback) Billplz Bills URL: <a href="' . $moreData['url'] . '" target="_blank">' . $moreData['url'] . '</a>', null, (int) $currencyid, false, $customer->secure_key
            );
            // Insert to Billplz_Orders Database to prevent multiple callback
            Db::getInstance(_PS_USE_SQL_SLAVE_)->insert('billplz_orders', array(
                'order_id' => pSQL((int) $cart->id), // PrestaShop standard is id_order
                'billplz_bills_id' => pSQL($moreData['id']),
            ));
        }
    }
}
