<?php
require_once __DIR__ . '/billplz-api.php';

class BillplzProcessModuleFrontController extends ModuleFrontController
{

    public $php_self = 'process';

    public function initContent()
    {
        $this->display_column_left = false;

        // Get Configuration Data

        $config = Configuration::getMultiple(array('BILLPLZ_APIKEY', 'BILLPLZ_COLLECTIONID', 'BILLPLZ_BILLNOTIFY'));
        if (isset($config['BILLPLZ_APIKEY']))
            $api_key = $config['BILLPLZ_APIKEY'];
        if (isset($config['BILLPLZ_COLLECTIONID']))
            $collection_id = $config['BILLPLZ_COLLECTIONID'];
        if (isset($config['BILLPLZ_BILLNOTIFY']))
            $deliver = $config['BILLPLZ_BILLNOTIFY'];

        $amount = isset($_POST['amount']) ? $_POST['amount'] : 300;
        $description = isset($_POST['proddesc']) ? $_POST['proddesc'] : exit('No Valid Description');
        $email = isset($_POST['email']) ? $_POST['email'] : exit('No Valid Email');
        $mobile = isset($_POST['mobile']) ? $_POST['mobile'] : exit('No Valid Mobile Number');
        $name = isset($_POST['name']) ? $_POST['name'] : exit('No Valid Name');
        $hash = isset($_POST['hash']) ? $_POST['hash'] : exit('No Valid Hash');
        $redirect_url = isset($_SERVER['HTTPS']) ? ($_SERVER['HTTPS'] == "on" ? 'https://' : 'http://') : 'http://';
        $callback_url = isset($_SERVER['HTTPS']) ? ($_SERVER['HTTPS'] == "on" ? 'https://' : 'http://') : 'http://';

        $redirect_url .= $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'index.php?fc=module&module=billplz&controller=return';
        $callback_url .= $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'index.php?fc=module&module=billplz&controller=callback';
        $reference_1 = isset($_POST['cartid']) ? $_POST['cartid'] : '5';
        $reference_2 = isset($_POST['currency']) ? $_POST['currency'] : 'MYR';


        // Check for possible fake form request. If fake, stop

        $this->checkDataIntegrity($hash, $reference_1, $amount);

        $billplz = new Billplz_API(trim($api_key));
        $billplz
            ->setCollection($collection_id)
            ->setName($name)
            ->setAmount($amount)
            ->setDeliver($deliver)
            ->setMobile($mobile)
            ->setEmail($email)
            ->setDescription($description)
            ->setReference_1($reference_1)
            ->setReference_1_Label('Cart ID')
            ->setReference_2($reference_2)
            ->setPassbackURL($callback_url, $redirect_url)
            ->create_bill(true);


        $url = $billplz->getURL();

        if (empty($url)) {
            exit('Something went wrong! ' . $billplz->getErrorMessage());
        } else {
            Tools::redirect($url);
        }
    }
    /*
     * Signature using MD5, combination of API Key, Customer Email and Amount
     */

    private function checkDataIntegrity($old_hash, $cart_id, $amount)
    {
        $x_signature = Configuration::get('BILLPLZ_X_SIGNATURE_KEY');
        $raw_string = $cart_id . $amount;
        $filtered_string = preg_replace("/[^a-zA-Z0-9]+/", "", $raw_string);
        $hash = hash_hmac('sha256', $filtered_string, $x_signature);
        if ($hash != $old_hash)
            die('Invalid Request. Reason: Input has been tempered');
    }
}
