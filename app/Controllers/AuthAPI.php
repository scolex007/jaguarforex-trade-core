<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AuthAPI extends ResourceController
{
    protected $format = 'json';
    protected $jwtSecretKey = 'dsdasdsadsadsdsadsasdsdsadsss'; // Use the same key from Auth.php
    
    /**
     * Login API endpoint that validates credentials and returns JWT token
     */
    public function login()
    {
        $rules = [
            'email' => 'required|valid_email',
            'password' => 'required',
        ];
        
        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }
        
        $email = $this->request->getVar('email');
        $password = $this->request->getVar('password');
        
        // Use the existing login verification logic from your Auth class
        $login = $this->user_account->verify_login($email, $password);
        
        if ($login == true) {
            $data = $this->user_account->get_user_data($email);
            
            if (count($data) > 0) {
                // Check if account is active
                if ($data[0]['status'] == 2) {
                    return $this->fail('Account Suspended. Please contact support for more information.');
                }
                
                if ($this->general->get_system_var('email_verification_required') == '1') {
                    if ($data[0]['status'] == 0) {
                        return $this->fail('Please verify your email address to continue.');
                    }
                }
                
                // Generate JWT token
                $token = $this->generateJWT($data[0]['id']);
                
                // Prepare user data to return
                $userData = [
                    'id' => $data[0]['id'],
                    'username' => $data[0]['username'],
                    'name' => $data[0]['name'],
                    'email' => $data[0]['email'],
                    'user_group' => $data[0]['user_group'],
                ];
                
                return $this->respond([
                    'success' => true,
                    'token' => $token,
                    'user' => $userData
                ]);
            }
        }
        
        return $this->failUnauthorized('Invalid email or password.');
    }
    
    /**
     * Registration API endpoint
     */
    public function register()
    {
        $rules = [
            'name' => 'required|min_length[4]',
            'last_name' => 'required|min_length[2]',
            'username' => 'required|min_length[4]|max_length[12]|alpha_numeric',
            'email' => 'required|valid_email',
            'password' => 'required|min_length[8]',
            'repassword' => 'required|matches[password]',
            'country' => 'required',
            'mobile' => 'required',
            'tos' => 'required',
        ];
        
        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }
        
        // Check if username already exists
        $username_exists = $this->user_account->username_exists($this->request->getVar('username'));
        if ($username_exists) {
            return $this->fail('Username already registered');
        }
        
        // Check if email already exists
        if ($this->user_account->email_exists($this->request->getVar('email'))) {
            return $this->fail('Email already exists');
        }
        
        // Check if mobile already exists
        if ($this->user_account->mobile_exists($this->request->getVar('mobile'))) {
            return $this->fail('Mobile Number already registered');
        }
        
        // Process sponsor information
        $binary = [
            'username' => $this->request->getVar('username')
        ];
        
        if (!empty($this->request->getVar('sponsor'))) {
            // Check if sponsor exists
            $sponsor_exists = $this->user_account->username_exists($this->request->getVar('sponsor'));
            if (!$sponsor_exists) {
                return $this->fail('Sponsor not found');
            }
            
            // Check if sponsor is activated
            $activation_status = $this->user_account->user_actication_status($this->request->getVar('sponsor'));
            if (!$activation_status) {
                return $this->fail('Sponsor not activated yet');
            }
            
            $binary['direct_referral'] = $this->request->getVar('sponsor');
        }
        
        // Create user data
        $data = [
            'name' => $this->request->getVar('name'),
            'last_name' => $this->request->getVar('last_name'),
            'username' => $this->request->getVar('username'),
            'email' => $this->request->getVar('email'),
            'country' => $this->request->getVar('country'),
            'mobile' => $this->request->getVar('mobile'),
            'password' => md5($this->request->getVar('password')),
            'user_group' => 'member',
            'status' => $this->general->get_system_var('email_verification_required') == '1' ? '0' : '1',
            'created' => date('Y-m-d H:i:s')
        ];
        
        // Insert user into database
        $save = $this->general->insert_data('users', $data);
        
        if ($save) {
            $user_id = $this->db->insertID();
            
            // Save binary data
            $this->general->insert_data('binary_2x', $binary);
            
            // Handle email verification if required
            if ($this->general->get_system_var('email_verification_required') == '1') {
                $key = $this->general->random_string(15);
                
                $keyData = [
                    'user_id' => $user_id,
                    'key_code' => $key,
                    'type' => 'email_verification',
                    'status' => 0,
                    'dated' => date('Y-m-d H:i:s')
                ];
                
                $this->general->insert_data('user_profile_key', $keyData);
                
                $verification_link = base_url('auth/verify-email/') . '/' . $key;
                
                // Send verification email (using existing email service)
                $mail_options = [
                    'name' => $this->request->getVar('name'),
                    'username' => $this->request->getVar('username'),
                    'system_name' => $this->data['system_name'],
                    'email_activation_link' => $verification_link,
                    'action_time' => date('H:i:s'),
                    'action_date' => date('Y-m-d')
                ];
                
                $this->emails->send_type_email($this->request->getVar('email'), 'email_confirmation', $mail_options);
                
                return $this->respond([
                    'success' => true,
                    'message' => 'Account created successfully. Please check your email for verification link.',
                    'verification_required' => true
                ]);
            } else {
                // Send welcome email
                $mail_options = [
                    'name' => $this->request->getVar('name'),
                    'username' => $this->request->getVar('username'),
                    'system_name' => $this->data['system_name'],
                    'action_time' => date('H:i:s'),
                    'action_date' => date('Y-m-d')
                ];
                
                $this->emails->send_type_email($this->request->getVar('email'), 'welcome_email', $mail_options);
                
                return $this->respond([
                    'success' => true,
                    'message' => 'Account created successfully.',
                    'verification_required' => false
                ]);
            }
        }
        
        return $this->fail('An error occurred during registration. Please try again.');
    }
    
    /**
     * Verify token API endpoint
     */
    public function verify()
    {
        // The JWT Filter has already verified the token
        // Just return the user data
        
        $userId = $this->request->uid;
        $userData = $this->user_account->get_user_data($userId);
        
        if (!$userData || empty($userData)) {
            return $this->failNotFound('User not found');
        }
        
        return $this->respond([
            'success' => true,
            'user' => [
                'id' => $userData[0]['id'],
                'username' => $userData[0]['username'],
                'name' => $userData[0]['name'],
                'email' => $userData[0]['email'],
                'user_group' => $userData[0]['user_group'],
            ]
        ]);
    }
    
    /**
     * Generate JWT token
     */
    protected function generateJWT($userId)
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + 86400; // 24 hours valid token
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'uid' => $userId
        ];
        
        return JWT::encode($payload, $this->jwtSecretKey, 'HS256');
    }
}