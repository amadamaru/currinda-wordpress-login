<?php
/*
Plugin Name: Currinda Login Widget
Plugin URI: http://currinda.com/wordpress.html
Description: This is a Currinda login widget
Version: 0.2
Author: currinda
Author URI: http://currinda.com/
*/

include_once dirname(__FILE__) . '/vendor/autoload.php';
include_once dirname(__FILE__) . '/CurrindaProvider.php';
include_once dirname( __FILE__ ) . '/login_afo_widget.php';

use \League\OAuth2\Client\Token\AccessToken;

class CurrindaLogin {
  protected $client_id, $client_secret, $domain, $scope;

  const CURRINDA_VERSION = "0.2";
  
  static protected $instance = null;
  static function instance() {
    if(!self::$instance) {
      return self::$instance = new CurrindaLogin;
    } else {
      return self::$instance;
    }
  }
	
	function __construct() {
		add_action( 'admin_menu', array( $this, 'menu_item' ) );
		add_action( 'plugins_loaded',  array( $this, 'widget_text_domain' ) );
		add_action( 'admin_init', array( $this, 'check_version' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );
		add_action( 'init', array( $this, 'login_validate' ) );
    add_shortcode("currinda-login", array($this, "handle_shortcode"));
    add_action( 'wp_enqueue_scripts', array($this, 'currinda_scripts') );

    $this->update_variables();
	}

	function currinda_scripts() {
	    wp_register_script( 'currinda', plugins_url( '/inc/js/currinda.js', __FILE__ ), array(), CURRINDA_VERSION, true);
	    wp_enqueue_script( 'currinda' );
      wp_localize_script('currinda', 'WPURLS', array( 'siteurl' => get_option('siteurl') ));
	}
	
  function handle_shortcode($attrs, $content) {
    $a = shortcode_atts( array(
      'class' => '',
    ), $atts );

    $output = "<div class='currinda'>";
    if($this->error) {
      $output .= "<div class='error_wid_login'>Error: {$this->error->get_error_message()}</div>";
    }
    
    $output .= "<a class='{$a["class"]}' href='javascript:currinda_login()'>$content</a>"; //$this->url?option=currinda_user_login
    
    $inactive_url = get_option('currinda_inactive_url');
    $expired_url = get_option('currinda_expired_url');
    $outstanding_url = get_option('currinda_outstanding_url');
    $login_success_url = get_option('currinda_login_success_url');
    
    $user_id = wp_get_current_user()->ID;
    $details = get_user_meta($user_id, 'currinda_membership', true);
    if ($details) {
        if ($this->is_unapproved($details)) {
            $output .= "<p>Your membership is not yet active yet. <a href='" . $inactive_url . "'>Contact the member administrator to get them to approve your account.</a></p>";
        } else if ($this->has_expired($details)) {
            $output .= "<p>The current membership has expired. <a href='" . $expired_url . "'>Click here to renew.</a></p>";
        } else if ($this->is_overdue($details)) {
            $output .= "<p>Your membership fees are outstanding. <a href='" . $outstanding_url . "'>Click here to make a payment.</a></p>";
        }
    }
    $output .= "</div>";
    
    return $output;

  }
  
  function has_expired($details) {
      if ($details->Membership->Expired) {
          return true;
      }
      foreach ($details->CorporateMemberships as $corp_member) {
          if ($corp_member->Expired) {
              return true;
          }
      }
      return false;
  }

  function is_unapproved($details) {
      if ($details->Membership->Status === 'unapproved') {
          return true;
      }
      foreach ($details->CorporateMemberships as $corp_member) {
          if ($corp_member->Status === 'unapproved') {
              return true;
          }
      }
      return false;
  }

  function is_overdue($details) {
      if ($details->Membership->Status === 'outstanding') {
          return true;
      }
      foreach ($details->CorporateMemberships as $corp_member) {
          if ($corp_member->Status === 'outstanding') {
              return true;
          }
      }
      return false;
  }
  
  function update_variables() {
		$this->client_id = get_option('currinda_client_id');
		$this->client_secret = get_option('currinda_client_secret');
    $this->domain = get_option("currinda_client_domain");
    $this->scope = intval(get_option("currinda_client_scope"));
		$this->inactive_url = get_option('currinda_inactive_url');
    $this->expired_url = get_option('currinda_expired_url');
    $this->outstanding_url = get_option('currinda_outstanding_url');
    $this->login_success_url = get_option('currinda_login_success_url');
  }

	function menu_item () {
		add_options_page( 'Currinda Login', 'Currinda Login', 'activate_plugins', 'currinda_login', array( $this, 'admin_options' ));
	}
	
	
	function  admin_options () {
		global $wpdb;
		
		$this->help_support();
		?>
		<form name="f" method="post" action="">
		<input type="hidden" name="option" value="currinda_save_settings" />
		<table width="98%" border="0" style="background-color:#FFFFFF; border:1px solid #CCCCCC; padding:0px 0px 0px 10px; margin:2px;">
		  <tr>
			<td width="45%"><h1>Currinda Login Widget</h1></td>
			<td width="55%">&nbsp;</td>
		  </tr>
		  <tr>
        <td><strong>Currinda Domain:</strong></td>
			  <td><input type="text" name="currinda_client_domain" value="<?php echo $this->domain;?>" /></td>
		  </tr>
		  <tr>
        <td><strong>App ID:</strong></td>
        <td><input type="text" name="currinda_client_id" value="<?php echo $this->client_id;?>" /></td>
		  </tr>
		  
		  <tr>
        <td><strong>App Secret:</strong></td>
			  <td><input type="text" name="currinda_client_secret" value="<?php echo $this->client_secret;?>" /></td>
		  </tr>
		  <tr>
        <td><strong>Scope:</strong></td>
			  <td><input type="text" name="currinda_client_scope" value="<?php echo $this->scope;?>" /></td>
		  </tr>
		  <tr>
        <td colspan="2">&nbsp;</td>
		  </tr>
		  <tr>
        <td><p><strong>Login Success URL:</strong></p>
            <p>Users will be redirected to this path when they login successfully.</p>
            <p><i>e.g. http://yourdomain.com/contact</i></p></td>
			  <td style="vertical-align:top"><input type="text" name="currinda_login_success_url" value="<?php echo $this->login_success_url;?>" size="80" /></td>
		  </tr>
		  <tr>
        <td><p><strong>Inactive membership URL:</strong></p>
            <p>Users will be redirected to this path when their membership hasn't yet been activated yet.</p>
            <p><i>e.g. http://yourdomain.com/contact</i></p></td>
			  <td style="vertical-align:top"><input type="text" name="currinda_inactive_url" value="<?php echo $this->inactive_url;?>" size="80" /></td>
		  </tr>
		  <tr>
        <td><p><strong>Expired membership URL:</strong></p>
            <p>Users will be redirected to this path when their membership has expired.</p>
            <p><i>e.g. https://<?php echo isset($this->domain) ? $this->domain : 'org.currinda.com' ?>/organisation/<?php echo isset($this->scope) ? $this->scope : '123' ?>/view</i></p></td>
        <td style="vertical-align:top"><input type="text" name="currinda_expired_url" value="<?php echo $this->expired_url;?>" size="80" /></td>
		  </tr>
		  <tr>
        <td><p><strong>Outstanding membership URL:</strong></p>
            <p>Users will be redirected to this path when their membership has expired.</p>
            <p><i>e.g. https://<?php echo isset($this->domain) ? $this->domain : 'org.currinda.com' ?>/organisation/<?php echo isset($this->scope) ? $this->scope : '123' ?>/view</i></p></td>
        <td style="vertical-align:top"><input type="text" name="currinda_outstanding_url" value="<?php echo $this->outstanding_url;?>" size="80" /></td>
		  </tr>
		  <tr>
        <td>&nbsp;</td>
        <td><input type="submit" name="submit" value="Save" class="button button-primary button-large" /></td>
		  </tr>
		  <tr>
			<td colspan="2"><?php $this->login_help();?></td>

		  </tr>
		</table>
		</form>
		<?php 
	}
	
	function widget_text_domain(){
		load_plugin_textdomain('clw', FALSE, basename( dirname( __FILE__ ) ) .'/languages');
	}

	
	function check_version() {
	    $plugin_path = __FILE__;
	    $plugin_data = get_plugin_data($plugin_path);
	    $plugin_version = $plugin_data['Version'];
	    $existing_version = get_option('currinda_version', 'NONE');
      if ($existing_version === 'NONE') {
          // Migrate from v0.1 to v0.2 - extract out the scope org ID from "org-123" to just "123" (ignore "event-456")
          $old_scope = get_option('currinda_client_scope');
          if (stripos(strtolower($old_scope), 'org-') == 0) { 
              
              // Get the org ID
              $org_id = intval(explode("-", $old_scope)[1]);
            
              // Save the new scope with just the integer value
              update_option('currinda_client_scope', $org_id);
          }

          update_option('currinda_version', $plugin_version);
      } 	    
	}
	
	function login_help(){ ?>
		<p><font color="#FF0000"><strong>Note*</strong></font>
			    <br />
          You need to have a Client ID and Client Secret created by your Currinda administrator.
        </p>
	<?php }
	
	
	function save_settings(){
		if(isset($_POST['option']) and $_POST['option'] == "currinda_save_settings"){
			update_option( 'currinda_client_id', $_POST['currinda_client_id'] );
			update_option( 'currinda_client_secret', $_POST['currinda_client_secret'] );
			update_option( 'currinda_client_domain', $_POST['currinda_client_domain'] );
			update_option( 'currinda_client_scope', $_POST['currinda_client_scope'] );
			update_option( 'currinda_inactive_url', $_POST['currinda_inactive_url'] );
			update_option( 'currinda_expired_url', $_POST['currinda_expired_url'] );
			update_option( 'currinda_outstanding_url', $_POST['currinda_outstanding_url'] );
			update_option( 'currinda_login_success_url', $_POST['currinda_login_success_url'] );
				
      $this->update_variables();
		}
	}
	
	function help_support(){ ?>
	<table width="98%" border="0" style="background-color:#FFFFFF; border:1px solid #CCCCCC; padding:0px 0px 0px 10px; margin:2px;">
	  <tr>
		<td align="right"><a href="http://currinda.com/support.html" target="_blank">Help and Support</a></td>
	  </tr>
	</table>
	<?php
	}

  var $error;

  function login_validate() {
    if(isset($_GET['option']) and $_GET['option'] == "currinda_user_login") {
      $provider = $this->provider();
      if ( ! isset($_GET['code'])) {
        if(isset($_GET["error"])) {
          $this->error = new WP_Error($_GET["error"], htmlspecialchars($_GET["error_description"]));
          return $this->error;
        } else {
          // If we don't have an authorization code then get one
          header('Location: '.$provider->getAuthorizationUrl());
          exit;
        }

      } else {
        // Try to get an access token (using the authorization code grant)
        $token = $provider->getAccessToken('authorization_code', [
          'code' => $_GET['code']
        ]);

        $this->save_token($token);

        // Optional: Now you have a token you can look up a users profile data
        try {
          // We got an access token, let's now get the user's details
          $details = $provider->getUserDetails($token);
          $this->setup_user_or_login($details);

        } catch (Exception $e) {
          $this->error = new WP_Error("404", "Unfortunately you do not have a membership.");
          return $this->error;
        }
      }
    }
  }

  function save_token($token) {
    $_SESSION["CurrindaLogin"]["AccessToken"] = $token->accessToken;
    $_SESSION["CurrindaLogin"]["RefreshToken"] = $token->refreshToken;
  }

  function get_saved_token() {
    return new AccessToken(array(
      "access_token" => $_SESSION["CurrindaLogin"]["AccessToken"],
      "refresh_token" => $_SESSION["CurrindaLogin"]["RefreshToken"]
    ));
  }

  function refresh_access_token() {
    $refreshToken = $_SESSION["CurrindaLogin"]["RefreshToken"];

    $grant = new \League\OAuth2\Client\Grant\RefreshToken();
    $token = $provider->getAccessToken($grant, ['refresh_token' => $refreshToken]);

    $this->save_token($token);

  }

  function getDetails() {
    $token = $this->get_saved_token();
    $provider = $this->provider();
    return $provider->getUserDetails($token);
  }

  
  function has_expiry_date_past($expiry_date) {
      $timezone = new DateTimeZone(date_default_timezone_get());
      $expiry_date = new DateTime($expiry_date, $timezone);
      $curr = new DateTime("now", $timezone);
      if ($expiry_date < $curr) {
          return true;
      }
      return false;
  }
  
  function is_a_standard_member($details) {
      if (!$this->has_expiry_date_past($details->Membership->ExpiryDate) && 
              ($details->Membership->Status !== "unapproved") && 
              $details->Membership->Checked && 
              !$details->Membership->Expired) {
          return true;
      }
      return false;
  }
  
  
  function is_a_corp_member($details) {
      // Check each corporate member individually - if one is valid, then is one of these
      foreach ($details->CorporateMemberships as $corp_member) {
          if (!$this->has_expiry_date_past($corp_member->ExpiryDate) && 
                ($corp_member->Status !== "unapproved") && 
                $corp_member->Checked &&
                !$corp_member->Expired) {
            return true;
          }
      }
      return false;
  }

  function is_a_sub_member($details) {
      if (!isset($details->Membership->Parent)) { return false; }
      if (!$this->has_expiry_date_past($details->Membership->Parent->ExpiryDate) && 
              ($details->Membership->Parent->Status !== "unapproved") && 
              $details->Membership->Parent->Checked && 
              !$details->Membership->Parent->Expired) {
          return true;
      }
      return false;
  }

  function check_valid_record($details) {
      // We need to check the different types of membership (standard, corporate, committee, sub-member)
      if ($this->is_a_standard_member($details)) { return true; }
      if ($this->is_a_corp_member($details)) { return true; }
      if ($this->is_a_sub_member($details)) { return true; }
      
      // If none of these memberships are valid, this user is not valid
      return false;
  }

  function setup_user_or_login($details) {
	$jdata = array('success'=>true);
    $user_email = $details->Email;
    $full_name = $details->FirstName." ".$details->LastName;

    $valid = $this->check_valid_record($details);

    if( email_exists( $user_email )) { // user is a member 
      $user = get_user_by('email', $user_email );
      $user_id = $user->ID;
    } else { // this user is a guest
      $random_password = wp_generate_password( 10, false );
      $user_id = wp_create_user( $user_email, $random_password, $user_email );

      wp_update_user(array(
        "ID" => $user_id,
        'user_nicename' => $full_name,
        'display_name' => $full_name
      ));
      update_user_meta($user_id, 'nickname', $full_name);
      
      $user = new WP_User($user_id);
    }
    
    // Persist the membership data into the user metadata table
    update_user_meta($user_id, 'currinda_membership', $details);

    if($valid) {
        $user->add_role("subscriber");
    } else {
        $user->remove_role("subscriber");
    }

    wp_set_current_user( $user_id, $user->user_login );
    wp_set_auth_cookie( $user_id, true );
    do_action( 'wp_login', $user->user_login );

    if(get_option('currinda_login_success_url')){
	$jdata['login_success_url'] = get_option('currinda_login_success_url');
    }
    header("Access-Control-Allow-Origin: *");
    echo json_encode($jdata);
    exit(0);
  }

  function provider() {
    $return_url = site_url()."?option=currinda_user_login";
    $url = "https://$this->domain/";
    $version = "v2";

    $details_url = $url."api/$version/organisation/$this->scope/user";

    return new League\OAuth2\Client\Provider\CurrindaProvider(array(
      'clientId'  =>  $this->client_id,
      'clientSecret'  =>  $this->client_secret,
      "scopes" => ["user"],
      'redirectUri' => $return_url,
      'url_authorize' => $url."api/$version/organisation/$this->scope/authorize",
      "url_access_token"=> $url."api/$version/organisation/$this->scope/token",
      "url_user_details" => $details_url
    ));
  }

  function get_details() {
  }


	
}
CurrindaLogin::instance();
