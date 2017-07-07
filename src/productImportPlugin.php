<?php
/**
 * Plugin Name: Insert Variation Products
 * Plugin URI: http://jafty.com/blog/
 * Description: Add a variable product with sizes to WP. Errors are written to wp-admin/insert_product_logs.txt file.
 * Version: 1.04
 * Author: Ian L. of Jafty.com
 * Author URI: http://jafty.com
 * License: GPL2
 * Created On: 10-14-2014
 * Updated On: 10-16-2014, 10-17-2014
 */

//call addaprdct function when plugin is activated by admin:
register_activation_hook(__FILE__, 'startProcess');

//require_once '/wp-config.php';
require_once 'EAPI.class.php';

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
	*
	*This function will sync all products
	*/
	function syncErplyProducts(){

		global $wpdb;

		//GET ALL PRODUCTS FROM ERPLY
		$result = $this->Eapi->sendRequest("getProducts");
		$erp_products = json_decode($result, true);


		foreach ($erp_products as $erp_product) {

				//CHECK IF WOO PRODUCT EXISTS
				$woo_product = $wpdb->get_results( "SELECT post_id FROM $wpdb->postmeta WHERE post_type = 'product' AND meta_key='_sku' AND meta_value='".$erp_product['code']."' ",  ARRAY_A );

				if(count($woo_product) > 0 ){

					//UPDATE EXISTING PRODUCT
					$product_id = $woo_product['post_id'];

					$product_id = updateProduct($product_id, array());
				} else {

					//NEW PRODUCT CREATE INSTEAD
					createProduct(array());

				}



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

					$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value = '$ErplyStock' WHERE post_id = '$product_id' AND meta_key = '_stock'");	// SET STOCK

				}

			}
		}
	}


    function addaprdct()
    {
        global $wpdb;

        //GET ALL PRODUCTS FROM ERPLY
        $result = $this->Eapi->sendRequest("getProducts");
        $erp_products = json_decode($result, true);

        foreach ($erp_products as $erp_product) {
            var_dump($erp_product);
        }

        // $cats = array(25);
        $insertLog = "insert_product_logs.txt";//name the log file in wp-admin folder
        $post = array(
            'post_title'   => "Product with Variations2",
            'post_content' => "product post content goes here...",
            'post_status'  => "publish",
            'post_excerpt' => "product excerpt content...",
            'post_name'    => "test_prod_vars2", //name/slug
            'post_type'    => "product"
        );

        //Create product/post:
        $new_post_id = wp_insert_post($post, $wp_error);
        $logtxt = "PrdctID: $new_post_id\n";

        //make product type be variable:
        //wp_set_object_terms($new_post_id, 'variable', 'product_type');
        //add category to product:
        wp_set_object_terms($new_post_id, '', 'product_cat');

        // //################### Add size attributes to main product: ####################
        // //Array for setting attributes
        // $avail_attributes = array(
        // '2xl',
        // 'xl',
        // 'lg',
        // 'md',
        // 'sm'
        // );
        // wp_set_object_terms($new_post_id, $avail_attributes, 'pa_size');

        // $thedata = Array('pa_size'=>Array(
        //     'name'=>'pa_size',
        //     'value'=>'',
        //     'is_visible' => '1',
        //     'is_variation' => '1',
        //     'is_taxonomy' => '1'
        // ));
        // update_post_meta($new_post_id, '_product_attributes', $thedata);
        //########################## Done adding attributes to product #################

        //set product values:
        update_post_meta($new_post_id, '_stock_status', 'instock');
        update_post_meta($new_post_id, '_weight', "0.06");
        update_post_meta($new_post_id, '_sku', "skutest1");
        update_post_meta($new_post_id, '_stock', "100");
        update_post_meta($new_post_id, '_visibility', 'visible');


        //add product image:
        //require_once 'inc/add_pic.php';
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/media.php';
        $thumb_url = "https://cdn.shopify.com/s/files/1/0255/3657/products/bikini_woven_shirt-2013_1024x1024.jpg?v=1483335091";

        // Download file to temp location
        $tmp = download_url($thumb_url);

        // Set variables for storage
        // fix file name for query strings
        preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $thumb_url, $matches);
        $file_array['name'] = basename($matches[0]);
        $file_array['tmp_name'] = $tmp;

        // If error storing temporarily, unlink
        if (is_wp_error($tmp) ) {
            @unlink($file_array['tmp_name']);
            $file_array['tmp_name'] = '';
            $logtxt .= "Error: download_url error - $tmp\n";
        }else{
            $logtxt .= "download_url: $tmp\n";
        }

        //use media_handle_sideload to upload img:
        $thumbid = media_handle_sideload($file_array, $new_post_id, 'gallery desc');
        // If error storing permanently, unlink
        if (is_wp_error($thumbid) ) {
            @unlink($file_array['tmp_name']);
            //return $thumbid;
            $logtxt .= "Error: media_handle_sideload error - $thumbid\n";
        }else{
            $logtxt .= "ThumbID: $thumbid\n";
        }

        set_post_thumbnail($new_post_id, $thumbid);

        //optionally add second image:
        include_once ABSPATH . "wp-admin" . '/includes/image.php';
        $thumb_url2 = "https://cdn.shopify.com/s/files/1/0255/3657/products/chill_merino_crew_socks_2pk-2013_1024x1024.png";
        // Download file to temp location
        $tmp2 = download_url($thumb_url2);
        $logtxt .= "download_url2: $tmp2\n";
        // fix file name for query strings
        preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $thumb_url2, $matches);
        $file_array2['name'] = basename($matches[0]);
        $file_array2['tmp_name'] = $tmp2;
        $imgID = media_handle_sideload($file_array2, $new_post_id, 'desc');
        $logtxt .= "ThumbID2: $imgID\n";
        update_post_meta($new_post_id, '_product_image_gallery', $imgID);

        //append to log file(file shows up in wp-admin folder):
        // $fh2 = fopen($insertLog, 'a') or die("can't open log file to append");
        // fwrite($fh2, $logtxt);
        // fclose($fh2);

    }//end addaprdct function
}

function startProcess() {
    $ErplyWoo = new ErplyWoo();
    $ErplyWoo->addaprdct();
}

?>
