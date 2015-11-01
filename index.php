﻿<?php

/*
  Plugin Name: Mondido Payments
  Plugin URI: https://www.mondido.com/
  Description: Mondido Payment plugin for WooCommerce
  Version: 2.0
  Author: Mondido Payments
  Author URI: https://www.mondido.com
 */

// Actions 
add_action('plugins_loaded', 'woocommerce_mondido_init', 0);
add_action('init', array('WC_Gateway_Mondido', 'check_mondido_response'));
add_action('valid-mondido-callcack', array('WC_Gateway_Mondido', 'successful_request'));

function woocommerce_mondido_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }


    class WC_Gateway_Mondido extends WC_Payment_Gateway {

        public function __construct() {

            global $woocommerce;
            $this->selected_currency	= '';
            $this->plugin_version = "2.0";

            // Currency
            if ( isset($woocommerce->session->client_currency) ) {

                // If currency is set by WPML
                $this->selected_currency = $woocommerce->session->client_currency;

            } elseif ( class_exists( 'WC_Aelia_CurrencySwitcher' ) && defined('AELIA_CS_USER_CURRENCY') ) {

                // If currency is set by WooCommerce Currency Switcher (http://dev.pathtoenlightenment.net/shop)
                $plugin_instance = WC_Aelia_CurrencySwitcher::instance();
                $this->selected_currency = strtoupper($plugin_instance->get_selected_currency());

            } else {

                // WooCommerce selected currency
                $this->selected_currency = get_option('woocommerce_currency');

            }


            $this->id = 'mondido';
            $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/mondido.png';
            $this->has_fields = false;
            $this->method_title = 'Mondido';
            $this->method_description = __('', 'mondido');
            $this->order_button_text = __('Proceed to Mondido', 'woocommerce');
            $this->liveurl = 'https://pay.mondido.com/v1/form';

            // Load forms and settings
            $this->init_form_fields();
            $this->init_settings();

            // Get from users settings
            $this->title = __('Visa/Mastercard', 'mondido');
            $this->description = __('Pay securely by Credit or Debit card through Mondido.', 'mondido');
            $this->merchant_id = $this->settings['merchant_id'];
            $this->secret = $this->settings['secret'];
            $this->password = $this->settings['password'];
            $this->currency = $this->selected_currency; //pick currency from shop
            $test = 'false';
            if($this->settings['test'] == 'yes'){
                $test = 'true';
            }
            $this->test = $test;

            $authorize = 'false';
            if($this->settings['authorize'] == 'yes'){
                $authorize = 'true';
            }
            $this->authorize = $authorize;

            // Actions
            add_action('woocommerce_api_' . strtolower(get_class()), array($this, 'check_mondido_response'));
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
            }
            add_action('woocommerce_receipt_mondido', array($this, 'receipt_page'));
        }

        /*
         * Function for get settings variabler in hash functions.
         */

        public function get_secret() {
            return $this->secret;
        }
        public function get_password() {
            return $this->password;
        }

        public function get_merchant_id() {
            return $this->merchant_id;
        }

        public function get_currency() {
            return $this->currency;
        }

        public function get_test() {
            return $this->test;
        }
        public function get_authorize() {
            return $this->authorize;
        }

        /*
         * Initialise settings form fields
         */

        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'mondido'),
                    'type' => 'checkbox',
                    'label' => __('Enable mondido Payment Module.', 'mondido'),
                    'default' => 'no'),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'mondido'),
                    'type' => 'text',
                    'description' => __('Merchant ID for Mondido')),
                'secret' => array(
                    'title' => __('Secret', 'mondido'),
                    'type' => 'text',
                    'description' => __('Given secret code from Mondido', 'mondido'),
                ),
                'password' => array(
                    'title' => __('API Password', 'mondido'),
                    'type' => 'text',
                    'description' => __('API Password from Mondido', 'mondido'),
                ),
                'test' => array(
                    'title' => __('Test', 'mondido'),
                    'type' => 'checkbox',
                    'label' => __('Set in testmode.', 'mondido'),
                    'default' => 'no'),
                'authorize' => array(
                    'title' => __('Authorize', 'mondido'),
                    'type' => 'checkbox',
                    'label' => __('Reserve money, do not auto-capture.', 'mondido'),
                    'default' => 'yes')

            );
        }

        /*
         * Create Admin page for settings
         */

        public function admin_options() {
            echo '<h3>' . __('Mondido', 'mondido') . '</h3>';
            echo '<p>' . __('Mondido, Simple payments, smart functions', 'mondido') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        }

        /*
         *  There are no payment fields for mondido, but we want to show the description if set.
         */

        function payment_fields() {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
        }

        /*
         * Generate Mondido button link
         */

        public function generate_mondido_form($order_id) {

            global $woocommerce;
            $order = new WC_Order($order_id);
            $products = [];
            $customer = [];
            $platform = [];
            $order = [];
            $items = [];
            $cart = $woocommerce->cart->cart_contents;
            #vat weight attributes
            foreach($cart as $item){
                $c_item = [];
                $c_item["id"] = $item['product_id'];
                $c_item["quantity"] = $item['quantity'];
                $c_item["total_amount"] = $item['line_total'];
                $prod = new WC_Product($item["product_id"]);

                $c_item["image"] = $prod->get_image();
                $c_item["weight"] = $prod->weight();
                foreach($item["data"] as $data){
                    $c_item["amount"] = $data->price;
                    $c_item["shipping_class"] = $data->shipping_class;
                    $c_item["name"] = $data->post->post_title;
                    $c_item["url"] = $data->post->guid;

                }

                array_push($products,$c_item);
            }

            #plugin version
            $platform["type"] = "wocoomerce";
            $platform["version"] = $woocommerce->version;
            $platform["language_version"] = phpversion();
            $platform["plugin_version"] = $this->plugin_version;
            $order = new WC_Order( $order_id );

            $customer["country"] = $order->billing_country;
            $customer["city"] = $order->billing_city;
            $customer["zip"] = $order->billing_postcode;
            $customer["state"] = $order->billing_state;
            $customer["address_1"] = $order->billing_address_1;
            $customer["address_2"] = $order->billing_address_2;
            $customer["email"] = $order->billing_email;
            $customer["first_name"] = $order->billing_first_name;
            $customer["last_name"] = $order->billing_last_name;
            $customer["phone"] = $order->billing_phone;


#order
            #discount
            #voucher name
#            items (array with product information)
#o	name (product name)
#o	description (description, quantity, reference number)
#o	amount (total incl. vat)
#o	vat_amount







            $md = [
                "products" => $products,
                "customer" => $customer,
                "platform" => $platform,
                "order" => $order

            ];

            //$cart = new WC_Cart();
            //$cart->get_cart_from_session();
            $metadata = json_encode($md);
            $amount = number_format($order->order_total, 2, '.', '');
            $merchant_id = trim($this->merchant_id);
            $currency = trim($this->currency);
            $customer_id = '';
            $hash = generate_mondido_hash($order_id);
            $mondido_args = array(
                'amount' => $amount,
                'merchant_id' => $merchant_id,
                'currency' => $currency,
                'customer_ref' => $customer_id,
                'payment_ref' => $order_id,
                'hash' => $hash,
                'success_url' => $this->get_return_url($order),
                'error_url' => $order->get_cancel_order_url(),
                'metadata' => $metadata,
                'test' => $this->test,
                'authorize' => $this->authorize,
                'items' => $items

            );

            $mondido_args_array = array();
            foreach ($mondido_args as $key => $value) {
                $mondido_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }
            return '<form action="' . $this->liveurl . '" method="post" id="mondido_payment_form">
            ' . implode('', $mondido_args_array) . '
				<div class="payment_buttons">
					<input type="submit" class="button alt" id="submit_mondido_payment_form" value="' . __('Pay via Mondido', 'mondido') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart', 'mondido') . '</a>
				</div>
            </form><script>document.getElementById("mondido_payment_form").submit();</script>';
        }

        /*
         * Process the payment and return the result
         */

        public function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /*
         * Receipt Page
         */

        public function receipt_page($order) {
            echo '<p>' . __('Thank you for your order, please click the button below to pay with Mondido.', 'mondido') . '</p>';
            echo $this->generate_mondido_form($order);
        }

        /*
         * Check for valid mondido server callback
         */

        public static function check_mondido_response() {
            $_GET = stripslashes_deep($_GET);
            $expected_fields = array(
                "hash",
                "payment_ref",
                "transaction_id"
            );

            foreach($expected_fields as $field){
                if(!isset($_GET[$field])) return;
            }

            do_action("valid-mondido-callcack", $_GET);
        }

        /*
         * Successful Payment
         */

        public static function successful_request($posted) {
            global $woocommerce;
            // If payment was success

            if ($posted['status'] == 'approved') {
                $order = new WC_Order((int) $posted["payment_ref"]);

                // if order not exists, die()
                if($order->post == null) return;

                // if order is not pending, die()
                if($order->post_status != "wc-pending"){
                    $message = __(
                        sprintf("Invalid Callback for Order #%s."
                            . " Status is not pending."
                            . " Customer may have reloaded the page or pressed F5.",
                            $posted["payment_ref"]
                        ),
                        'woocommerce'
                    );

                    $log = new WC_Logger();
                    $log->add( 'mondido', $message );
                    return;
                }

                // Check whether payment is correct
                $hash = generate_mondido_hash(
                    (int) $posted["payment_ref"],
                    true,
                    $posted["status"]
                );

                if ($hash == $posted['hash']) {
                    $order->add_order_note(
                        __(
                            sprintf("Success: Callback completed."
                                . " Transaction ID #%s.",
                                $posted['transaction_id']
                            ),
                            'woocommerce'
                        )
                    );

                    // Payment Complete
                    $order->payment_complete( $posted['transaction_id'] );

                    // Remove cart
                    $woocommerce->cart->empty_cart();

                } else {
                    $order->update_status('failed');
                    $order->add_order_note(
                        __(
                            sprintf( "Failure. Callback completed. "
                                . "Transaction #%s."
                                . " Incorrect hash -- fake payment?",

                                $posted['transaction_id']
                            ),
                            'woocommerce'
                        )
                    );
                }
            }
        }
    }

    /*
     * Generate Mondido Hash
     */

    function generate_mondido_hash($order_id, $callback = false, $status = "") {
        $order = new WC_Order((int) $order_id);
        $mondido = new WC_Gateway_Mondido();

        $amount = number_format($order->order_total, 2, '.', '');
        $merchant_id = trim($mondido->get_merchant_id());
        $secret = trim($mondido->get_secret());
        $customer_id = '';
        $currency = strtolower($mondido->get_currency());
        $test = ((string)$mondido->get_test() == 'true') ? 'test' : '';

        if ($callback) {
            $str = $merchant_id . $order_id . $customer_id . $amount . $currency . strtolower($status) . $secret;
        } else {
            $str = $merchant_id . $order_id . $customer_id . $amount . $currency . $test . $secret;
        }

        return MD5($str);
    }

    add_filter('generate_mondido_hash', 'generate_mondido_hash');

    /*
     * Add the Gateway to WooCommerce
     */

    function woocommerce_add_mondido_gateway($methods) {
        $methods[] = 'WC_Gateway_Mondido';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_mondido_gateway');

    function init_mondido_gateway() {
        $plugin_dir = basename(dirname(__FILE__));
        load_plugin_textdomain('mondido', false, $plugin_dir . '/languages/');
    }

    add_action('plugins_loaded', 'init_mondido_gateway');

    function WC_Gateway_Mondido() {
        return new WC_Gateway_Mondido();
    }

    if (is_admin()) {
        add_action('load-post.php', 'WC_Gateway_Mondido');
    }
}