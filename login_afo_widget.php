<?php
class CurrindaLoginWidget extends WP_Widget {
	private $app_id, $app_secret, $domain, $scope;

	public function __construct() {
		$this->app_id = get_option('currinda_app_id');
		$this->app_secret = get_option('currinda_app_secret');
    $this->domain = get_option("currinda_app_domain");
    $this->scope = get_option("currinda_app_scope");

		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
		parent::__construct(
	 		'currinda_login_wid',
			'Currinda Login Widget',
			array( 'description' => __( 'This is a login form for Currinda.', 'clw' ), )
		);
	 }

	public function widget( $args, $instance ) {
		extract( $args );
		
		$wid_title = apply_filters( 'widget_title', $instance['wid_title'] );
		
		echo $args['before_widget'];
			$this->loginForm($wid_title);
		echo $args['after_widget'];
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['wid_title'] = strip_tags( $new_instance['wid_title'] );
		return $instance;
	}


	public function form( $instance ) {
		$wid_title = $instance[ 'wid_title' ];
		?>
		<p><label for="<?php echo $this->get_field_id('wid_title'); ?>"><?php _e('Title:'); ?> </label>
		<input class="widefat" id="<?php echo $this->get_field_id('wid_title'); ?>" name="<?php echo $this->get_field_name('wid_title'); ?>" type="text" value="<?php echo $wid_title; ?>" />
		</p>
		<?php 
	}
	
	public function loginForm($title = ""){
		global $post;
		$this->error_message();
		if(!is_user_logged_in()){
		    //<?php echo $this->url; ?option=currinda_user_login
		?>
	<iframe scrolling="no" style="width:100%; height: 205px; border: none; scroll: auto;" id="currinda_iframe" src="https://<?php echo get_option('currinda_client_domain'); ?>/api/v2/organisation/<?php echo get_option('currinda_client_scope'); ?>/authorize?client_id=<?php echo get_option('currinda_client_id'); ?>&redirect_uri=<?php echo get_site_url(); ?>%3Foption%3Dcurrinda_user_login&scope=user&response_type=code&approval_prompt=auto&version=2"></iframe>
		<?php 
		} else {
		  global $current_user;
     	get_currentuserinfo();
		$link_with_username = __('Howdy,','clw')." ".$current_user->display_name;
		?>
		<ul class="login_wid">
			<li><?php echo $link_with_username;?> | <a id="logout" data-logouturl="https://<?php echo get_option('currinda_client_domain'); ?>/logout" href="<?php echo wp_logout_url(site_url()); ?>" title="<?php _e('Logout','clw');?>"><?php _e('Logout','clw');?></a></li>
		</ul>
		<?php 
		}
	}
	
	public function error_message(){
    $error = CurrindaLogin::instance()->error;
    
		if($error){
			echo '<div class="error_wid_login">'.$error->get_error_message().'</div>';
		}
	}
	
	public function register_plugin_styles() {
		wp_enqueue_style( 'style_login_widget', plugins_url( 'facebook-login-afo/style_login_widget.css' ) );
	}
	
} 

  function fb_login_validate(){
	if(isset($_POST['option']) and $_POST['option'] == "afo_user_login"){
		global $post;
		if($_POST['user_username'] != "" and $_POST['user_password'] != ""){
			$creds = array();
			$creds['user_login'] = $_POST['user_username'];
			$creds['user_password'] = $_POST['user_password'];
			$creds['remember'] = true;
		
			$user = wp_signon( $creds, true );
			if($user->ID == ""){
				$_SESSION['msg_class'] = 'error_wid_login';
				$_SESSION['msg'] = __('Error in login!','flw');
			} else{
				wp_set_auth_cookie($user->ID);
				wp_redirect( site_url() );
				exit;
			}
		} else {
			$_SESSION['msg_class'] = 'error_wid_login';
			$_SESSION['msg'] = __('Username or password is empty!','flw');
		}
		
	}
	
	
	if(isset($_REQUEST['option']) and $_REQUEST['option'] == "fblogin"){
		global $wpdb;
		$appid 		= get_option('afo_fb_app_id');
		$appsecret  = get_option('afo_fb_app_secret');
		$facebook   = new Facebook(array(
			'appId' => $appid,
			'secret' => $appsecret,
			'cookie' => TRUE,
		));
		$fbuser = $facebook->getUser();
		if ($fbuser) {
			try {
				$user_profile = $facebook->api('/me');
			}
			catch (Exception $e) {
				echo $e->getMessage();
				exit();
			}
			$user_fbid	= $fbuser;
			$user_email = $user_profile["email"];
			$user_fnmae = $user_profile["first_name"];
  
		  if( email_exists( $user_email )) { // user is a member 
			  $user = get_user_by('login', $user_email );
			  $user_id = $user->ID;
			  wp_set_auth_cookie( $user_id, true );
		   } else { // this user is a guest
			  $random_password = wp_generate_password( 10, false );
			  $user_id = wp_create_user( $user_email, $random_password, $user_email );
			  wp_set_auth_cookie( $user_id, true );
		   }
		   
   			wp_redirect( site_url() );
			exit;
   
		}		
	}
}

add_action( 'widgets_init', create_function( '', 'register_widget( "CurrindaLoginWidget" );' ) );
add_action( 'init', 'fb_login_validate' );
?>
