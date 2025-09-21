<?php
// Simple test to see what might be missing in barangay
session_start();

// Test data
$test_patient = [
    'age' => '25',
    'age_category' => 'Adult',
    'sex' => 'Male',
    'dob' => '1999-01-01',
    'contact' => '09123456789',
    'email' => 'test@example.com',
    'barangay' => 'Test Barangay',  // This should NOT be missing
    'philhealth_type' => '',  // This SHOULD be missing
    'philhealth_id_number' => '',  // This SHOULD be missing
    'is_pwd' => 'no',  // Not PWD
    'pwd_id_number' => '',  // Should be skipped
    'blood_type' => 'O+',
    'civil_status' => 'Single',
    'religion' => 'Catholic',
    'occupation' => 'Student',
    'philhealth_id' => '',  // This SHOULD be missing
    'address' => '123 Test Street'
];

$personal_field_labels = [
    // From patients table
    'age' => 'AGE',
    'age_category' => 'AGE CATEGORY',
    'sex' => 'SEX', 
    'dob' => 'DATE OF BIRTH',
    'contact' => 'CONTACT NUMBER',
    'email' => 'EMAIL',
    'barangay' => 'BARANGAY',
    'philhealth_type' => 'PHILHEALTH TYPE',
    'philhealth_id_number' => 'PHILHEALTH ID NUMBER',
    'is_pwd' => 'PWD STATUS',
    'pwd_id_number' => 'PWD ID NUMBER',
    // From personal_information table
    'blood_type' => 'BLOOD TYPE',
    'civil_status' => 'CIVIL STATUS',
    'religion' => 'RELIGION',
    'occupation' => 'OCCUPATION',
    'philhealth_id' => 'PHILHEALTH ID (Personal Info)',
    'address' => 'HOUSE NO. & STREET'
];

echo "<h2>Testing Missing Fields Logic</h2>";
echo "<h3>Test Patient Data:</h3>";
echo "<pre>";
print_r($test_patient);
echo "</pre>";

$missing_personal_fields = [];

foreach ($personal_field_labels as $field => $label) {
    $value = $test_patient[$field] ?? '';
    
    echo "<h4>Processing Field: {$field} ({$label})</h4>";
    echo "<p>Original value: '" . $value . "'</p>";
    
    // Special handling for specific fields
    if ($field === 'age_category' && !empty($value)) {
        // Add badge styling for age category
        if ($value === 'Minor') {
            $value = '<span class="status-badge" style="background: #fff3cd; color: #856404;">' . $value . '</span>';
        } elseif ($value === 'Senior Citizen') {
            $value = '<span class="status-badge" style="background: #d4edda; color: #155724;">' . $value . '</span>';
        } else {
            $value = '<span class="status-badge" style="background: #e2e3e5; color: #495057;">' . $value . '</span>';
        }
    } elseif ($field === 'is_pwd') {
        // Handle PWD status - only show if TRUE
        if (strtolower($value) === 'yes' || $value === '1' || strtolower($value) === 'true') {
            $value = '<span class="status-badge" style="background: #cce5ff; color: #004085;">PWD</span>';
        } else {
            // Skip this field if not PWD - don't count as missing
            echo "<p><strong>SKIPPING</strong> - Not PWD</p>";
            continue;
        }
    } elseif ($field === 'pwd_id_number') {
        // Only show PWD ID if patient is PWD
        if (empty($test_patient['is_pwd']) || 
            !(strtolower($test_patient['is_pwd']) === 'yes' || $test_patient['is_pwd'] === '1' || strtolower($test_patient['is_pwd']) === 'true')) {
            echo "<p><strong>SKIPPING</strong> - Patient is not PWD</p>";
            continue; // Skip if not PWD - don't count as missing
        }
        if (empty($value)) {
            $value = '<span style="color: #6c757d; font-style: italic;">PWD ID not provided</span>';
        }
    }
    
    echo "<p>Processed value: '" . $value . "'</p>";
    
    // Check if field is actually missing (more reliable check)
    $is_missing = false;
    if ($field === 'age_category') {
        // For age category, check the original value
        $is_missing = empty($test_patient[$field]);
    } elseif ($field === 'is_pwd' || $field === 'pwd_id_number') {
        // For PWD fields, if we reach here they are not missing
        $is_missing = false;
    } else {
        // For other fields, check original value
        $original_value = $test_patient[$field] ?? '';
        $is_missing = empty($original_value);
    }
    
    echo "<p>Is missing: " . ($is_missing ? 'YES' : 'NO') . "</p>";
    
    if ($is_missing) {
        $missing_personal_fields[] = $label;
        echo "<p><strong>ADDED TO MISSING LIST</strong></p>";
    }
    
    echo "<hr>";
}

echo "<h3>Final Missing Fields:</h3>";
echo "<pre>";
print_r($missing_personal_fields);
echo "</pre>";
?>