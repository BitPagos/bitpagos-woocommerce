<?php
/*
Plugin Name: WooCommerce BitPagos Payment Gateway
Plugin URI: http://www.devsar.com
Description: BitPagos Payment gateway for woocommerce
Version: 0.1
Author: Devsar
Author URI: http://www.devsar.com
*/

add_action('plugins_loaded', 'woocommerce_bitpagos_init', 0);

error_reporting(1);

function woocommerce_bitpagos_init() {

  	if ( !class_exists('WC_Payment_Gateway') ) return;
  	
  	class WC_BitPagos extends WC_Payment_Gateway {
    
    	public function __construct(){
    		
    		global $woocommerce;
    		
		    $this->id 					= 'bitpagos';
		    $this->method_title 		= 'BitPagos';
		    $this->method_description 	= 'Description goes here';
		    $this->has_fields 			= false;
        	$this->ipn_url				= str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_BitPagos', home_url( '/' ) ) );

      		$this->init_form_fields();
      		$this->init_settings();
 	
		    $this->title 				= $this->get_option('title');
		    $this->description 			= $this->get_option('description');
		    $this->merchant_id 			= $this->get_option('merchant_id');
		    $this->salt 				= $this->get_option('salt');
		    $this->redirect_page_id 	= $this->get_option('redirect_page_id');		    
		    $this->msg['message'] 		= '';
		    $this->msg['class'] 		= '';
 
    		add_action( 'woocommerce_api_wc_' . $this->id, array( $this, 'check_ipn_response' ) );
    		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

   		}

   		function init_form_fields(){

   			$this->form_fields = array(
   				'enabled' => array(
                    'title' => __('Enable/Disable', 'devsar'),
                    'type' => 'checkbox',
                    'label' => __('Enable BitPagos Payment Module.', 'devsar'),
                    'default' => 'no'),
   				'title' => array(
                    'title' => __('Title', 'devsar'),
                    'type' => 'text',
                    'label' => __('Title: ', 'devsar'),
                    'default' => 'BitPagos'),
   				'account_id' => array(
                    'title' => __('Account ID', 'devsar'),
                    'type' => 'text',
                    'label' => __('Account ID from BitPagos.', 'devsar'),
                    'default' => ''),
   				'api_key' => array(
                    'title' => __('API KEY', 'devsar'),
                    'type' => 'text',
                    'label' => __('API KEY from BitPagos.', 'devsar'),
                    'default' => ''),
			);
      
   		}

   		public function admin_options(){
	        echo '<h3>'.__('BitPagos Payment Gateway', 'devsar').'</h3>';
	        echo '<table class="form-table">';
		        $this->generate_settings_html();
	        echo '</table>';
	    }	    

	    public function check_ipn_response() {	    	

			if ( sizeOf( $_POST ) == 0 ) { 
	            header("HTTP/1.1 500 EMPTY_POST ");
	            return false;
	        }

	        if (!isset( $_POST['transaction_id'] ) || 
	            !isset( $_POST['reference_id'] ) ) {
	            header("HTTP/1.1 500 BAD_PARAMETERS");
	            return false;
	        }

			$transaction_id = filter_var( $_POST['transaction_id'], FILTER_SANITIZE_STRING);
			$url = 'https://www.bitpagos.net/api/v1/transaction/' . $transaction_id . '/?api_key=' . $this->get_option('api_key') . '&format=json';

	    	$cbp = curl_init( $url );
	    	curl_setopt($cbp, CURLOPT_RETURNTRANSFER, TRUE);
	    	$response_curl = curl_exec( $cbp );
	    	curl_close( $cbp );
    		$response = json_decode( $response_curl );

	    	$order_id = (int)$_POST['reference_id'];

	    	if ( $order_id != $response->reference_id ) {
	    		die('Wrong reference id');
	    	}

	    	if ( $response->status == 'PA' || $response->status == 'CO' ) {

	    		global $woocommerce;    		
	    		
				$order = new WC_Order( $order_id );
				
				$order->update_status('completed');

				$order->payment_complete();

				file_put_contents('/tmp/ipn.log', print_r($order, TRUE));
				
				header("HTTP/1.1 200 OK");

	    	}
	    }

	    public function process_payment( $order_id ) {
			
			global $woocommerce;
	    	
	    	$order = new WC_Order( $order_id );
	    	
	    	$order->update_status('pending');

	    	// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			$woocommerce->cart->empty_cart();

	        return array(
	        	'result' => 'success', 
	        	'redirect' => add_query_arg(
        						'order', $order->id, 
        						add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
	        );

		}

	    /**
	     * Output for the order received page.
	     *
	     * @access public
	     * @return void
	     */
		function receipt_page( $order_id ) {			

			$order = new WC_Order( $order_id );

			$tpl = '<div style="text-align: center">';
			$tpl .= '<form action=' . $this->get_return_url( $order ) . ' method="post">';			
			$tpl .= '<p>'.__( 'Thank you for your order, please click the button below to pay with BitPagos.', 'woocommerce' ).'</p>';
			$tpl .= "<script src='https://www.bitpagos.net/public/js/partner/m.js' class='bp-partner-button' data-role='checkout' data-account-id='" . $this->get_option('account_id') . "' data-reference-id='" . $order->id . "' data-title='product description' data-amount='" . $order->order_total . "' data-currency='USD' data-description='' data-ipn='" . $this->ipn_url . "'></script> ";
			$tpl .= '</form></div>';
			echo $tpl;
		}

 	}

    /**
     * Add the Gateway to WooCommerce
     **/
    
    function woocommerce_add_bitpagos_gateway($methods) {
        $methods[] = 'WC_BitPagos';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_bitpagos_gateway' );    	
    
}