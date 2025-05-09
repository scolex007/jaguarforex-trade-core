<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use Firebase\JWT\ExpiredException;

class JwtFilter implements FilterInterface
{
    use ResponseTrait;
    
    protected $jwtSecretKey = 'dsdasdsadsadsdsadsasdsdsadsss'; // Use the same key from Auth.php
    
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');
        
        // Check if Authorization header is present
        if (!$header) {
            return $this->failUnauthorized('Authorization header not found');
        }
        
        // Extract the token
        $token = null;
        if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            $token = $matches[1];
        }
        
        if (!$token) {
            return $this->failUnauthorized('Token not found in request');
        }
        
        try {
            // Decode the token
            $decoded = JWT::decode($token, new Key($this->jwtSecretKey, 'HS256'));
            
            // Check if token is expired
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return $this->failUnauthorized('Token has expired');
            }
            
            // Add user data to request for controller access
            $request->uid = $decoded->uid;
            
            return $request;
        } catch (ExpiredException $e) {
            return $this->failUnauthorized('Token has expired');
        } catch (Exception $e) {
            return $this->failUnauthorized('Invalid token: ' . $e->getMessage());
        }
    }
    
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No actions needed after request
        return $response;
    }
    
    protected function failUnauthorized($message = 'Unauthorized')
    {
        $response = service('response');
        return $response->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
            ->setJSON(['success' => false, 'message' => $message])
            ->setContentType('application/json');
    }
}