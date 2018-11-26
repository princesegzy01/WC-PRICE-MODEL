<?php
/**
 * Plugin Name: DrugStoc Price Model  
 * Description: Display and manage prices for Distributors on DrugStoc Front-end.
 * Version: 1.0.0
 * Author: SODIMU SEGUN 
 * Text Domain: cpac
 * Domain Path: /languages
 * License: GPL2 
====================================================================================
    
    Copyright 2014  DRUGSTOC_PRICE_MODEL  (email : info@drugstoc.biz)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

====================================================================================
*/
defined('ABSPATH') or die("No script kiddies please!"); 

if(!class_exists('DrugstocPriceModel')):
/**
 * DrugstocPriceModel class.
 * Display and manage DrugStoc Commissions per order / item.
 *
 * @since 1.0.0
 */

register_activation_hook( __FILE__, array( 'DrugstocPriceModel', 'ds_price_model_install'));
register_deactivation_hook( __FILE__, array( 'DrugstocPriceModel', 'ds_price_model_deactivate'));

class DrugstocPriceModel
{ 
    private static $instance;
    const VERSION = '1.0.0';
    var $table_name;

    private static function has_instance() {
        return isset(self::$instance) && self::$instance != null;
    }

    public static function get_instance() {
        if (!self::has_instance())
            self::$instance = new DrugstocPriceModel;
        return self::$instance;
    }

    public static function setup() {
        self::get_instance();
    }

    protected function __construct() {
        if (!self::has_instance()) {
            add_action('init', array(&$this, 'init'));
        }
    } 

    // Plug into all necessary actions and filters
    function init(){  
        // Actions - Frontend
        add_action( 'wp_print_scripts', array( $this, 'ds_my_price_list_scripts' ));

        // Update Ajax Actions
        add_action( 'wp_ajax_myajax-submit', array( $this, 'update_distributor_price'));
        add_action( 'wp_ajax_bulk-update', array( $this, 'bulk_update_distributor_price'));

        add_action( 'wp_ajax_app-login', array( $this, 'ds_app_authenticate_user'));
        add_action( 'wp_ajax_nopriv_app-login', array( $this, 'ds_app_authenticate_user'));

        // Actions - Backend
        add_action( 'admin_head', array( $this, 'ds_price_model_scripts' ) ); 
        add_action( 'admin_menu', array( $this, 'register_ds_price_model') );  
        add_action( 'admin_notices', array( $this, 'notices' ) );  

        // Add Shortcode for My Price List 
        add_shortcode( 'ds_my_price_list', array( $this, 'ds_mypricelist')  ); 
        add_shortcode( 'ds_other_price_list', array( $this, 'ds_otherpricelist')  );
    }

    // Create Schema for DS Price Model
    function ds_price_model_install(){
 
        global $wpdb, $ds_pricemodel_db_version; 

        $table_name = $wpdb->prefix . 'ds_price_change_log';        
        /*
         * We'll set the default character set and collation for this table.
         * If we don't do this, some characters could end up being converted 
         * to just ?'s when saved in our table.
         */
        $charset_collate = '';

        if ( ! empty( $wpdb->charset ) ) {
          $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
        }

        if ( ! empty( $wpdb->collate ) ) {
          $charset_collate .= " COLLATE {$wpdb->collate}";
        }

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int PRIMARY KEY AUTO_INCREMENT,
            user_id int NOT NULL, 
            ip_address varchar(20) NOT NULL,
            product_id int NOT NULL,
            old_price float NOT NULL,
            new_price float NOT NULL,
            distributor varchar(100) NOT NULL,
            is_approved int NOT NULL, 
            created_at datetime NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
        )$charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        add_option( 'ds_pricemodel_db_version', $ds_pricemodel_db_version );
    }

    function ds_price_model_scripts()
    {     
        wp_enqueue_style( 'dsc-datatable-css', "//cdn.datatables.net/1.10.4/css/jquery.dataTables.min.css");
        wp_enqueue_script('jquery');   
        wp_enqueue_script('dsc-datatable-js', "//cdnjs.cloudflare.com/ajax/libs/datatables/1.10.3/js/jquery.dataTables.min.js",  array('jquery' )); 
        wp_enqueue_script('dsc-changelog-js', plugins_url("/drugstoc-price-model/js/price-change-log.js"), array('jquery'), '1.0.0', true);  
    }

    function ds_my_price_list_scripts()
    { 
        if ( is_page('my-price-list') )
        {  
            wp_enqueue_style( 'bootstrap-css', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css');
            wp_enqueue_script( 'bootstrap-js', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js');

            wp_enqueue_style( 'dataTables-css', '//cdn.datatables.net/1.10.5/css/jquery.dataTables.min.css');
            wp_enqueue_script( 'dataTables-js', '//cdn.datatables.net/1.10.5/js/jquery.dataTables.min.js');

            // embed the javascript file that makes the AJAX request
            wp_enqueue_script( 'ds-change-price', plugins_url("/drugstoc-price-model/js/drugstoc_pricemodel.js"), array('jquery'), '1.0.0', true);
                         
            wp_localize_script( 'ds-change-price', 'MyAjax', array( 
                // URL to wp-admin/admin-ajax.php to process the request
                'ajaxurl'          => admin_url( 'admin-ajax.php' ), 
                'ds_price_nouce'   => wp_create_nonce( 'myajax-post-comment-nonce' ),
                'pluginurl'        => plugins_url('/drugstoc-price-model/'))          
            );
        }
    } 

    function register_ds_price_model(){
         // DrugStoc Price Model 
        add_options_page(
            'Drugstoc Price Model Settings',
            __('DS Price Model', 'ds-price-model'),
            'manage_options',
            __FILE__,
            array($this,'ds_price_model_settings')
        );
    }  

    // Change Number to Money format (#1234 to #1,234.00)
    function show_price($value){
        return number_format((float)$value, 2);
    }   

    // Display Admin Notice
    function notices()
    {
        if (isset($_POST['recalculate']) && $_POST['recalculate'] != "" && is_string($_POST['recalculate'])) {
            echo '<div class="updated"><p>';
            echo __('Prices updated successfully');
            echo "</p></div>";
        } 
    } 

    // Settings Page
    function ds_price_model_settings()
    {
        global $wpdb; 

        // Recalculate all prices
        if (isset($_POST['recalculate']) && $_POST['recalculate'] != "" && is_string($_POST['recalculate'])) {
            $val = sanitize_text_field($_POST['recalculate']);

            $products = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'product' AND post_status LIKE '{$val}'");
 
            foreach ($products as $key => $value) { 
                $id = $value->ID; 

                // Update DrugStoc Price
                // $this->update_ds_price($id); 
                
                $this->ds_get_lowest_price($id);
            }  
        }?>
        <br/>
        <form method="post">
            <h3>Recalculate Prices for All :</h3>
            <span class="description">Recalculates all product/drug regular prices displayed using DS Price Algorithm </span> <br/>
            <input type="radio" name="recalculate" id="recalculate" value="publish"/> Published Drugs<br/>
            <input type="radio" name="recalculate" id="recalculate" value="draft"/> Draft Drugs<br/><br/>
            <input type="submit" value="Recalculate" name="dsc_settings" id="dsc_settings" class="button-primary" />
        </form> <br>  
        <div>
            <h1>Price Change Log</h1>
            <table class="wp-list-table widefat fixed posts" id="price-change-log">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>  
                        <th>Distributor</th>
                        <th>New Price(&#8358;)</th>
                        <th>Old Price(&#8358;)</th>
                        <th>Change</th> 
                        <th>Drugstoc Price(&#8358;)</th> 
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Logged</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                    $products = $wpdb->get_results("SELECT l.*, p.post_title FROM {$wpdb->prefix}ds_price_change_log l INNER JOIN {$wpdb->prefix}posts p ON l.product_id = p.ID ORDER BY l.id DESC");
                    foreach ($products as $i => $product) { ?>
                    <tr>
                        <td><?php echo $product->product_id; ?></td>
                        <td>
                            <a target="_blank" href="<?php echo get_edit_post_link( $product->product_id );?>">
                                <?php echo $product->post_title; ?>
                            </a>   
                        </td>  
                        <td><?php echo strtoupper(substr($product->distributor, 0, -6)); ?></td>
                        <td><?php echo $product->new_price; ?></td>
                        <td><?php echo $product->old_price; ?></td>
                        <td><?php echo $this->price_diff($product->new_price, $product->old_price); ?></td>
                        <td><?php echo get_post_meta($product->product_id, '_price', true); ?></td> 
                        <td><?php echo ($product->user_id > 0)? get_user_meta($product->user_id, 'nickname', true):"NONE"; ?></td>
                        <td><?php echo $product->ip_address; ?></td>
                        <td><?php echo date("d M Y H:m:s", strtotime($product->created_at)); ?></td>
                    </tr>
                <?php 
                    }?>
                </tbody>
            </table>
        </div>
        <?php 
    }

    // Calculate percentage price difference 
    function price_diff($a, $b){
        $diff = $a - $b;
        $change = ($diff != 0)? round(($diff/$a)*100, 2): 0;
        $color = "";

        if($diff > 0) $color = "green";     // Positive    
        else if($diff < 0) $color = "red";  // Negative
        else   $color = "blue";             // No Change
            
        return "<span style='color: {$color}'><b>&#8358;$diff ($change%)</b></span>";
    }

    // Get User IP Address
    function get_user_ip() {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /*********************** FRONT-END ************************/
    
    // [ds_my_price_list] 
    function ds_mypricelist(){
        global $wpdb; 

        $id        = 20;//get_current_user_id();
        $user_info = get_userdata($id); 
        $user_role = implode(',', $user_info->roles); 

        if($user_role != 'shop_manager'){
            echo "<h2>This page is restricted to only Registered DrugStoc Distributors</h2><br/>";
        }else{
            $username  = get_user_meta($id,'nickname',true);
            $primary_distributor = get_user_meta($id,'primary_distributor',true);  
            $products = $wpdb->get_results("SELECT wp_posts.*, wp_postmeta.meta_key, wp_postmeta.meta_value FROM wp_posts INNER JOIN wp_postmeta ON wp_posts.id = wp_postmeta.post_id WHERE wp_posts.post_type='product' AND wp_posts.post_status!='trash' AND wp_postmeta.meta_key='$primary_distributor' AND wp_postmeta.meta_value != '' "); 
            ?> 
            <h4>My Price List</h4>
            <p>Review and Update your drug prices on DrugStoc</p><br><br>
            <button id="bulkupdate">Update Selected</button><br><br>
            <div class="msg"></div>
            <table width="100%" id="allDistributorsProductTable" class="table table-striped hover display compact" cellspacing="0">
                <thead>
                    <tr>
                      <th><input type="checkbox" id="bulk_select" name="bulk_select" value="all" /></th>
                      <th>ID</th> 
                      <th>Product Name</th>
                      <th>Manufacturer</th> 
                      <th>NAFDAC</th>
                      <th>Price(&#8358.00)</th>
                    </tr> 
                </thead>
                <tbody> 
            <?php  
                $sn = 0;

                foreach ($products as $key => $product_) {

                    $sn += 1; 
                    $product = new WC_Product($product_->ID);
                    $nafdac_no = $product->get_attribute("pa_nafdac-no");
                    $pa_origin = $product->get_attribute("pa_origin");
                    $pa_composition = $product->get_attribute("pa_composition");
                    $pa_manufacturer = $product->get_attribute("pa_manufacturer");
                    $distributor_price = get_post_meta($product_->ID, $primary_distributor, true);
                ?>
                    <tr>
                        <td><input type="checkbox" name="chk_price_drugs" value="<?php echo $product_->ID; ?>"/></td>
                        <td><?php echo $product_->ID; ?></td> 
                        <td>
                            <a target="_blank" href="<?php echo get_permalink($product_->ID);?>">
                                <?php echo get_the_post_thumbnail( $product_->ID, array(65,65) )." : {$product_->post_title} [$product_->post_status]"; ?>
                            </a>
                        </td>
                        <td><?php echo $pa_manufacturer; ?></td> 
                        <td><?php echo $nafdac_no; ?></td>
                        <td>
                            <form id="update-price-<?php echo $product_->ID;?>" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo $product_->ID; ?>"/> 
                                <input type="hidden" name="distributor" value="<?php echo $primary_distributor; ?>"/> 
                                <input name="product_price" class="digit" type="text" id="id<?php echo $product_->ID; ?>" style="width:70px;" maxlength="6" value="<?php echo $distributor_price;?>"/>
                                <button type="button" class="btn_update_single_product_price" data-id="<?php echo $product_->ID;?>">UPDATE</button>
                            </form> 
                        </td>
                    </tr>
            <?php } ?>
                </tbody> 
                <tfoot>
                    <tr>
                      <th><input type="checkbox" id="bulk_select2" name="bulk_select" value="all" /></th>
                      <th>ID</th> 
                      <th>Product Name</th>
                      <th>Manufacturer</th> 
                      <th>Nafdac</th>
                      <th>Price(&#8358.00)</th>
                    </tr> 
                </tfoot>
            </table>
            <br><br>
            <button id="bulkupdate2">Update Selected</button>
            <script type="text/javascript">
                jQuery("#v_pricelist").val("<?php echo get_user_meta($user->ID, 'institution', true);?>");
            </script> 
        <?php
        } 
    }

    // [ds_other_price_list] 
    function ds_otherpricelist(){
        global $wpdb; 

        $id        = 20;//get_current_user_id();
        $user_info = get_userdata($id); 
        $user_role = implode(',', $user_info->roles); 

        if($user_role != 'shop_manager'){
            echo "<h2>This page is restricted to only Registered DrugStoc Distributors</h2><br/>";
        }else{
            $username  = get_user_meta($id,'nickname',true);
            $primary_distributor = get_user_meta($id,'primary_distributor',true);  
            $products = $wpdb->get_results("SELECT wp_posts.*, wp_postmeta.meta_key, wp_postmeta.meta_value FROM wp_posts INNER JOIN wp_postmeta ON wp_posts.id = wp_postmeta.post_id WHERE wp_posts.post_type='product' AND wp_posts.post_status!='trash' AND wp_postmeta.meta_key='$primary_distributor' AND wp_postmeta.meta_value = '' "); 
            
            ?> 
            <h4><b>Other DrugStoc Products</b></h4>
            <p>Add your prices to other drugs on DrugStoc</p><br>
            <button id="bulkattach">Update Selected</button><br><br>
            <div class="msg"></div>
            <table width="100%" id="allOtherProductsTable" class="table table-striped hover display compact" cellspacing="0">
                <thead>
                    <tr>
                      <th><input type="checkbox" id="bulk_select_" name="bulk_select_" value="all" /></th>
                      <th>ID</th> 
                      <th>Product Name</th>
                      <th>Manufacturer</th> 
                      <th>NAFDAC</th>
                      <th>Price(&#8358.00)</th>
                    </tr> 
                </thead>
                <tbody> 
            <?php  
                $sn = 0;

                foreach ($products as $key => $product_) {

                    $sn += 1; 
                    $product = new WC_Product($product_->ID);
                    $nafdac_no = $product->get_attribute("pa_nafdac-no");
                    $pa_origin = $product->get_attribute("pa_origin");
                    $pa_composition = $product->get_attribute("pa_composition");
                    $pa_manufacturer = $product->get_attribute("pa_manufacturer");
                    $distributor_price = get_post_meta($product_->ID, $primary_distributor, true);
                ?>
                    <tr>
                        <td><input type="checkbox" name="chk_price_drugs" value="<?php echo $product_->ID; ?>"/></td>
                        <td><?php echo $product_->ID; ?></td> 
                        <td>
                            <a target="_blank" href="<?php echo get_permalink($product_->ID);?>">
                                <?php echo get_the_post_thumbnail( $product_->ID, array(65,65) )." : {$product_->post_title} [$product_->post_status]"; ?>
                            </a>
                        </td>
                        <td><?php echo $pa_manufacturer; ?></td> 
                        <td><?php echo $nafdac_no; ?></td>
                        <td>
                            <form id="update-price-<?php echo $product_->ID;?>" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo $product_->ID; ?>"/> 
                                <input type="hidden" name="distributor" value="<?php echo $primary_distributor; ?>"/> 
                                <input name="product_price" class="digit" type="text" id="id<?php echo $product_->ID; ?>" style="width:70px;" maxlength="6" value="<?php echo $distributor_price;?>"/>
                                <button type="button" class="btn_attach_single_product_price" data-id="<?php echo $product_->ID;?>">UPDATE</button>
                            </form> 
                        </td>
                    </tr>
            <?php } ?>
                </tbody> 
                <tfoot>
                    <tr>
                      <th><input type="checkbox" id="bulk_select_2" name="bulk_select_2" value="all" /></th>
                      <th>ID</th> 
                      <th>Product Name</th>
                      <th>Manufacturer</th> 
                      <th>NAFDAC</th>
                      <th>Price(&#8358.00)</th>
                    </tr> 
                </tfoot>
            </table>
            <br><br>
            <button id="bulkattach2">Update Selected</button>
        <?php
        } 
    }

    // Log Change in Price to DB
    function ds_log_price_update($product_id='', $old_price='', $new_price='', $distributor='', $is_approved=0){
        global $wpdb, $woocommerce;    
  
        // Log updated_items
        $log = $wpdb->insert(
            $wpdb->prefix . 'ds_price_change_log', 
            array( 
                'user_id'     => get_current_user_id(),
                'ip_address'  => $this->get_user_ip(),
                'product_id'  => $product_id, 
                'old_price'   => $old_price,
                'new_price'   => $new_price,
                'distributor' => $distributor, 
                'is_approved' => $is_approved,
                'created_at'  => date('Y-m-d H:m:s')
            ) 
        );

        return $log;
    }

    // Update Distributor Price
    function update_distributor_price()
    { 
        $nonce = $_POST['ds_price_nouce'];

        $id        = 20;//get_current_user_id();
        $user_info = get_userdata($id); 
        $user_role = implode(',', $user_info->roles);   

        // check to see if the submitted nonce matches with the
        // generated nonce we created earlier
        if ( ! wp_verify_nonce( $nonce, 'myajax-post-comment-nonce' ) )
            die ( 'Busted!');   

        // ignore the request if the current user doesn't have
        // sufficient permissions 
        if($user_role == 'shop_manager'){
            // get the submitted parameters
            $postID = $_POST['p_ID'];
            $distributor = $_POST['distributor'];
            $price = sanitize_text_field($_POST['p_price']);

            if (isset($postID) && $postID != "" && is_numeric($price) && is_string($distributor) ) {    
                // Log changes to DB
                $this->ds_log_price_update(
                    $postID, 
                    get_post_meta($postID, $distributor, true), 
                    $price, 
                    $distributor, 0);
 
                // Notify Administrator here, via SMS/E-mail

                // Update distributor and DrugStoc prices
                update_post_meta( $postID, $distributor, $price); 

                $this->update_ds_price($postID);
                $response = json_encode( array( 'success' => true, 'price' => $price ) );                  
            }else{  
                $response = json_encode( array( 'success' => false ) );  // generate the response
            }   

            // response output
            header( "Content-Type: application/json" );
            echo $response; 
        }else{
            echo "Access Denied - Insufficient Permissions for user role";
        }

        exit;
    }

    // Bulk Update Distributor Prices
    function bulk_update_distributor_price()
    { 
        $nonce = $_POST['ds_price_nouce'];

        $id        = 20;//get_current_user_id();
        $user_info = get_userdata($id); 
        $user_role = implode(',', $user_info->roles);   

        // check to see if the submitted nonce matches with the
        // generated nonce we created earlier
        if ( ! wp_verify_nonce( $nonce, 'myajax-post-comment-nonce' ) )
            die ( 'Busted!');   

        // ignore the request if the current user doesn't have
        // sufficient permissions 
        if($user_role == 'shop_manager'){
            // get the submitted parameters
            $data = $_POST['products'];
            $distributor = $_POST['distributor'];
            foreach ($data as $i => $product) {
                // Log changes to DB
                $this->ds_log_price_update(
                    $product['id'], 
                    get_post_meta($product['id'], $distributor, true), 
                    $product['price'], 
                    $distributor, 0);
 
                // Notify Administrator here, via SMS/E-mail

                // Update distributor and DrugStoc prices
                update_post_meta($product['id'], $distributor, $product['price']);
                $this->update_ds_price($product['id']);
            }   
            $response = json_encode( array('success' => true) );                  
             
            // response output
            header( "Content-Type: application/json" );
            echo $response; 
        }else{
            echo "Access Denied - Insufficient Permissions for user role";
        } 

        exit;
    }

    // Sample Authentication Test 
    function ds_app_authenticate_user()
    {   
        if(!is_ssl()){ 
            echo json_encode( array('error' => 'Invalid access protocol used.' ));
            exit;
        }
        
        $email = sanitize_text_field($_GET['email']);
        $password = sanitize_text_field($_GET['password']);
        $response = '';

        $user = get_user_by('email', $email);
        
        if ( $user && wp_check_password( $password,$user->data->user_pass ) ){
            
            $response = json_encode( 
                array(
                    'key' => $user->woocommerce_api_consumer_key,
                    'secret' => $user->woocommerce_api_consumer_secret,
                    'user_id' => $user->ID,
                    'primary_distributor' => get_user_meta($user->ID, 'primary_distributor', true)
            ));  
             
            header("Content-Type: application/json"); 
            echo $response;
            exit;
            //var_dump($_GET);
            //var_dump(headers_list());
            
        }else{
            $response = json_encode( array('error' => 'Access Denied - Wrong Email or Password') );
            header('HTTP/1.1 401 Unauthorized', true, 401);
            echo $response;
            exit;
        }   
    }

    // Update Drugstoc Price
    function update_ds_price($id)
    { 
        global $wpdb, $woocommerce; 
        
        $distributors = $wpdb->get_results("SELECT meta_id, meta_key FROM `wp_postmeta` WHERE `meta_key` LIKE '%_price' and meta_key NOT IN ('_price','_regular_price','_sale_price') group by meta_key");

        $products = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE ID = $id AND post_type = 'product' AND post_status LIKE 'publish'");// {$val}'");
       
        foreach ($products as $key => $value) { 
            $id = $value->ID;
            $product = new WC_Product($id); 
             
            $price = 0; // Temporary price variable

            foreach ($distributors as $key => $distributor) { 
                // Get Distributor Price and Store in temp var 
                $temp_price = (float) get_post_meta($id, $distributor->meta_key, true);  

                if($price == 0) $price = ($temp_price != 0)? $temp_price: 0; // Assign a value to price 

                if($temp_price != 0 && $temp_price < $price) $price = $temp_price;   
            }     
            $price = $price * 1.05;

            update_post_meta( $id, '_regular_price', $price);
            update_post_meta( $id, '_sale_price', "");
            update_post_meta( $id, '_price', $price); 
        }   
    } 

    function ds_get_lowest_price($id)
    { 
        global $wpdb, $woocommerce; 
        
        $distributors = $wpdb->get_results("SELECT meta_id, meta_key FROM `wp_postmeta` WHERE `meta_key` LIKE '%_price' and meta_key NOT IN ('_price','_regular_price','_sale_price') group by meta_key");
       
        $prices_ = array();  
        $product = new WC_Product($id);  

        foreach ($distributors as $key => $distributor) { 
            // Get Distributor Price and Store in temp var 
            $temp_price = (float) get_post_meta($id, $distributor->meta_key, true);  

            if($temp_price != 0) array_push($prices_, (float) $temp_price);    
        }   

        if(count($prices_) > 0){
            $price = min($prices_); //$price * 1.05;
            update_post_meta( $id, '_regular_price', $price);
            update_post_meta( $id, '_sale_price', "");
            update_post_meta( $id, '_price', $price);
        }     
    }

    function ds_price_model_deactivate()
    {
        if ( ! current_user_can( 'activate_plugins' ) )
            return;
        // Ensure request is from Admin Plugins Page
        $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
        check_admin_referer( "deactivate-plugin_{$plugin}" );

        // delete_option('drugstoc_commission_per_order'); 
    }
}
endif;

// Activate Plugin 
DrugstocPriceModel::setup();
 

 