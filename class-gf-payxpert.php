<?php

add_action( 'wp', array( 'GFPayxpert', 'maybe_thankyou_page' ), 5 );

GFForms::include_payment_addon_framework();
 
class GFPayxpert extends GFPaymentAddOn {
 
    protected $_version = GF_PAYXPERT_VERSION;
    protected $_min_gravityforms_version = '1.9';
    protected $_slug = 'gravityformspayxpert';
    protected $_path = 'gravityformspayxpert/payxpert.php';
    protected $_full_path = __FILE__;
    protected $_title = 'PayXpert for Gravity Forms';
    protected $_short_title = 'PayXpert';
    protected $_supports_callbacks = true;
 
    private static $_instance = null;
 
    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new GFPayxpert();
        }
 
        return self::$_instance;
    }
 
    public function init() {
        parent::init();

        add_filter( 'gform_submit_button', array( $this, 'form_submit_button' ), 10, 2 );
        include_once ('includes/Connect2PayClient.php');
    }

    public function init_admin() {

        parent::init_admin();

        //add actions to allow the payment status to be modified
        add_action( 'gform_payment_status', array( $this, 'admin_edit_payment_status' ), 3, 3 );

        if ( version_compare( GFCommon::$version, '1.8.17.4', '<' ) ){
            //using legacy hook
            add_action( 'gform_entry_info', array( $this, 'admin_edit_payment_status_details' ), 4, 2 );
        }
        else {
            add_action( 'gform_payment_date', array( $this, 'admin_edit_payment_date' ), 3, 3 );
            add_action( 'gform_payment_transaction_id', array( $this, 'admin_edit_payment_transaction_id' ), 3, 3 );
            add_action( 'gform_payment_amount', array( $this, 'admin_edit_payment_amount' ), 3, 3 );
        }

        add_action( 'gform_after_update_entry', array( $this, 'admin_update_payment' ), 4, 2 );
    }

    public function admin_edit_payment_status( $payment_status, $form, $lead ) {
        //allow the payment status to be edited when for payxpert, not set to Approved/Paid, and not a subscription
        if ( ! $this->is_payment_gateway( $lead['id'] ) || strtolower( rgpost( 'save' ) ) <> 'edit' || $payment_status == 'Approved' || $payment_status == 'Paid' || rgar( $lead, 'transaction_type' ) == 2 ) {
            return $payment_status;
        }

        //create drop down for payment status
        $payment_string = gform_tooltip( 'payxpert_edit_payment_status', '', true );
        $payment_string .= '<select id="payment_status" name="payment_status">';
        $payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status . '</option>';
        $payment_string .= '<option value="Paid">Paid</option>';
        $payment_string .= '</select>';

        return $payment_string;
    }

    public function admin_edit_payment_status_details( $form_id, $lead ) {

        $form_action = strtolower( rgpost( 'save' ) );
        if ( ! $this->is_payment_gateway( $lead['id'] ) || $form_action <> 'edit' ) {
            return;
        }

        //get data from entry to pre-populate fields
        $payment_amount = rgar( $lead, 'payment_amount' );
        if ( empty( $payment_amount ) ) {
            $form           = GFFormsModel::get_form_meta( $form_id );
            $payment_amount = GFCommon::get_order_total( $form, $lead );
        }
        $transaction_id = rgar( $lead, 'transaction_id' );
        $payment_date   = rgar( $lead, 'payment_date' );
        if ( empty( $payment_date ) ) {
            $payment_date = gmdate( 'y-m-d H:i:s' );
        }

        //display edit fields
        ?>
        <div id="edit_payment_status_details" style="display:block">
            <table>
                <tr>
                    <td colspan="2"><strong>Payment Information</strong></td>
                </tr>

                <tr>
                    <td>Date:<?php gform_tooltip( 'payxpert_edit_payment_date' ) ?></td>
                    <td>
                        <input type="text" id="payment_date" name="payment_date" value="<?php echo $payment_date ?>">
                    </td>
                </tr>
                <tr>
                    <td>Amount:<?php gform_tooltip( 'payxpert_edit_payment_amount' ) ?></td>
                    <td>
                        <input type="text" id="payment_amount" name="payment_amount" class="gform_currency" value="<?php echo $payment_amount ?>">
                    </td>
                </tr>
                <tr>
                    <td nowrap>Transaction ID:<?php gform_tooltip( 'payxpert_edit_payment_transaction_id' ) ?></td>
                    <td>
                        <input type="text" id="payxpert_transaction_id" name="payxpert_transaction_id" value="<?php echo $transaction_id ?>">
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    public function admin_edit_payment_date( $payment_date, $form, $lead ) {
        //allow the payment status to be edited when for payxpert, not set to Approved/Paid, and not a subscription
        if ( ! $this->is_payment_gateway( $lead['id'] ) || strtolower( rgpost( 'save' ) ) <> 'edit' ) {
            return $payment_date;
        }

        $payment_date = $lead['payment_date'];
        if ( empty( $payment_date ) ) {
            $payment_date = gmdate( 'y-m-d H:i:s' );
        }

        $input = '<input type="text" id="payment_date" name="payment_date" value="' . $payment_date . '">';

        return $input;
    }

    public function admin_edit_payment_transaction_id( $transaction_id, $form, $lead ) {
        //allow the payment status to be edited when for payxpert, not set to Approved/Paid, and not a subscription
        if ( ! $this->is_payment_gateway( $lead['id'] ) || strtolower( rgpost( 'save' ) ) <> 'edit' ) {
            return $transaction_id;
        }

        $input = '<input type="text" id="payxpert_transaction_id" name="payxpert_transaction_id" value="' . $transaction_id . '">';

        return $input;
    }

    public function admin_edit_payment_amount( $payment_amount, $form, $lead ) {

        //allow the payment status to be edited when for payxpert, not set to Approved/Paid, and not a subscription
        if ( ! $this->is_payment_gateway( $lead['id'] ) || strtolower( rgpost( 'save' ) ) <> 'edit' ) {
            return $payment_amount;
        }

        if ( empty( $payment_amount ) ) {
            $payment_amount = GFCommon::get_order_total( $form, $lead );
        }

        $input = '<input type="text" id="payment_amount" name="payment_amount" class="gform_currency" value="' . $payment_amount . '">';

        return $input;
    }

    public function admin_update_payment( $form, $lead_id ) {
        check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

        //update payment information in admin, need to use this function so the lead data is updated before displayed in the sidebar info section
        $form_action = strtolower( rgpost( 'save' ) );
        if ( ! $this->is_payment_gateway( $lead_id ) || $form_action <> 'update' ) {
            return;
        }

        //get lead
        $lead = GFFormsModel::get_lead( $lead_id );

        //check if current payment status is processing
        // if($lead['payment_status'] != 'Processing')
        //     return;

        //get payment fields to update
        $payment_status = rgpost( 'payment_status' );
        //when updating, payment status may not be editable, if no value in post, set to lead payment status
        if ( empty( $payment_status ) ) {
            $payment_status = $lead['payment_status'];
        }

        $payment_amount      = GFCommon::to_number( rgpost( 'payment_amount' ) );

        $payment_transaction = rgpost( 'payxpert_transaction_id' );
        $payment_date        = rgpost( 'payment_date' );
        if ( empty( $payment_date ) ) {
            $payment_date = gmdate( 'y-m-d H:i:s' );
        } else {
            //format date entered by user
            $payment_date = date( 'Y-m-d H:i:s', strtotime( $payment_date ) );
        }

        global $current_user;
        $user_id   = 0;
        $user_name = 'System';
        if ( $current_user && $user_data = get_userdata( $current_user->ID ) ) {
            $user_id   = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        $lead['payment_status'] = $payment_status;
        $lead['payment_amount'] = $payment_amount;
        $lead['payment_date']   = $payment_date;
        $lead['transaction_id'] = $payment_transaction;

        // if payment status does not equal approved/paid or the lead has already been fulfilled, do not continue with fulfillment
        if ( ( $payment_status == 'Approved' || $payment_status == 'Paid' ) && ! $lead['is_fulfilled'] ) {
            $action['id']               = $payment_transaction;
            $action['type']             = 'complete_payment';
            $action['transaction_id']   = $payment_transaction;
            $action['amount']           = $payment_amount;
            $action['entry_id']         = $lead['id'];

            $this->complete_payment( $lead, $action );
            $this->fulfill_order( $lead, $payment_transaction, $payment_amount );
        }
        //update lead, add a note
        GFAPI::update_entry( $lead );
        GFFormsModel::add_note( $lead['id'], $user_id, $user_name, sprintf( __( 'Payment information was manually updated. Status: %s. Amount: %s. Transaction Id: %s. Date: %s', 'gravityformspayxpert' ), $lead['payment_status'], GFCommon::to_money( $lead['payment_amount'], $lead['currency'] ), $payment_transaction, $lead['payment_date'] ) );
    }

    function form_submit_button( $button, $form ) {
        
        $text   = "WARNING: You will be redirected to the payment page.";
        $button = "<div>{$text}</div>" . $button;
        
        return $button;
    }

    function redirect_url( $feed, $submission_data, $form, $entry ) {

        $connect2pay = $this->get_plugin_setting( 'apiurl' );

        $originator_id = $this->get_plugin_setting( 'originator' );

        $password = $this->get_plugin_setting( 'password' );

        $c2pClient = new Connect2PayClient($connect2pay, $originator_id, $password);

        $c2pClient->setPaymentType(Connect2PayClient::_PAYMENT_TYPE_CREDITCARD);
        $c2pClient->setPaymentMode(Connect2PayClient::_PAYMENT_MODE_SINGLE);
        $c2pClient->setShippingType(Connect2PayClient::_SHIPPING_TYPE_VIRTUAL);

        $customer_fields = $this->customer_query_string( $feed, $entry );

        // Convert amount to cents
        $amount = $submission_data["payment_amount"] * 100;

        $callback_url = add_query_arg( 'page', 'gf_payxpert_form_ipn', home_url( '/' ) );
        $return_url = $this->return_url( $form['id'], $entry['id'] );
        $secure = $this->get_plugin_setting( '3dsecure' );

        $c2pClient->setOrderID($entry["id"]);
        $c2pClient->setShopperID($customer_fields["email"]);
        $c2pClient->setAmount($amount);
        $c2pClient->setOrderDescription($submission_data["line_items"][0]["name"]);
        $c2pClient->setCurrency($entry["currency"]);
        $c2pClient->setShopperFirstName($customer_fields["first_name"]);
        $c2pClient->setShopperLastName($customer_fields["last_name"]);
        $c2pClient->setShopperAddress($customer_fields["address1"]);
        $c2pClient->setShopperZipcode($customer_fields["postcode"]);
        $c2pClient->setShopperCity($customer_fields["town"]);
        $c2pClient->setShopperCountryCode($customer_fields["country"]);
        $c2pClient->setShopperPhone($customer_fields["phone"]);
        $c2pClient->setShopperEmail($customer_fields["email"]);
        $c2pClient->setCtrlRedirectURL($return_url);
        $c2pClient->setCtrlCallbackURL($callback_url); // http://shops/wordpress/4.8.1/?page=gf_payxpert_form_ipn
        $c2pClient->setSecure3d(isset($secure) ? $secure : false);

        if ($c2pClient->validate()) {
          if ($c2pClient->prepareTransaction()) {
        
            $_SESSION['merchantToken'] = $c2pClient->getMerchantToken();
        
            $url = $c2pClient->getCustomerRedirectURL();
          } else {
            $message = "<b>PayXpert</b> payment module: Error in prepareTransaction: <br />";
            $message .= "Order id: " . $entry["id"] . "<br />";
            $message .= "Result code: " . htmlentities($c2pClient->getReturnCode(), ENT_QUOTES, 'UTF-8');
            $message .= "Preparation error occured: " . htmlentities($c2pClient->getClientErrorMessage(), ENT_QUOTES, 'UTF-8');
          }
        } else {
            $message = "<b>PayXpert</b> payment module: Error in validate function: <br />";
            $message .= "Order id: " . $entry["id"] . "<br />";
            $message .= "Validation error occured: " . payxpert_escapeHTML($c2pClient->getClientErrorMessage()) . "<br />";
        }

        GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Processing' );

        return $url;
    }

    public function return_url( $form_id, $lead_id ) {
        $pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

        if ( $_SERVER['SERVER_PORT'] != '80' ) {
            $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        $ids_query = "ids={$form_id}|{$lead_id}";
        $ids_query .= '&hash=' . wp_hash( $ids_query );

        return add_query_arg( 'gf_payxpert_form_return', base64_encode( $ids_query ), $pageURL );
    }

    public static function maybe_thankyou_page() {
        $instance = self::get_instance();

        if ( ! $instance->is_gravityforms_supported() ) {
            return;
        }

        if ( $str = rgget( 'gf_payxpert_form_return' ) ) {
            $str = base64_decode( $str );

            parse_str( $str, $query );
            if ( wp_hash( 'ids=' . $query['ids'] ) == $query['hash'] ) {
                list( $form_id, $lead_id ) = explode( '|', $query['ids'] );

                $form = GFAPI::get_form( $form_id );
                $lead = GFAPI::get_entry( $lead_id );

                if ( ! class_exists( 'GFFormDisplay' ) ) {
                    require_once( GFCommon::get_base_path() . '/form_display.php' );
                }

                $confirmation = GFFormDisplay::handle_confirmation( $form, $lead, false );

                if ( is_array( $confirmation ) && isset( $confirmation['redirect'] ) ) {
                    header( "Location: {$confirmation['redirect']}" );
                    exit;
                }

                GFFormDisplay::$submission[ $form_id ] = array( 'is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $lead );
            }
        }
    }

    public function callback() {

        if ( ! $this->is_callback_valid() ) {
            return;
        }

        $connect2pay = $this->get_plugin_setting( 'apiurl' );

        $originator_id = $this->get_plugin_setting( 'originator' );

        $password = $this->get_plugin_setting( 'password' );

        $c2pClient = new Connect2PayClient($connect2pay, $originator_id, $password);

        if ($c2pClient->handleCallbackStatus()) {
    
            $status = $c2pClient->getStatus();

            // get the Error code
            $errorCode = $status->getErrorCode();
            $action['note'] = "Payment details: " . $status->getErrorMessage();
            $action['entry_id'] = $status->getOrderID();
            $action['transaction_id'] = $status->getOrderID();
            $action['amount'] = number_format($status->getAmount() / 100, 2, '.', '');

            $array = explode('|', $c2pClient->getCtrlCustomData());
            $baseCurrencyAmount = $array[0];

            // errorCode = 000 transaction is successfull
            if ($errorCode == '000') {

                $action['type'] = 'complete_payment';
            } else {

                $action['type'] = 'fail_payment';             
            }          

        } else {

            return new WP_Error( 'invalid_request', sprintf( __( 'Callback not valid', 'gravityformspayxpert' ) ) );              
        }  

        // Send a response to mark this transaction as notified
        $response = array("status" => "OK", "message" => "Status recorded");
        header("Content-type: application/json");
        echo json_encode($response);

        return $action;
    }
 
    public function plugin_settings_fields() {
        return array(
            array(
                'title'  => esc_html__( 'PayXpert API', 'gravityformspayxpert' ),
                'fields' => array(
                    array(
                        'name'          => 'api_mode',
                        'label'         => esc_html__( 'API', 'gravityformspayxpert' ),
                        'type'          => 'radio',
                        'default_value' => 'live',
                        'choices'       => array(
                            array(
                                'label' => esc_html__( 'Live', 'gravityformspayxpert' ),
                                'value' => 'live',
                            ),
                            array(
                                'label'    => esc_html__( 'Test', 'gravityformspayxpert' ),
                                'value'    => 'test',
                                'selected' => true,
                            ),
                        ),
                        'horizontal'    => true,
                    ),
                    array(
                        'name'     => 'originator',
                        'label'    => esc_html__( 'Originator ID', 'gravityformspayxpert' ),
                        'type'     => 'text',
                        'class'    => 'medium'
                    ),
                    array(
                        'name'     => 'password',
                        'label'    => esc_html__( 'Password', 'gravityformspayxpert' ),
                        'type'     => 'text',
                        'class'    => 'medium'
                    ),
                    array(
                        'name'     => 'url',
                        'label'    => esc_html__( 'Gateway URL', 'gravityformspayxpert' ),
                        'type'     => 'text',
                        'class'    => 'medium'
                    ),
                    array(
                        'name'     => 'apiurl',
                        'label'    => esc_html__( 'API URL', 'gravityformspayxpert' ),
                        'type'     => 'text',
                        'class'    => 'medium',
                        'default_value' => 'https://connect2.payxpert.com/'
                    ),
                    array(
                        'name'          => '3dsecure',
                        'label'         => esc_html__( '3D Secure', 'gravityformspayxpert' ),
                        'type'          => 'radio',
                        'default_value' => 'live',
                        'choices'       => array(
                            array(
                                'label' => esc_html__( 'Enabled', 'gravityformspayxpert' ),
                                'value' => true,
                            ),
                            array(
                                'label'    => esc_html__( 'Disabled', 'gravityformspayxpert' ),
                                'value'    => false,
                                'selected' => true,
                            )
                        ),
                        'horizontal'    => true,
                    )
                )
            )
        );
    }
 
    public function is_valid_setting( $value ) {
        return strlen( $value ) < 10;
    }

    public function customer_query_string( $feed, $lead ) {
        $fields = array();
        $first_name = "";
        $last_name = "";
        foreach ( $this->get_customer_fields() as $field ) {
            $field_id = $feed['meta'][ $field['meta_name'] ];
            $value    = rgar( $lead, $field_id );

            if ( $field['name'] == 'country' ) {
                $value = GFCommon::get_country_code( $value );
            } else if ( $field['name'] == 'state' ) {
                $value = GFCommon::get_us_state_code( $value );
            }

            $fields[$field['name']] = $value;

        }

        return $fields;
    }

    public function get_customer_fields() {
        return array(
            array( 'name' => 'first_name', 'label' => 'First Name', 'meta_name' => 'billingInformation_firstName' ),
            array( 'name' => 'last_name', 'label' => 'Last Name', 'meta_name' => 'billingInformation_lastName' ),
            array( 'name' => 'email', 'label' => 'Email', 'meta_name' => 'billingInformation_email' ),
            array( 'name' => 'address1', 'label' => 'Address', 'meta_name' => 'billingInformation_address' ),
            array( 'name' => 'address2', 'label' => 'Address 2', 'meta_name' => 'billingInformation_address2' ),
            array( 'name' => 'town', 'label' => 'City', 'meta_name' => 'billingInformation_city' ),
            array( 'name' => 'region', 'label' => 'State', 'meta_name' => 'billingInformation_state' ),
            array( 'name' => 'postcode', 'label' => 'Zip', 'meta_name' => 'billingInformation_zip' ),
            array( 'name' => 'country', 'label' => 'Country', 'meta_name' => 'billingInformation_country' ),
            array( 'name' => 'phone', 'label' => 'Phone', 'meta_name' => 'billingInformation_phone' ),
        );
    }

    public function feed_settings_fields() {

        // Get default payment feed settings fields.
        $default_settings = parent::feed_settings_fields();
        $form = $this->get_current_form();
        // Prepare customer information fields.
        $billing_info   = parent::get_field( 'billingInformation', $default_settings );

        $billing_fields = $billing_info['field_map'];
        $add_first_name = true;
        $add_last_name  = true;
        foreach ( $billing_fields as $mapping ) {
            //add first/last name if it does not already exist in billing fields
            if ( $mapping['name'] == 'firstName' ) {
                $add_first_name = false;
            } else if ( $mapping['name'] == 'lastName' ) {
                $add_last_name = false;
            }
        }

        if ( $add_last_name ) {
            //add last name
            array_unshift( $billing_info['field_map'], array( 'name' => 'lastName', 'label' => __( 'Last Name', 'gravityformspayxpert' ), 'required' => false ) );
        }
        if ( $add_first_name ) {
            array_unshift( $billing_info['field_map'], array( 'name' => 'firstName', 'label' => __( 'First Name', 'gravityformspayxpert' ), 'required' => false ) );
        }

        $billing_info['field_map'][] = array( 'name' => 'phone', 'label' => __( 'Phone', 'gravityformspayxpert' ), 'required' => false );

        $default_settings = parent::replace_field( 'billingInformation', $billing_info, $default_settings );

        return $default_settings;
    }

    public function is_callback_valid() {
        if ( rgget( 'page' ) != 'gf_payxpert_form_ipn' ) {
            return false;
        }
        return true;
    }
 
}