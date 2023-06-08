<?php

class BillplzProcessModuleFrontController extends ModuleFrontController
{

    public $php_self = 'process';

    public function initContent()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active){
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module)
            if ($module['name'] == 'billplz')
            {
                $authorized = true;
                break;
            }
        if (!$authorized){
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $id_address = (int) $cart->id_address_delivery;
        if (($id_address == 0) && ($customer)) {
            $id_address = Address::getFirstCustomerAddressId($customer->id);
        }

        $address = new Address($id_address);

        $currency = $this->context->currency;
        $total = $cart->getOrderTotal(true, Cart::BOTH);

        require_once _PS_MODULE_DIR_ . '/billplz/classes/BillplzAPI.php';
        require_once _PS_MODULE_DIR_ . '/billplz/classes/BillplzConnect.php';

        $config = Configuration::getMultiple(array('BILLPLZ_IS_STAGING', 'BILLPLZ_API_KEY', 'BILLPLZ_COLLECTION_ID'));

        $products = $cart->getProducts();
        $product_description = '';
        foreach ($products as $product) {
            $product_description .= "{$product['name']} ";
        }

        $parameter = array(
            'collection_id' => trim($config['BILLPLZ_COLLECTION_ID']),
            'email' => trim($customer->email),
            'mobile' => trim((empty($address->phone)) ? $address->phone_mobile : $address->phone),
            'name' => trim($customer->firstname . " " . $customer->lastname),
            'amount' => (string) ($total * 100),
            'callback_url' => $this->context->link->getModuleLink($module['name'], 'return', array(), true),
            'description' => mb_substr($product_description, 0, 200),
        );

        if (empty($parameter['mobile']) && empty($parameter['email'])) {
            $parameter['email'] = 'noreply@billplz.com';
        }

        if (empty($parameter['name'])) {
            $parameter['name'] = 'Payer Name Unavailable';
        }

        $this->module->validateOrder($cart->id, Configuration::get('BILLPLZ_OS_WAITING'), '0.0', $this->module->displayName, "", array(), (int) $currency->id, false, $customer->secure_key);

        $order_id = Order::getOrderByCartId($cart->id);
        $order = new Order($order_id);
        $order->setInvoice();

        $optional = array(
            'redirect_url' => $parameter['callback_url'],
            'reference_1_label' => 'Cart ID',
            'reference_1' => $cart->id,
            'reference_2_label' => 'Order ID',
            'reference_2' => $order_id,
        );

        $connect = new BillplzConnect(trim($config['BILLPLZ_API_KEY']));
        $connect->setStaging($config['BILLPLZ_IS_STAGING'] == 'yes');

        $billplz = new BillplzApi($connect);
        list($rheader, $rbody) = $billplz->toArray($billplz->createBill($parameter, $optional, '0'));

        if ($rheader !== 200) {
            if (isset($rbody['error'])) {
                if (is_array($rbody['error']['message'])) {
                    $error_messages = [];

                    foreach ($rbody['error']['message'] as $error_message) {
                        $error_messages[] = $error_message;
                    }

                    $error_messages = implode(' | ', $error_messages);
                } else {
                    $error_messages = $rbody['error']['message'];
                }

                $formatted_error_message = '[' . $rbody['error']['type'] . '] ' . $error_messages;

                PrestaShopLogger::addLog('BillplzValidationModuleFrontController::postProcess - Unable to create a bill: ' . $formatted_error_message, 4, (int) $rheader, 'Order', (int) $order_id, true);
            } else {
                PrestaShopLogger::addLog('BillplzValidationModuleFrontController::postProcess - Unable to create a bill', 4, (int) $rheader, 'Order', (int) $order_id, true);
            }

            die(Tools::displayError($this->module->l('Payment error! Please contact admin for further assistance.', 'validation')));
        }

        Db::getInstance()->insert(
            'billplz',
            array(
                'cart_id' => pSQL((int) $cart->id),
                'bill_id' => pSQL($rbody['id']),
            )
        );

        Tools::redirect($rbody['url']);
    }
}
