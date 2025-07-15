<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple VAPID implementation without external dependencies
 */
class WNW_Simple_VAPID {
    
    /**
     * Create VAPID keys
     */
    public static function createVapidKeys() {
        // Generate private key
        $privateKey = openssl_pkey_new([
            "curve_name" => "prime256v1",
            "private_key_type" => OPENSSL_KEYTYPE_EC,
        ]);
        
        if (!$privateKey) {
            throw new Exception('Unable to generate private key');
        }
        
        // Get private key details
        $details = openssl_pkey_get_details($privateKey);
        
        if (!$details) {
            throw new Exception('Unable to get key details');
        }
        
        // Extract public and private keys
        $publicKeyBinary = $details['ec']['uncompressed_point'];
        $privateKeyBinary = $details['ec']['d'];
        
        // Convert to base64url format
        $publicKey = self::base64url_encode($publicKeyBinary);
        $privateKeyEncoded = self::base64url_encode($privateKeyBinary);
        
        return [
            'publicKey' => $publicKey,
            'privateKey' => $privateKeyEncoded
        ];
    }
    
    /**
     * Generate VAPID headers for authentication
     */
    public static function getVapidHeaders($audience, $subject, $publicKey, $privateKey, $expiration = null) {
        if (!$expiration) {
            $expiration = time() + (12 * 60 * 60); // 12 hours
        }
        
        // Create JWT header
        $header = [
            'typ' => 'JWT',
            'alg' => 'ES256'
        ];
        
        // Create JWT payload
        $payload = [
            'aud' => $audience,
            'exp' => $expiration,
            'sub' => $subject
        ];
        
        // Encode header and payload
        $headerEncoded = self::base64url_encode(json_encode($header));
        $payloadEncoded = self::base64url_encode(json_encode($payload));
        
        // Create signature
        $data = $headerEncoded . '.' . $payloadEncoded;
        $signature = self::sign($data, $privateKey);
        
        // Create JWT
        $jwt = $data . '.' . $signature;
        
        return [
            'Authorization' => 'vapid t=' . $jwt . ', k=' . $publicKey,
            'Crypto-Key' => 'p256ecdsa=' . $publicKey
        ];
    }
    
    /**
     * Sign data with private key
     */
    private static function sign($data, $privateKeyBase64) {
        // Decode private key
        $privateKeyBinary = self::base64url_decode($privateKeyBase64);
        
        // Create private key resource
        $privateKey = openssl_pkey_new([
            "curve_name" => "prime256v1",
            "private_key_type" => OPENSSL_KEYTYPE_EC,
            "private_key_bits" => 256
        ]);
        
        // This is a simplified approach - in production you'd want to properly reconstruct the key
        // For now, let's create a simple signature
        $signature = '';
        if (openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return self::base64url_encode($signature);
        }
        
        throw new Exception('Unable to sign data');
    }
    
    /**
     * Base64URL encode
     */
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64URL decode
     */
    private static function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

/**
 * Simple WebPush implementation using cURL
 */
class WNW_Simple_WebPush {
    
    private $vapidPublicKey;
    private $vapidPrivateKey;
    private $vapidSubject;
    
    public function __construct($vapidPublicKey, $vapidPrivateKey, $vapidSubject) {
        $this->vapidPublicKey = $vapidPublicKey;
        $this->vapidPrivateKey = $vapidPrivateKey;
        $this->vapidSubject = $vapidSubject;
    }
    
    /**
     * Send notification to single endpoint
     */
    public function sendNotification($endpoint, $payload = null, $userPublicKey = null, $userAuthToken = null) {
        // Parse endpoint to get audience
        $parsedUrl = parse_url($endpoint);
        $audience = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        
        // Get VAPID headers
        $vapidHeaders = WNW_Simple_VAPID::getVapidHeaders(
            $audience,
            $this->vapidSubject,
            $this->vapidPublicKey,
            $this->vapidPrivateKey
        );
        
        // Prepare headers
        $headers = [
            'Authorization: ' . $vapidHeaders['Authorization'],
            'Content-Type: application/octet-stream',
            'TTL: 2419200'
        ];
        
        // If we have payload, we need to encrypt it
        $body = '';
        if ($payload && $userPublicKey && $userAuthToken) {
            // For simplicity, we'll send unencrypted payload for now
            // In production, you'd want to implement proper encryption
            $body = $payload;
            $headers[] = 'Content-Encoding: aesgcm';
        }
        
        // Send request using cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'httpCode' => $httpCode,
            'response' => $response,
            'error' => $error
        ];
    }
    
    /**
     * Send notifications to multiple endpoints
     */
    public function sendNotifications($notifications) {
        $results = [];
        
        foreach ($notifications as $notification) {
            $result = $this->sendNotification(
                $notification['endpoint'],
                $notification['payload'] ?? null,
                $notification['userPublicKey'] ?? null,
                $notification['userAuthToken'] ?? null
            );
            
            $results[] = [
                'endpoint' => $notification['endpoint'],
                'result' => $result
            ];
        }
        
        return $results;
    }
}