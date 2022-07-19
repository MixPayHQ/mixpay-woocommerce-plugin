<?php

/**
 * @wordpress-plugin
 * Plugin Name:             MixPay Gateway for WooCommerce
 * Plugin URI:              https://github.com/MixPayHQ/mixpay-woocommerce-plugin
 * Description:             Cryptocurrency Payment Gateway.
 * Version:                 1.0.0
 * Author:                  MixPay Payment
 * Author URI:              https://mixpay.me/
 * License:                 proprietary
 * License URI:             http://www..org/
 * Text Domain:             wc-mixpay-gateway
 * Domain Path:             /i18n/languages/
 */

/**
 * Exit if accessed directly.
 */
if (! defined('ABSPATH'))
{
    exit();
}

if (version_compare(PHP_VERSION, '7.1', '>=')) {
    ini_set('precision', 10);
    ini_set('serialize_precision', 10);
}

if (! defined('MIXPAY_FOR_WOOCOMMERCE_PLUGIN_DIR')) {
    define('MIXPAY_FOR_WOOCOMMERCE_PLUGIN_DIR', dirname(__FILE__));
}

if (! defined('MIXPAY_FOR_WOOCOMMERCE_ASSET_URL')) {
    define('MIXPAY_FOR_WOOCOMMERCE_ASSET_URL', plugin_dir_url(__FILE__));
}

if (! defined('VERSION_PFW')) {
    define('VERSION_PFW', '1.0.0');
}

if (! defined('SUPPORT_EMAIL')) {
    define('SUPPORT_EMAIL', 'bd@mixpay.me');
}

if (! defined('DEBUG_URL')) {
    define('DEBUG_URL', 'https://pluginsapi.mixpay.me/v1/debug/wordpress');
}

if (! defined('ICON_URL')) {
    define('ICON_URL', 'http://wordpress.test/wp-content/uploads/2022/07/mixpay-button.png');
}

if (! defined('MIXPAY_PAY_LINK')) {
    define('MIXPAY_PAY_LINK', 'https://mixpay.me/pay');
}

if (! defined('MIXPAY_API_URL')) {
    define('MIXPAY_API_URL', 'https://api.mixpay.me');
}

if (! defined('MIXPAY_SETTLEMENT_ASSETS_API')) {
    define('MIXPAY_SETTLEMENT_ASSETS_API', MIXPAY_API_URL . '/v1/setting/settlement_assets');
}

if (! defined('MIXPAY_QUOTE_ASSETS_API')) {
    define('MIXPAY_QUOTE_ASSETS_API', MIXPAY_API_URL . '/v1/setting/quote_assets');
}

if (! defined('MIXPAY_ASSETS_EXPIRE_SECONDS')) {
    define('MIXPAY_ASSETS_EXPIRE_SECONDS', 600);
}

if (! defined('MIXPAY_PAYMENTS_RESULT')) {
    define('MIXPAY_PAYMENTS_RESULT', MIXPAY_API_URL . '/v1/payments_result');
}

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_mixpay_add_to_gateways( $gateways ) {
    if (! in_array('WC_Gateway_mixpay', $gateways)) {
        $gateways[] = 'WC_Gateway_mixpay';
    }

	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_mixpay_add_to_gateways' );

/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_mixpay_gateway_plugin_links( $links ) {

	$plugin_links = [
	    '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mixpay_gateway' ) . '">' . __( 'Configure', 'wc-mixpay-gateway' ) . '</a>',
	    '<a href="mailto:' . SUPPORT_EMAIL . '">' . __( 'Email Developer', 'wc-mixpay-gateway' ) . '</a>'
	];

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_mixpay_gateway_plugin_links' );

function add_cron_every_minute_interval( $schedules) {
    if(! isset($schedules['every_minute'])) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => esc_html__('Every minute'),
        ];
    }

    return $schedules;
}
add_filter( 'cron_schedules', 'add_cron_every_minute_interval' );

/**
 * MixPay Payment Gateway
 *
 * @class 		WC_Gateway_mixpay
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Echo
 */
add_action('plugins_loaded', 'wc_mixpay_gateway_init', 11);
function wc_mixpay_gateway_init()
{

    if (! class_exists('WC_Payment_Gateway')) {
        return;
    }

    add_action('check_payments_result_cron_hook', ['WC_Gateway_mixpay', 'check_payments_result'], 10, 1);

    class WC_Gateway_mixpay extends WC_Payment_Gateway
    {
        /**
         * Constructor for the gateway.
         *
         * @access public
         * @return void
         */
        public function __construct()
        {
            global $woocommerce;
            $this->id                 = 'mixpay_gateway';
            $this->icon               = apply_filters('woocommerce_mixpay_icon', ICON_URL);
            $this->has_fields         = false;
            $this->method_title       = __('MixPay Payment', 'wc-gateway-mixpay');
            $this->method_description = __( 'Allows Cryptocurrency payments via MixPay', 'wc-mixpay-gateway' );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title               = $this->get_option('title');
            $this->description         = $this->get_option('description');
            $this->instructions        = $this->get_option( 'instructions');
            $this->payee_uuid          = $this->get_option('payee_uuid');
            $this->domain              = $this->get_option('domain');
            $this->settlement_asset_id = $this->get_option('settlement_asset_id');
            $this->invoice_prefix      = $this->get_option('invoice_prefix', 'WORDPRESS-WC-');
            $this->debug               = $this->get_option('debug', false);

            // Logs
            $this->log = new WC_Logger();

            // Actions
            add_action('woocommerce_after_checkout_billing_form', [$this, 'is_valid_for_use']);
            add_action('woocommerce_page_wc-settings', [$this, 'is_valid_for_use']);
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
			add_action('woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
            add_action('woocommerce_api_wc_gateway_mixpay', [$this, 'mixpay_callback']);

            // Customer Emails
			add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
        }

        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields()
        {
            require_once( 'includes/setting_form_fields.php' );
        }

        /**
         * Output for the order received page.
         */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
        }

		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
				echo wpautop(wptexturize($this->instructions));
			}
		}

        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment($order_id)
        {
            $order        = wc_get_order($order_id);
            $redirect_url = $this->generate_mixpay_url($order);

            if ( ! wp_next_scheduled( 'check_payments_result_cron_hook', [$order_id]) ) {
                wp_schedule_event(time(), 'every_minute', 'check_payments_result_cron_hook', [$order_id]);
            }

            return [
                'result'   => 'success',
                'redirect' => $redirect_url
            ];
        }

         /**
          * Generate the mixpay button link
          *
          * @access public
          * @param mixed $order_id
          * @param mixed $order
          * @return string
          */
        function generate_mixpay_url($order)
        {
            global $woocommerce;

            if ($order->status != 'completed' && get_post_meta($order->id, 'MixPay payment complete', true) != 'Yes') {
                $order->add_order_note('Customer is being redirected to MixPay...');
            }

            $amount = number_format($order->get_total(), 8, '.', '');
            $rev    = bccomp($amount, 0, 8);

            if($rev === 0){
                $order->update_status('completed', 'The order amount is zero.');
                return $this->get_return_url($order);
            }elseif ($rev === -1){
                throw new Exception("The order amount is incorrect, please contact customer");
            }

            $mixpay_args = $this->get_mixpay_args($order);
            $mixpay_adr  = MIXPAY_PAY_LINK . '?' . http_build_query($mixpay_args);

            return $mixpay_adr;
        }

        /**
         * Get MixPay Args
         *
         * @access public
         * @param mixed $order
         * @return array
         */
        function get_mixpay_args($order)
        {
            global $woocommerce;
            $mixpay_args = [
                'payeeId'           => $this->payee_uuid,
                'orderId'           => $this->invoice_prefix . $order->get_order_number(),
                'store_name'        => $this->domain,
                'settlementAssetId' => $this->settlement_asset_id,
                'quoteAssetId'      => strtolower($order->get_currency()),
                'quoteAmount'       => number_format($order->get_total(), 8, '.', ''),
                'returnTo'          => $this->get_return_url($order),
                'callbackUrl'       => "https://{$this->domain}/?wc-api=wc_gateway_mixpay"
            ];

            if(get_option('woocommerce_manage_stock') === 'yes'){
                $woocommerce_hold_stock_minutes  = get_option('woocommerce_hold_stock_minutes') ?: 1;
                $woocommerce_hold_stock_minutes  = $woocommerce_hold_stock_minutes > 240 ? 240 : $woocommerce_hold_stock_minutes;
                $created_time                    = strtotime($order->get_date_created());
                $mixpay_args['expiredTimestamp'] = $created_time + $woocommerce_hold_stock_minutes * 60 - 30;

                if(! $order->has_status('pending')){
                    throw new Exception('The order has expired, please place another order');
                }

                if($mixpay_args['expiredTimestamp'] <= time()){
                    if($order->has_status('pending')) {
                        $order->update_status('cancelled', 'Unpaid order cancelled - time limit reached');
                    }
                    throw new Exception('The order has expired, please place another order');
                }
            }

            $mixpay_args = apply_filters('woocommerce_mixpay_args', $mixpay_args);

            return $mixpay_args;
        }

        /**
         * Check if this gateway is enabled and available in the user's country
         *
         * @access public
         * @return bool
         */
        function is_valid_for_use()
        {
            $asset_lists = $this->get_quote_asset_lists();
            $currency    = get_woocommerce_currency();

            if(! in_array(strtolower($currency), $asset_lists)){
                $woocommerce_mixpay_gateway_settings            = get_option('woocommerce_mixpay_gateway_settings');
                $woocommerce_mixpay_gateway_settings['enabled'] = 'no';
                update_option('woocommerce_mixpay_gateway_settings', $woocommerce_mixpay_gateway_settings);
                $this->enabled = false;

                return false;
            }

            return true;
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         * @since 1.0.0
         */
        public function admin_options()
        {
            ?>
            <h3><?php _e('MixPay Payment', 'woocommerce'); ?></h3>
            <p><?php _e('Completes checkout via MixPay Payment', 'woocommerce'); ?></p>

            <?php if ($this->enabled) { ?>

                <table class="form-table">
                    <?php
                    $this->generate_settings_html();
                    ?>
                </table>
                <!--/.form-table-->

            <?php } else { ?>
                <div class="inline error">
                    <p><strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php _e('MixPay Payment does not support your store currency.', 'woocommerce'); ?></p>
                </div>
            <?php }

        }

        /**
         * @access public
         * @param array $posted
         * @return void
         */
        function mixpay_callback()
        {
            ob_start();
            global $woocommerce;

            $request_json         = file_get_contents('php://input');
            $request_data         = json_decode($request_json, true);
            $payments_result_data = $this->get_payments_result($request_data["orderId"], $request_data["payeeId"]);
            $valid_order_id       = str_replace($this->invoice_prefix, '', $request_data["orderId"]);
            $result               = $this->update_order_status($valid_order_id, $payments_result_data);

            ob_clean();
            wp_send_json([ 'code' => $result['code']], $result['status']);
        }

        static function check_payments_result($order_id)
        {
            $mixpay_gatway        = (new self());
            $payments_result_data = $mixpay_gatway->get_payments_result($order_id, $mixpay_gatway->payee_uuid);
            $mixpay_gatway->update_order_status($order_id, $payments_result_data);
        }

        function update_order_status($order_id, $payments_result_data)
        {
            $order  = new WC_Order($order_id);
            $result = ['code' => 'FAIL', 'status' => 500];

            $status_before_update = $order->get_status();

            if($payments_result_data["status"] == "pending" && $status_before_update == 'pending') {
                $order->update_status('processing', 'Order is processing.');
            } elseif($payments_result_data["status"] == "success" && in_array($status_before_update, ['pending', 'processing'])) {
                $order->update_status('completed', 'Order has been paid.');
                $result = ['code' => 'SUCCESS', 'status' => 200];
            } elseif($payments_result_data["status"] == "failed") {
                $order->update_status('cancelled', "Order has been cancelled, reason: {$payments_result_data['failureReason']}.");
            }

            if (! $order->has_status(['pending', 'processing'])){
                wp_clear_scheduled_hook('check_payments_result_cron_hook', [$order_id]);
            }

            $this->debug_post_out(
                'mixpay_callback',
                [
                    'payments_result_data' => $payments_result_data,
                    'status_before_update' => $status_before_update,
                    'order_status'         => $order->get_status()
                ]
            );

            return $result;
        }

        function get_payments_result($order_id, $payee_uuid)
        {
            $response             = wp_remote_get(MIXPAY_PAYMENTS_RESULT . "?orderId={$order_id}&payeeId={$payee_uuid}");
            $payments_result_data = wp_remote_retrieve_body($response);

            return  json_decode($payments_result_data, true)['data'];
        }

        function get_quote_asset_lists()
        {
            $key               = 'mixpay_quote_asset_lists';
            $quote_asset_lists = get_option($key);

            if(isset($quote_asset_lists['expire_time']) && $quote_asset_lists['expire_time'] > time()){
                return $quote_asset_lists['data'];
            }

            $response      = wp_remote_get(MIXPAY_QUOTE_ASSETS_API);
            $response_data = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_data, true)['data'] ?? [];
            $lists         = array_column($response_data, 'assetId');

            if(! empty($lists)) {
                $quote_asset_lists = $lists;
                update_option($key, ['data' => $lists, 'expire_time' => time() + MIXPAY_ASSETS_EXPIRE_SECONDS]);
            }

            return $quote_asset_lists;
        }

        function debug_post_out($key, $datain)
        {
            if ($this->debug) {
                $data = [
                    $key => $datain
                ];
                wp_remote_post(DEBUG_URL, ['body' => $data]);
            }
        }
    }

}
