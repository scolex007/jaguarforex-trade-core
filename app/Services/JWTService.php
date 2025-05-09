<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JWTService
{
    private $secretKey = 'dsdasdsadsadsdsadsasdsdsadsss'; // Use the same key from Auth.php
    private $algorithm = 'HS256';
    
    /**
     * Generate JWT token
     * 
     * @param int $userId User ID to include in token
     * @param int $expiration Token expiration time in seconds (default 24 hours)
     * @return string JWT token
     */
    public function generateToken($userId, $expiration = 86400)
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $expiration;
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'uid' => $userId
        ];
        
        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }
    
    /**
     * Verify JWT token
     * 
     * @param string $token JWT token to verify
     * @return object|false Decoded token payload or false on failure
     */
    public function verifyToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            
            // Check if token is expired
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return false;
            }
            
            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get user ID from token
     * 
     * @param string $token JWT token
     * @return int|false User ID or false on failure
     */
    public function getUserIdFromToken($token)
    {
        $decoded = $this->verifyToken($token);
        
        if ($decoded && isset($decoded->uid)) {
            return $decoded->uid;
        }
        
        return false;
    }
}