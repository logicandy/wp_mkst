<?php
/*
	Provider name: Russian Post
	version: 0.1
*/
?>
<?php
	class Russian_Post {
		private $display_name = "Russian Post";
		private $options = array( "username" => array( "display_name" => "Login",
													 "comment" => "You can receive login and password for Russian Post API here <a href='mailto:fc@russianpost.ru'>fc@russianpost.ru</a>",
													 "value" => "" ),
								 "password" => array( "display_name" => "Password",
													 "comment" => "",
								 					 "value" => "" ),
								 "api_href" => array( "display_name" => "API link",
													 "comment" => "",
								 					 "value" => "" ),
								 "lang" => array( "display_name" => "Language",
								 				"comment" => "",
								 				"value" => "" )
								);
		private $required_fields = array( "track_number" => "Tracking number" );

		public function get_display_name() {
			return $this->display_name;
		}

		public function get_options() {
			return $this->options;
		}

		public function get_required_fields() {
			return $this->required_fields;
		}

		public function get_track_history( $fields ) {
			global $mkst_domain;
			
			$soap_vars = array( 'soap_version' => SOAP_1_2,
								'trace' => true,
								'exceptions' => false );
			$soap_client = new SoapClient( $this->options['api_href']['value'], $soap_vars );
			$soap_result = $soap_client->getOperationHistory( array( 'OperationHistoryRequest' => array( 'Barcode' => $fields['track_number'], 
																									'MessageType' => 0,
																									'Language' => $this->options['lang']['value'] ),
																'AuthorizationHeader' => array( 'login' => $this->options['username']['value'], 
																								'password' => $this->options['password']['value'] )
																 ) );
			if ( is_a( $soap_result, 'soapFault' ) ) {
				return array( 'error' => $soap_result->getMessage() );
			}
			$i = 0;
			if ( is_array( $soap_result->OperationHistoryData->historyRecord ) ){
		        $records = $soap_result->OperationHistoryData->historyRecord;
		      } else {
		        $records[] = $soap_result->OperationHistoryData->historyRecord;
		      }
			foreach( $records as $rec ) {
		        $result[$i]['oper_id']                   = $i;
		        if ( isset( $rec->OperationParameters->OperDate ) && !empty( $rec->OperationParameters->OperDate ) ) {
		        	$result[$i]['oper_date'] = $rec->OperationParameters->OperDate;
		        }
		        if ( isset( $rec->AddressParameters->OperationAddress->Index ) && !empty( $rec->AddressParameters->OperationAddress->Index ) ) {
		        	$result[$i]['operation_code'] = $rec->AddressParameters->OperationAddress->Index;
		        }
		        if ( isset( $rec->AddressParameters->OperationAddress->Description ) && !empty( $rec->AddressParameters->OperationAddress->Description ) ) {
		        	$result[$i]['operation_address'] = $rec->AddressParameters->OperationAddress->Description;
		        }
		        if ( isset( $rec->OperationParameters->OperType->Name ) && !empty( $rec->OperationParameters->OperType->Name ) ) {
		        	$result[$i]['oper_type_name'] = $rec->OperationParameters->OperType->Name;
		        }
		        if ( isset( $rec->OperationParameters->OperType->Id ) && !empty( $rec->OperationParameters->OperType->Id ) ) {
		        	$result[$i]['oper_type_id'] = $rec->OperationParameters->OperType->Id;
		        }
		        if ( isset( $rec->OperationParameters->OperAttr->Name ) && !empty( $rec->OperationParameters->OperAttr->Name ) ) {
		        	$result[$i]['oper_attr_name'] = $rec->OperationParameters->OperAttr->Name;
		        }
		        if ( isset( $rec->OperationParameters->OperAttr->Id ) && !empty( $rec->OperationParameters->OperAttr->Id ) ) {
		        	$result[$i]['oper_attr_id'] = $rec->OperationParameters->OperAttr->Id;
		        }
		        if ( isset( $rec->AddressParameters->DestinationAddress->Index ) && !empty( $rec->AddressParameters->DestinationAddress->Index ) ) {
		        	$result[$i]['destination_code'] = $rec->AddressParameters->DestinationAddress->Index;
		        }
		        if ( isset( $rec->AddressParameters->DestinationAddress->Description ) && !empty( $rec->AddressParameters->DestinationAddress->Description ) ) {
		        	$result[$i]['destination_address'] = $rec->AddressParameters->DestinationAddress->Description;
		        }
		        if ( isset( $rec->AddressParameters->MailDirect->Id ) && !empty( $rec->AddressParameters->MailDirect->Id ) ) {
		        	$result[$i]['mail_direct_id'] = $rec->AddressParameters->MailDirect->Id;
		        }
		        if ( isset( $rec->AddressParameters->MailDirect->NameRU ) && !empty( $rec->AddressParameters->MailDirect->NameRU ) ) {
		        	$result[$i]['mail_direct_name'] = $rec->AddressParameters->MailDirect->NameRU;
		        }
		        if ( isset( $rec->AddressParameters->CountryOper->Id ) && !empty( $rec->AddressParameters->CountryOper->Id ) ) {
		        	$result[$i]['country_oper'] = $rec->AddressParameters->CountryOper->Id;
		        }
		        if ( isset( $rec->ItemParameters->Mass ) && !empty( $rec->ItemParameters->Mass ) ) {
		        	$result[$i]['item_weight'] = round( floatval( $rec->ItemParameters->Mass ) / 1000, 3 );
		        }
		        if ( isset( $rec->ItemParameters->ValidRuType ) && !empty( $rec->ItemParameters->ValidRuType ) ) {
		        	$result[$i]['valid_ru_type'] = $rec->ItemParameters->ValidRuType;
		        }
		        if ( isset( $rec->ItemParameters->ValidEnType ) && !empty( $rec->ItemParameters->ValidEnType ) ) {
		        	$result[$i]['valid_en_type'] = $rec->ItemParameters->ValidEnType;
		        }
		        if ( isset( $rec->ItemParameters->ComplexItemName ) && !empty( $rec->ItemParameters->ComplexItemName ) ) {
		        	$result[$i]['complex_item_name'] = $rec->ItemParameters->ComplexItemName;
		        }
		        if ( isset( $rec->ItemParameters->MailRank->Name ) && !empty( $rec->ItemParameters->MailRank->Name ) ) {
		        	$result[$i]['mail_rank'] = $rec->ItemParameters->MailRank->Name;
		        }
		        if ( isset( $rec->ItemParameters->PostRank->Name ) && !empty( $rec->ItemParameters->PostRank->Name ) ) {
		        	$result[$i]['post_rank'] = $rec->ItemParameters->PostRank->Name;
		        }
		        if ( isset( $rec->ItemParameters->MailType->Name ) && !empty( $rec->ItemParameters->MailType->Name ) ) {
		        	$result[$i]['mail_type'] = $rec->ItemParameters->MailType->Name;
		        }
		        if ( isset( $rec->ItemParameters->MailCtg->Name ) && !empty( $rec->ItemParameters->MailCtg->Name ) ) {
		        	$result[$i]['mail_ctg'] = $rec->ItemParameters->MailCtg->Name;
		        }
		        /* not used
		        $result[$i]['rcpn']                     = isset( $rec->UserParameters->Rcpn )$rec->UserParameters->Rcpn;
		        $result[$i]['declaredValue']            = isset( $rec->FinanceParameters->Value )?round( floatval( $rec->FinanceParameters->Value ) / 100, 2 );
		        $result[$i]['collectOnDeliveryPrice']   = isset( $rec->FinanceParameters->Payment )?round( floatval( $rec->FinanceParameters->Payment ) / 100, 2 );
		        $result[$i]['financeMassRate']          = isset( $rec->FinanceParameters->MassRate )$rec->FinanceParameters->MassRate;
		        $result[$i]['financeInsrRate']          = isset( $rec->FinanceParameters->InsrRate )$rec->FinanceParameters->InsrRate;
		        $result[$i]['financeAirRate']           = isset( $rec->FinanceParameters->AirRate )$rec->FinanceParameters->AirRate;
		        $result[$i]['financeRate']              = isset( $rec->FinanceParameters->Rate )$rec->FinanceParameters->Rate;
				*/
		        $i++;
		    }
			return $result;
		}

		public function set_option( $name, $value ) {
			$this->options[$name]['value'] = $value;
		}

	}
?>