<?php
/**
 * 2007-2019 PrestaShop
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
 *  @copyright 2007-2019 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

class BillplzReturnModuleFrontController extends ModuleFrontController
{
    //public $php_self = 'return';

    public function postProcess()
    {
        require_once _PS_MODULE_DIR_ . 'billplz/classes/BillplzConnect.php';
        $x_signature = trim(Configuration::get('BILLPLZ_X_SIGNATURE'));
        try {
            $data = BillplzConnect::getXSignature($x_signature);
        } catch (Exception $e) {
            header('HTTP/1.1 403 X Signature matching failed', true, 403);
            exit();
        }

        $sql = 'SELECT `cart_id` FROM `' . _DB_PREFIX_ . 'billplz` WHERE `bill_id` = "' . $data['id'] . '"';
        $result = Db::getInstance()->getRow($sql);

        if (empty($result)) {
            exit('No valid order');
        }

        $cart_id = $result['cart_id'];
        $cart = new Cart($cart_id);
        //$total = $cart->getOrderTotal(true, Cart::BOTH);

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php');
        }

        if ($data['type'] === 'redirect') {
            if (!$data['paid']) {
                Tools::redirect('index.php');
            } else {
                Tools::redirect(
                    $this->context->link->getPageLink('history')
                );
            }
        } else {
            if ($data['paid']) {
                $order_id = Order::getOrderByCartId($cart_id);
                $order = new Order($order_id);

                if ($order->getCurrentState() != Configuration::get('PS_OS_PAYMENT')) {

                    $new_history = new OrderHistory();
                    $new_history->id_order = $order_id;
                    $new_history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), $order, true);
                    $new_history->addWithemail(true);

                    $payment = $order->getOrderPaymentCollection();
                    if (isset($payment[0]))
                    {
                        $payment[0]->transaction_id = $data['id'];
                        $payment[0]->save();
                    }
                }
            }
            exit;
        }
    }
}
