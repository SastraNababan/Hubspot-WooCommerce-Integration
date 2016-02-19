<?php
if ( ! class_exists( 'hubspot_settings' ) ) :
class hubspot_settings extends WC_Integration {
	public $client_id;
	public $access_token;
	public $connected;
	public $hubspot_api;
	public $hubspot_setting_url;
	public $errors;
 

	public function __construct() {
		global $woocommerce;
		$this->hubspot_setting_url=admin_url()."admin.php?page=wc-settings&tab=integration&section=hubspot-settings";

		$this->hubspot_api = new hubspot_api ();
		$this->client_id 		  = $this->hubspot_api->client_id;	
		$this->id                 ='hubspot-settings';
		$this->method_title       = __( 'Hubspot', 'hubspot-woocommerce-integration' );
		$this->connector 		  =$this->get_option('connector');
		$this->access_token		  =$this->get_option('access_token');
		$this->refresh_token	  =$this->get_option('refresh_token');
		$this->expires_in		  =$this->get_option('expires_in');
		$this->registered_time	  =$this->get_option('registered_time');
		$this->list 			  =$this->get_option('list');
		$this->occurs 			  =$this->get_option('occurs');


 		$this->check_connection();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->portal_id        = $this->get_option( 'portal_id' );
		$this->debug            = $this->get_option( 'debug' );

		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_ajax_nopriv_setting-submit', array($this,'ajax_nopriv'));
		add_action( 'wp_ajax_setting-submit', array($this,'ajax_update_settings' ));

		add_action( 'woocommerce_checkout_update_order_meta',  array( $this, 'order_status_changed' ), 1000, 1 );

		// hook into woocommerce order status changed hook to handle the desired subscription event trigger
		add_action( 'woocommerce_order_status_changed',        array( $this, 'order_status_changed' ), 10, 3 );

		// Filters.
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );

		// Maybe add an "opt-in" field to the checkout
		add_filter( 'woocommerce_checkout_fields',array( $this, 'maybe_add_checkout_fields' ) );

		// Maybe save the "opt-in" field on the checkout
		add_action( 'woocommerce_checkout_update_order_meta',  array( $this, 'maybe_save_checkout_fields' ) );


	}

	public function order_status_changed( $id, $status = 'new', $new_status = 'pending' ) {
		$integration=$this->get_option('enable_integration');
 		if (!($integration == "disable")) {
			$integration=$this->get_option('enable_integration');
			$occurs=$this->get_option('occurs');
			$list_id=$this->get_option('list');
			$subscribe_customer = get_post_meta( $id, 'hubspot_woo_opt_in', true );
	 		
	 		if ($new_status == $this->occurs){
				$order = $this->wc_get_order( $id );
				if ( ! $subscribe_customer || empty( $subscribe_customer ) || 'yes' == $subscribe_customer || 'auto' == $this->get_option('enable_integration') ) {
					$this->subscribe( $order->id, $order->billing_first_name, $order->billing_last_name, $order->billing_email, $list_id );
				}
			}

			self::log( sprintf('Order Id:%s | Integration: %s | Listid : %s | Occurs: %s | NewStatus: %s | Subsribe customer : %s', 
					   $id,$integration,$occurs,$new_status,$subscribe_customer
			));
 
		}	

	}

	function maybe_add_checkout_fields( $checkout_fields ) {
		$integration=$this->get_option('enable_integration');
 		if (!($integration == "disable")) {
			$integration=$this->get_option('enable_integration');
	 		if (! ($integration == "disable") && ($integration =="ask")){
					$display_location = 'billing';
					$checkout_fields[$display_location]['hubspot_woo_opt_in'] = array(
						'type'    => 'checkbox',
						'label'   => esc_attr( $label=$this->get_option('label') ),
						'default' => 1,
					);
			}
		}
			return $checkout_fields;
	}	


	function maybe_save_checkout_fields( $order_id ) {
		$integration=$this->get_option('enable_integration');
 		if (!($integration == "disable")) {
			$integration=$this->get_option('enable_integration');
			if ( $integration =="ask" || $integration =="auto" ) {
				$opt_in = isset( $_POST['hubspot_woo_opt_in'] ) ? 'yes' : 'no';
				update_post_meta( $order_id, 'hubspot_woo_opt_in', $opt_in );
			}
		}	
	}

	public function subscribe( $order_id, $first_name, $last_name, $email, $listid) {
		$listId= $this->get_option('list');
 		$order_id_property=array();
 		$order_id_property["name"] = "order_id";
        $order_id_property["label"] = "Order ID";
        $order_id_property["description"] = "Woocommerce Order ID";
        $order_id_property["groupName"] = "contactinformation";
        $order_id_property["type"] = "string";
        $order_id_property["fieldType"] = "text";
        $order_id_property["formField"] = true;
        $order_id_property["displayOrder"] = 6;
        $order_id_property["options"] = [];
 		
 		$order_id_property=json_encode($order_id_property);
 		$this->hubspot_api->add_property($order_id_property,$this->connector);
 		self::log('Property Added');
		$contact=array();
		$item[]=array("property"=>"email","value" => $email);	
		$item[]=array("property"=>"firstname","value" => $first_name);	
		$item[]=array("property"=>"lastname","value" => $last_name);	
		$item[]=array("property"=>"order_id","value" => $order_id);	
 		$contact['properties']=$item;
 		$contact=json_encode($contact);
 		$contact_id=$this->hubspot_api->create_contact($contact);
 

 		if ($contact_id){
 			$this->hubspot_api->add_contact_to_list($listId,$contact_id);
 			self::log(sprintf('Contact id= %s Added to %s',$contact_id,$listId)); 
 		}
	}

	public function ajax_nopriv(){
		die ( 'Unauthorized Access' );
		exit;
	}

 	public function ajax_update_settings(){
 		if (isset($_POST)){
			$this->update_settings(($_POST['data']));
			// $this->update_settings(array('connected' => '1','registered_time' =>" date("Y-m-d H:i:s")")); 		
		}
 		exit();
 	}


 	public function update_settings($data=array()){
 	  	$options=get_option('woocommerce_hubspot-settings_settings');

 		if ($options) {
 	 		foreach ($data as $key => $value) {
	 			// if(array_key_exists($key, $options)) {
	    			$options[$key] = $value;
				// }	
	 		}
	 	}else{
	 		$options=$data;
	 	}
 	 	update_option('woocommerce_hubspot-settings_settings',$options);
 	}

	public function check_connection(){
		/* check incoming oauth return */
		if (isset($_GET['access_token'])){
			$values=array(
				'access_token'	=> $_GET['access_token'],
				'refresh_token'	=> $_GET['refresh_token'],
				'expires_in'	=> $_GET['expires_in'],
				'registered_time' =>  date("Y-m-d H:i:s")
			);
			$this->update_settings($values);
			wp_redirect($this->hubspot_setting_url);
		}

		/* refresh token */
		$new_token=$this->hubspot_api->refresh_token($this->get_option('refresh_token'),$this->get_option('registered_time'),$this->get_option('expires_in'));

 		if ($new_token){
			$new_token->registered_time=date("Y-m-d H:i:s");
 			$this->update_settings($new_token);
 		}

 		/* if no connection */
 		if ($this->get_option('refresh_token') == "" || $this->get_option('access_token') =="" ){
 			$this->errors=array('Could not establish connection to Hubspot, <a href="'.$this->hubspot_setting_url.'"> Connect Now</a>');
 			$this->display_errors();
 		}else{
 			$this->hubspot_api->access_token=$this->get_option('access_token');
 		}
	}



	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$hubspot_lists=$this->hubspot_api->get_lists();
		$this->form_fields = array(
			'connector' => array(
				'title'             => __( 'Hub ID', 'hubspot-woocommerce-integration' ),
				'type'              => 'connector',
			),
			'access_token' => array(
				'type'              => 'hidden',
			),
			'refresh_token' => array(
				'type'              => 'hidden',
			),
			'expires_in' => array(
				'type'              => 'hidden',
			),
			'registered_time' => array(
				'type'              => 'hidden',
			),
			'enable_integration' => array(
				'title'             => __( 'Integration', 'hubspot-woocommerce-integration' ),
				'type'              => 'select',
				'description'       =>'',
				'desc_tip'          => '',
				'default'           => 'ask',
				'options'			=> array(
										'disable' => __('Disabled', 'hubspot-woocommerce-integration'),
										'ask' =>__('Ask for permission', 'hubspot-woocommerce-integration'),
										'auto' =>__('Subscribe automatically', 'hubspot-woocommerce-integration'),
										)
			),
			'occurs' => array(
				'title'       => __( 'Subscribe Event', 'hubspot-woocommerce-integration' ),
				'type'        => 'select',
				'description' => __( 'When should customers be subscribed to lists?', 'hubspot-woocommerce-integration' ),
				'default'     => 'pending',
				'options'     => array(
					'pending'    => __( 'Order Created', 'hubspot-woocommerce-integration' ),
					'processing' => __( 'Order Processing', 'hubspot-woocommerce-integration' ),
					'completed'  => __( 'Order Completed', 'hubspot-woocommerce-integration' ),
				),
			),
			'list' => array(
				'title'       => __( 'Hubspot List', 'hubspot-woocommerce-integration' ),
				'type'        => 'select',
				'description' => __( 'Only <a href="http://knowledge.hubspot.com/articles/kcs_article/contacts/what-is-the-difference-between-a-smart-list-and-a-static-list">Static List </a> Supported', 'hubspot-woocommerce-integration' ),
				'default'     => '',
				'options'     => $hubspot_lists,
			), 
			'label' => array(
				'title'       => __( 'Label', 'hubspot-woocommerce-integration' ),
				'type'        => 'text',
				'description' => __( '', 'hubspot-woocommerce-integration' ),
				'default'     => 'Subscribe to our newsletter',
			),


		);
	}


 
	public function generate_hidden_html( $key, $data ) {
        $field    = $this->get_field_key( $key );
        $defaults = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'hidden',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array()
        );

        $data = wp_parse_args( $data, $defaults );
        ob_start();
        ?>
        <input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="<?php echo esc_attr( $data['type'] ); ?>" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>"  value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" />
                     
        <?php

        return ob_get_clean();
    }

	public function generate_connector_html($key,$data){
		$field    = $this->plugin_id . $this->id . '_' . $key;
			$button_text="Connect to Hubspot";
			$button_url="";
		 

		$defaults = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => 'Enter your Hub ID Here',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '<a href="http://knowledge.hubspot.com/articles/kcs_article/account/where-can-i-find-my-hub-id"> Where can I find my Hub ID? </a> ',
			'custom_attributes' => array(),
			'button_text'		=> $button_text,
			'button_url'		=> $button_url			
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
			<div class="hubwoo-preloader">
				<div class="hubwoo-status">
				 <img src="<?php echo plugin_dir_url(dirname(__FILE__)) ?>/admin/v2-sprocket-loader.gif" />
			      <h1>Connecting to Hubspot</h1>
			      <p>This could take a while</p>
			    </div>  
			</div>

		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<input type="hidden" name="client_id" value="<?php echo $this->hubspot_api->client_id ?>" />
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="<?php echo esc_attr( $data['type'] ); ?>" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); ?> />
					<a class="button-primary" type="button" name="<?php echo esc_attr( $field ); ?>_button" id="<?php echo esc_attr( $field ); ?>_button" href="<?php echo esc_attr( $data['button_url'] ); ?>" ><?php echo esc_attr( $data['button_text'] ); ?></a>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php // if ($this->get_option('connected')) { ?>
  		<tr valign="top">
			<td><b>Connection Status :</b></td>
			<td>
			
			<table class="tbl tbl-simple">
				<tr>
					<td>Access Token</td><td> : </td><td> <?php echo $this->get_option('access_token')?></td>
				</tr>
				<tr>	
					<td>Refresh Token</td><td> : </td><td> <?php echo $this->get_option('refresh_token') ?> </td>
				</tr>
				<!-- <tr>	
					<td>Expires in</td><td> : </td><td> <?php echo $this->get_option('expires_in') ?>  </td>
				</tr> -->
				<tr>	
					<td>Registered Time</td><td> : </td><td>  <?php echo  $this->get_option('registered_time')?>  </td>
				</tr>
 				<!-- <tr>	
					<td>Expires at</td><td> : </td><td>  <?php echo  $this->get_option('expires_at')?><br/></td>
				</tr> -->

			</table>
			
 
			</td>
		</tr>  
		<?php // } ?>
		<!-- <tr><td colspan="2"><hr/></td></tr> -->
		<?php
		return ob_get_clean();	}


	/**
	 * Generate Button HTML.
	 */
	public function generate_button_html( $key, $data ) {
		$field    = $this->plugin_id . $this->id . '_' . $key;
		$defaults = array(
			'class'             => 'button-secondary',
			'css'               => '',
			'custom_attributes' => array(),
			'desc_tip'          => false,
			'description'       => '',
			'title'             => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<!-- <label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label> -->
				<?php // echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<button class="<?php echo esc_attr( $data['class'] ); ?>" type="button" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>><?php echo wp_kses_post( $data['title'] ); ?></button>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}


	/**
	 * Santize our settings
	 * @see process_admin_options()
	 */
	public function sanitize_settings( $settings ) {
		// We're just going to make the api key all upper case characters since that's how our imaginary API works
		if ( isset( $settings ) &&
		     isset( $settings['portal_id'] ) ) {
			$settings['portal_id'] = strtoupper( $settings['portal_id'] );
		}
		return $settings;
	}


	/**
	 * Helper log function for debugging
	 *
	 * @since 1.2.2
	 */
	static function log( $message ) {
		// if ( WP_DEBUG === true ) {
			$logger = new WC_Logger();

			if ( is_array( $message ) || is_object( $message ) ) {
				$logger->add( 'hubspot_woo_integration', print_r( $message, true ) );
			}
			else {
				$logger->add( 'hubspot_woo_integration', $message );
			}
		// }
	}

	private function wc_get_order( $order_id ) {
		if ( function_exists( 'wc_get_order' ) ) {
			return wc_get_order( $order_id );
		} else {
			return new WC_Order( $order_id );
		}
	}
	

	public function display_errors() {
		if (isset($this->errors)){
			// loop through each error and display it
			foreach ( $this->errors as $key => $value ) {
				?>
				<div class="error">
					<p>
					<?php _e( $value);?>
					</p>
				</div>
				<?php
			}
		}
	}
}
endif; // class exist