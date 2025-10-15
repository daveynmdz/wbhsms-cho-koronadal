<?php
/**
 * Patient Flow Validator
 * Handles PhilHealth validation and routing logic for healthcare queueing system
 * 
 * @author CHO Koronadal Development Team
 * @version 1.0
 * @date October 15, 2025
 */

class PatientFlowValidator {
    
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check if patient is PhilHealth member
     * @param int $patient_id
     * @return bool
     */
    public function isPhilHealthMember($patient_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT is_philhealth FROM patients WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? (bool)$result['is_philhealth'] : false;
        } catch (Exception $e) {
            error_log("PatientFlowValidator::isPhilHealthMember Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if service is primary care (eligible for PhilHealth coverage)
     * @param string $service_id
     * @return bool
     */
    public function isPrimaryCareService($service_id) {
        $primary_care_services = ['1', '2', '3', '4', '6', '7'];
        return in_array($service_id, $primary_care_services);
    }
    
    /**
     * Check if service is lab-only
     * @param string $service_id
     * @return bool
     */
    public function isLabOnlyService($service_id) {
        return $service_id === '8';
    }
    
    /**
     * Check if service is medical document request
     * @param string $service_id
     * @return bool
     */
    public function isDocumentRequestService($service_id) {
        return $service_id === '9';
    }
    
    /**
     * Determine if patient should skip billing for a given service
     * @param int $patient_id
     * @param string $service_id
     * @return bool
     */
    public function shouldSkipBilling($patient_id, $service_id) {
        // Document requests and lab-only services always require billing for non-PhilHealth
        if ($this->isDocumentRequestService($service_id)) {
            return false; // All document requests are billable
        }
        
        // PhilHealth members skip billing for primary care services
        if ($this->isPrimaryCareService($service_id) && $this->isPhilHealthMember($patient_id)) {
            return true;
        }
        
        // Lab-only services: PhilHealth members skip billing
        if ($this->isLabOnlyService($service_id) && $this->isPhilHealthMember($patient_id)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if patient needs double consultation (non-PhilHealth primary care)
     * @param int $patient_id
     * @param string $service_id
     * @return bool
     */
    public function needsDoubleConsultation($patient_id, $service_id) {
        return $this->isPrimaryCareService($service_id) && !$this->isPhilHealthMember($patient_id);
    }
    
    /**
     * Check if lab station can requeue to consultation (time-based rule)
     * @return array ['allowed' => bool, 'message' => string]
     */
    public function canLabRequeueToConsultation() {
        $current_time = date('H:i:s');
        $cutoff_time = '16:00:00'; // 4:00 PM
        
        if ($current_time < $cutoff_time) {
            return [
                'allowed' => true,
                'message' => 'Requeue to consultation allowed (before 4:00 PM)'
            ];
        } else {
            return [
                'allowed' => false,
                'message' => 'Requeue not allowed after 4:00 PM. Please issue referral for next day.'
            ];
        }
    }
    
    /**
     * Check if billing can route to consultation (service restrictions)
     * @param string $service_id
     * @return array ['allowed' => bool, 'message' => string]
     */
    public function canBillingRouteToConsultation($service_id) {
        if ($this->isLabOnlyService($service_id)) {
            return [
                'allowed' => false,
                'message' => 'Lab-only service cannot return to consultation'
            ];
        }
        
        if ($this->isDocumentRequestService($service_id)) {
            return [
                'allowed' => false,
                'message' => 'Document request service cannot route to consultation'
            ];
        }
        
        return [
            'allowed' => true,
            'message' => 'Routing to consultation allowed'
        ];
    }
    
    /**
     * Get patient's current visit information including service details
     * @param int $queue_entry_id
     * @return array|false
     */
    public function getPatientVisitInfo($queue_entry_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    qe.*,
                    v.visit_id,
                    v.service_id,
                    p.patient_id,
                    p.is_philhealth,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    si.service_name,
                    s.station_name,
                    s.station_type
                FROM queue_entries qe
                JOIN visits v ON qe.visit_id = v.visit_id
                JOIN patients p ON v.patient_id = p.patient_id
                LEFT JOIN service_items si ON v.service_id = si.service_id
                LEFT JOIN stations s ON qe.station_id = s.station_id
                WHERE qe.queue_entry_id = ?
            ");
            $stmt->execute([$queue_entry_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("PatientFlowValidator::getPatientVisitInfo Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log routing action for audit trail
     * @param int $queue_entry_id
     * @param string $action
     * @param string $from_station
     * @param string $to_station
     * @param int $employee_id
     * @param string $reason
     * @return bool
     */
    public function logRoutingAction($queue_entry_id, $action, $from_station, $to_station, $employee_id, $reason = '') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO queue_logs 
                (queue_entry_id, action, previous_status, new_status, performed_by, reason, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $log_message = "Routed from {$from_station} to {$to_station}. {$reason}";
            
            return $stmt->execute([
                $queue_entry_id,
                $action,
                $from_station,
                $to_station,
                $employee_id,
                $log_message
            ]);
        } catch (Exception $e) {
            error_log("PatientFlowValidator::logRoutingAction Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate routing decision and provide recommendation
     * @param int $queue_entry_id
     * @param string $target_station
     * @param int $employee_id
     * @return array ['valid' => bool, 'message' => string, 'recommendation' => string]
     */
    public function validateRouting($queue_entry_id, $target_station, $employee_id) {
        $visit_info = $this->getPatientVisitInfo($queue_entry_id);
        
        if (!$visit_info) {
            return [
                'valid' => false,
                'message' => 'Unable to retrieve patient visit information',
                'recommendation' => 'Please verify queue entry exists and try again'
            ];
        }
        
        $service_id = $visit_info['service_id'];
        $patient_id = $visit_info['patient_id'];
        $current_station = $visit_info['station_type'];
        $is_philhealth = (bool)$visit_info['is_philhealth'];
        
        // Validate specific routing scenarios
        switch ($target_station) {
            case 'billing':
                if ($this->shouldSkipBilling($patient_id, $service_id)) {
                    return [
                        'valid' => false,
                        'message' => 'PhilHealth member does not require billing for this service',
                        'recommendation' => 'Route directly to lab, pharmacy, or document station'
                    ];
                }
                break;
                
            case 'consultation':
                if ($current_station === 'billing') {
                    $billing_check = $this->canBillingRouteToConsultation($service_id);
                    if (!$billing_check['allowed']) {
                        return [
                            'valid' => false,
                            'message' => $billing_check['message'],
                            'recommendation' => 'Route to appropriate final station (lab/document)'
                        ];
                    }
                } elseif ($current_station === 'lab') {
                    $lab_check = $this->canLabRequeueToConsultation();
                    if (!$lab_check['allowed']) {
                        return [
                            'valid' => false,
                            'message' => $lab_check['message'],
                            'recommendation' => 'Complete visit or issue referral for next day'
                        ];
                    }
                }
                break;
                
            case 'lab':
            case 'pharmacy':
            case 'document':
                // These are generally valid endpoints
                break;
                
            default:
                return [
                    'valid' => false,
                    'message' => 'Invalid target station specified',
                    'recommendation' => 'Select a valid station: billing, consultation, lab, pharmacy, or document'
                ];
        }
        
        return [
            'valid' => true,
            'message' => 'Routing validation successful',
            'recommendation' => "Patient can be routed to {$target_station}"
        ];
    }
}