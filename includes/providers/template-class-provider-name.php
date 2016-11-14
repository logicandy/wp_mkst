<?php
/*
	Provider name: 
	version: 
*/
?>
<?php
	/* 
		Class name should be equal to filename (according to WP naming convention)
		For example if filename is class-junk-provider.php then class should be named Junk_Provider
	*/
	class Class_Name {
		/*
			Name that will be displayed on settings page, select box etc. 
		*/
		private $display_name = '';
		/*
			Options for settings page (user login, password for providers API, etc.)
			should with next structure:
				array( "option_name_1" => array( "display_name" => $string,
												 "comment" => $string,
												 "value" => "" ),
					   "option_name_2" ... );
		*/
		private $options = array(  );
		/*
			Array of fields, that required for getting tracking information from provider (№, city, etc..)
			Should be in pairs "field_name" => "display_name"
		*/
		private $required_fields = array(  );

		public function get_display_name() {
			return $this->display_name;
		}

		public function get_options() {
			return $this->options;
		}

		public function get_required_fields() {
			return $this->required_fields;
		}
		/*
			main function, that gets tracking information from provider. Should return array with next structure:
			
			array( "oper_id(0)" => array( "table_field_1" => $value1,
								 "table_field_2" => $value2,
								 ... ),
				   "oper_id(1)" => ... );

			Return null if nothing was found or array( 'error' => $error_content ) if an error occured
			
			available table fields:
			  `oper_id` int(11) NOT NULL 			operation ID
              `operation_address` varchar(50)		current operation address
              `operation_code` varchar(50)			current operation ZIP code (index)
              `destination_address` varchar(50)		destination address
              `destination_code` varchar(50)		destination ZIP code (index)
              `mail_direct_name` varchar(50)		destination country name
              `country_oper` varchar(50)			current operation country name
              `oper_type_id` varchar(50)			current operation type id
              `oper_type_name` varchar(50)			current operation type name (on the way, shipped, lost etc. =)
              `oper_attr_id` varchar(50)			current operation attribute id
              `oper_attr_name` varchar(50)			current operation attribute name (more precise operation type)
              `oper_date` timestamp 				operation timestamp - should be formatted for mysql timestamp		
              `item_weight` varchar(50)	
              `complex_item_name` varchar(50)		shipment category and type text representation (small package, letter, etc.)
              `mail_rank` varchar(50)				shipment rank (normal, government, official, etc.)
              `mail_type` varchar(50)				shipment type (letter, package, postcard, etc.)
              `mail_ctg` varchar(50)				shipment category (simple, ordered, financial rated, etc.)
              `rcpn` varchar(50)					shipment recipient

        */
		public function get_track_history( $fields ) {
			global $mkst_domain;
			
			$result = null;
			//here goes interaction with providers API
			return $result;
		}

		/*
			set option defined on settings page
		*/
		public function set_option( $name, $value ) {
			$this->options[$name]['value'] = $value;
		}

	}
?>