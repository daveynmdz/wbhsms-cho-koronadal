<?php
/**
 * QR Code Generator Utility for Appointment Confirmations
 * 
 * Handles QR code generation for appointment bookings with proper
 * JSON payload format and file management
 * 
 * WBHSMS - City Health Office Queueing System
 * Created: October 2025
 */

class AppointmentQRGenerator {
    
    private $qr_directory;
    private $base_url;
    
    public function __construct() {
        // Set QR code storage directory - use absolute path from project root
        $project_root = dirname(__DIR__);
        $this->qr_directory = $project_root . '/assets/qr/appointments/';
        
        // Debug logging
        error_log("AppointmentQRGenerator: Project root: " . $project_root);
        error_log("AppointmentQRGenerator: QR directory: " . $this->qr_directory);
        
        // Ensure directory exists with proper permissions
        if (!is_dir($this->qr_directory)) {
            error_log("AppointmentQRGenerator: Creating QR directory: " . $this->qr_directory);
            if (!mkdir($this->qr_directory, 0755, true)) {
                throw new Exception('Failed to create QR code directory: ' . $this->qr_directory);
            }
        }
        
        // Check if directory is writable
        if (!is_writable($this->qr_directory)) {
            throw new Exception('QR code directory is not writable: ' . $this->qr_directory);
        }
        
        // Set base URL for QR code access
        $this->base_url = $this->getBaseUrl() . '/assets/qr/appointments/';
        error_log("AppointmentQRGenerator: Base URL: " . $this->base_url);
    }
    
    /**
     * Generate QR code for appointment with proper JSON payload
     * 
     * @param int $appointment_id Database appointment ID
     * @param int $patient_id Patient ID
     * @param int|null $referral_id Referral ID (optional)
     * @param int|null $facility_id Facility ID (optional)
     * @param string|null $facility_type Facility type (optional)
     * @return array Result with success status, file paths, and QR data
     */
    public function generateAppointmentQR($appointment_id, $patient_id, $referral_id = null, $facility_id = null, $facility_type = null) {
        try {
            error_log("QRGenerator: Starting generation for appointment_id=$appointment_id");
            
            // Include QR code library
            $phpqrcode_path = dirname(__DIR__) . '/includes/phpqrcode.php';
            error_log("QRGenerator: Including QR library from: " . $phpqrcode_path);
            require_once $phpqrcode_path;
            
            if (!class_exists('QRcode')) {
                throw new Exception('QRcode class not available after include');
            }
            error_log("QRGenerator: QRcode class confirmed available");
            
            // Format appointment ID with leading zeros
            $appointment_code = 'APT-' . str_pad($appointment_id, 8, '0', STR_PAD_LEFT);
            
            // Create JSON payload as specified
            $qr_data = [
                'appointment_id' => $appointment_code,
                'patient_id' => (string)$patient_id,
                'referral_id' => $referral_id ? (string)$referral_id : null,
                'facility_id' => $facility_id ? (string)$facility_id : null,
                'facility_type' => $facility_type
            ];
            
            // Convert to JSON string
            $qr_json = json_encode($qr_data, JSON_UNESCAPED_SLASHES);
            error_log("QRGenerator: QR JSON data: " . $qr_json);
            
            // Generate QR code filename
            $qr_filename = 'QR-' . $appointment_code . '.png';
            $qr_filepath = $this->qr_directory . $qr_filename;
            error_log("QRGenerator: Target file path: " . $qr_filepath);
            
            // Generate QR code image with appropriate settings for scanning
            // Using higher error correction (QR_ECLEVEL_M) and larger size for better scanning
            error_log("QRGenerator: Calling QRcode::png()");
            QRcode::png($qr_json, $qr_filepath, QR_ECLEVEL_M, 8, 2);
            error_log("QRGenerator: QRcode::png() completed");
            
            // Verify file was created successfully
            if (!file_exists($qr_filepath)) {
                throw new Exception('QR code file was not created successfully at: ' . $qr_filepath);
            }
            error_log("QRGenerator: QR file confirmed exists");
            
            // Get file size for verification
            $file_size = filesize($qr_filepath);
            if ($file_size === 0) {
                throw new Exception('QR code file is empty');
            }
            error_log("QRGenerator: QR file size: " . $file_size . " bytes");
            
            // Generate public URL for the QR code
            $qr_url = $this->base_url . $qr_filename;
            error_log("QRGenerator: QR public URL: " . $qr_url);
            
            return [
                'success' => true,
                'message' => 'QR code generated successfully',
                'qr_data' => $qr_data,
                'qr_json' => $qr_json,
                'qr_filename' => $qr_filename,
                'qr_filepath' => $qr_filepath,
                'qr_url' => $qr_url,
                'file_size' => $file_size
            ];
            
        } catch (Exception $e) {
            error_log("QRGenerator: Exception occurred: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate QR code: ' . $e->getMessage(),
                'qr_data' => null,
                'qr_json' => null,
                'qr_filename' => null,
                'qr_filepath' => null,
                'qr_url' => null,
                'file_size' => 0
            ];
        }
    }
    
    /**
     * Update appointment record with QR code BLOB data
     * 
     * @param mysqli $conn Database connection
     * @param int $appointment_id Appointment database ID
     * @param string $qr_filepath Full path to QR code file
     * @return bool Success status
     */
    public function updateAppointmentQRBlob($conn, $appointment_id, $qr_filepath) {
        try {
            // Read the QR code file as binary data
            if (!file_exists($qr_filepath)) {
                throw new Exception("QR file not found: " . $qr_filepath);
            }
            
            $qr_binary_data = file_get_contents($qr_filepath);
            if ($qr_binary_data === false) {
                throw new Exception("Failed to read QR file: " . $qr_filepath);
            }
            
            // Store binary data in database BLOB column
            // For BLOB data, we need to use a different approach
            $stmt = $conn->prepare("UPDATE appointments SET qr_code_path = ? WHERE appointment_id = ?");
            
            // Bind parameters - 'b' for BLOB, 'i' for integer
            $null = NULL;
            $stmt->bind_param("bi", $null, $appointment_id);
            
            // Send the actual BLOB data
            $stmt->send_long_data(0, $qr_binary_data);
            
            $success = $stmt->execute();
            
            if ($success) {
                error_log("AppointmentQRGenerator: BLOB data sent successfully, affected rows: " . $conn->affected_rows);
            } else {
                error_log("AppointmentQRGenerator: BLOB storage failed: " . $stmt->error);
            }
            
            $stmt->close();
            
            // Clean up the temporary file since we've stored it in database
            if ($success && file_exists($qr_filepath)) {
                unlink($qr_filepath);
                error_log("QRGenerator: Temporary QR file deleted after storing in database");
            }
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Failed to update appointment QR BLOB: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the base URL for the application
     * 
     * @return string Base URL
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get the path to the web root
        $script_path = $_SERVER['SCRIPT_NAME'] ?? '';
        $path_parts = explode('/', trim($script_path, '/'));
        
        // Find the project root (look for wbhsms-cho-koronadal)
        $project_root = '';
        foreach ($path_parts as $part) {
            if ($part === 'wbhsms-cho-koronadal') {
                $project_root = '/wbhsms-cho-koronadal';
                break;
            }
        }
        
        return $protocol . '://' . $host . $project_root;
    }
    
    /**
     * Clean up old QR code files (optional maintenance function)
     * 
     * @param int $days_old Delete files older than this many days
     * @return array Cleanup results
     */
    public function cleanupOldQRCodes($days_old = 30) {
        $deleted_count = 0;
        $error_count = 0;
        $cutoff_time = time() - ($days_old * 24 * 60 * 60);
        
        try {
            $files = glob($this->qr_directory . 'QR-APT-*.png');
            
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoff_time) {
                    if (unlink($file)) {
                        $deleted_count++;
                    } else {
                        $error_count++;
                    }
                }
            }
            
            return [
                'success' => true,
                'message' => "Cleanup completed: {$deleted_count} files deleted, {$error_count} errors",
                'deleted_count' => $deleted_count,
                'error_count' => $error_count
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage(),
                'deleted_count' => $deleted_count,
                'error_count' => $error_count
            ];
        }
    }
    
    /**
     * Test QR code generation functionality
     * 
     * @return array Test results
     */
    public function test() {
        try {
            // Test data
            $test_appointment_id = 999999;
            $test_patient_id = 12345;
            $test_referral_id = 67890;
            
            // Generate test QR code
            $result = $this->generateAppointmentQR($test_appointment_id, $test_patient_id, $test_referral_id);
            
            if ($result['success']) {
                // Clean up test file
                if (file_exists($result['qr_filepath'])) {
                    unlink($result['qr_filepath']);
                }
                
                return [
                    'success' => true,
                    'message' => 'QR code generation test passed successfully',
                    'test_data' => $result['qr_data'],
                    'test_json' => $result['qr_json']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'QR code generation test failed: ' . $result['message']
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'QR code test exception: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify QR code can be scanned and parsed correctly
     * 
     * @param string $qr_filepath Path to QR code file
     * @return array Verification results
     */
    public function verifyQRCode($qr_filepath) {
        try {
            // For verification, we'll just check file properties
            // Full QR scanning would require additional libraries
            
            if (!file_exists($qr_filepath)) {
                throw new Exception('QR code file not found');
            }
            
            $file_size = filesize($qr_filepath);
            if ($file_size === 0) {
                throw new Exception('QR code file is empty');
            }
            
            // Check if it's a valid PNG image
            $image_info = getimagesize($qr_filepath);
            if ($image_info === false || $image_info[2] !== IMAGETYPE_PNG) {
                throw new Exception('QR code file is not a valid PNG image');
            }
            
            return [
                'success' => true,
                'message' => 'QR code file verification passed',
                'file_size' => $file_size,
                'image_width' => $image_info[0],
                'image_height' => $image_info[1],
                'mime_type' => $image_info['mime']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'QR code verification failed: ' . $e->getMessage()
            ];
        }
    }
}
?>