<?php
/**
 * Clase para registro de errores - DoorDash Drive
 * Ubicación: includes/class-logger.php
 */

if (!defined('ABSPATH')) exit;

class DD_Logger {
    
    private static $log_file;

    public static function init() {
        self::$log_file = DD_PLUGIN_DIR . 'includes/debug.log';
    }

    public static function log($message, $data = null) {
        self::init();
        $timestamp = date("Y-m-d H:i:s");
        $log_entry = "[$timestamp] $message";

        if ($data) {
            $log_entry .= " | Data: " . json_encode($data);
        }

        $log_entry .= "\n";

        // Escribir al archivo (APPEND para no borrar lo anterior)
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND);
    }
}