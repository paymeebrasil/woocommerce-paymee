<?php
/**
 * WooCommerce PayMee API class
 *
 * @package WooCommerce_PayMee/Classes/API
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce PayMee API.
 */
class WC_PayMee_API {

	/**
	 * Gateway class.
	 *
	 * @var WC_PayMee_Gateway
	 */
	protected $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_PayMee_Gateway $gateway Payment Gateway instance.
	 */
	public function __construct( $gateway = null ) {
		$this->gateway = $gateway;
	}

	/**
	 * Get the API environment.
	 *
	 * @return string
	 */
	public function get_environment() {
		return ( 'yes' == $this->gateway->sandbox ) ? 'apisandbox.' : 'api.';
	}

	/**
	 * Get the checkout URL.
	 *
	 * @return string.
	 */
	protected function get_checkout_url() {
		return 'https://' . $this->get_environment() . 'paymee.com.br/v1/checkout';
	}

	/**
	 * Get the payment URL.
	 *
	 * @param  string $token Payment code.
	 *
	 * @return string.
	 */
	protected function get_payment_url( $token ) {
		return 'https://www2.paymee.com.br/redir/' . $token;
	}

	/**
	 * Get the transactions URL.
	 *
	 * @return string.
	 */
	protected function get_transactions_url() {
		return 'https://' . $this->get_environment() . 'paymee.com.br/v1/transactions';
	}

	/**
	 * Check if is localhost.
	 *
	 * @return bool
	 */
	protected function is_localhost() {
		$url  = home_url( '/' );
		$home = untrailingslashit( str_replace( array( 'https://', 'http://' ), '', $url ) );

		return in_array( $home, array( 'localhost', '127.0.0.1' ) );
	}

	/**
	 * Money format.
	 *
	 * @param  int/float $value Value to fix.
	 *
	 * @return float            Fixed value.
	 */
	protected function money_format( $value ) {
		return number_format( $value, 2, '.', '' );
	}

	/**
	 * Sanitize the item description.
	 *
	 * @param  string $description Description to be sanitized.
	 *
	 * @return string
	 */
	protected function sanitize_description( $description ) {
		return sanitize_text_field( substr( $description, 0, 95 ) );
	}

	/**
	 * Get payment name by type.
	 *
	 * @param  int $value Payment Type number.
	 *
	 * @return string
	 */
	public function get_payment_name_by_type( $value ) {
		$types = array(
			1 => __( 'Bank Transfer', 'woocommerce-paymee' ),
			2 => __( 'Cash Payment', 'woocommerce-paymee' ),
		);

		return isset( $types[ $value ] ) ? $types[ $value ] : __( 'Unknown', 'woocommerce-paymee' );
	}

	/**
	 * Get payment method name.
	 *
	 * @param  int $value Payment method number.
	 *
	 * @return string
	 */
	public function get_payment_method_name( $value ) {
		$transfer = __( 'Bank Transfer %s', 'woocommerce-paymee' );
		$cash = __( 'Cash Payment %s', 'woocommerce-paymee' );

		$methods = array(
			101 => sprintf( $credit, 'Banco do Brasil' ),
			102 => sprintf( $credit, 'Bradesco' ),
			103 => sprintf( $credit, 'Itaú-Unibanco' ),
			104 => sprintf( $credit, 'Santander Brasil' ),
			105 => sprintf( $credit, 'Itaú-Unibanco Cash' ),
		);

		return isset( $methods[ $value ] ) ? $methods[ $value ] : __( 'Unknown', 'woocommerce-paymee' );
	}

	/**
	 * Get the paymet method.
	 *
	 * @param  string $method Payment method.
	 *
	 * @return string
	 */
	public function get_payment_method( $method ) {
		$methods = array(
			'transfer'    => 'transfer',
			'cash' => 'cash',
		);

		return isset( $methods[ $method ] ) ? $methods[ $method ] : '';
	}

	/**
	 * Get error message.
	 *
	 * @param  int $code Error code.
	 *
	 * @return string
	 */
	public function get_error_message( $code ) {
		$code = (string) $code;

		$messages = array(
			'-1' => __( 'Falha em validar as informações fornecidas, verifique o erro no log e tente novamente.', 'woocommerce-paymee' ),
			'998' => __( 'Não foi possivel recuperar a transação pelo identificador informado.', 'woocommerce-paymee' ),
			'999' => __( 'A situação da transação não está pendente.', 'woocommerce-paymee' ),
			'1000' => __( 'A transação não está com o status Pago ou não existe.', 'woocommerce-paymee' ),
			'1001' => __( 'O código de referência informado já existe para outra venda.', 'woocommerce-paymee' ),
		);

		if ( isset( $messages[ $code ] ) ) {
			return $messages[ $code ];
		}

		return __( 'Ocorreu um erro, tente novamente ou contate o administrador do site.', 'woocommerce-paymee' );
	}

	/**
	 * Get the available payment methods.
	 *
	 * @return array
	 */
	protected function get_available_payment_methods() {
		$methods = array();

		if ( 'yes' == $this->gateway->tc_transfer ) {
			$methods[] = 'transfer';
		}

		if ( 'yes' == $this->gateway->tc_cash ) {
			$methods[] = 'cash';
		}

		return $methods;
	}

	/**
	 * Do requests in the PayMee API.
	 *
	 * @param  string $url      URL.
	 * @param  string $method   Request method.
	 * @param  array  $data     Request data.
	 * @param  array  $headers  Request headers.
	 *
	 * @return array            Request response.
	 */
	protected function do_request( $url, $method = 'POST', $data = array(), $headers = array() ) {
		$params = array(
			'method'  => $method,
			'timeout' => 60,
		);

		if ( 'POST' == $method && ! empty( $data ) ) {
			$params['body'] = $data;
		}

		if ( ! empty( $headers ) ) {
			$params['headers'] = $headers;
		}

		return wp_safe_remote_post( $url, $params );
	}


	/**
	 * Get order items.
	 *
	 * @param  WC_Order $order Order data.
	 *
	 * @return array           Items list, extra amount and shipping cost.
	 */
	protected function get_order_items( $order ) {
		$items         = array();
		$extra_amount  = 0;
		$shipping_cost = 0;

		// Force only one item.
		if ( 'yes' == $this->gateway->send_only_total ) {
			$items[] = array(
				'description' => $this->sanitize_description( sprintf( __( 'Order %s', 'woocommerce-paymee' ), $order->get_order_number() ) ),
				'amount'      => $this->money_format( $order->get_total() ),
				'quantity'    => 1,
			);
		} else {

			// Products.
			if ( 0 < count( $order->get_items() ) ) {
				foreach ( $order->get_items() as $order_item ) {
					if ( $order_item['qty'] ) {
						$item_total = $order->get_item_total( $order_item, false );
						if ( 0 >= (float) $item_total ) {
							continue;
						}

						$item_name = $order_item['name'];

						if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.0', '<' ) ) {
							if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.4.0', '<' ) ) {
								$item_meta = new WC_Order_Item_Meta( $order_item['item_meta'] );
							} else {
								$item_meta = new WC_Order_Item_Meta( $order_item );
							}

							if ( $meta = $item_meta->display( true, true ) ) {
								$item_name .= ' - ' . $meta;
							}
						}

						$items[] = array(
							'description' => $this->sanitize_description( str_replace( '&ndash;', '-', $item_name ) ),
							'amount'      => $this->money_format( $item_total ),
							'quantity'    => $order_item['qty'],
						);
					}
				}
			}

			// Fees.
			if ( 0 < count( $order->get_fees() ) ) {
				foreach ( $order->get_fees() as $fee ) {
					if ( 0 >= (float) $fee['line_total'] ) {
						continue;
					}

					$items[] = array(
						'description' => $this->sanitize_description( $fee['name'] ),
						'amount'      => $this->money_format( $fee['line_total'] ),
						'quantity'    => 1,
					);
				}
			}

			// Taxes.
			if ( 0 < count( $order->get_taxes() ) ) {
				foreach ( $order->get_taxes() as $tax ) {
					$tax_total = $tax['tax_amount'] + $tax['shipping_tax_amount'];
					if ( 0 >= (float) $tax_total ) {
						continue;
					}

					$items[] = array(
						'description' => $this->sanitize_description( $tax['label'] ),
						'amount'      => $this->money_format( $tax_total ),
						'quantity'    => 1,
					);
				}
			}

			// Shipping Cost.
			if ( 0 < $order->get_total_shipping() ) {
				$shipping_cost = $this->money_format( $order->get_total_shipping() );
			}

			// Discount.
			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3', '<' ) ) {
				if ( 0 < $order->get_order_discount() ) {
					$extra_amount = '-' . $this->money_format( $order->get_order_discount() );
				}
			}
		}

		return array(
			'items'         => $items,
			'extra_amount'  => $extra_amount,
			'shipping_cost' => $shipping_cost,
		);
	}

	/**
	 * Do checkout request.
	 *
	 * @param  WC_Order $order  Order data.
	 * @param  array    $posted Posted data.
	 *
	 * @return array
	 */
	public function do_checkout_request( $order, $posted ) {
		
		$json[] = array(
			"currency" => "BRL",
			"amount" => $order->get_total(),
			"referenceCode" => $this->gateway->invoice_prefix . $order->get_id(),
			"maxAge" => 1440,
			"callbackURL" =>  site_url() . "/?wc-api=paymee_ipn_listener",
			"shopper" => array(
				"firstName" => $order->get_billing_first_name(),
				"lastName" => $order->get_billing_last_name(),
				"cpf" => $order->billing_cpf,
				"email" => $order->get_billing_email()
			)
		);
		$response = $this->do_request( $this->get_checkout_url(), 'POST', json_encode($json), array( 'Content-Type' => 'application/json;charset=UTF-8',
																						'x-api-key' => 		$this->gateway->get_api_key(),
																						'x-api-token' =>  	$this->gateway->get_api_token()
		));
		if ( is_wp_error( $response ) ) {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'WP_Error in generate payment token: ' . $response->get_error_message() );
			}
		} else if ( 401 === $response['response']['code'] || 403 === $response['response']['code'] ) {
			if ( 'yes' == $this->gateway->debug ) {
				$this->gateway->log->add( $this->gateway->id, 'Invalid token and/or email settings!' );
			}

			return array(
				'url'   => '',
				'data'  => '',
				'error' => array( __( 'Falha em suas credenciais da PayMee do Brasil!', 'woocommerce-paymee' ) ),
			);
		} else {
			try {
				$body = json_decode($response['body']);
			} catch ( Exception $e ) {
				$body = '';
				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Falha em processar o resultado da API: ' . print_r( $e->getMessage(), true ) );
				}
			}

			if ( isset( $body->status ) && $body->status === 0) {
				$token = $body->response[0]->transactionToken;
				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Token de transação gerado com sucesso: ' . $token );
				}

				return array(
					'url'   => $this->get_payment_url( $token ),
					'token' => $token,
					'error' => '',
				);
			}

			if ( isset( $body->error ) ) {
				$errors = array();

				if ( 'yes' == $this->gateway->debug ) {
					$this->gateway->log->add( $this->gateway->id, 'Falha em gerar o token da transação: ' . print_r( $response, true ) );
				}

				foreach ( $body->error as $error_key => $error ) {
					if ( $message = $this->get_error_message( $error->code ) ) {
						$errors[] = '<strong>' . __( 'PayMee', 'woocommerce-paymee' ) . '</strong>: ' . $message;
					}
				}

				return array(
					'url'   => '',
					'token' => '',
					'error' => $errors,
				);
			}
		}

		if ( 'yes' == $this->gateway->debug ) {
			$this->gateway->log->add( $this->gateway->id, 'Falha em gerar o token da transação: ' . print_r( $response, true ) );
		}

		// Return error message.
		return array(
			'url'   => '',
			'token' => '',
			'error' => array( '<strong>' . __( 'PayMee', 'woocommerce-paymee' ) . '</strong>: ' . __( 'Ocorreu um erro, tente novamente ou contate o administrador do site.', 'woocommerce-paymee' ) ),
		);
	}
}
