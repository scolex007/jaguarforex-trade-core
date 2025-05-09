<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Services\JWTService;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class Auth extends BaseController
{
	protected $jwtService;

	public function __construct()
	{
		$this->jwtService = new JWTService();
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

		if (isset($decoded_response['error'])) {
			error_log('API error in sendUserDataToWordPress: ' . $decoded_response['message']);
		}

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
			// All the existing validation and registration logic...
			// The code is preserved as it was except for WordPress integration changes noted below

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
			
			// ... rest of existing registration logic ...
			
			// The rest of the function remains the same
			// Just leaving this unmodified
			
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

			if ($this->user_account->mobile_exists($this->request->getPost('mobile')) == true) {
				die(json_encode(array(
					'status' => 'error',
					'message' => 'Mobile Number already registered',
				)));
			}

			list($country_name, $country_code) = explode('-', $this->request->getPost('country'));

			$data['name'] = $this->request->getPost('name');
			$data['last_name'] = $this->request->getPost('last_name');
			$data['username'] = $this->request->getPost('username');
			$data['email'] = $this->request->getPost('email');
			$data['country'] = $country_name;
			$data['mobile'] = $this->request->getPost('mobile');
			$data['password'] = md5($this->request->getPost('password'));
			$data['user_group'] = 'member';
			if ($this->general->get_system_var('email_verification_required') == '1') {
				$data['status'] = '0';
			} else {
				$data['status'] = '1';
			}
			$data['created'] = date('Y-m-d H:i:s');
			$save = $this->general->insert_data('users', $data);
			$email_add = $this->request->getPost('email');
			
			if ($save) {
				$user_id = $this->db->insertID();
				
				// Optional: WordPress integration if needed
				// If you want to keep WordPress integration, uncomment this section
				/*
				list($country_name, $country_code) = explode('-', $this->request->getPost('country'));
				$signup_array1 = [
					'nickname' => $this->request->getPost('username'),
					'email'    => $this->request->getPost('email'),
					'password' => $this->request->getPost('password'),
					'sponsor'  => $this->request->getPost('sponsor'),
					'country'  => $country_name,
					'mobile'   => $this->request->getPost('mobile'),
					'u_id'   => $user_id
				];

				$wordpressApiResponse = $this->sendUserDataToWordPress($signup_array1);

				if (isset($wordpressApiResponse['user_id'])) {
					$wp_id =  $wordpressApiResponse['user_id'];
					$up_user['wp_id'] = $wp_id;
					$this->general->update_data('users', 'id', $user_id, $up_user);
				}
				*/

				$this->general->insert_data('binary_2x', $binary);

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
					
					// Return API-friendly response for React frontend
					$apiResponse = [
						'success' => true,
						'message' => 'Account created successfully, Activation Link can be found in Email.',
						'verification_required' => true,
						'redirect' => 'https://jaguarforex.com/afterregistration'
					];
					
					// Support both API and regular web responses
					if ($this->request->getHeaderLine('Accept') === 'application/json') {
						return $this->response->setJSON($apiResponse);
					} else {
						die(json_encode($apiResponse));
					}
				} else {
					$mail_options = array(
						'name' 			=> $this->request->getPost('name'),
						'username' 		=> $this->request->getPost('username'),
						'system_name' 	=> $this->data['system_name'],
						'action_time' 	=> date('H:i:s'),
						'action_date' 	=> date('Y-m-d')
					);
					$this->emails->send_type_email($email_add, 'welcome_email', $mail_options);
					
					// Return API-friendly response for React frontend
					$apiResponse = [
						'success' => true,
						'message' => 'Account created successfully.',
						'verification_required' => false,
						'redirect' => 'https://jaguarforex.com/afterregistration'
					];
					
					// Support both API and regular web responses
					if ($this->request->getHeaderLine('Accept') === 'application/json') {
						return $this->response->setJSON($apiResponse);
					} else {
						die(json_encode($apiResponse));
					}
				}
			} else {
				$apiResponse = [
					'success' => false,
					'message' => 'An error occurred, please try again.',
				];
				
				if ($this->request->getHeaderLine('Accept') === 'application/json') {
					return $this->response->setJSON($apiResponse);
				} else {
					die(json_encode($apiResponse));
				}
			}
		} else {
			session()->setFlashdata('error', 'Invalid request sent.');
			return redirect()->to(base_url('auth/register'));
			exit();
		}
	}
	
	// All other existing methods remain the same
	// ...
	
	function generateJWT($user)
	{
		// Use the JWTService instead of local implementation
		return $this->jwtService->generateToken($user, 36000); // 10 hour token
	}
	
	// Modified login method to support both web and API requests
	public function do_login($role = "")
	{
		if (strtolower($this->request->getMethod()) == 'post') {
			// if already login, redirect to dashboard of logged in user, whether admin or member
			if (session()->get('isLogin')) {
				if ($this->request->getHeaderLine('Accept') === 'application/json') {
					return $this->response->setJSON([
						'success' => true,
						'message' => 'Already logged in',
						'redirect' => base_url(session()->get('user_group') . '/dashboard')
					]);
				} else {
					echo "<script>window.location.href = 'https://jaguarforex.com/wp-json/wp/v1/logout/';</script>";
				}
				return;
			}

			// Honeypot check
			$honeypot = $this->request->getPost('user_url');
			if (!empty($honeypot)) {
				log_message('error', 'Honeypot triggered during login by IP: ' . $this->request->getIPAddress());
				
				if ($this->request->getHeaderLine('Accept') === 'application/json') {
					return $this->response->setJSON([
						'success' => false,
						'message' => 'Invalid request'
					]);
				} else {
					session()->setFlashdata('error', 'Invalid request.');
					return redirect()->back();
				}
			}

			// Optional: Keep CAPTCHA validation for web interface only
			if ($this->request->getHeaderLine('Accept') !== 'application/json') {
				$num_1 = $this->request->getPost('num_1');
				$num_2 = $this->request->getPost('num_2');
				$num_sum = $num_1 + $num_2;
				$sum = $this->request->getPost('sum');
				if (empty($sum)) {
					session()->setFlashdata('error', 'Please fill captcha.');
					return redirect()->back();
				}
				if ($sum != $num_sum) {
					session()->setFlashdata('error', 'Invalid Captcha Code.');
					return redirect()->back();
				}
			}
			
			// Verify login credentials
			$login = $this->user_account->verify_login($this->request->getPost('email'), $this->request->getPost('password'));
			if ($login == true) {
				$data = $this->user_account->get_user_data($this->request->getPost('email'));
				if (count($data) > 0) {
					// Email verification check
					if ($this->general->get_system_var('email_verification_required') == '1') {
						if ($data[0]['status'] == 0) {
							$errorMessage = 'Please verify your email address to continue.';
							if ($this->request->getHeaderLine('Accept') === 'application/json') {
								return $this->response->setJSON([
									'success' => false,
									'message' => $errorMessage
								]);
							} else {
								session()->setFlashdata('error', $errorMessage);
								return redirect()->back();
							}
						}
					}
					
					// Account status check
					if ($data[0]['status'] == 2) {
						$errorMessage = 'Account Suspended, Please Contact department for further details.';
						if ($this->request->getHeaderLine('Accept') === 'application/json') {
							return $this->response->setJSON([
								'success' => false,
								'message' => $errorMessage
							]);
						} else {
							session()->setFlashdata('error', $errorMessage);
							return redirect()->back();
						}
					}

					// Set session data
					$sessiondata = array(
						'isLogin'		=>	true,
						'user_name'		=>	$data[0]['username'],
						'user_group'	=>	$data[0]['user_group'],
						'user_id'		=>	$data[0]['id'],
					);

					// Handle 2FA if needed
					if ($data[0]['user_group'] == 'member') {
						$token = $this->exness->generate_auth();
						session()->set('exness_token', $token);
						
						if ($this->general->get_system_var('2fa_auth_status') == '0' || 
							$data[0]['g_auth_required'] == '0' || $data[0]['g_auth'] == '0') {
							
							// No 2FA required
							session()->set($sessiondata);
							$jwt = $this->generateJWT($data[0]['id']);
							
							// For API requests, return JWT
							if ($this->request->getHeaderLine('Accept') === 'application/json') {
								return $this->response->setJSON([
									'success' => true,
									'message' => 'Login Successful',
									'token' => $jwt,
									'user' => [
										'id' => $data[0]['id'],
										'username' => $data[0]['username'],
										'name' => $data[0]['name'],
										'email' => $data[0]['email'],
										'user_group' => $data[0]['user_group'],
									]
								]);
							} else {
								// For web requests, set cookie and redirect
								setcookie('auth_token1', $jwt, time() + 36000, "/", ".jaguarforex.com", false, true);
								session()->setFlashdata('success', 'Login Successful.');
								echo "<script>window.location.href = 'https://jaguarforex.com/dashboard/';</script>";
							}
						} else {
							// 2FA required
							$teo_factor_sess_data = array(
								'name_fa2'			=>	$data[0]['name'],
								'user_name_fa2'		=>	$data[0]['username'],
								'user_group_fa2'	=>	$data[0]['user_group'],
								'user_id_fa2'		=>	$data[0]['id'],
								'user_email_fa2'	=>	$data[0]['email'],
								'two_factor_auth_code'	=>	$data[0]['g_auth_key'],
							);
							session()->set($teo_factor_sess_data);
							
							if ($this->request->getHeaderLine('Accept') === 'application/json') {
								return $this->response->setJSON([
									'success' => true,
									'message' => '2FA Required',
									'requires_2fa' => true,
									'redirect' => base_url('auth/two-factor-authentication')
								]);
							} else {
								session()->setFlashdata('warning', 'Please enter Google Authenticator verification code.');
								return redirect()->to(base_url('auth/two-factor-authentication'));
							}
						}
					} else {
						// Admin login
						$token = $this->exness->generate_auth();
						session()->set('exness_token', $token);
						session()->set($sessiondata);
						
						if ($this->request->getHeaderLine('Accept') === 'application/json') {
							$jwt = $this->generateJWT($data[0]['id']);
							return $this->response->setJSON([
								'success' => true,
								'message' => 'Login Successful',
								'token' => $jwt,
								'user' => [
									'id' => $data[0]['id'],
									'username' => $data[0]['username'],
									'name' => $data[0]['name'],
									'email' => $data[0]['email'],
									'user_group' => $data[0]['user_group'],
								]
							]);
						} else {
							session()->setFlashdata('success', 'Login Successful.');
							return redirect()->to('https://jaguarforex.com/dashboard/');
						}
					}
				}
			}
			
			// Invalid login
			$errorMessage = 'Invalid Credentials Entered.';
			if ($this->request->getHeaderLine('Accept') === 'application/json') {
				return $this->response->setJSON([
					'success' => false,
					'message' => $errorMessage
				]);
			} else {
				session()->setFlashdata('error', $errorMessage);
				return redirect()->back();
			}
		} else {
			$errorMessage = 'Invalid request sent.';
			if ($this->request->getHeaderLine('Accept') === 'application/json') {
				return $this->response->setJSON([
					'success' => false,
					'message' => $errorMessage
				]);
			} else {
				session()->setFlashdata('error', $errorMessage);
				return redirect()->to(base_url('auth/login'));
			}
		}
	}
	
	public function logout()
	{
		// API-style logout
		if ($this->request->getHeaderLine('Accept') === 'application/json') {
			session()->destroy();
			return $this->response->setJSON([
				'success' => true,
				'message' => 'Logged out successfully'
			]);
		}
		
		// Web-style logout
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
	
	// Other methods remain unchanged...
}