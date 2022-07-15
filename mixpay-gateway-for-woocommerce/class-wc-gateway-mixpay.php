<?php

/**
 * @wordpress-plugin
 * Plugin Name:             MixPay Gateway for WooCommerce
 * Plugin URI:              https://mixpay.me/
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
	    '<a href="mailto:bd@mixpay.me">' . __( 'Email Developer', 'wc-mixpay-gateway' ) . '</a>'
	];

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_mixpay_gateway_plugin_links' );


function add_cron_one_minute_interval( $schedules) {
    $schedules['one_minute'] = [
        'interval' => 60,
        'display'  => esc_html__( 'Every minute' ),
    ];

    return $schedules;
}
add_filter( 'cron_schedules', 'add_cron_one_minute_interval' );


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

    class WC_Gateway_mixpay extends WC_Payment_Gateway
    {
        private $mixpay_adr     = 'https://mixpay.me';
        private $mixpay_api     = 'https://api.mixpay.me';
        private $support_email  = 'bd@mixpay.me';
        private $debug_post_url = 'https://pluginsapi.mixpay.me/v1/debug/wordpress';
        private $icon_url       = 'https://pluginsapi.mixpay.me/plugins/shopify/button.png';

        public function __construct()
        {
            global $woocommerce;
            $this->id                 = 'mixpay_gateway';
            $this->icon               = apply_filters('woocommerce_mixpay_icon', $this->icon_url);
            $this->has_fields         = false;
            $this->method_title       = __('MixPay Payment', 'wc-gateway-mixpay');
            $this->method_description = __( 'Allows Cryptocurrency payments via MixPay', 'wc-mixpay-gateway' );

            $this->init_settlement_asset_lists();

            $this->init_form_fields();
            $this->init_settings();

            $this->title               = $this->get_option('title');
            $this->description         = $this->get_option('description');
            $this->instructions        = $this->get_option( 'instructions');
            $this->payee_uuid          = $this->get_option('payee_uuid');
            $this->domain              = $this->get_option('domain');
            $this->settlement_asset_id = $this->get_option('settlement_asset_id');
            $this->invoice_prefix      = $this->get_option('invoice_prefix', 'WORDPRESS-WC-');
            $this->debug               = $this->get_option('debug', false);

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
			add_action('woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
            add_action('woocommerce_api_wc_gateway_mixpay', [$this, 'mixpay_callback']);
            add_action( 'check_payments_result_cron_hook', [$this, 'check_payments_result'], 10, 1);
			add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );

            if (! $this->is_valid_for_use()) {
                $this->enabled = false;
            }
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
		public function email_instructions( $order, $sent_to_admin) {
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
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

            if ( ! wp_next_scheduled( 'check_payments_result_cron_hook', ['order_id' => $order_id]) ) {
                wp_schedule_event(current_time('timestamp'), 'one_minute', 'check_payments_result_cron_hook', ['order_id' => $order_id]);
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
          * @param mixed $order
          * @return string
          */
        function generate_mixpay_url($order)
        {
            global $woocommerce;

            if ($order->status != 'completed' && get_post_meta($order->id, 'MixPay payment complete', true) != 'Yes') {
                $order->add_order_note('Customer is being redirected to MixPay...');
                $order->update_status('pending', 'Customer is being redirected to MixPay...');
            }

            $mixpay_adr  = $this->mixpay_adr . "/pay?";
            $mixpay_args = $this->get_mixpay_args($order);

            $mixpay_adr .= http_build_query($mixpay_args);

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
                'failedReturnTo'    => esc_url_raw($order->get_view_order_url()),
                'callbackUrl'       => "https://{$this->domain}/?wc-api=wc_gateway_mixpay"
            ];

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
            <h3><?php _e('MixPay', 'woocommerce'); ?></h3>
            <p><?php _e('Completes checkout via MixPay', 'woocommerce'); ?></p>

            <?php if ($this->is_valid_for_use()) { ?>

                <table class="form-table">
                    <?php
                    $this->generate_settings_html();
                    ?>
                </table>
                <!--/.form-table-->

            <?php } else { ?>
                <div class="inline error">
                    <p><strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php _e('MixPay does not support your store currency.', 'woocommerce'); ?></p>
                </div>
            <?php }

        }

        function mixpay_callback()
        {
            ob_start();
            global $woocommerce;

            $request_json = file_get_contents('php://input');
            $request_data = json_decode($request_json, true);

            $response             = wp_remote_get($this->mixpay_api . "/v1/payments_result?orderId={$request_data["orderId"]}&payeeId={$request_data["payeeId"]}");
            $payments_result_data = wp_remote_retrieve_body($response);
            $payments_result_data = json_decode($payments_result_data, true)['data'];

            $valid_order_id = str_replace($this->invoice_prefix, '', $request_data["orderId"]);
            $result         = $this->update_order_status($valid_order_id, $payments_result_data);
            ob_clean();

            wp_send_json([ 'code' => $result['code']], $result['status']);
        }

        function check_payments_result($order_id)
        {
            $payments_result_data = $this->get_payments_result($order_id);
            $this->update_order_status($order_id, $payments_result_data);
        }

        function update_order_status($order_id, $payments_result_data)
        {
            $order = new WC_Order($order_id);
            $order->add_order_note("MixPay status: {$payments_result_data["status"]}, Order status before update: {$order->get_status()}");
            $result = ['code' => 'FAIL', 'status' => 500];

            if ($payments_result_data["status"] == "success" && in_array($order->get_status(), ['pending', 'processing'])) {//支付成功
                $order->update_status('completed', 'Order has been paid.');
                $result = ['code' => 'SUCCESS', 'status' => 200];

                wp_clear_scheduled_hook('check_payments_result_cron_hook', ['order_id' => $order_id]);

            } elseif($payments_result_data["status"] == "pending" && $order->get_status() == 'pending') {//用户已支付
                $order->update_status('processing', 'Order is processing.');
            } elseif($payments_result_data["status"] == "failed" && $order->get_status() == 'processing') {//支付失败，用户已转帐
                $order->update_status('on-hold', 'Order is failed. Please contact ' . $this->support_email);
                wp_clear_scheduled_hook('check_payments_result_cron_hook', ['order_id' => $order_id]);
            }

            $this->debug_post_out('mixpay_callback', ['payments_result_data' => $payments_result_data, 'payments_result' => $payments_result_data, 'status_before_update' => $order->get_status()]);

            return $result;
        }

        function get_payments_result($order_id)
        {
            $response             = wp_remote_get($this->mixpay_api . "/v1/payments_result?orderId={$order_id}&payeeId={$this->payee_uuid}");
            $payments_result_data = wp_remote_retrieve_body($response);

            return  json_decode($payments_result_data, true)['data'];
        }

        function get_quote_asset_lists()
        {
            $cache_key         = 'mixpay:quote_asset_lists';
            $quote_asset_lists = wp_cache_get($cache_key);

            if(empty($quote_asset_lists)) {
                $response             = wp_remote_get($this->mixpay_api . "/v1/setting/quote_assets");
                $payments_result_data = wp_remote_retrieve_body($response);
                $payments_result_data = json_decode($payments_result_data, true)['data'];
                $quote_asset_lists    = array_column($payments_result_data, 'assetId');
                wp_cache_set($cache_key, $quote_asset_lists, 'mixpay', 60);
            }

            return $quote_asset_lists;
        }

        function init_settlement_asset_lists()
        {
            $cache_key              = 'mixpay:settlement_asset_lists';
            $settlement_asset_lists = wp_cache_get($cache_key);

            if(empty($settlement_asset_lists)) {
                $response               = wp_remote_get($this->mixpay_api . '/v1/setting/settlement_assets');
                $body_arr               = json_decode($response['body'], true) ?: [];
                $settlement_asset_lists = [];

                foreach ($body_arr['data'] as $asset) {
                    $settlement_asset_lists[$asset['assetId']] = $asset['symbol'] . ' - ' . $asset['network'];
                }
                wp_cache_set($cache_key, $settlement_asset_lists, 'mixpay', 60);
            }

            return $settlement_asset_lists;
        }

        function debug_post_out($key, $datain)
        {
            if ($this->debug) {
                $data = [
                    $key => $datain
                ];
                wp_remote_post($this->debug_post_url, ['body' => $data]);
            };
        }
    }

}
