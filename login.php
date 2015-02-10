<?php
/*
Plugin Name: Currinda Login Widget
Plugin URI: http://currinda.com/wordpress.html
Description: This is a Currinda login widget
Version: 0.1
Author: currinda
Author URI: http://currinda.com/
*/

include_once dirname(__FILE__) . '/vendor/autoload.php';
include_once dirname(__FILE__) . '/CurrindaProvider.php';
include_once dirname( __FILE__ ) . '/login_afo_widget.php';

use \League\OAuth2\Client\Token\AccessToken;

class CurrindaLogin {
  protected $client_id, $client_secret, $domain, $scope;

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
		add_action( 'admin_init',  array( $this, 'save_settings' ) );
		add_action( 'plugins_loaded',  array( $this, 'widget_text_domain' ) );
    add_action( 'init', array( $this, 'login_validate' ) );
    add_shortcode("currinda-login", array($this, "handle_shortcode"));


    $this->update_variables();
	}

  function handle_shortcode($attrs, $content) {
    $a = shortcode_atts( array(
      'class' => '',
    ), $atts );


    $output = "";
    if($this->error) {
      $output .= "<div class='error_wid_login'>Error: {$this->error->get_error_message()}</div>";
    }

    $output .= "<a class='{$a["class"]}' href='$this->url?option=currinda_user_login'>$content</a>";

    return $output;

  }

  function update_variables() {
		$this->client_id = get_option('currinda_client_id');
		$this->client_secret = get_option('currinda_client_secret');
    $this->domain = get_option("currinda_client_domain");
    $this->scope = get_option("currinda_client_scope");
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
	
	function login_help(){ ?>
		<p><font color="#FF0000"><strong>Note*</strong></font>
			    <br />
		      You need create a new facebook API Applitation to setup this plugin. Please follow the instructions provided below.
			</p>
			  <p>
			  <strong>1.</strong> Go to <a href="https://developers.facebook.com/" target="_blank">https://developers.facebook.com/</a> <br /><br />
			  <strong>2.</strong> Click on Create a new app button. A popup will open.<br /><br />
              <strong>3.</strong> Add the required informations and don't forget to make your app live. This is very important otherwise your app will not work for all users.<br /><br />
			  <strong>4.</strong> Then Click the "Create App" button and follow the instructions, your new app will be created. <br /><br />
			  <strong>5.</strong> Copy and Paste "App ID" and "App Secret" here. <br /><br />
			  <strong>6.</strong> That's All. Have fun :)
			  </p>
			  
	<?php }
	
	
	function save_settings(){
		if(isset($_POST['option']) and $_POST['option'] == "currinda_save_settings"){
			update_option( 'currinda_client_id', $_POST['currinda_client_id'] );
			update_option( 'currinda_client_secret', $_POST['currinda_client_secret'] );
			update_option( 'currinda_client_domain', $_POST['currinda_client_domain'] );
			update_option( 'currinda_client_scope', $_POST['currinda_client_scope'] );

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


  function check_valid_record($details) {
    if(stripos($this->scope, "event") === 0) {
      return !!$details->Checked;
    } elseif(stripos($this->scope, "organisation") === 0) {
      return !$details->Membership->Expired;
    } else {
      return false;
    }
  }

  function setup_user_or_login($details) {
    $user_email = $details->Email;
    $full_name = $details->FirstName." ".$details->LastName;

    $status = $details->Checked;

    $valid = $this->check_valid_record($details);


    if( email_exists( $user_email )) { // user is a member 
      $user = get_user_by('login', $user_email );
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
    }

    $user = new WP_User($user_id);

    if($valid) {
      $user->add_role("subscriber");
    } else {
      $user->remove_role("subscriber");
    }

    wp_set_auth_cookie( $user_id, true );
     
    wp_redirect( site_url() );
  }

  function provider() {
    $return_url = site_url()."?option=currinda_user_login";
    $url = "https://$this->domain/";

    $scope = explode("-", $this->scope);
    if($scope[0] == "event" ) {
      $details_url = $url."api/".strtr(strtolower($this->scope), array("-" => "/"));
    } else {
      $details_url = $url."api/organisation/$scope[1]";
    }

    return new League\OAuth2\Client\Provider\CurrindaProvider(array(
      'clientId'  =>  $this->client_id,
      'clientSecret'  =>  $this->client_secret,
      "scopes" => [$this->scope],
      'redirectUri'   =>  $return_url,
      'url_authorize' => $url."api/authorize",
      "url_access_token"=> $url."api/token",
      "url_user_details" => $details_url
    ));
  }

  function get_details() {
  }


	
}
CurrindaLogin::instance();
