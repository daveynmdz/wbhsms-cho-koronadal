<?php
/**
 * Mock Configuration for Frontend Development
 * CHO Koronadal Healthcare Management System
 */

// Mock database connection for development
function getMockConnection() {
    return null; // Not needed for frontend testing
}

// Mock patients data
function getMockPatients() {
    return [
        [
            'patient_id' => 1,
            'name' => 'Juan Dela Cruz',
            'age' => 45,
            'gender' => 'Male',
            'contact' => '+63 912 345 6789',
            'blood_type' => 'O+',
            'address' => 'Brgy. Zone 1, Koronadal City',
            'civil_status' => 'Married',
            'occupation' => 'Farmer',
            'emergency_contact' => 'Maria Dela Cruz - +63 912 345 6788',
            'medical_history' => 'Hypertension, Diabetes Type 2'
        ],
        [
            'patient_id' => 2,
            'name' => 'Maria Garcia',
            'age' => 32,
            'gender' => 'Female',
            'contact' => '+63 923 456 7890',
            'blood_type' => 'A+',
            'address' => 'Brgy. Zone 2, Koronadal City',
            'civil_status' => 'Single',
            'occupation' => 'Teacher',
            'emergency_contact' => 'Pedro Garcia - +63 923 456 7891',
            'medical_history' => 'Asthma, Allergic Rhinitis'
        ],
        [
            'patient_id' => 3,
            'name' => 'Pedro Mendoza',
            'age' => 58,
            'gender' => 'Male',
            'contact' => '+63 934 567 8901',
            'blood_type' => 'B+',
            'address' => 'Brgy. Zone 3, Koronadal City',
            'civil_status' => 'Married',
            'occupation' => 'Construction Worker',
            'emergency_contact' => 'Ana Mendoza - +63 934 567 8900',
            'medical_history' => 'Lower Back Pain, Hypertension'
        ]
    ];
}