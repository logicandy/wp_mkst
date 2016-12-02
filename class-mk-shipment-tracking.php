<?php
class MKST {
	
	/** initializing class variables, maybe should make some final */
	public static $db_version = '0.1.0';
	public static $domain = 'mkst';

	private $provider_directory = 'includes/providers';
	private $providers = null;
	private $user_table_name = null;
	private $tracks_table_name = null;    
	private $result = null;

	public function __construct() {
	  $this->init_vars();
	  if ( is_admin() ){
	    $this->register_settings();
	  } else {
	    $this->form_processing();
	  }
		$this->load_textdomain();
		$this->init_hooks();
	}

	public function add_menu() {
	  add_options_page( __( 'Shipment Tracking Settings', self::$domain ), __( 'Shipment Tracking', self::$domain ), 'edit_plugins', __FILE__, array( $this, 'show_settings' ) );
	}

	private function add_track_to_db( $data ) {
	  global $wpdb;
	  
	  $track_info = serialize( $data['options'] );
	  $query = $wpdb->prepare( 'SELECT * FROM '.$wpdb->get_blog_prefix().self::$domain.'_'.'user_tracks'.' WHERE track_info="%s";', $track_info );
	  if ( null === $wpdb->get_row( $query ) ) {
	    if ( $wpdb->insert( $this->user_table_name, array( 'user_id' => get_current_user_id(),
	                                                'track_info' => $track_info,
	                                                'status' => 'new',
	                                                'desc' => $data['description'] ) ) ) {
	      return __( 'Tracking info successfully added.', self::$domain );
	    } else {
	      return __( 'An error occured, tracking info not added.', self::$domain );
	    }
	  } else {
	    return __( 'Looks like this track already exists.', self::$domain );
	  }
	}

	public function form_processing() {
	  global $wpdb;

	  if ( isset( $_POST['act'] ) && sanitize_text_field( $_POST['act'] ) === 'add' ) {
	    if ( !wp_verify_nonce( $_POST['_wpnonce'], self::$domain.'_add_tracking_element' ) ) {
	      return false;
	    }
	    $this->result = null;
	    foreach ($_POST as $key => $value) {
	      $_POST[$key] = sanitize_text_field($value);
	    }
	    $provider_class = $_POST['ship_provider'];
	    foreach ($this->providers as $provider) {
	      if( $provider['class_name'] == $provider_class ) {
	        foreach ($provider['instance']->get_required_fields() as $key => $value) {
	          $options[$key] = $_POST[$key];
	        }
	      }
	    }
	    $result['description'] = $_POST['ship_desc'];
	    $result['options'] = $options;
	    $result['options']['class_name'] = $provider_class;
	    $this->result[] = $this->add_track_to_db( $result );
	  } elseif ( isset( $_GET['act'] ) && sanitize_text_field( $_GET['act'] ) === 'confirm' ) {
	  	$track_id = sanitize_text_field( $_GET['id'] );
	  	$user_id = get_current_user_id();
	  	$wpdb->update( $this->user_table_name, array( 'received' => 1 ), array( 'track_id' => $track_id, 'user_id' => $user_id ) );
	  } elseif ( isset( $_POST['act'] ) && sanitize_text_field( $_POST['act'] ) == 'verify' ) {
	  	if ( empty( $_POST['verify_code'] ) ) {
	  		$phone = esc_attr( $_POST['phone'] );
	  		$verify_code = $this->generate_string( 5 );
	  		set_transient( 'cellphone_tr_'.get_current_user_id(), $phone, 10*MINUTE_IN_SECONDS );
	  		set_transient( 'cellphone_verify_tr_'.get_current_user_id(), $verify_code, 10*MINUTE_IN_SECONDS );
	  		$this->send_sms( preg_replace( '/\D+/', '', $phone ), $verify_code );
	  		$this->result[] = __( 'Verify code was sent, it will be active for 10 minutes.', self::$domain );
	  	} else {
	  		$phone = get_transient( 'cellphone_tr_'.get_current_user_id() );
	  		$verify = get_transient( 'cellphone_verify_tr_'.get_current_user_id() );
	  		if ( false === $verify ) {
	  			$this->result[] = __( 'Verifying code is overdue.', self::$domain );
	  			return false;
	  		}
	  		if ( esc_attr( $_POST['verify_code'] == $verify ) ) {
	  			delete_transient( 'cellphone_tr_'.get_current_user_id() );
	  			delete_transient( 'cellphone_verify_tr_'.get_current_user_id() );
	  			add_user_meta( get_current_user_id(), 'cellphone', preg_replace( '/\D+/', '', $phone ), true );
	  			$this->result[] = __( 'Cellphone successfully verified.', self::$domain );
	  		} else {
	  			$this->result[] = __( 'Verifying code is wrong.', self::$domain );
	  			return false;	
	  		}
	  	}
	  }
	}

	private function generate_string( $length = 10 ) {
		return substr( str_shuffle( str_repeat( $x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil( $length/strlen( $x ) ) ) ), 1, $length );
	}

	private function get_provider_instance( $class_name ) {
	  foreach ($this->providers as $provider) {
	    if ( $provider['class_name'] == $class_name ) {
	      return $provider['instance'];
	    }
	  }
	  return false;
	}

	private function get_provider( $class_name ) {
	  foreach ($this->providers as $provider) {
	    if ( $provider['class_name'] == $class_name ) {
	      return $provider;
	    }
	  }
	  return false;
	}

	public static function init() {
		$class = __CLASS__;
		new $class;
	}

	private function init_hooks() {
		$css_order = get_option( self::$domain.'_css_order' );
		if ( empty( $css_order ) ) {
			$css_order = 99;
		}
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_styles' ), $css_order );
		add_shortcode( self::$domain.'_display_tracks_section', array( $this, 'show_tracks_section' ) );
		add_shortcode( self::$domain.'_display_add_section', array( $this, 'show_add_section' ) );
		add_shortcode( self::$domain.'_display_phone_section', array( $this, 'show_phone_section' ) );
	}

	private function init_vars() {
	  global $wpdb;

	  if ( is_admin() ){
	    $this->load_providers();
	  } else {
	    $this->load_active_providers();
	  }
	  $this->user_table_name = $wpdb->get_blog_prefix().self::$domain.'_'.'user_tracks';
	  $this->tracks_table_name = $wpdb->get_blog_prefix().self::$domain.'_'.'track_history';    

	}

	public static function install() {
	  global $wpdb;

	  $db_version_installed = get_option( self::$domain.'_version' );
	  if ( FALSE === $db_version_installed ) {
	    $db_version_installed = self::$db_version;
	    add_option( self::$domain.'_version', self::$db_version );
	  } elseif ( $db_version_installed > self::$db_version ) {
	    //self::update();
	  }
	  
	  $charset_collate = $wpdb->get_charset_collate();
	  $sql = "";
	  $user_table_name = $wpdb->get_blog_prefix().self::$domain.'_'.'user_tracks';
	  $tracks_table_name = $wpdb->get_blog_prefix().self::$domain.'_'.'track_history';    

	  require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	  
	  if ($wpdb->get_var("SHOW TABLES LIKE '$user_table_name'") != $user_table_name) {
	    $sql = "CREATE TABLE `$user_table_name` (
	              `track_id` int(11) NOT NULL AUTO_INCREMENT,
	              `user_id` int(11) NOT NULL,
	              `track_info` varchar(255) NOT NULL,
	              `status` varchar(50) NOT NULL,
	              `desc` varchar(255),
	              `received` int(11) NOT NULL DEFAULT '0',
	              `add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	              `receive_date` timestamp,
	              `update_date` timestamp ON UPDATE CURRENT_TIMESTAMP,
	              PRIMARY KEY (`track_id`)
	            ) $charset_collate;";
	    dbDelta( $sql );
	  }
	  if ($wpdb->get_var("SHOW TABLES LIKE '$tracks_table_name'") != $tracks_table_name) {
	    $sql = "CREATE TABLE `$tracks_table_name` (
	              `id` int(11) NOT NULL AUTO_INCREMENT,
	              `track_id` int(11) NOT NULL,
	              `oper_id` int(11) NOT NULL,
	              `operation_address` varchar(50),
	              `operation_code` varchar(50),
	              `destination_address` varchar(50),
	              `destination_code` varchar(50),
	              `mail_direct_name` varchar(50),
	              `country_oper` varchar(50),
	              `oper_type_id` varchar(50),
	              `oper_type_name` varchar(50),
	              `oper_attr_id` varchar(50),
	              `oper_attr_name` varchar(50),
	              `oper_date` timestamp,
	              `item_weight` varchar(50),
	              `complex_item_name` varchar(50),
	              `mail_rank` varchar(50),
	              `mail_type` varchar(50),
	              `mail_ctg` varchar(50),
	              `rcpn` varchar(50),
	              PRIMARY KEY (`id`)
	            ) $charset_collate;";
	    dbDelta( $sql );
	  }
	}

	private function load_active_providers() {
	  $active_providers = get_option( self::$domain.'_active_providers' );
	  if ( empty( $active_providers ) ) {
	    return false;
	  }
	  $i = 0;
	  foreach ($active_providers as $class_name) {
	    $class_filename = 'class-' . str_replace( '_', '-', $class_name ) . '.php';
	    $class_filename = strtolower( $class_filename );
	    include_once( $this->provider_directory . '/' . $class_filename );
	    $provider = new $class_name();
	    foreach ($provider->get_options() as $key => $value) {
	      $option = get_option( self::$domain.'_'.$class_name.'_'.$key );
	      $provider->set_option( $key, $option );
	    }
	    $this->providers[] = array ( 'class_name' => $class_name, 'name' => $provider->get_display_name(), 'instance' => $provider );
	    $i++;
	  }
	  if ( $i == 0 ) {
	      $this->providers = null;
	  }
	}

	private function load_providers() {
	  $i = 0;
	  foreach ( glob( dirname( __FILE__ ) . '/' . $this->provider_directory . '/class-*.php' ) as $file ) {
	    include_once( $this->provider_directory . '/' . basename( $file ) );
	    $arr_name = explode( '-', basename($file,'.php') );
	    array_shift( $arr_name );
	    foreach ($arr_name as $index => $word) {
	        $arr_name[$index] = ucfirst($word);
	    }
	    $class_name = implode( '_', $arr_name );
	    $provider = new $class_name();
	    $this->providers[] = array ( 'class_name' => $class_name, 'name' => $provider->get_display_name(), 'instance' => $provider );
	    $i++;
	  }
	  if ( $i == 0 ) {
	    $this->providers = null;
	  }
	}

	public function load_scripts() {
	  wp_enqueue_script( 'jquery_masked_input', plugins_url( 'assets/js/jquery.maskedinput.min.js', __FILE__ ), array( 'jquery' ) );
	  wp_enqueue_script( self::$domain.'_js', plugins_url( 'assets/js/mkst.js', __FILE__ ), array( 'jquery-ui-accordion', 'jquery_masked_input' ), '', true );
	  $this->load_variables_js();
	}

	public function load_styles() {
	  wp_enqueue_style( self::$domain.'_style', plugins_url( 'assets/css/mkst.css', __FILE__ ) );
	}

	private function load_textdomain() {
	  load_plugin_textdomain( self::$domain, FALSE, basename( dirname( __FILE__ ) ) . '/lang/' );
	}

	private function load_variables_js() {
	  $provider_fields = null;
	  $l10n = array( "add_button" => __( "add new track", self::$domain ));
	  foreach ($this->providers as $provider) {
	    $provider_fields[$provider['class_name']] = $provider['instance']->get_required_fields();
	  }
	  wp_localize_script( self::$domain.'_js', self::$domain.'_provider_options', $provider_fields );
	  wp_localize_script( self::$domain.'_js', self::$domain.'_l10n', $l10n );
	}

	public function register_settings() {
	  register_setting( self::$domain, self::$domain.'_active_providers' );
	  register_setting( self::$domain, self::$domain.'_css_order' );
	  register_setting( self::$domain, self::$domain.'_sms_api_key' );
	  foreach ($this->providers as $provider ) {
	  	foreach ($provider['instance']->get_options() as $option => $value) {
	      register_setting( self::$domain, self::$domain.'_'.$provider['class_name'].'_'.$option );
	    }
	  }
	}

	private function send_sms($phone, $text){
		if(empty($phone)) return false;
		$text_cp = urlencode($text);
		$sms_api = get_option( self::$domain.'_sms_api_key' );
		$body=file_get_contents("http://sms.ru/sms/send?api_id=".$sms_api."&to=".$phone."&text=".$text_cp."&from=MKBox");
		return true;
	}

	public function show_add_section() {
	  if ( !is_user_logged_in() ) {
	    _e( 'You should be logged in to view this page.', self::$domain );
	    return false;
	  }
	  $button = '<div id="toggle_add"><a>'.__( 'Add new track', self::$domain ).'</a>';
	  $html = '<div class="track_add_content">
	<form method="POST" action="">'.wp_nonce_field( self::$domain.'_add_tracking_element' ).'
	  <input type="hidden" name="act" value="add" />
	  <select name="ship_provider" id="ship_provider" autocomplete="off">
	    <option value selected disabled>'.__( 'Select provider', self::$domain ).'</option>';
	  foreach ( $this->providers as $provider ) {
	    $html .= '<option value="'.$provider['class_name'].'">'.$provider['name'].'</option>';
	  }
	  $html .= '</select><input type="text" name="ship_desc" id="ship_desc" placeholder="'.__( 'Shipment description', self::$domain ).'" /></form></div></div>';

	  if ( !empty( $this->result ) ) {
	    if ( is_array( $this->result ) ) {
	    	foreach ($this->result as $string) {
	    		echo '<p>'.$string.'</p>';
	    	}
	    } else {
	    	echo '<p>'.$this->result.'</p>';
	    }
	  }
	  echo $button;
	  echo $html;
	}

	public function show_main_option_checkbox( $args ) {
	  echo '<input type="checkbox" name="'.$args['name'].'[]" id="'.$args['name'].'" value="'.$args['class_name'].'"'.$args['checked'].'>';
	}

	public function show_main_section() {
	  $option = get_option( self::$domain.'_active_providers' );
	  foreach ( $this->providers as $provider ) {
	    if ( !empty( $option ) && in_array( $provider['class_name'], $option ) ) {
	      $checked = ' checked="checked"';
	    } else {
	      $checked = '';
	    }
	    add_settings_field( self::$domain.'_active_providers_'.$provider['name'], 
	                      $provider['name'], 
	                      array( $this, 'show_main_option_checkbox' ), 
	                      __FILE__, 
	                      self::$domain.'_main', 
	                      array( 'name' => self::$domain.'_active_providers', 
	                            'class_name' => $provider['class_name'],
	                            'checked' => $checked ) );
	  }
	  add_settings_field( self::$domain.'_css_order',
	  					__( 'CSS order', self::$domain ),
	  					array( $this, 'show_setting_field' ),
	  					__FILE__,
	  					self::$domain.'_main',
	  					array( 'value' => array( self::$domain.'_css_order' ) )
	  					 );
	  add_settings_field( self::$domain.'_sms_api_key',
	  					__( 'SMS.RU API key', self::$domain ),
	  					array( $this, 'show_setting_field'),
	  					__FILE__,
	  					self::$domain.'_main',
	  					array( 'value' => array( 'name' => self::$domain.'_sms_api_key') ) 
	  					 );
	}

	public function show_phone_section() {
		$meta_phone = get_user_meta( get_current_user_id(), 'cellphone', true );
		if ( !empty( $meta_phone ) ) {
			$phone = $meta_phone;
		} else {
			$phone = get_transient( 'cellphone_tr_'.get_current_user_id() );
		}
		$head = '<div id="toggle_phone"><a>'.__( 'Cellphone', self::$domain ).'</a>';
		$main = '<form method="POST" action"">'.wp_nonce_field( self::$domain.'_phone' ).'
	<input type="hidden" name="act" value="verify" />';
		if ( !empty( $phone ) ) {
			$phone_input = '<p><a href="act=rm_phone">'.$phone.' ('.__( 'click to remove', self::$domain ).')</a></p>';
			$notice = __( 'Your current phone, used for sms notification', self::$domain );
			if ( empty( $meta_phone ) ) {
				$verify = '<input type="text" name="verify_code" id="verify_code" placeholder="Verify code" /><input type="submit" name="submit" class="button mini" value="'.__( 'Verify', self::$domain ).'" />';
			} else {
				$verify = '';
			}
		} else {
			$phone_input = '<p><input type="text" name="phone" id="phone_masked" value="'.$phone.'" />';
			$notice = __( 'Provide phone number for receiving shipment updates by sms.', self::$domain );
			$verify = '<input type="submit" name="submit" class="button mini" value="'.__( 'Save phone', self::$domain ).'" />';
		}
		$foot = '</p></form></div>';
		echo $head.$main.$notice.$phone_input.$verify.$foot;
	}

	public function show_providers_section() {
	  $active_providers = get_option( self::$domain.'_active_providers' );
	  foreach ($active_providers as $class) {
	  	$instance = $this->get_provider( $class );
	    $first = true;
	    foreach ($instance['instance']->get_options() as $option => $value) {
	      $value['name'] = self::$domain.'_'.$instance['class_name'].'_'.$option;
	      if ( $first ) {
	      	add_settings_field( self::$domain.'_'.$instance['class_name'],
	      						null,
	      						array( $this, 'show_setting_provider_header' ),
	      						__FILE__,
	      						self::$domain.'_providers',
	      						array( 'name' => $instance['name'] ) 
	      					);
	      }
	      add_settings_field( self::$domain.'_'.$instance['class_name'].'_'.$option, 
	      					  __( $value['display_name'], self::$domain ), 
	                          array( $this, 'show_setting_field' ), 
	                          __FILE__, 
	                          self::$domain.'_providers', 
	                          array( 'value' => $value )
	                        );
	  	  $first = false;
	    }
	  }
	}

	public function show_settings() {
	  $active_providers = get_option( self::$domain.'_active_providers' );
	  
	  echo '<div class="wrap">';
	  echo '<h1>'.__( "Shipment Tracking Settings", self::$domain ).'</h1>';

	  if ( empty( $this->providers ) ) {
	    echo '<h2>';
	    _e( 'No providers found.', self::$domain );
	    echo '</h2>';
	  } else {
	    echo '<form method="post" action="options.php">';
	    wp_nonce_field( "update-options" );
	    add_settings_section( self::$domain.'_main', __( 'Main plugin settings', self::$domain ), array( $this, 'show_main_section' ), __FILE__ );
	    if ( !empty( $active_providers ) ) {
	      add_settings_section( self::$domain.'_providers', __( 'Providers settings', self::$domain ), array( $this, 'show_providers_section' ), __FILE__ );
	    }
	    settings_fields(self::$domain);
	    do_settings_sections( __FILE__ );
	    submit_button();
	    echo '</form>';
	  }
	  echo '</div>';
	}

	public function show_setting_provider_header( $args ) {
	    echo "<h3>".$args['name']."</h3>";
	}

	public function show_setting_field( $args ) {
	  echo "<input name='".$args['value']['name']."' id='".$args['value']['name']."' class='regular-text' type='text' value='".get_option($args['value']['name'])."' />";
	  if ( isset( $args['value']['comment'] ) ) {
	      echo "<p id='".$args['value']['name']."-description"."' class='description'>".$args['value']['comment']."</p>";
	  }
	}

	public function show_tracks_section() {
	  global $wpdb;
	  if ( !is_user_logged_in() ) {
	    _e( 'You should be logged in to view this page.', self::$domain );
	    return false;
	  }
	  $query = $wpdb->prepare( 'SELECT * FROM '.$this->user_table_name.' WHERE user_id=%d AND received=0 ORDER BY add_date DESC;', get_current_user_id() );
	  $result = $wpdb->get_results( $query, ARRAY_A );
      if ( $wpdb->num_rows > 0 ) {
	    echo '<div id="toggle_track">';
	    foreach ($result as $row) {
	      $history = null;
	      $options = unserialize( $row['track_info'] );
	      $class = array_pop( $options );
	      $query = $wpdb->prepare( 'SELECT * FROM '.$this->tracks_table_name.' WHERE track_id=%d ORDER BY oper_date ASC;', $row['track_id'] );
	      $history = $wpdb->get_results( $query, ARRAY_A );
	      if ( empty( $history ) ) {
	      	$operdate = "  -  -  ";
	      } else {
	      	$operdate = date( 'd-m', strtotime( $history[count( $history )-1]['oper_date'] ) );
	      }
	      if ( empty( $row['update_date'] ) ) {
			$updatedate = "  -  -  ";
	      } else {
	      	$updatedate = date( 'd-m', strtotime( $row['update_date'] ) );
	      }
	      echo '<div class="toggle-header">'.implode( ', ', $options ).', '.$row['desc'];
	      echo '<span class="text-right">'.__( 'last oper.', self::$domain ).':'.$operdate.'; ';
	      echo __( 'updated', self::$domain ).':'.$updatedate;
	      echo '</span></div>';
	      echo '<div class="tracks_content">';
	      echo '<a href="?act=confirm&id='.$row['track_id'].'" class="confirm-right">'.__( 'Confirm receipt.', self::$domain ).'</a>';
	      if ( empty( $history ) ) {
	        _e( 'History not found.', self::$domain );
	      } else {
	      	echo '<table>';
	      	foreach ($history as $record) {
	      		echo '<tr>';
	      		echo '<td class="datetime">'.date( 'd-m H:i', strtotime( $record['oper_date'] ) ).'</td>';
	      		$oper_code = "";
	      		if ( !empty( $record['operation_code'] ) ) {
	      			$oper_code = ' ('.$record['operation_code'].')';
	      		}
	      		echo '<td>'.$record['operation_address'].$oper_code.'</td>';
	      		echo '<td>'.$record['oper_type_name'].'</td>';
	      		echo '</tr>';
	      	}
	      	echo '</table>';
	      }
	      echo '</div>';
	    }
	    echo '</div>';
	  } else {
	  	_e( 'Active tracks not found.', self::$domain );
	  }
	}

	public function update_tracking_history() {
	  global $wpdb;
	  $query = 'SELECT * FROM '.$this->user_table_name.' WHERE received=0 ORDER BY add_date DESC;';
	  $result = $wpdb->get_results( $query, ARRAY_A );
	  $history = null;
	  $updated_tracks = null;
      if ( $wpdb->num_rows > 0 ) {
		foreach ($result as $row) {
	      $history = null;
	      $options = unserialize( $row['track_info'] );
	      $class = array_pop( $options );
	      $history = $this->get_provider_instance( $class )->get_track_history( $options );
	      if ( empty( $history ) ) {
	      	continue;
	      }
	      if ( !empty( $history['error'] ) ) {
	      	continue;
	      }
	      $a = $wpdb->update( $this->user_table_name, array( 'update_date' => current_time( 'mysql' ) ), array( 'track_id' => $row['track_id'] ) );
	      $query = $wpdb->prepare( 'SELECT MAX(oper_id) AS last_oper_id FROM '.$this->tracks_table_name.' WHERE track_id = %d', $row['track_id'] );
	      $var = $wpdb->get_var( $query );
	      if ( $var != null ) {
	        $last_oper_id = $var;
	      } else {
	        $last_oper_id = -1;
	      }
	      if ( $history[count( $history )-1]['oper_id'] > $last_oper_id ){
	      	for ($i = $last_oper_id + 1; $i < count( $history ); $i++) { 
        		if ( $history[$i] !== null ) {
	        		$history[$i]['track_id'] = $row['track_id'];
			        if ( !$wpdb->insert( $this->tracks_table_name, $history[$i] ) ) {
			        	echo "Database error";
			        } else {
			        	$phone = get_user_meta( $row['user_id'], 'cellphone', true );
			        	$this->send_sms( $phone, "New status on shipment (".$row['desc']."): ".$history[$i]['oper_type_name']." (".$history[$i]['operation_address'].")" );
			        }
			    }
	        }
	        $updated_tracks[] = array( 'track_id' => $row['track_id'] );
	      }
	    }  
	  }
	  return $updated_tracks;
	}

}
?>