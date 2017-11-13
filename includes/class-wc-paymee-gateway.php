<?php
/**
 * WooCommerce PayMee Gateway class
 *
 * @package WooCommerce_PayMee/Classes/Gateway
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce PayMee gateway.
 */
class WC_PayMee_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'paymee';
		$this->icon               = apply_filters( 'woocommerce_paymee_icon', plugins_url( 'assets/images/pague-com-paymee.png', plugin_dir_path( __FILE__ ) ) );
		$this->method_title       = __( 'PayMee', 'woocommerce-paymee' );
		$this->method_description = __( 'Aceite transferência ou dinheiro instantaneamente com a PayMee.', 'woocommerce-paymee' );
		$this->order_button_text  = __( 'Pagar à vista com a PayMee', 'woocommerce-paymee' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title             = $this->get_option( 'title' );
		$this->description       = $this->get_option( 'description' );
		$this->api_key             = $this->get_option( 'api_key' );
		$this->api_token             = $this->get_option( 'api_token' );
		$this->method            = $this->get_option( 'method', 'direct' );
		$this->tc_transfer         = $this->get_option( 'tc_transfer', 'yes' );
		$this->tc_cash       = $this->get_option( 'tc_cash', 'yes' );
		$this->send_only_total   = $this->get_option( 'send_only_total', 'no' );
		$this->invoice_prefix    = $this->get_option( 'invoice_prefix', 'WC-' );
		$this->sandbox           = $this->get_option( 'sandbox', 'yes' );
		$this->debug             = $this->get_option( 'debug' );

		// Active logs.
		if ( 'yes' == $this->debug ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				$this->log = wc_get_logger();
			} else {
				$this->log = new WC_Logger();
			}
		}

		// Set the API.
		$this->api = new WC_PayMee_API( $this );

		// Main actions.
		add_action( 'woocommerce_api_wc_paymee_gateway', array( $this, 'ipn_handler' ) );
		add_action( 'valid_paymee_ipn_request', array( $this, 'update_order_status' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
	}

	/**
	 * Returns a bool that indicates if currency is amongst the supported ones.
	 *
	 * @return bool
	 */
	public function using_supported_currency() {
		return 'BRL' === get_woocommerce_currency();
	}

	/**
	 * Get email.
	 *
	 * @return string
	 */
	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * Get token.
	 *
	 * @return string
	 */
	public function get_api_token() {
		return $this->api_token;
	}

	/**
	 * Returns a value indicating the the Gateway is available or not. It's called
	 * automatically by WooCommerce before allowing customers to use the gateway
	 * for payment.
	 *
	 * @return bool
	 */
	public function is_available() {
		// Test if is valid for use.
		$available = 'yes' === $this->get_option( 'enabled' ) && '' !== $this->get_api_key() && '' !== $this->get_api_token() && $this->using_supported_currency();

		if ( 'transparent' == $this->method && ! class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
			$available = false;
		}

		return $available;
	}

	/**
	 * Has fields.
	 *
	 * @return bool
	 */
	public function has_fields() {
		return 'transparent' === $this->method;
	}

	/**
	 * Get log.
	 *
	 * @return string
	 */
	protected function get_log_view() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
			return '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'System Status &gt; Logs', 'woocommerce-paymee' ) . '</a>';
		}

		return '<code>woocommerce/logs/' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.txt</code>';
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-paymee' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar a PayMee', 'woocommerce-paymee' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Título', 'woocommerce-paymee' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-paymee' ),
				'desc_tip'    => true,
				'default'     => __( 'PayMee', 'woocommerce-paymee' ),
			),
			'description' => array(
				'title'       => __( 'Descrição', 'woocommerce-paymee' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-paymee' ),
				'default'     => __( 'Pague em dinheiro ou transferência bancária com a PayMee Brasil', 'woocommerce-paymee' ),
			),
			'integration' => array(
				'title'       => __( 'Integração', 'woocommerce-paymee' ),
				'type'        => 'title',
				'description' => '',
			),
			'method' => array(
				'title'       => __( 'Método de integração', 'woocommerce-paymee' ),
				'type'        => 'select',
				'description' => __( 'Choose how the customer will interact with the PayMee. Redirect (Client goes to PayMee page)', 'woocommerce-paymee' ),
				'desc_tip'    => true,
				'default'     => 'direct',
				'class'       => 'wc-enhanced-select',
				'options'     => array(
					'redirect'    => __( 'Redirect (padrão)', 'woocommerce-paymee' )
				),
			),
			'sandbox' => array(
				'title'       => __( 'PayMee Sandbox', 'woocommerce-paymee' ),
				'type'        => 'checkbox',
				'label'       => __( 'Habilitar a PayMee Sandbox', 'woocommerce-paymee' ),
				'desc_tip'    => true,
				'default'     => 'no',
				'description' => __( 'PayMee Sandbox can be used to test the payments.', 'woocommerce-paymee' ),
			),
			'api_key' => array(
				'title'       => __( 'PayMee X-API-KEY', 'woocommerce-paymee' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Para gerar suas credenciais acesse: %s.', 'woocommerce-paymee' ), '<a href="https://apisandbox.paymee.com.br/register">' . __( 'here', 'woocommerce-paymee' ) . '</a>' ),
				'default'     => '',
			),
			'api_token' => array(
				'title'       => __( 'PayMee X-API-TOKEN', 'woocommerce-paymee' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Para gerar suas credenciais acesse: %s.', 'woocommerce-paymee' ), '<a href="https://apisandbox.paymee.com.br/register">' . __( 'here', 'woocommerce-paymee' ) . '</a>' ),
				'default'     => '',
			),
			'behavior' => array(
				'title'       => __( 'Informações extras', 'woocommerce-paymee' ),
				'type'        => 'title',
				'description' => '',
			),
			'send_only_total' => array(
				'title'   => __( 'Apenas o valor final', 'woocommerce-paymee' ),
				'type'    => 'checkbox',
				'label'   => __( 'Se essa opção estiver marcada, será enviado apenas o valor total do pedido.', 'woocommerce-paymee' ),
				'default' => 'no',
			),
			'invoice_prefix' => array(
				'title'       => __( 'Prefixo da venda', 'woocommerce-paymee' ),
				'type'        => 'text',
				'description' => __( 'Please enter a prefix for your invoice numbers. If you use your PayMee account for multiple stores ensure this prefix is unqiue as PayMee will not allow orders with the same invoice number.', 'woocommerce-paymee' ),
				'desc_tip'    => true,
				'default'     => 'WC-',
			),
			'testing' => array(
				'title'       => __( 'Testes', 'woocommerce-paymee' ),
				'type'        => 'title',
				'description' => '',
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'woocommerce-paymee' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'woocommerce-paymee' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log PayMee events, such as API requests, inside %s', 'woocommerce-paymee' ), $this->get_log_view() ),
			),
		);
	}

	/**
	 * Admin page.
	 */
	public function admin_options() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'paymee-admin', plugins_url( 'assets/js/admin/admin' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery' ), WC_PayMee::VERSION, true );

		include dirname( __FILE__ ) . '/admin/views/html-admin-page.php';
	}

	/**
	 * Send email notification.
	 *
	 * @param string $subject Email subject.
	 * @param string $title   Email title.
	 * @param string $message Email message.
	 */
	protected function send_email( $subject, $title, $message ) {
		$mailer = WC()->mailer();
		$mailer->send( get_option( 'admin_email' ), $subject, $mailer->wrap_message( $title, $message ) );
	}


	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		WC()->cart->empty_cart();
		$response = $this->api->do_checkout_request( $order, $_POST );
		return array(
			'result'   => 'success',
			'redirect' => $response['url'],
		);
	}


	/**
	 * Save payment meta data.
	 *
	 * @param  WC_Order $order Order instance.
	 * @param  array   $posted Posted data.
	 */
	protected function save_payment_meta_data( $order, $posted ) {
		$meta_data    = array();
		$payment_data = array(
			'type'         => '',
			'method'       => '',
			'installments' => '',
			'link'         => '',
		);

		if ( isset( $posted->sender->email ) ) {
			$meta_data[ __( 'Payer email', 'woocommerce-paymee' ) ] = sanitize_text_field( (string) $posted->sender->email );
		}
		if ( isset( $posted->sender->name ) ) {
			$meta_data[ __( 'Payer name', 'woocommerce-paymee' ) ] = sanitize_text_field( (string) $posted->sender->name );
		}
		if ( isset( $posted->paymentMethod->type ) ) {
			$payment_data['type'] = intval( $posted->paymentMethod->type );
			$meta_data[ __( 'Payment type', 'woocommerce-paymee' ) ] = $this->api->get_payment_name_by_type( $payment_data['type'] );
		}
		if ( isset( $posted->paymentMethod->code ) ) {
			$payment_data['method'] = $this->api->get_payment_method_name( intval( $posted->paymentMethod->code ) );
			$meta_data[ __( 'Payment method', 'woocommerce-paymee' ) ] = $payment_data['method'];
		}
		if ( isset( $posted->paymentLink ) ) {
			$payment_data['link'] = sanitize_text_field( (string) $posted->paymentLink );
			$meta_data[ __( 'Payment URL', 'woocommerce-paymee' ) ] = $payment_data['link'];
		}

		$meta_data['_wc_paymee_payment_data'] = $payment_data;

		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'update_meta_data' ) ) {
			foreach ( $meta_data as $key => $value ) {
				$order->update_meta_data( $key, $value );
			}
			$order->save();
		} else {
			foreach ( $meta_data as $key => $value ) {
				update_post_meta( $order->id, $key, $value );
			}
		}
	}
}
