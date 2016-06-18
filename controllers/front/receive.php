<?php
/**
* 2007-2015 PrestaShop
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
*  @author    Wan Zulkarnain <sales@wanzul-hosting.com>
*  @copyright 2007-2013 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.6.0
 */

class billplzReceiveModuleFrontController extends ModuleFrontController
{

	public function initContent()
	{

		$this->display_column_left = false;
		parent::initContent();

		$this->billplz = new billplz();

		if ( Tools::getIsset('billplz') ) :
			$tranID  = implode(Tools::getValue('billplz'));
			
			//RE CURL
			$host = Configuration::get('PURL') == '1' ? 'https://www.billplz.com/api/v3/bills/' : 'https://billplz-staging.herokuapp.com/api/v3/bills/';
			$process = curl_init($host.$tranID);
			curl_setopt($process, CURLOPT_HEADER, 0);
			curl_setopt($process, CURLOPT_USERPWD, Configuration::get('MCODE') . ":");
			curl_setopt($process, CURLOPT_TIMEOUT, 30);
			curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
			$return = curl_exec($process);
			curl_close($process);
			$arr = Tools::jsonDecode($return, true);
			//RE CURL
			
			// If Response page signature match
			if ( Tools::getValue('scvalidate') == $arr['reference_1'] ) :
			
				// Check if the order is successful
				if ( $arr['paid'] ) :

					$cart = $this->context->cart;
					if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
						Tools::redirect('index.php?controller=order&step=1');

					// Check this payment option is still available in case the customer changed his address just before the end of the checkout process
					$authorized = false;
					foreach (Module::getPaymentModules() as $module)
						if ($module['name'] == 'billplz')
						{
							$authorized = true;
							break;
						}
					if (!$authorized)
						die($this->module->l('This payment method is not available.', 'validation'));

					$customer = new Customer($cart->id_customer);
					if (!Validate::isLoadedObject($customer))
						Tools::redirect('index.php?controller=order&step=1');

					$currency = $this->context->currency;
					$total = (float)$cart->getOrderTotal(true, Cart::BOTH);

					$this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, NULL, null, (int)$currency->id, false, $customer->secure_key);
					Tools::redirect('index.php?controller=history');				
				elseif ( !$arr['paid'] ):
					Tools::redirect('index.php?controller=order');
				else:

					$this->context->smarty->assign(array(
						'unsuccessful' => 'Sorry, processing your order is unsuccessful due to an error. Please contact our support team.'
					));	
				endif;
			else:

				$this->context->smarty->assign(array(
					'mismatched' => 'Sorry, response signature mismatched.'
				));
			endif;
			
			$this->context->smarty->assign(array(
				'status' => $arr['state']
			));			
		endif;

		$this->setTemplate('receive.tpl');
	}	
}