<?php
/**
 * Cipher Class for credentials - DoorDash Drive
 * Location: includes/class-cipher.php
 */

if (!defined('ABSPATH')) exit;

class DD_Cipher {
    
    private static $method = 'aes-256-cbc';

    /**
     * Obtiene la llave de 32 caracteres
     */
    private static function get_key() {
        $key = defined('DD_ENCRYPTION_KEY') ? DD_ENCRYPTION_KEY : hash('sha256', get_bloginfo('name'));
        return substr(hash('sha256', $key), 0, 32);
    }

    /**
     * Encrypt plain text
     */
    public static function encrypt($data) {
        if (empty($data)) return '';

        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::$method);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt($data, self::$method, $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt encrypted text
     */
    public static function decrypt($data) {
        if (empty($data)) return '';

        $data = base64_decode($data);
        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::$method);

        $iv = substr($data, 0, $iv_length);
        $encrypted_text = substr($data, $iv_length);

        return openssl_decrypt($encrypted_text, self::$method, $key, 0, $iv);
    }
}