<?php

/**
 * @wordpress-plugin
 * Plugin Name:             MixPay Gateway for WooCommerce
 * Plugin URI:              https://github.com/MixPayHQ/mixpay-woocommerce-plugin
 * Description:             Cryptocurrency Payment Gateway.
 * Version:                 1.0.0
 * Author:                  MixPay Payment
 * Author URI:              https://github.com/MixPayHQ/mixpay-woocommerce-plugin
 * License:                 GPLv2 or later
 * License URI:             http://www.gnu.org/licenses/gpl-2.0.html
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
    define('ICON_URL', 'data:image/png;base64,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');
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
