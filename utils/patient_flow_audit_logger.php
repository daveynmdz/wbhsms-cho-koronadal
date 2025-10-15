<?php
/**
 * Enhanced Audit Logging System
 * Comprehensive logging and reporting for patient flow routing decisions
 * 
 * @author CHO Koronadal Development Team
 * @version 1.0
 * @date October 15, 2025
 */

class PatientFlowAuditLogger {
    
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log routing decision with enhanced context
     * @param int $queue_entry_id
     * @param array $routing_context
     * @return bool
     */
    public function logRoutingDecision($queue_entry_id, $routing_context) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO patient_flow_audit_logs 
                (queue_entry_id, patient_id, service_id, from_station, to_station, 
                 routing_rule, philhealth_status, employee_id, decision_timestamp, 
                 routing_context, validation_result)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            
            return $stmt->execute([
                $queue_entry_id,
                $routing_context['patient_id'],
                $routing_context['service_id'],
                $routing_context['from_station'],
                $routing_context['to_station'],
                $routing_context['routing_rule'],
                $routing_context['philhealth_status'],
                $routing_context['employee_id'],
                json_encode($routing_context),
                json_encode($routing_context['validation_result'] ?? [])
            ]);
        } catch (Exception $e) {
            error_log("PatientFlowAuditLogger::logRoutingDecision Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log PhilHealth validation action
     * @param int $patient_id
     * @param array $validation_context
     * @return bool
     */
    public function logPhilHealthValidation($patient_id, $validation_context) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO philhealth_validation_logs 
                (patient_id, validation_type, old_status, new_status, philhealth_id, 
                 employee_id, validation_timestamp, validation_notes)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            
            return $stmt->execute([
                $patient_id,
                $validation_context['type'], // 'check-in', 'update', 'verification'
                $validation_context['old_status'] ?? null,
                $validation_context['new_status'],
                $validation_context['philhealth_id'] ?? null,
                $validation_context['employee_id'],
                $validation_context['notes'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("PatientFlowAuditLogger::logPhilHealthValidation Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate routing compliance report
     * @param string $date_from
     * @param string $date_to
     * @return array
     */
    public function generateRoutingComplianceReport($date_from, $date_to) {
        try {
            // Get routing statistics
            $stats_query = "
                SELECT 
                    routing_rule,
                    philhealth_status,
                    COUNT(*) as routing_count,
                    COUNT(CASE WHEN JSON_EXTRACT(validation_result, '$.valid') = true THEN 1 END) as valid_routings,
                    COUNT(CASE WHEN JSON_EXTRACT(validation_result, '$.valid') = false THEN 1 END) as invalid_routings
                FROM patient_flow_audit_logs 
                WHERE DATE(decision_timestamp) BETWEEN ? AND ?
                GROUP BY routing_rule, philhealth_status
                ORDER BY routing_count DESC
            ";
            
            $stmt = $this->pdo->prepare($stats_query);
            $stmt->execute([$date_from, $date_to]);
            $routing_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get PhilHealth validation statistics
            $philhealth_query = "
                SELECT 
                    validation_type,
                    old_status,
                    new_status,
                    COUNT(*) as validation_count
                FROM philhealth_validation_logs 
                WHERE DATE(validation_timestamp) BETWEEN ? AND ?
                GROUP BY validation_type, old_status, new_status
                ORDER BY validation_count DESC
            ";
            
            $stmt = $this->pdo->prepare($philhealth_query);
            $stmt->execute([$date_from, $date_to]);
            $philhealth_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get compliance issues
            $compliance_query = "
                SELECT 
                    pfa.*,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                    si.service_name
                FROM patient_flow_audit_logs pfa
                LEFT JOIN patients p ON pfa.patient_id = p.patient_id
                LEFT JOIN employees e ON pfa.employee_id = e.employee_id
                LEFT JOIN service_items si ON pfa.service_id = si.service_id
                WHERE DATE(pfa.decision_timestamp) BETWEEN ? AND ?
                AND JSON_EXTRACT(pfa.validation_result, '$.valid') = false
                ORDER BY pfa.decision_timestamp DESC
                LIMIT 50
            ";
            
            $stmt = $this->pdo->prepare($compliance_query);
            $stmt->execute([$date_from, $date_to]);
            $compliance_issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'routing_statistics' => $routing_stats,
                'philhealth_statistics' => $philhealth_stats,
                'compliance_issues' => $compliance_issues,
                'report_period' => ['from' => $date_from, 'to' => $date_to],
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("PatientFlowAuditLogger::generateRoutingComplianceReport Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get patient journey audit trail
     * @param int $patient_id
     * @param string $date
     * @return array
     */
    public function getPatientJourneyAudit($patient_id, $date = null) {
        try {
            $date = $date ?? date('Y-m-d');
            
            $journey_query = "
                SELECT 
                    pfa.*,
                    s1.station_name as from_station_name,
                    s2.station_name as to_station_name,
                    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                    si.service_name
                FROM patient_flow_audit_logs pfa
                LEFT JOIN stations s1 ON pfa.from_station = s1.station_type
                LEFT JOIN stations s2 ON pfa.to_station = s2.station_type
                LEFT JOIN employees e ON pfa.employee_id = e.employee_id
                LEFT JOIN service_items si ON pfa.service_id = si.service_id
                WHERE pfa.patient_id = ? 
                AND DATE(pfa.decision_timestamp) = ?
                ORDER BY pfa.decision_timestamp ASC
            ";
            
            $stmt = $this->pdo->prepare($journey_query);
            $stmt->execute([$patient_id, $date]);
            $journey = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get PhilHealth validation history for this patient
            $philhealth_query = "
                SELECT *
                FROM philhealth_validation_logs 
                WHERE patient_id = ? 
                AND DATE(validation_timestamp) = ?
                ORDER BY validation_timestamp ASC
            ";
            
            $stmt = $this->pdo->prepare($philhealth_query);
            $stmt->execute([$patient_id, $date]);
            $philhealth_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'patient_id' => $patient_id,
                'audit_date' => $date,
                'routing_journey' => $journey,
                'philhealth_history' => $philhealth_history
            ];
            
        } catch (Exception $e) {
            error_log("PatientFlowAuditLogger::getPatientJourneyAudit Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Create audit log tables if they don't exist
     * @return bool
     */
    public function createAuditTables() {
        try {
            // Create patient flow audit logs table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS patient_flow_audit_logs (
                    audit_id INT AUTO_INCREMENT PRIMARY KEY,
                    queue_entry_id INT,
                    patient_id INT,
                    service_id VARCHAR(10),
                    from_station VARCHAR(20),
                    to_station VARCHAR(20),
                    routing_rule VARCHAR(100),
                    philhealth_status BOOLEAN,
                    employee_id INT,
                    decision_timestamp TIMESTAMP,
                    routing_context JSON,
                    validation_result JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_patient_date (patient_id, decision_timestamp),
                    INDEX idx_employee_date (employee_id, decision_timestamp),
                    INDEX idx_routing_rule (routing_rule)
                )
            ");
            
            // Create PhilHealth validation logs table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS philhealth_validation_logs (
                    validation_id INT AUTO_INCREMENT PRIMARY KEY,
                    patient_id INT,
                    validation_type VARCHAR(20),
                    old_status BOOLEAN,
                    new_status BOOLEAN,
                    philhealth_id VARCHAR(15),
                    employee_id INT,
                    validation_timestamp TIMESTAMP,
                    validation_notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_patient_validation (patient_id, validation_timestamp),
                    INDEX idx_validation_type (validation_type)
                )
            ");
            
            // Create patient referrals table if not exists
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS patient_referrals (
                    referral_id INT AUTO_INCREMENT PRIMARY KEY,
                    patient_id INT,
                    referring_employee_id INT,
                    referral_date DATE,
                    target_date DATE,
                    referral_type VARCHAR(20),
                    notes TEXT,
                    status VARCHAR(20) DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_patient_referral (patient_id, referral_date),
                    INDEX idx_target_date (target_date, status)
                )
            ");
            
            return true;
        } catch (Exception $e) {
            error_log("PatientFlowAuditLogger::createAuditTables Error: " . $e->getMessage());
            return false;
        }
    }
}