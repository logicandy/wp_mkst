<?php
/*
	Provider name: Деловые Линии
	version: 0.1
*/
?>
<?php
	/* 
		Class name should be equal to filename (according to WP naming convention)
		For example if filename is class-junk-provider.php then class should be named Junk_Provider
	*/
	class Dellin {
		/*
			Name that will be displayed on settings page, select box etc. 
		*/
		private $display_name = 'Dellin';
		/*
			Options for settings page (user login, password for providers API, etc.)
			should with next structure:
				array( "option_name_1" => array( "display_name" => $string,
												 "comment" => $string,
												 "value" => "" ),
					   "option_name_2" ... );
		*/
		private $options = array( 'app_key' => array( 'display_name' => 'Application key',
													'comment' => 'You can get your app_key here <a href="http://dev.dellin.ru/registration" target="_blank">dev.dellin.ru</a>',
													'value' => '' ),
								  'api_href' => array( 'display_name' => 'API link',
								  					'comment' => '',
								  					'value' => '' ) 
								);
		/*
			Array of fields, that required for getting tracking information from provider (№, city, etc..)
			Should be in pairs "field_name" => "display_name"
		*/
		private $required_fields = array( 'doc_id' => 'Invoice number' );

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

			dellin order states:
				0	processing
				1	pickup
				2	waiting
				3	received
				4	received_warehousing
				5	inway
				6	arrived
				7	airport_warehousing
				8	warehousing
				9	delivery
				10	accompanying_documents_return
				11	finished
				15	declined
        */
		public function get_track_history( $fields ) {
			global $mkst_domain;
			
			$history = null;
			$date = null;
			//here goes interaction with providers API
			$doc_id = $fields['doc_id'];
			$postdata = json_encode( array( 'appKey' => $this->options['app_key']['value'], 
											'docid' => $doc_id  ) );
			$opts = array('http' => array(
								        'method'  => 'POST',
								        'header'  => 'Content-type: application/json',
								        'content' => $postdata
								    )
								);
			$context  = stream_context_create($opts);
			$history = file_get_contents( $this->options['api_href']['value'], false, $context );
			$history = json_decode($history);

			if ( !empty( $history->errors ) ) {
				return array( "error" => $history->errors->docid );
			}
			$result[$opid]['operation_address'] = $history->derival_city;
			switch ($history->state) {
				case 'processing':
					$opid = 0;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->processing_date );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					break;
				case 'pickup':
					$opid = 1;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->pickup );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					break;
				case 'waiting':
					$opid = 2;
					$result[$opid]['oper_id'] = $opid;
					$result[$opid]['oper_date'] = date( 'Y-m-d H:i:s' );
					break;
				case 'received':
					$opid = 3;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->arrival_to_osp_sender );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					break;
				case 'received_warehousing':
					$opid = 4;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->warehousing );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					break;
				case 'inway':
					$opid = 5;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->derrival_from_osp_sender );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					break;
				case 'arrived':
					$opid = 6;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->arrival_to_osp_receiver );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					$result[$opid]['operation_address'] = $history->arrival_city;
					break;
				case 'airport_warehousing':
					$opid = 7;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->warehousing );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					$result[$opid]['operation_address'] = $history->arrival_city;
					break;
				case 'warehousing':
					$opid = 8;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->warehousing );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					$result[$opid]['operation_address'] = $history->arrival_city;
					break;
				case 'delivery':
					$opid = 9;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->derrival_from_osp_receiver );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					$result[$opid]['operation_address'] = $history->arrival_city;
					break;
				case 'accompanying_documents_return':
					$opid = 10;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->derrival_from_osp_recevier_accdoc );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					break;
				case 'finished':
					$opid = 11;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->finish );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					$result[$opid]['operation_address'] = $history->arrival_city;
					break;
				case 'declined':
					$opid = 15;
					$result[$opid]['oper_id'] = $opid;
					$date = DateTime::createFromFormat( 'Y-m-d', $history->order_dates->decline_date );
					$result[$opid]['oper_date'] = $date->format( 'Y-m-d H:i:s' );
					break;
				default:
					return array( 'error' => 'unknown operation: '.$history->state );
			}
			$result[$opid]['destination_address'] = $history->arrival_city;
			$result[$opid]['oper_type_name'] = $history->state_name;
			for ( $i=0; $i < $opid; $i++ ) { 
				$result[$i] = null;
			}

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