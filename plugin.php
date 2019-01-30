<?php
/*
Plugin Name: Weldpay
Description: Weldpay Payment Gateway for WooCommerce
Author: Nicola Usai
Author URI: https://www.upwork.com/freelancers/~0196ad45cb4b5bf7af
*/
add_filter( 'woocommerce_payment_gateways', 'weldpay_add_gateway_class' );
  function weldpay_add_gateway_class( $gateways ) {
  $gateways[] = 'WC_Weldpay_Gateway';
  return $gateways;
}
add_action( 'plugins_loaded', 'weldpay_init_gateway_class' );
function weldpay_init_gateway_class() {
  class WC_Weldpay_Gateway extends WC_Payment_Gateway {
    public function __construct() {
      $this->id = 'weldpay';
      $this->icon = '';
      $this->has_fields = false;
      $this->method_title = 'Weldpay';
      $this->method_description = 'Accepts payments with the Weldpay Gateway for WooCommerce';
      $this->supports = array('products');
      $this->init_form_fields();
      $this->init_settings();
      $this->enabled = $this->get_option( 'enabled' );
      $this->title = $this->get_option( 'title' );
      $this->description = $this->get_option( 'description' );
      $this->clientId = $this->get_option( 'clientId' );
      $this->clientSecret = $this->get_option( 'clientSecret' );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'woocommerce_api_weldpay_webhook', array( $this, 'webhook' ) );
    }
    public function init_form_fields(){
      $this->form_fields = array(
        'enabled' => array(
          'title'       => 'Enable/Disable',
          'label'       => 'Enable Weldpay',
          'type'        => 'checkbox',
          'description' => '',
          'default'     => 'no'
        ),
        'title' => array(
          'title'       => 'Title',
          'type'        => 'text',
          'description' => 'This controls the title which the user sees during checkout.',
          'default'     => 'Weldpay'
        ),
        'description' => array(
          'title'       => 'Description',
          'type'        => 'textarea',
          'description' => 'This controls the description which the user sees during checkout.',
          'default'     => 'Pay with the Weldpay payment gateway.'
        ),
        'clientId' => array(
          'title'       => 'Client ID',
          'type'        => 'text',
          'description'       => 'Enter your Weldpay Client ID'
        ),
        'clientSecret' => array(
          'title'       => 'Client secret',
          'type'        => 'text',
          'description'       => 'Enter your Weldpay Client secret'
        )
      );
    }
    public function process_payment( $order_id ) {
      global $woocommerce;
      $order = new WC_Order( $order_id );
      $items = $order->get_items();
      $weldpay_items = array();
      $weldpay_item = array();
      foreach ($items as $item) {
        $weldpay_item = array(
          'Name' => $item['name'],
          'Notes' => $item['quantity'],
          'Amount' => $item['total']
        );
        $weldpay_items[] = json_encode($weldpay_item);
      }
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://payments.weldpay.it/api/1.0/gateway/generate-transaction");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HEADER, FALSE);
      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, "{
        \"Buyer\": {
          \"Firstname\": \"".$order->get_billing_first_name()."\",
          \"Lastname\": \"".$order->get_billing_last_name()."\",
          \"TaxCode\": \"Inserire il codice fiscale\",
          \"Email\": \"".$order->get_billing_email()."\",
        },
        \"OrderId\": \"".$order_id."\",
        \"Items\": [".implode(',', $weldpay_items)."],
        \"ShippingItems\": [
          {
            \"Name\": \"".$order->get_shipping_method()."\",
            \"Notes\": null,
            \"Amount\": ".$order->get_total_shipping()."
          }
        ],
        \"SuccessUrl\": \"".$this->get_return_url( $order )."\",
        \"CancelUrl\": \"".wc_get_cart_url()."\",
        \"ServerNotificationUrl\": \"".get_bloginfo('url')."/wc-api/weldpay_webhook/?order_id=".$order_id."\"
      }");
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/json",
        "Authorization: Basic ".base64_encode($this->clientId.":".$this->clientSecret).""
      ));
      $response = curl_exec($ch);
      curl_close($ch);
      return array(
        'result' => 'success',
        'redirect' => $response
      );
    }
    public function webhook() {
      $order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
      $order = wc_get_order( $order_id );
      $order->payment_complete();
      wc_reduce_stock_levels($order_id);
    }
  }
}