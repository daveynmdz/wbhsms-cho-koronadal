/**
 * Triage Screen JavaScript
 * Clinical Encounter Module - CHO Koronadal Healthcare Management System
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeTriageScreen();
});

function initializeTriageScreen() {
    // Initialize form validation
    setupFormValidation();
    
    // Initialize patient search
    setupPatientSearch();
    
    // Initialize vital signs monitoring
    setupVitalSignsMonitoring();
    
    // Initialize form auto-save (optional)
    setupAutoSave();
}

/**
 * Form Validation Setup
 */
function setupFormValidation() {
    const form = document.getElementById('triageForm');
    const requiredFields = form.querySelectorAll('[required]');
    
    // Add real-time validation to required fields
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            validateField(this);
        });
        
        field.addEventListener('input', function() {
            clearFieldError(this);
        });
    });
    
    // Blood pressure pattern validation
    const bpInput = document.getElementById('bloodPressure');
    if (bpInput) {
        bpInput.addEventListener('input', function() {
            validateBloodPressure(this);
        });
    }
    
    // Numeric field validation
    const numericFields = ['heartRate', 'temperature', 'respiratoryRate', 'weight', 'height', 'oxygenSaturation'];
    numericFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                validateNumericField(this);
            });
        }
    });
}

function validateField(field) {
    const value = field.value.trim();
    const fieldName = field.getAttribute('name');
    
    // Remove existing error styling
    clearFieldError(field);
    
    // Check if required field is empty
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, `${getFieldLabel(field)} is required`);
        return false;
    }
    
    // Specific validation based on field type
    switch (fieldName) {
        case 'chiefComplaint':
            if (value.length < 10) {
                showFieldError(field, 'Chief complaint must be at least 10 characters long');
                return false;
            }
            break;
            
        case 'heartRate':
            if (value && (parseInt(value) < 40 || parseInt(value) > 200)) {
                showFieldError(field, 'Heart rate should be between 40 and 200 bpm');
                return false;
            }
            break;
            
        case 'temperature':
            if (value && (parseFloat(value) < 30 || parseFloat(value) > 45)) {
                showFieldError(field, 'Temperature should be between 30°C and 45°C');
                return false;
            }
            break;
            
        case 'respiratoryRate':
            if (value && (parseInt(value) < 8 || parseInt(value) > 40)) {
                showFieldError(field, 'Respiratory rate should be between 8 and 40 breaths/min');
                return false;
            }
            break;
    }
    
    return true;
}

function validateBloodPressure(field) {
    const value = field.value.trim();
    const bpPattern = /^\d{2,3}\/\d{2,3}$/;
    
    clearFieldError(field);
    
    if (value && !bpPattern.test(value)) {
        showFieldError(field, 'Blood pressure format should be like 120/80');
        return false;
    }
    
    if (value) {
        const [systolic, diastolic] = value.split('/').map(Number);
        if (systolic < 70 || systolic > 250 || diastolic < 40 || diastolic > 150) {
            showFieldError(field, 'Blood pressure values seem unusual. Please verify.');
            return false;
        }
    }
    
    return true;
}

function validateNumericField(field) {
    const value = field.value;
    const min = parseFloat(field.getAttribute('min'));
    const max = parseFloat(field.getAttribute('max'));
    
    clearFieldError(field);
    
    if (value && (isNaN(value) || parseFloat(value) < min || parseFloat(value) > max)) {
        showFieldError(field, `Value must be between ${min} and ${max}`);
        return false;
    }
    
    return true;
}

function showFieldError(field, message) {
    field.classList.add('error');
    field.style.borderColor = '#dc3545';
    
    // Remove existing error message
    const existingError = field.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Add error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '0.85rem';
    errorDiv.style.marginTop = '4px';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    field.classList.remove('error');
    field.style.borderColor = '';
    
    const errorMessage = field.parentNode.querySelector('.error-message');
    if (errorMessage) {
        errorMessage.remove();
    }
}

function getFieldLabel(field) {
    const label = field.parentNode.querySelector('.form-label');
    return label ? label.textContent.replace(' *', '') : field.getAttribute('name');
}

/**
 * Patient Search Setup
 */
function setupPatientSearch() {
    const searchBtn = document.getElementById('searchPatientBtn');
    const searchInput = document.getElementById('patientSearch');
    
    if (searchBtn && searchInput) {
        searchBtn.addEventListener('click', function() {
            performPatientSearch();
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performPatientSearch();
            }
        });
    }
}

function performPatientSearch() {
    const searchTerm = document.getElementById('patientSearch').value.trim();
    
    if (!searchTerm) {
        alert('Please enter a search term');
        return;
    }
    
    // Show loading state
    const searchBtn = document.getElementById('searchPatientBtn');
    const originalText = searchBtn.innerHTML;
    searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
    searchBtn.disabled = true;
    
    // Simulate API call (in real app, this would be an actual API request)
    setTimeout(() => {
        // Mock search results
        const mockPatients = [
            {
                patient_id: 1,
                name: 'Juan Dela Cruz',
                age: 39,
                gender: 'Male',
                contact: '09171234567',
                blood_type: 'O+'
            },
            {
                patient_id: 2,
                name: 'Maria Garcia',
                age: 34,
                gender: 'Female',
                contact: '09282345678',
                blood_type: 'A+'
            }
        ];
        
        // For demo purposes, always show the first patient
        const patient = mockPatients.find(p => 
            p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            searchTerm.includes(p.patient_id.toString())
        ) || mockPatients[0];
        
        if (patient) {
            displayPatientInfo(patient);
        } else {
            alert('Patient not found. Please check the search term or register a new patient.');
        }
        
        // Reset button state
        searchBtn.innerHTML = originalText;
        searchBtn.disabled = false;
    }, 1500);
}

function displayPatientInfo(patient) {
    // Show patient info section
    document.getElementById('patientInfoSection').style.display = 'block';
    
    // Populate patient information
    document.getElementById('displayPatientId').textContent = `P${String(patient.patient_id).padStart(4, '0')}`;
    document.getElementById('displayPatientName').textContent = patient.name;
    document.getElementById('displayPatientAge').textContent = `${patient.age} years`;
    document.getElementById('displayPatientGender').textContent = patient.gender;
    document.getElementById('displayPatientContact').textContent = patient.contact;
    document.getElementById('displayPatientBloodType').textContent = patient.blood_type;
    
    // Set hidden patient ID
    document.getElementById('patientId').value = patient.patient_id;
    
    // Smooth scroll to vital signs section
    setTimeout(() => {
        document.querySelector('.vitals-grid').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
    }, 300);
}

/**
 * Vital Signs Monitoring Setup
 */
function setupVitalSignsMonitoring() {
    const vitalInputs = document.querySelectorAll('.vital-sign-input');
    
    vitalInputs.forEach(input => {
        input.addEventListener('input', function() {
            monitorVitalSigns(this);
        });
        
        input.addEventListener('blur', function() {
            formatVitalSign(this);
        });
    });
}

function monitorVitalSigns(input) {
    const value = parseFloat(input.value);
    const inputId = input.id;
    
    // Remove existing warnings
    clearVitalSignWarning(input);
    
    // Check for abnormal values and show warnings
    switch (inputId) {
        case 'bloodPressure':
            checkBloodPressureWarning(input);
            break;
            
        case 'heartRate':
            if (value < 60 || value > 100) {
                showVitalSignWarning(input, value < 60 ? 'Low heart rate' : 'High heart rate', 'warning');
            }
            if (value < 40 || value > 150) {
                showVitalSignWarning(input, value < 40 ? 'Critically low heart rate' : 'Critically high heart rate', 'critical');
            }
            break;
            
        case 'temperature':
            if (value < 36.0 || value > 37.5) {
                showVitalSignWarning(input, value < 36.0 ? 'Low temperature' : 'Elevated temperature', 'warning');
            }
            if (value < 35.0 || value > 39.0) {
                showVitalSignWarning(input, value < 35.0 ? 'Hypothermia' : 'High fever', 'critical');
            }
            break;
            
        case 'respiratoryRate':
            if (value < 12 || value > 20) {
                showVitalSignWarning(input, value < 12 ? 'Low respiratory rate' : 'High respiratory rate', 'warning');
            }
            if (value < 8 || value > 30) {
                showVitalSignWarning(input, 'Critical respiratory rate', 'critical');
            }
            break;
            
        case 'oxygenSaturation':
            if (value < 95) {
                showVitalSignWarning(input, 'Low oxygen saturation', value < 90 ? 'critical' : 'warning');
            }
            break;
    }
}

function checkBloodPressureWarning(input) {
    const value = input.value.trim();
    const bpPattern = /^(\d{2,3})\/(\d{2,3})$/;
    const match = value.match(bpPattern);
    
    if (match) {
        const systolic = parseInt(match[1]);
        const diastolic = parseInt(match[2]);
        
        let warning = '';
        let level = 'normal';
        
        if (systolic >= 180 || diastolic >= 110) {
            warning = 'Hypertensive crisis';
            level = 'critical';
        } else if (systolic >= 140 || diastolic >= 90) {
            warning = 'High blood pressure';
            level = 'warning';
        } else if (systolic < 90 || diastolic < 60) {
            warning = 'Low blood pressure';
            level = 'warning';
        }
        
        if (warning) {
            showVitalSignWarning(input, warning, level);
        }
    }
}

function showVitalSignWarning(input, message, level) {
    const vitalGroup = input.closest('.vital-sign-group');
    
    // Add warning styling
    if (level === 'critical') {
        vitalGroup.style.border = '2px solid #dc3545';
        vitalGroup.style.background = '#f8d7da';
    } else if (level === 'warning') {
        vitalGroup.style.border = '2px solid #ffc107';
        vitalGroup.style.background = '#fff3cd';
    }
    
    // Add warning icon and tooltip
    const warningIcon = document.createElement('div');
    warningIcon.className = 'vital-warning-icon';
    warningIcon.style.position = 'absolute';
    warningIcon.style.top = '5px';
    warningIcon.style.right = '5px';
    warningIcon.style.color = level === 'critical' ? '#dc3545' : '#ffc107';
    warningIcon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
    warningIcon.title = message;
    
    vitalGroup.style.position = 'relative';
    vitalGroup.appendChild(warningIcon);
}

function clearVitalSignWarning(input) {
    const vitalGroup = input.closest('.vital-sign-group');
    vitalGroup.style.border = '2px solid #e9ecef';
    vitalGroup.style.background = '#f8f9fa';
    
    const warningIcon = vitalGroup.querySelector('.vital-warning-icon');
    if (warningIcon) {
        warningIcon.remove();
    }
}

function formatVitalSign(input) {
    const inputId = input.id;
    const value = input.value.trim();
    
    // Format specific vital signs
    switch (inputId) {
        case 'temperature':
            if (value && !isNaN(value)) {
                input.value = parseFloat(value).toFixed(1);
            }
            break;
    }
}

/**
 * Auto-save Setup (Optional)
 */
function setupAutoSave() {
    let autoSaveTimeout;
    const form = document.getElementById('triageForm');
    
    // Auto-save every 30 seconds if there are changes
    form.addEventListener('input', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(() => {
            autoSaveFormData();
        }, 30000);
    });
}

function autoSaveFormData() {
    const formData = new FormData(document.getElementById('triageForm'));
    const data = Object.fromEntries(formData.entries());
    
    // Store in localStorage for demo purposes
    localStorage.setItem('triageAutoSave', JSON.stringify(data));
    
    // Show auto-save indicator
    showAutoSaveIndicator();
}

function showAutoSaveIndicator() {
    const indicator = document.createElement('div');
    indicator.style.position = 'fixed';
    indicator.style.top = '20px';
    indicator.style.right = '20px';
    indicator.style.background = '#28a745';
    indicator.style.color = 'white';
    indicator.style.padding = '10px 15px';
    indicator.style.borderRadius = '5px';
    indicator.style.zIndex = '9999';
    indicator.innerHTML = '<i class="fas fa-save"></i> Auto-saved';
    
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        indicator.remove();
    }, 3000);
}

/**
 * Utility Functions
 */
function calculateBMI() {
    const weight = parseFloat(document.getElementById('weight').value);
    const height = parseFloat(document.getElementById('height').value);
    
    if (weight && height) {
        const heightInMeters = height / 100;
        const bmi = weight / (heightInMeters * heightInMeters);
        
        let category = '';
        if (bmi < 18.5) category = ' (Underweight)';
        else if (bmi < 25) category = ' (Normal)';
        else if (bmi < 30) category = ' (Overweight)';
        else category = ' (Obese)';
        
        document.getElementById('bmi').value = bmi.toFixed(1) + category;
    }
}

// Export functions for global access
window.triageJS = {
    calculateBMI,
    performPatientSearch,
    displayPatientInfo,
    validateField,
    clearFieldError
};