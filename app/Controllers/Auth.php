<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Services\JWTService;
// require_once APPPATH . 'Libraries/JWT/BeforeValidException.php';
// require_once APPPATH . 'Libraries/JWT/ExpiredException.php';
// //require_once APPPATH . 'Libraries/JWT/SignatureInvalidException.php';
// require_once APPPATH . 'Libraries/JWT/JWT.php';
// require_once APPPATH . 'Libraries/JWT/Key.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// require_once APPPATH . 'Libraries/google/vendor/autoload.php';
class Auth extends BaseController
{

	public function __construct()
	{
		//$this->load->helper('captcha');
	}
	public function index()
	{

		return redirect()->to('auth/login');
	}
	public function terms_and_conditions()
	{
		// terms_and_conditions modal
		$this->data['body'] = $this->general->get_system_var('terms_conditions_body');
		return view('modal/terms', $this->data);
	}
	public function login()
	{


		// if already login, redirect to dashboard of logged in user, whether admin or member
		if (session()->get('isLogin')) {
			return redirect()->to(base_url(session()->get('user_group') . '/dashboard'));
		}

		/* $config = array(
            'img_path'      => 'captcha_images/',
            'img_url'       => base_url().'/captcha_images/',
            'font_path'     => 'assets/frontend/themify9f24.ttf',
            'img_width'     => '260',
            'img_height'    => 50,
            'word_length'   => 4,
            'font_size'     => 22,
			'pool'          => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',
			
        );
        $captcha = create_captcha($config);
        //echo '<pre>'; print_r($captcha);exit();
        // Unset previous captcha and set new captcha word
        session()->set('captchaCode', FALSE);
        session()->set('captchaCode',$captcha['word']);
       
		
		$this->data['captcha'] = $captcha['image']; */
		$this->data['num_1'] = $this->general->five_digit_key(1);
		$this->data['num_2'] = $this->general->five_digit_key(1);
		if (isset($_GET['redirect'])) {
			$redirect = $_GET['redirect'];
		} else {
			$redirect = '';
		}
		$this->data['title'] = 'Sign In - ' . $this->data['system_name'];
		$this->data['file_title'] = 'Sign In';
		$this->data['redirect'] = $redirect;
		$this->data['file'] = 'login';
		return view('authentication/index', $this->data);
	}
	public function sendUserDataToWordPress($userData)
	{
		$apiUrl = 'https://jaguarforex.com/wp-json/customapi/v1/register2/';
		// Log the request before sending
        error_log("📤 Sending Data to WordPress API: " . json_encode($userData));
        
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $apiUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($userData));
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

		$response = curl_exec($ch);
		if (curl_errno($ch)) {
			error_log('cURL error in sendUserDataToWordPress: ' . curl_error($ch));
			curl_close($ch);
			return ['error' => true, 'message' => curl_error($ch)];
		}

		curl_close($ch);
		
		// Log the API response
        error_log("📥 WordPress API Response: " . $response);
    
		$decoded_response = json_decode($response, true);

		// echo '<pre>';
		// print_r($decoded_response);
		// echo "ssss";
		// exit();
		if (isset($decoded_response['error'])) {
			error_log('API error in sendUserDataToWordPress: ' . $decoded_response['message']);
		}

		// echo '<pre>';
		// print_r($decoded_response);
		// exit();
		

		return $decoded_response; //Assuming WordPress sends back a JSON response
	}

	public function register($param1 = '')
	{
		// if already login, redirect to dashboard of logged in user, whether admin or member
		if (session()->get('isLogin')) {
			return redirect()->to(base_url(session()->get('user_group') . '/dashboard'));
		}
		$countries = file_get_contents(base_url('assets/countries.json'));
		$this->data['countries'] = json_decode($countries, true);
		$this->data['sponsor'] = $param1;
		$this->data['title'] = 'Sign Up - ' . $this->data['system_name'];
		$this->data['file_title'] = 'Create Account';
		$this->data['file'] = 'register';
		return view('authentication/index', $this->data);
	}
	public function get_country_code()
	{
		list($country_name, $country_code) = explode('-', $this->request->getPost('country'));
		$country_code      = str_replace(' ', '', $country_code);
		echo $country_code;
	}
	public function forget_password()
	{
		// if already login, redirect to dashboard of logged in user, whether admin or member
		if (session()->get('isLogin')) {
			return redirect()->to(base_url(session()->get('user_group') . '/dashboard'));
		}
		$this->data['title'] = 'Recover Password - ' . $this->data['system_name'];
		$this->data['file'] = 'forget-password';
		return view('authentication/index', $this->data);
	}
	public function new_password($param1 = '')
	{
		// if already login, redirect to dashboard of logged in user, whether admin or member
		if (session()->get('isLogin')) {
			return redirect()->to(base_url(session()->get('user_group') . '/dashboard'));
		}
		$this->data['key'] = $param1;
		$this->data['title'] = 'Reset Password - ' . $this->data['system_name'];
		$this->data['file'] = 'new-password';
		return view('authentication/index', $this->data);
	}
	public function do_register($param1 = "")
	{

		error_reporting(E_ALL);
		ini_set('display_errors', 1);
		// if already login, redirect to dashboard of logged in user, whether admin or member
		if (session()->get('isLogin')) {
			return redirect()->to(base_url(session()->get('user_group') . '/dashboard'));
		}
		if (strtolower($this->request->getMethod()) == 'post') {
			if ($this->request->getPost('tos') == false) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'You must agree our Terms and Conditions to continue',
				)));
			}

			// Honeypot check
			$honeypot = $this->request->getPost('user_profile'); // 'user_profile' is the name of the honeypot field
			if (!empty($honeypot)) {
				// Log the attempt if necessary
				log_message('error', 'Honeypot triggered during registration by IP: ' . $this->request->getIPAddress());

				// Optionally, you can redirect or display a generic error message
				die(json_encode(array(
					'status' => 'error',
					'message' => 'An error occurred, please try again later.',
				)));
			}




			if ($this->general->get_system_var('register_without_sponsor') == '0') {
				if (empty($this->request->getPost('sponsor'))) {
					die(json_encode(array(
						'status' => 'error',
						'message' => 'Sponsor Required to complete registration',
					)));
				}
			}
			/* if (empty($this->request->getPost('service'))){
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Please select Service to complete registration',
				)));
			}
			if (empty($this->request->getPost('account_number'))){
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Please enter your account number',
				)));
			}
			
			$account_number_exists = $this->user_account->account_number_exists($this->request->getPost('service'), $this->request->getPost('account_number'));
			if ($account_number_exists){
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Account Number already registered',
				)));
			} */
			// matrix and matrix type, can be updated only once while installing system
			$matrix = $this->general->get_system_var('matrix');
			$matrix_type = $this->general->get_system_var('matrix_type');

			//checking if username already exists
			$username_exists = $this->user_account->username_exists($this->request->getPost('username'));
			if ($username_exists) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Username already registered',
				)));
			}
			// validating password for any special character
			if (preg_match("/[\'^£$%&*()}{@#~?><>,|=_+¬-]/", $this->request->getPost('username'))) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Username contains illegal characters',
				)));
			}
			if (preg_match('/\s/', $this->request->getPost('username'))) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Username contains whitespace',
				)));
			}
			if (strlen($this->request->getPost('username')) < 4 || strlen($this->request->getPost('username')) > 12) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Username must be greater than 4 and less than 12 characters',
				)));
			}

			// User Needs to be entered in matrix table whether he has sponsor or not
			$binary['username'] = $this->request->getPost('username');
			// if user has a sponsor

			if (!empty($this->request->getPost('sponsor'))) {
				// checking whether sponsor user exists
				$sponsor_exists = $this->user_account->username_exists($this->request->getPost('sponsor'));
				if ($sponsor_exists == false) {
					die(json_encode(array(
						'status' => 'error',
						'message' => 'Sponsor not found',
					)));
				}
				$activation_status = $this->user_account->user_actication_status($this->request->getPost('sponsor'));
				if ($activation_status == false) {
					die(json_encode(array(
						'status' => 'error',
						'message' => 'Sponsor not activated yet',
					)));
				}

				$binary['direct_referral'] = $this->request->getPost('sponsor');
			}
			if (strlen($this->request->getPost('name')) < 4 || strlen($this->request->getPost('name')) > 25) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'First Name must be greater than 4 chracters',
				)));
			}
			if (strlen($this->request->getPost('last_name')) < 2 || strlen($this->request->getPost('last_name')) > 25) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Last Name must be greater than 2 chracters',
				)));
			}
			//validating email address
			if (!filter_var($this->request->getPost('email'), FILTER_VALIDATE_EMAIL)) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Invalid Email address format',
				)));
			}
			// checking if email already existed
			if ($this->user_account->email_exists($this->request->getPost('email')) == true) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Email already exists',
				)));
			}

			if (strlen($this->request->getPost('password')) < 8) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Password must be greater than 8 chracters',
				)));
			}
			if ($this->general->validate_password($this->request->getPost('password')) == false) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Password must have atleast one digit, one upper case letter one lower case letter and one special chracter',
				)));
			}
			if ($this->request->getPost('password') !== $this->request->getPost('repassword')) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Passwords not match',
				)));
			}

			if (preg_match('/\s/', $this->request->getPost('password'))) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Password contains whitespace',
				)));
			}
			if (empty($this->request->getPost('country'))) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Please select your country',
				)));
			}
			if (empty($this->request->getPost('mobile'))) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Mobile Number Required',
				)));
			}

			/* $is_mobile_valid = $this->mobile->is_valid_number($this->request->getPost('mobile'));
			if ($is_mobile_valid == false){
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Invalid Mobile number',
				)));
			} */
			if ($this->user_account->mobile_exists($this->request->getPost('mobile')) == true) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Mobile Number already registered',
				)));
			}


			/* $secret = "6LeMowwqAAAAAPcohDShd5OXh073kiCDK9IFrZsQ";
			$response = $this->request->getPost('g-recaptcha-response');
			$verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$response}");
			$captcha_success = json_decode($verify);
			if ($captcha_success->success == true || $captcha_success->success == "true" || $captcha_success->success == 1) {
				
			} else {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Invalid Captcha',
				)));
			} */
			/* $post = $_POST;
			echo '<pre>';
			print_r($captcha_success);
			print_r($post);exit(); */

			list($country_name, $country_code) = explode('-', $this->request->getPost('country'));

			$data['name'] = $this->request->getPost('name');
			$data['last_name'] = $this->request->getPost('last_name');
			$data['username'] = $this->request->getPost('username');
			$data['email'] = $this->request->getPost('email');
			/* $data['service'] = $this->request->getPost('service');
			if ($this->request->getPost('service') == 'Exness'){
				$data['client_id'] = $this->request->getPost('account_number');
			} else {
				$data['roboforex_status'] = '1';
			}
			$data['account_number'] = $this->request->getPost('account_number'); */
			$data['country'] = $country_name;
			$data['mobile'] = $this->request->getPost('mobile');
			//$data['region_code'] = $this->mobile->region_code($this->request->getPost('mobile'));
			$data['password'] = md5($this->request->getPost('password'));
			$data['user_group'] = 'member';
			if ($this->general->get_system_var('email_verification_required') == '1') {
				$data['status'] = '0';
			} else {
				$data['status'] = '1';
			}
			$data['created'] = date('Y-m-d H:i:s');
			$save = $this->general->insert_data('users', $data);
			//$save = true;
			$email_add = $this->request->getPost('email');
			if ($save) {
				$user_id = $this->db->insertID();
				list($country_name, $country_code) = explode('-', $this->request->getPost('country'));
				$signup_array1 = [
					'nickname' => $this->request->getPost('username'),
					'email'    => $this->request->getPost('email'),
					'password' => $this->request->getPost('password'),
					'sponsor'  => $this->request->getPost('sponsor'),
					'country'  => $country_name,  // Only send the country name, or adjust as needed
					'mobile'   => $this->request->getPost('mobile'),
					'u_id'   => $user_id
				];

				$wordpressApiResponse = $this->sendUserDataToWordPress($signup_array1);
				// echo '<pre>';
				// print_r($wordpressApiResponse);
				// exit();

				if (isset($wordpressApiResponse['user_id'])) {
					$wp_id =  $wordpressApiResponse['user_id'];
					$up_user['wp_id'] = $wp_id;
					$this->general->update_data('users', 'id', $user_id, $up_user);
				}


				/*$db = \Config\Database::connect(); // Connect to the database
			  $builder = $db->table('users'); // Get the Query Builder for the 'users' table

			 // Specify the condition
			 $builder->where('id', $user_id);
			 
			 // Specify the data to update
			 $data2 = [
				 'wp_id' => $wp_id
			 ];
			 
			 // Execute the update operation
			 $builder->update($data2); */
				$this->general->insert_data('binary_2x', $binary);

				$signup_array = array(
					'nickname' 				=> $this->request->getPost('username'),
					'email' 				=> $this->request->getPost('email'),
					'password' 				=> $this->request->getPost('password'),
					'residence' 			=> $country_name,
					'reward_schema' 		=> 'revenue_share',
					'website' 				=> 'https://one.exnesstrack.net/a/tffad7az66',
					//'g_recaptcha_response' 	=> $response,
					'g_recaptcha_version' 	=> 2,
				);

				//$this->exness->send_signup_request($signup_array);

				/* $service_accounts['username'] = $this->request->getPost('username');
				$service_accounts['account_number'] = $this->request->getPost('account_number');
				$service_accounts['service'] = $this->request->getPost('service');
				$service_accounts['status'] = '1';
				$service_accounts['dated'] = date('Y-m-d H:i:s');
				$this->general->insert_data('users_service_accounts', $service_accounts); */

				if (!empty($this->request->getPost('sponsor'))) {
					// sending notification to sponsor, if sponsor available
					$noti['username'] = $this->request->getPost('sponsor');
					$noti['msg_from'] = $this->request->getPost('username');
					$noti['message'] = $data['name'] . ' ' . $data['last_name'] . ' has signed up under you having username ' . $this->request->getPost('username');
					$noti['dated'] = date('Y-m-d H:i:s');
					$this->general->insert_data('notifications', $noti);
				}
				if ($this->general->get_commission_var('registration_bonus_status') == '1') {
					$registration_bonus_amount = $this->general->get_commission_var('registration_bonus_amount');
					$this->wallet->add_income($this->request->getPost('username'), $registration_bonus_amount, 'registration_bonus');
				}


				$mail_options = array(
					'name' 			=> $this->request->getPost('name'),
					'username' 		=> $this->request->getPost('username'),
					'service' 		=> '',
					'system_name' 	=> $this->data['system_name'],
					'action_time' 	=> date('H:i:s'),
					'action_date' 	=> date('Y-m-d')
				);
				$admin_mail = $this->general->get_system_var('email');
				$this->emails->send_type_email($admin_mail, 'admin_new_user_email', $mail_options);

				if ($this->general->get_system_var('email_verification_required') == '1') {
					$key = $this->general->random_string(15);

					$keyData['user_id'] = $user_id;
					$keyData['key_code'] = $key;
					$keyData['type'] = 'email_verification';
					$keyData['status'] = 0;
					$keyData['dated'] = date('Y-m-d H:i:s');
					$this->general->insert_data('user_profile_key', $keyData);

					$message = base_url('auth/verify-email/') . '/' . $key;

					$s = strtotime($keyData['dated']);
					$mail_options = array(
						'name' 			=> $this->request->getPost('name'),
						'username' 		=> $this->request->getPost('username'),
						'system_name' 	=> $this->data['system_name'],
						'email_activation_link' 	=> $message,
						'action_time' 	=> date('H:i:s'),
						'action_date' 	=> date('Y-m-d')
					);
					$this->emails->send_type_email($email_add, 'email_confirmation', $mail_options);
					die(json_encode(array(
						'redirect' => 'yes',
						'url' => 'https://jaguarforex.com/afterregistration',
						'status' => 'success',
						'message' => 'Account created successfully, Activation Link can be found in Email.',
					)));
				} else {
					$mail_options = array(
						'name' 			=> $this->request->getPost('name'),
						'username' 		=> $this->request->getPost('username'),
						'system_name' 	=> $this->data['system_name'],
						'action_time' 	=> date('H:i:s'),
						'action_date' 	=> date('Y-m-d')
					);
					$this->emails->send_type_email($email_add, 'welcome_email', $mail_options);
					die(json_encode(array(
						'redirect' => 'yes',
						'url' => 'https://jaguarforex.com/afterregistration',
						'status' => 'success',
						'message' => 'Account created successfully.',
					)));
				}
			} else {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'An error occured, please try again.',
				)));
			}
		} else {
			session()->setFlashdata('error', 'Invalid request sent.');
			return redirect()->to(base_url('auth/register'));
			exit();
		}
	}
	public function verify_email($param1 = '')
	{
		//verifying email address by clicking link in email
		$link = $param1;
		$key_data = $this->general->get_tbl_field('user_profile_key', '*', 'key_code', $link);
		if (count($key_data) > 0) {
			$expiry_date_time = date('Y-m-d H:i:s', strtotime($key_data[0]['dated'] . '+2 days'));
			if (date('Y-m-d H:i:s') > $expiry_date_time) {
				$this->general->delete_tbl_data('users', 'id', $key_data[0]['user_id']);
				$username = $this->user_account->username_by_id($key_data[0]['user_id']);
				$this->general->delete_tbl_data('binary_2x', 'username', $username);
				session()->setFlashdata('error', 'Link Expired.');
				return redirect()->to('auth/login');
			}
			if ($key_data[0]['status'] == 1) {
				session()->setFlashdata('error', 'Link Expired.');
				return redirect()->to('auth/login');
			} else {
				$user_data = $this->user_account->get_user_data($key_data[0]['user_id']);
				$mail_options = array(
					'name' 			=> $user_data[0]['name'],
					'username' 		=> $user_data[0]['username'],
					'system_name' 	=> $this->data['system_name'],
					'action_time' 	=> date('H:i:s'),
					'action_date' 	=> date('Y-m-d')
				);
				$this->emails->send_type_email($user_data[0]['email'], 'welcome_email', $mail_options);


				$link_data['status'] = 1;
				$this->general->update_data('user_profile_key', 'key_code', $param1, $link_data);

				$userdata['status'] = 1;
				$this->general->update_data('users', 'id', $key_data[0]['user_id'], $userdata);
				session()->setFlashdata('success', 'Email successfully verified, Login now.');
				return redirect()->to(base_url('auth/login'));
			}

			exit();
		} else {
			session()->setFlashdata('error', 'Email not found in our records, Please register now.');
			return redirect()->to(base_url('auth/register'));
			exit();
		}
	}
	public function set_new_password($param1 = '')
	{
		//setting new password
		if (strtolower($this->request->getMethod()) == 'post') {
			if (strlen($this->request->getPost('password')) < 5) {
				session()->setFlashdata('error', 'Password must contain atleast 5 characters.');
				return redirect()->back();
				exit();
			}
			if ($this->request->getPost('password') !== $this->request->getPost('repassword')) {
				session()->setFlashdata('error', 'Passwords don\'t match.');
				return redirect()->back();
				exit();
			}
			$key = $this->request->getPost('key');
			// validating token against ugainst for allowing user to set new password
			$data = $this->general->get_tbl_field_where2('user_profile_key', '*', 'key_code', $key, 'type', 'password_reset');
			if (count($data) > 0) {
				if ($data[0]['status'] == 1) {
					session()->setFlashdata('error', 'Link Expired.');
					return redirect()->back();
					exit();
				}
				$userdata = $this->general->get_tbl_field('users', '*', 'id', $data[0]['user_id']);
				if (count($userdata) > 0) {
					if ($userdata[0]['status'] == 2) {
						session()->setFlashdata('error', 'Your account has been blocked, please contact department for further information.');
						return redirect()->back();
						exit();
					} else {
						$link_data['status'] = 1;
						$this->general->update_data('user_profile_key', 'key_code', $key, $link_data);

						$usernewdata['password'] = md5($this->request->getPost('repassword'));
						$this->general->update_data('users', 'id', $data[0]['user_id'], $usernewdata);
						session()->setFlashdata('success', 'Password successfully updated, Login now.');
						if ($param1 == 'front') {
							return redirect()->to(base_url('site/login'));
						} else {
							return redirect()->to(base_url('auth/login'));
						}
					}
				} else {
					session()->setFlashdata('error', 'Record not found.');
					return redirect()->back();
					exit();
				}
			} else {
				session()->setFlashdata('error', 'Invalid token provided.');
				return redirect()->back();
				exit();
			}
		} else {
			session()->setFlashdata('error', 'Invalid request sent.');
			return redirect()->back();
			exit();
		}
	}

	public function do_sso_login()
	{
		if (strtolower($this->request->getMethod()) == 'post') {
			$reDirect = $this->request->getPost('redirect');
			if (isset($reDirect) && $reDirect != '') {
				$redirect = $reDirect;
			} else {
				$redirect = base_url(session()->get('user_group') . '/dashboard');
			}
			if (session()->get('isLogin')) {
				return redirect()->to($redirect);
			}

			$num_1 = $this->request->getPost('num_1');
			$num_2 = $this->request->getPost('num_2');
			$num_sum = $num_1 + $num_2;
			$sum = $this->request->getPost('sum');
			if (empty($sum)) {
				session()->setFlashdata('error', 'Please fill captcha.');
				return redirect()->back();
				exit();
			}
			if ($sum != $num_sum) {
				session()->setFlashdata('error', 'Invalid Captcha Code.');
				return redirect()->back();
				exit();
			}
			$login = $this->user_account->verify_login($this->request->getPost('email'), $this->request->getPost('password'));
			if ($login == true) {
				$data = $this->user_account->get_user_data($this->request->getPost('email'));
				if (count($data) > 0) {
					if ($data[0]['status'] == 2) {
						session()->setFlashdata('error', 'Account Suspended, Please Contact department for further details.');
						return redirect()->back();
						exit();
					}
					$sessiondata = array(
						'isLogin'		=>	true,
						'user_name'		=>	$data[0]['username'],
						'user_group'	=>	$data[0]['user_group'],
						'user_id'		=>	$data[0]['id'],
					);
					if ($data[0]['user_group'] == 'member') {
						$token = $this->exness->generate_auth();
						session()->set('exness_token', $token);
						if ($this->general->get_system_var('2fa_auth_status') == '0') {
							session()->set($sessiondata);
							session()->setFlashdata('success', 'Login Successful.');
							return redirect()->to(base_url('member'));
						} else {
							if ($data[0]['g_auth_required'] == '0' || $data[0]['g_auth'] == '0') {
								session()->set($sessiondata);
								session()->setFlashdata('success', 'Login Successful.');
								return redirect()->to(base_url('member'));
							} else {
								$teo_factor_sess_data = array(
									'name_fa2'				=>	$data[0]['name'],
									'user_name_fa2'			=>	$data[0]['username'],
									'user_group_fa2'		=>	$data[0]['user_group'],
									'user_id_fa2'			=>	$data[0]['id'],
									'user_email_fa2'		=>	$data[0]['email'],
									'two_factor_auth_code'	=>	$data[0]['g_auth_key'],
								);
								session()->set($teo_factor_sess_data);
								session()->setFlashdata('warning', 'Please enter Google Authenticator verification code.');
								return redirect()->to(base_url('auth/two-factor-authentication'));
								exit();
							}
						}
					} else {
						$token = $this->exness->generate_auth();
						session()->set('exness_token', $token);
						session()->set($sessiondata);
						session()->setFlashdata('success', 'Login Successful.');
						return redirect()->to(base_url('admin'));
					}
				} else {
					session()->setFlashdata('error', 'Invalid Credentials.');
					return redirect()->back();
					exit();
				}
			} else {
				session()->setFlashdata('error', 'Invalid Credentials.');
				return redirect()->back();
				exit();
			}
		} else {
			return redirect()->back();
			exit();
		}
		/* $MySecretKey = 'ILoveU';

		// Generate signature from authentication info + secret key
		$sig = hash(
			'sha256',
			 $user->id . $user->email,
			 $MySecretKey
		);

		// Make sure we're redirecting somewhere safe
		$source = parse_url($_GET['source']);
		if(in_array($source->host, $list_of_safe_hosts))
		  $target = 'http://'.$source->host.$source->path;

		// Send the authenticated user back to the originating site
		header('Location: '.$target.'?'.
			'user_id='.$user->id.
			'&user_email='.urlencode($user->email).
			'&sig='.$sig); */
	}



	function generateJWT($user)
	{
		$issuedAt = time();
		$expirationTime = $issuedAt + 3600;  // Token validity: 1 hour from now
		$payload = [
			'iat' => $issuedAt,
			'exp' => $expirationTime,
			'uid' => $user // User ID from database
		];

		$key = 'dsdasdsadsadsdsadsasdsdsadsss';  // Replace with your actual key
		$algorithm = 'HS256';  // HMAC using SHA-256

		$jwt = JWT::encode($payload, $key, $algorithm);

		return $jwt;
	}

	// Example of setting this token in a cookie
	function onUserLogin($user)
	{
		$jwt = generateJWT($user);
		setcookie('auth_token1', $jwt, time() + 36000, "/", ".jaguarforex.com", false, true);
	}
	public function do_login($role = "")
	{

		if (strtolower($this->request->getMethod()) == 'post') {
			// if already login, redirect to dashboard of logged in user, whether admin or member
			if (session()->get('isLogin')) {
				echo "<script>window.location.href = 'https://jaguarforex.com/wp-json/wp/v1/logout/';</script>";
				//return redirect()->to(base_url(session()->get('user_group').'/dashboard'));
			}

			// Honeypot check
			$honeypot = $this->request->getPost('user_url'); // 'user_url' is the name of the honeypot field in your login form
			if (!empty($honeypot)) {
				// Log the attempt if necessary
				log_message('error', 'Honeypot triggered during login by IP: ' . $this->request->getIPAddress());

				// Optionally, you can redirect or display a generic error message
				session()->setFlashdata('error', 'Invalid request.');
				return redirect()->back();
			}


			/* $inputCaptcha = $this->request->getPost('captcha');
            $sessCaptcha = session()->get('captchaCode');
            if($inputCaptcha != $sessCaptcha){
				session()->setFlashdata('error', 'Invalid Captcha.');
				return redirect()->back();
				exit();
			} */

			$num_1 = $this->request->getPost('num_1');
			$num_2 = $this->request->getPost('num_2');
			$num_sum = $num_1 + $num_2;
			$sum = $this->request->getPost('sum');
			if (empty($sum)) {
				session()->setFlashdata('error', 'Please fill captcha.');
				return redirect()->back();
				exit();
			}
			if ($sum != $num_sum) {
				session()->setFlashdata('error', 'Invalid Captcha Code.');
				return redirect()->back();
				exit();
			}
			// verifying email aand password
			$login = $this->user_account->verify_login($this->request->getPost('email'), $this->request->getPost('password'));
			if ($login == true) {
				$data = $this->user_account->get_user_data($this->request->getPost('email'));
				if (count($data) > 0) {
					if ($this->general->get_system_var('email_verification_required') == '1') {
						if ($data[0]['status'] == 0) {
							session()->setFlashdata('error', 'Please verify your email address to continue.');
							return redirect()->back();
							exit();
						}
					}
					if ($data[0]['status'] == 2) {
						session()->setFlashdata('error', 'Account Suspended, Please Contact department for further details.');
						return redirect()->back();
						exit();
					}
					/* if ($data[0]['user_group'] !== 'admin'){
						if ($data[0]['client_id'] == ''){
							session()->setFlashdata('error', 'Account Inactive, Please wait for us to activate your account.');
							return redirect()->back();
							exit();
							
						} 
					} */


					$sessiondata = array(
						'isLogin'		=>	true,
						'user_name'		=>	$data[0]['username'],
						'user_group'	=>	$data[0]['user_group'],
						'user_id'		=>	$data[0]['id'],
					);

					if ($data[0]['user_group'] == 'member') {
						$token = $this->exness->generate_auth();
						session()->set('exness_token', $token);
						if ($this->general->get_system_var('2fa_auth_status') == '0') {
							session()->set($sessiondata);
							$jwt = $this->generateJWT($data[0]['wp_id']);



							setcookie('auth_token1', $jwt, time() + 36000, "/", ".jaguarforex.com", false, true);

							//print_r($_COOKIE['auth_token']);
							session()->setFlashdata('success', 'Login Successful.');
							echo "<script>window.location.href = 'https://jaguarforex.com/dashboard/';</script>";
							//return redirect()->to(base_url('member'));
						} else {
							if ($data[0]['g_auth_required'] == '0' || $data[0]['g_auth'] == '0') {
								session()->set($sessiondata);
								$jwt = $this->generateJWT($data[0]['wp_id']);

								setcookie('auth_token1', $jwt, time() + 36000, "/", ".jaguarforex.com", false, true);


								session()->setFlashdata('success', 'Login Successful.');
								echo "<script>window.location.href = 'https://jaguarforex.com/dashboard/';</script>";
								//return redirect()->to(base_url('member'));
							} else {
								$teo_factor_sess_data = array(
									'name_fa2'			=>	$data[0]['name'],
									'user_name_fa2'		=>	$data[0]['username'],
									'user_group_fa2'	=>	$data[0]['user_group'],
									'user_id_fa2'		=>	$data[0]['id'],
									'user_email_fa2'	=>	$data[0]['email'],
									'two_factor_auth_code'		=>	$data[0]['g_auth_key'],
								);
								session()->set($teo_factor_sess_data);
								session()->setFlashdata('warning', 'Please enter Google Authenticator verification code.');
								return redirect()->to(base_url('auth/two-factor-authentication'));
								exit();
							}
						}
					} else {
						$token = $this->exness->generate_auth();
						session()->set('exness_token', $token);
						session()->set($sessiondata);




						session()->setFlashdata('success', 'Login Successful.');
						return redirect()->to('https://jaguarforex.com/dashboard/');
						exit();
						//echo "<script>window.location.href = 'https://jaguarforex.com/dashboard/';</script>";
						//return redirect()->to(base_url('admin'));
						//exit();
					}
				} else {
					session()->setFlashdata('error', 'Invalid Credentials Entered.');
					return redirect()->back();
					exit();
				}
			} else {
				session()->setFlashdata('error', 'Invalid Credentials Entered.');
				return redirect()->back();
				exit();
			}
		} else {
			session()->setFlashdata('error', 'Invalid request sent.');
			return redirect()->to(base_url('auth/login'));
			exit();
		}
	}
	public function two_factor_authentication($param1 = '')
	{
		if ($param1 == '') {
			//if (session()->get('two_factor_auth_code')){
			$this->data['title'] = 'Two Factor Authentication - ' . $this->data['system_name'];
			$this->data['file_title'] = 'Two Factor Authentication';
			$this->data['file'] = 'two_factor_auth';
			return view('authentication/index', $this->data);
			/* } else {
				session()->setFlashdata('error', 'Invalid Credentials Entered.');
				return redirect()->to(base_url('auth/login'));
				exit();
			} */
		} elseif ($param1 == 'resend') {
			$key = $this->general->five_digit_key();

			$s = strtotime(date('Y-m-d H:i:s'));
			$mail_options = array(
				'name' 			=> session()->get('name_fa2'),
				'username' 		=> session()->get('user_name_fa2'),
				'system_name' 	=> $this->data['system_name'],
				'two_factor_code' 		=> $key,
				'action_time' 	=> date('H:i:s', $s),
				'action_date' 	=> date('Y-m-d', $s)
			);
			//$this->emails->send_type_email(session()->get('user_email_fa2'), 'two_factor_login', $mail_options);
			/* $mobile = $this->user_account->user_mobile(session()->get('user_name_fa2'));
			if ($mobile){
				$this->sms->send_type_sms($mobile, 'two_factor_login', $mail_options);
			} */
			session()->set('two_factor_auth_code', $key);
			session()->setFlashdata('success', 'Code sent again.');
			return redirect()->to(base_url('auth/two-factor-authentication'));
			exit();
		} elseif ($param1 == 'verify') {
			if (strtolower($this->request->getMethod()) == 'post') {
				$code = $this->request->getPost('two_factor_auth_code');
				if ($code == '') {
					session()->setFlashdata('error', 'Incorrect Code, Please try again.');
					return redirect()->back();
					exit();
				}


				$secretKey = session()->get('two_factor_auth_code');
				$googleAuthenticator = new \Dolondro\GoogleAuthenticator\GoogleAuthenticator();
				$filesystemAdapter = new \League\Flysystem\Adapter\Local(sys_get_temp_dir() . "/");
				$filesystem = new \League\Flysystem\Filesystem($filesystemAdapter);
				$pool = new \Cache\Adapter\Filesystem\FilesystemCachePool($filesystem);
				$googleAuthenticator->setCache($pool);
				if ($googleAuthenticator->authenticate($secretKey, $code)) {


					$login_date_time = date('Y-m-d H:i:s');
					$sessiondata = array(
						'isLogin'		=>	true,
						'user_name'		=>	session()->get('user_name_fa2'),
						'user_group'	=>	session()->get('user_group_fa2'),
						'user_id'		=>	session()->get('user_id_fa2'),

					);
					session()->set($sessiondata);

					session()->set('two_factor_auth_code', false);
					session()->set('name_fa2', false);
					session()->set('user_name_fa2', false);
					session()->set('user_group_fa2', false);
					session()->set('user_email_fa2', false);
					session()->set('user_id_fa2', false);
					$token = $this->exness->generate_auth();
					session()->set('exness_token', $token);

					session()->setFlashdata('success', 'Login Successful.');
					if (session()->get('user_group') == 'admin') {
						return redirect()->to(base_url('admin'));
					} elseif (session()->get('user_group') == 'member') {
						return redirect()->to(base_url('member'));
					}
					exit();
				} else {
					session()->setFlashdata('error', 'Incorrect Code, Please try again.');
					return redirect()->back();
					exit();
				}
			} else {
				session()->setFlashdata('error', 'Invalid request sent.');
				return redirect()->to(base_url('auth/login'));
				exit();
			}
		}
	}
	public function recover_password($param1 = '')
	{
		// if already login, redirect to dashboard of logged in user, whether admin or member
		if (session()->get('isLogin')) {
			return redirect()->to(base_url(session()->get('user_group') . '/dashboard'));
		}
		if (strtolower($this->request->getMethod()) == 'post') {
			if (empty($this->request->getPost('email'))) {
				session()->setFlashdata('error', 'Email addresss required.');
				return redirect()->back();
				exit();
			}
			$data = $this->general->get_tbl_field('users', '*', 'email', $this->request->getPost('email'));
			if (count($data) > 0) {
				$key = $this->general->random_string(15);

				$keyData['user_id'] = $data[0]['id'];
				$keyData['key_code'] = $key;
				$keyData['type'] = 'password_reset';
				$keyData['status'] = 0;
				$keyData['dated'] = date('Y-m-d H:i:s');
				$this->general->insert_data('user_profile_key', $keyData);
				$message = base_url('auth/new-password/') . '/' . $key;

				$email_add = $this->request->getPost('email');

				$s = strtotime(date('Y-m-d H:i:s'));
				$mail_options = array(
					'name' 			=> $data[0]['name'],
					'username' 		=> $data[0]['username'],
					'system_name' 	=> $this->data['system_name'],
					'password_reset_link' 	=> $message,
					'action_time' 	=> date('H:i:s', $s),
					'action_date' 	=> date('Y-m-d', $s)
				);
				$this->emails->send_type_email($email_add, 'password_reset', $mail_options);
			}
			session()->setFlashdata('success', 'If we find your email in our record, we wil send you a recovery email.');
			return redirect()->to(base_url('auth/login'));
			exit();
		} else {
			session()->setFlashdata('error', 'Invalid Request sent.');
			return redirect()->to(base_url('auth/forget-password'));

			exit();
		}
	}

public function logout()
{
    // Destroy session
    session()->set('isLogin', FALSE);
    session()->set('user_group', FALSE);
    session()->set('user_id', FALSE);
    session()->destroy();

    // Destroy the authentication cookie
    setcookie('auth_token1', '', time() - 3600, '/', '.jaguarforex.com', false, true);

    // Echo JavaScript to disable the iframe and redirect the parent window
    echo "<script>
        if (window.top !== window.self) {
            // If inside an iframe, remove it and redirect parent
            window.top.location.href = 'https://jaguarforex.com/wp-json/wp/v1/logout/';
        } else {
            // If not in an iframe, just redirect
            window.location.href = 'https://jaguarforex.com/wp-json/wp/v1/logout/';
        }
    </script>";

    exit();
}



	protected function logout_from_wordpress()
	{

		echo "<script>window.location.href = 'https://jaguarforex.com/wp-json/wp/v1/logout/';</script>";
		die();
	}

	public function wp_logout()
	{

		session()->set('isLogin', FALSE);
		session()->set('user_group', FALSE);
		session()->set('user_id', FALSE);
		session()->destroy();
		echo "<script>window.location.href = 'https://jaguarforex.com/logout_download';</script>";
	}
}
