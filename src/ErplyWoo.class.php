<?php
error_reporting(-1);
require_once dirname(__FILE__).'/../wp-config.php';
require_once dirname(__FILE__).'/EAPI.class.php';

/*
 *
 *        ALTER TABLE `wp_users` ADD `ERPLY_Customer_id` BIGINT( 20 ) NOT NULL ;
 * 		  ALTER TABLE `wp_woocommerce_order_items` ADD `ERPLY_invoice_id` BIGINT( 20 ) NOT NULL
 *
 */

class ErplyWoo {


	public $api;
	/**
	*connection variable initiation
	*/
	function __construct() {
		$api = new EAPI();
		$api->clientCode =  "";
		$api->username =   "";
		$api->password =   "";
		$api->url = "https://".$api->clientCode.".erply.com/api/";
		$this->Eapi = $api;
	}
	/**
	*creating invoice in erply
	*
	*
	*
	*/

	function createErplyInvoices() {

			global $wpdb ;
			$orderID = 0;
			$counter=0;
			$warehouse_id = 1;

			//Getting new ordered items where ERPLY_invoice_id = '0';

			$order_items = $wpdb->get_results( "SELECT * FROM wp_woocommerce_order_items WHERE ERPLY_invoice_id = '0' ",  ARRAY_A );

			//print_r($order_items);
			//echo "<br><br>";
			//exit;

		if($order_items!=null){

			foreach ($order_items as $order_item)
			{
				$order_id = $order_item["order_id"];
				$order_item_id = $order_item["order_item_id"];
				$order_item_name=$order_item["order_item_name"];
				$Cr_order[$counter]=$order_id;
				$Cr_order_item_id[$order_id][]=$order_item_id;
				$Cr_order_item_name[$order_id][]=$order_item_name;
				$counter++;
			}

			$Orders=array_unique($Cr_order);
			sort($Orders,SORT_REGULAR);

			// print_r($Orders);
			// exit();


			for($zz=0;$zz<count($Orders);$zz++){

				//Getting the customer id that have the same order id

				$order_id=$Orders[$zz];
				//var_dump($Orders);

				$customerID = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s ", $order_id, "_customer_user") ) ;
				$customer_id= $wpdb->get_var( $wpdb->prepare( "SELECT ERPLY_Customer_id FROM $wpdb->users WHERE id = %d ", $customerID) ) ;
				$shipping= $wpdb->get_results($wpdb->prepare("SELECT meta_key,meta_value FROM $wpdb->postmeta WHERE post_id = '$order_id'", ARRAY_A));

				if ($customer_id) {
					//Getting shipping information and format it
					//Also update customer if there is any change in his account info

					for ($i=0;$i<count($shipping);$i++)
					{
						$inf=$shipping[$i];
						$kmeta=$inf->meta_key;
						$vmeta=$inf->meta_value;
						$info[$kmeta]=$vmeta;
					}

					if ($info) {
						extract($info);
					} else {
						$_shipping_address_1 = '';
						$_shipping_address_2 = '';
						$_shipping_city = '';
						$_shipping_postcode = '';
						$_shipping_state = '';
						$_shipping_country = '';
						$_shipping_first_name = '';
						$_shipping_last_name = '';
					}

					//Generating invoice information

						$itemnames="woocommerce invoice, order # $order_id , Items SKU: ";
						$orderID = $order_id;

					$lin2=array('ownerID'=>$customer_id,'typeID'=>1, 'street'=>$_shipping_address_1,'address2'=>$_shipping_address_2,
					'city'=>$_shipping_city,'postalCode'=>$_shipping_postcode,'state'=>$_shipping_state,'country'=>$_shipping_country ,
					'attributeName1'=>'firstName','attributeType1'=>'text','attributeValue1'=>$_shipping_first_name,'attributeName2'=>'lastName',
					'attributeType2'=>'text',	'attributeValue2'=>$_shipping_last_name	);

					//Saving the new billing address

					$res = $this->Eapi->sendRequest("saveAddress",$lin2);
					//var_dump($res);

					$res = json_decode($res, true);
					$addressID=$res['records'][0]['addressID'];
					//echo "<br><br>O:$order_id<br><br>A:$addressID";

					$lines = array("type" => "INVWAYBILL", "invoiceState" => "READY", "date"=>date("l jS of F g:i A.", time()),
					"addressID"=>$addressID,"paymentType"=>$_payment_method_title,"internalNotes" => "woocommerce invoice", "paymentStatus" => "PAID",
					"customerID" => $customer_id, "warehouseID" => $warehouse_id,"currencyCode"=>$_order_currency );

					$i=0;

					foreach ($Cr_order_item_id[$order_id] as $order_item_id)
					{
						$order_item_name=$Cr_order_item_name[$order_id][$i];
						$order_itemmetas = $wpdb->get_results( "SELECT * FROM wp_woocommerce_order_itemmeta WHERE order_item_id = '$order_item_id' ",  ARRAY_A );

						foreach ($order_itemmetas as $order_itemmeta)
						{

							if($order_itemmeta['meta_key'] == "_qty") $lines["amount$i"] = $order_itemmeta['meta_value'];
							if($order_itemmeta['meta_key'] == "_line_total") $lines["price$i"] = $order_itemmeta['meta_value'];
							if($order_itemmeta['meta_key'] == "pa_size"){$lines["attributeName$i"]="size";$lines["attributeType$i"]="text";$lines["attributeValue$i"]=$order_itemmeta['meta_value'];}
							$s=$i+1;
							if($order_itemmeta['meta_key'] == "pa_colors"){$lines["attributeName$s"]="color";$lines["attributeType$s"]="text";$lines["attributeValue$s"]=$order_itemmeta['meta_value'];}
							if($order_itemmeta['meta_key'] == "_line_tax" ) $lines["vatrateID$i"] = $order_itemmeta['meta_value'];
							if($order_itemmeta['meta_key'] == "_product_id" ) $_product_id = $order_itemmeta['meta_value'];
							//Get the sku for each product
							$SkuWo = $wpdb->get_results( "SELECT meta_value FROM $wpdb->postmeta WHERE post_id= '$_product_id' AND meta_key='_sku'",  ARRAY_A);

							//Get the id of the product from Erply
							if($SkuWo[0]["meta_value"]!=null){
								//echo "SKUWOO:" . $SkuWo[0]["meta_value"];
								$ress = $this->Eapi->sendRequest("getProducts", array("code"=>$SkuWo[0]["meta_value"], "getStockInfo" => 1));

								//echo '<br>' .$customer_id. '<br>';
								$ress = json_decode($ress, true);
								$lines["productID$i"]=$ress['records'][0]['productID'];
								//var_dump($ress);
							}

						}

						$itemnames .=$SkuWo[0]["meta_value"].", ";
						$i++;
					}
					$lines["notes"]=$itemnames;
					$this->saveErplyInvoice($lines, $order_id);
				} //If customer_id //ERPLY


		}
	}
}
	/**
	*saving the invoice functionality
	*
	*/

	function saveErplyInvoice($lines, $orderID){

		global $wpdb ;

		//Saving the order in Erply and get an invoice number

		$result = $this->Eapi->sendRequest("saveSalesDocument", $lines);
		$result = json_decode($result, true);

		//print_r($result);

		$invoiceNo = $result['records'][0]['invoiceNo'];

		//update the woocommerce with the invoice id
		//echo "saving invoice$:" . $invoiceNo . "<br>$orderID<br>";
		$res = $wpdb->update("{$wpdb->prefix}woocommerce_order_items",	array('ERPLY_invoice_id'   => $invoiceNo),array( 'order_id' => $orderID ));
		//var_dump($res);
	}

	/**
	*
	*This function just sync quantities
	*/
	function syncErplyInventory(){

		global $wpdb ;

		$products = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type = 'product' ",  ARRAY_A );

		foreach ($products as $product) {

			$product_id = $product['ID'];

			//GET PRODUCT WITH SKU
			$SkuWoo = $wpdb->get_results( "SELECT meta_value FROM $wpdb->postmeta WHERE post_id= '$product_id' AND meta_key='_sku'",  ARRAY_A);
			$SkuWoo=$SkuWoo[0]['meta_value'];

			if($SkuWoo != NULL){

				$result = $this->Eapi->sendRequest("getProducts", array("code"=> $SkuWoo, "getStockInfo" => 1));
				$result = json_decode($result, true);

				if(count($result['records']) > 0 ){

					$Erply_product_id = $result['records'][0]['productID'];
					$ErplyStock = 0;

					foreach ($result['records'][0]['warehouses'] as $warehouse) {

						$ErplyStock+=  $warehouse['totalInStock'];

					}

					$erp_product = $result['records'][0];

					update_post_meta($product_id, '_weight', $erp_product['grossWeight']);
					update_post_meta($product_id, '_sku', $erp_product['code']);
					update_post_meta($product_id, '_stock', $ErplyStock);
					update_post_meta($product_id, '_price', $erp_product['price']);
					update_post_meta($product_id, '_regular_price', $erp_product['price']);
					update_post_meta($product_id, '_height', $erp_product['height']);
					update_post_meta($product_id, '_width', $erp_product['width']);
					update_post_meta($product_id, '_length', $erp_product['length']);

					$product_post = array(
					  'ID'           => $product_id,
					  'post_excerpt'   => $erp_product['description'],
					  'post_content' => $erp_product['longdesc'],
					  'post_title'   => $erp_product['name']
					);

					// Update the post into the database
					wp_update_post( $product_post );

				}

			}
		}

	}

	/**
	*
	*This function will sync all products
	*/
	function syncErplyUsers() {
		global $wpdb;
		$page = 1;

        while($page > 0){

			$result = $this->Eapi->sendRequest("getCustomers",array("recordsOnPage"=>100, "pageNo"=>$page));

			$erp_users = json_decode($result, true);

			//var_dump($erp_users);

			//echo " " . $page;

			$page++;

			if ( isset($erp_users['records']) && count($erp_users['records'])>0 ) {

				foreach ($erp_users['records'] as $erp_user) {

					if (!empty($erp_user['email']) && !empty($erp_user['fullName']) && !username_exists( $erp_user['email'] )) {

						$user_data = array();
						$user_data['user_email'] = $erp_user['email'];
						$user_data['user_login'] = $erp_user['email'];
						$user_data['user_pass'] = wp_generate_password( 12, false );
						$user_data['display_name'] = '';


						if ($erp_user['customerType'] == 'COMPANY') {
							$user_data['display_name'] = $erp_user['companyName'];
						} else {
							if (!empty($erp_user['firstName'])) {
								$user_data['first_name'] = $erp_user['firstName'];
								$user_data['display_name'] = $erp_user['firstName'];
							}
							if (!empty($erp_user['lastName'])) {
								$user_data['last_name'] = $erp_user['lastName'];
								$user_data['display_name'] = $user_data['display_name'] . " " . $erp_user['lastName'];
							}
							$user_data['display_name'] = trim($user_data['display_name']);
						}

						$user_id = wp_insert_user( $user_data ) ;

						//On success
						if ( ! is_wp_error( $user_id ) ) {
						    //echo "User created : ". $user_id;

							//UPDATE ADDRESS INFO
							//$wpdb->query( "UPDATE {$wpdb->usermeta} SET meta_value = 'TEST' WHERE user_id = '$user_id' AND meta_key = 'billing_city");	// SET STOCK

							if ($erp_user['state'] <> '') {
								$wpdb->query( $wpdb->prepare( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES (%d, %s, %s) ",$user_id,'billing_state', $erp_user['state'] ) );
								$wpdb->query( $wpdb->prepare( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES (%d, %s, %s) ",$user_id,'shipping_state', $erp_user['state'] ) );
							}
							if ($erp_user['postalCode'] <> '') {
								$wpdb->query( $wpdb->prepare( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES (%d, %s, %s) ",$user_id,'billing_postcode', $erp_user['postalCode'] ) );
								$wpdb->query( $wpdb->prepare( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES (%d, %s, %s) ",$user_id,'shipping_postcode', $erp_user['postalCode'] ) );
							}
							if ($erp_user['city'] <> '') {
								$wpdb->query( $wpdb->prepare( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES (%d, %s, %s) ",$user_id,'billing_city', $erp_user['city'] ) );
								$wpdb->query( $wpdb->prepare( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES (%d, %s, %s) ",$user_id,'shipping_city', $erp_user['city'] ) );
							}
							if ($erp_user['street'] <> '') {
								$wpdb->query( $wpdb->prepare( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES (%d, %s, %s) ",$user_id,'billing_address_1', $erp_user['street'] ) );
								$wpdb->query( $wpdb->prepare( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES (%d, %s, %s) ",$user_id,'shipping_address_1', $erp_user['street'] ) );
							}
							if ($erp_user['address2'] <> '') {
								$wpdb->query( $wpdb->prepare( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES (%d, %s, %s) ",$user_id,'billing_address_2', $erp_user['address2'] ) );
								$wpdb->query( $wpdb->prepare( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES (%d, %s, %s) ",$user_id,'shipping_address_2', $erp_user['address2'] ) );
							}
						}

					} //IF VALID NEW USER

				} //USERS LOOP

			} //IF USERS EXIST
			else {
				$page = 0;
			}
		} //PAGES LOOP

	}

	/**
	*
	*This function will sync all products
	*/
	function syncErplyProducts() {

		//add product image:
		//require_once 'inc/add_pic.php';
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

        global $wpdb;
		$page = 1;
		$cnt = 1;

        while($page > 0){

			$result = $this->Eapi->sendRequest("getProducts",array("getStockInfo" => 1, "recordsOnPage"=>20, "pageNo"=>$page));

	        $erp_products = json_decode($result, true);

			$page++;

			if ( isset($erp_products['records']) && count($erp_products['records'])>0 ) {

				foreach ($erp_products['records'] as $erp_product) {

					// echo " " . $cnt;
					// $cnt++;

					//CHECK IF WOO PRODUCT EXISTS
					$woo_product = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='".$erp_product['code']."' ",  ARRAY_A );

					if(count($woo_product) == 0 ){

					        $post = array(
					            'post_title'   => $erp_product['name'],
					            'post_content' => $erp_product['longdesc'],
					            'post_status'  => "publish",
					            'post_excerpt' => $erp_product['description'],
					            'post_name'    => $erp_product['code'], //name/slug
					            'post_type'    => "product"
					        );

					        //Create product/post:
					        $new_post_id = wp_insert_post($post, $wp_error);

					        //make product type be variable:
					        //wp_set_object_terms($new_post_id, 'variable', 'product_type');
					        //add category to product:
					        //wp_set_object_terms($new_post_id, $erp_product['categoryName'], 'product_cat');
							wp_set_object_terms($new_post_id, $erp_product['groupName'], 'product_cat');

							$ErplyStock = 0;

							//CALCULATE STOCK
							foreach ($erp_product['warehouses'] as $warehouse) {
								$ErplyStock+=  (int)$warehouse['totalInStock'];
							}

							if ($ErplyStock >0) {
					        	update_post_meta($new_post_id, '_stock_status', 'instock');
							} else {
								update_post_meta($new_post_id, '_stock_status', 'outofstock');
							}

							// update_post_meta($new_post_id, '_manage_stock', true);

					        update_post_meta($new_post_id, '_weight', $erp_product['grossWeight']);
					        update_post_meta($new_post_id, '_sku', $erp_product['code']);
					        update_post_meta($new_post_id, '_stock', $ErplyStock);
					        update_post_meta($new_post_id, '_visibility', 'visible');
							update_post_meta($new_post_id, '_price', $erp_product['price']);
							update_post_meta($new_post_id, '_regular_price', $erp_product['price']);
							update_post_meta($new_post_id, '_height', $erp_product['height']);
							update_post_meta($new_post_id, '_width', $erp_product['width']);
							update_post_meta($new_post_id, '_length', $erp_product['length']);


							if (isset($erp_product['images'])) {
					        	$thumb_url = $erp_product['images'][0]['largeURL'];

								//echo $thumb_url . "\n";

								// Download file to temp location
						        $tmp = download_url($thumb_url);

						        // Set variables for storage
						        // fix file name for query strings
						        preg_match('/[^\?]+\.(jpg|JPG|jpeg|JPEG|gif|GIF|png|PNG)/', $thumb_url, $matches);
						        $file_array['name'] = basename($matches[0]);
						        $file_array['tmp_name'] = $tmp;

						        // If error storing temporarily, unlink
						        if (is_wp_error($tmp) ) {
						            @unlink($file_array['tmp_name']);
						            $file_array['tmp_name'] = '';
						            // echo "Error: download_url error - $tmp\n";
						        }else{
						            // echo "download_url: $tmp\n";
									// var_dump($file_array);
						        }
						        //use media_handle_sideload to upload img:
						        $thumbid = media_handle_sideload($file_array, $new_post_id, $erp_product['name']);
						        // If error storing permanently, unlink
						        if (is_wp_error($thumbid) ) {
						            @unlink($file_array['tmp_name']);
						            // echo "Error: media_handle_sideload error - $thumbid\n";
						            return $thumbid;
						        }else{
						            // echo "ThumbID: $thumbid\n";
						        }
						        set_post_thumbnail($new_post_id, $thumbid);
							} //IF IMAGE

					} //IF NEW PRODUCT

				} //PRODUCTS LOOP

			} else {
				$page = 0;
			} //IF NOT EMPTY

		} //PAGES LOOP

		//echo 'DONE';

    } //end syncErplyProducts function


	/**
	*
	*
	*/
	function syncErplyCustomers(){

		global $wpdb ;
		//$users = $wpdb->get_results( "SELECT * FROM $wpdb->users WHERE ERPLY_Customer_id = 0 ",  ARRAY_A );	it suppose to be by default that the user is a customer so no need to WHERE
		$users = $wpdb->get_results( "SELECT * FROM $wpdb->users ",  ARRAY_A );

		foreach ($users as $user){

			$user_email = $user['user_email'];
			$user_id    = $user['ID'];

			//Getting user information from Erply using e-mail
			$result = $this->Eapi->sendRequest("getCustomers", array("searchName"=> $user_email, "responseMode"=> "detail"));
			$result = json_decode($result, true);

			//If the user do not exist in Erply create him and form his information
			if (!count($result['records'])){

				$user_info = $wpdb->get_results( "SELECT meta_key,meta_value FROM $wpdb->usermeta  where user_id='$user_id'",  ARRAY_A );


				for($i=0;$i<count($user_info);$i++){
					$info[$user_info[$i]['meta_key']]=$user_info[$i]['meta_value'];
				}

				//Forming customer information and saving them in Erply assuming that the new customer have 0 reward points
				$lin=array('firstName'=>$info['first_name'],
				'lastName'=>$info['last_name'],
				'fullName'=>$user['user_nicename'],
				'email'=>$user_email,
				'phone'=>$info['billing_phone'],
				'companyName'=>$info['billing_company'],
				'username'=>$user_email);

				$res = $this->Eapi->sendRequest("saveCustomer",$lin);
				$res = json_decode($res, true);

				$customerID = $result['records'][0]['customerID'];

				$wpdb->query( "	UPDATE {$wpdb->users} SET ERPLY_Customer_id = $customerID WHERE user_email = '$user_email' ");
        }

		if (count($result['records']) > 0){

			$customerID = $result['records'][0]['customerID'];

			//Getting reward points and loyalty information from Erply
			$Rewardresult = $this->Eapi->sendRequest("getCustomerRewardPoints", array("customerID"=>$customerID));
            $Rewardoutput = json_decode($Rewardresult, true);
			$reward_points = $Rewardoutput['records'][0]['points'];

			//Updating customer ID in woo-commerce ERPLY_Customer_id

			$wpdb->query( $wpdb->prepare("	UPDATE $wpdb->users SET ERPLY_Customer_id = %d WHERE user_email = %s ",$customerID, $user_email) );

			//Updating reward points and loyalty level
			$this->insertOnDuplicateUpdateUserMeta($user_id, 'reward_points', $reward_points);
			$this->insertOnDuplicateUpdateUserMeta($user_id, 'loyalty_level', $loyalty_level);

			}
		}

	}
	/**
	*
	*
	*/
	function insertOnDuplicateUpdateUserMeta($user_id, $meta_key, $meta_value){

		//Updating loyalty level and points into woo-commerce if not exist create them
		global $wpdb ;

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->usermeta WHERE user_id= %d AND meta_key = %s ", $user_id, $meta_key) ) ;

		if($exists||$exists=="0"){

			$wpdb->query( $wpdb->prepare( "	UPDATE $wpdb->usermeta SET meta_value ='$meta_value' WHERE user_id = %d AND meta_key = %s",$user_id,$meta_key) );

		}
		else {
			//$wpdb->query( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES ('$user_id', '$meta_key', '$meta_value') " );
			$wpdb->query( $wpdb->prepare( "	INSERT INTO $wpdb->usermeta (user_id, meta_key, meta_value) VALUES (%d, %s, %s) ",$user_id,$meta_key,$meta_value ) );
		}

	}

}
?>
