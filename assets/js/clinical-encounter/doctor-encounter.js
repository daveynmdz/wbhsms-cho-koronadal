/**
 * Doctor Encounter Screen JavaScript
 * Clinical Encounter Module - CHO Koronadal Healthcare Management System
 */

// Global variables
let selectedLabTests = [];
let selectedServices = [];
let prescriptions = [];

document.addEventListener('DOMContentLoaded', function() {
    initializeDoctorEncounter();
});

function initializeDoctorEncounter() {
    setupFormValidation();
    setupPrescriptionManagement();
    setupModalHandlers();
    setupStatusHandlers();
    setupReferralHandlers();
    setupFormSubmission();
}

/**
 * Form Validation Setup
 */
function setupFormValidation() {
    const form = document.getElementById('encounterForm');
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        field.addEventListener('blur', validateField);
        field.addEventListener('input', clearFieldError);
    });
}

function validateField(event) {
    const field = event.target;
    const value = field.value.trim();
    
    clearFieldError(field);
    
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, `${getFieldLabel(field)} is required`);
        return false;
    }
    
    // Specific validations
    if (field.id === 'treatmentPlan' && value.length < 20) {
        showFieldError(field, 'Treatment plan should be detailed (at least 20 characters)');
        return false;
    }
    
    return true;
}

function showFieldError(field, message) {
    field.style.borderColor = '#dc3545';
    
    const existingError = field.parentNode.querySelector('.error-message');
    if (existingError) existingError.remove();
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '0.85rem';
    errorDiv.style.marginTop = '4px';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    field.style.borderColor = '';
    const errorMessage = field.parentNode.querySelector('.error-message');
    if (errorMessage) errorMessage.remove();
}

function getFieldLabel(field) {
    const label = field.parentNode.querySelector('.form-label');
    return label ? label.textContent.replace(' *', '') : field.getAttribute('name');
}

/**
 * Prescription Management
 */
function setupPrescriptionManagement() {
    const addPrescriptionBtn = document.getElementById('addPrescriptionBtn');
    if (addPrescriptionBtn) {
        addPrescriptionBtn.addEventListener('click', showAddPrescriptionForm);
    }
}

function showAddPrescriptionForm() {
    const prescriptionsList = document.getElementById('prescriptionsList');
    const noPrescriptions = document.getElementById('noPrescriptions');
    
    const prescriptionForm = document.createElement('div');
    prescriptionForm.className = 'prescription-form-container';
    prescriptionForm.style.background = '#e8f4f8';
    prescriptionForm.style.border = '2px solid #03045e';
    prescriptionForm.style.borderRadius = '8px';
    prescriptionForm.style.padding = '20px';
    prescriptionForm.style.marginBottom = '15px';
    
    prescriptionForm.innerHTML = `
        <h4 style="margin: 0 0 15px 0; color: #03045e;">
            <i class="fas fa-plus"></i> Add New Prescription
        </h4>
        
        <div class="form-grid two-column">
            <div class="form-group">
                <label class="form-label">Medication Name</label>
                <input type="text" id="newMedicationName" class="form-control" placeholder="e.g., Paracetamol 500mg">
            </div>
            <div class="form-group">
                <label class="form-label">Dosage</label>
                <input type="text" id="newMedicationDosage" class="form-control" placeholder="e.g., 1 tablet">
            </div>
        </div>
        
        <div class="form-grid two-column">
            <div class="form-group">
                <label class="form-label">Frequency</label>
                <select id="newMedicationFrequency" class="form-control">
                    <option value="">Select frequency</option>
                    <option value="Once daily">Once daily</option>
                    <option value="Twice daily">Twice daily (BID)</option>
                    <option value="Three times daily">Three times daily (TID)</option>
                    <option value="Four times daily">Four times daily (QID)</option>
                    <option value="Every 4 hours">Every 4 hours</option>
                    <option value="Every 6 hours">Every 6 hours</option>
                    <option value="Every 8 hours">Every 8 hours</option>
                    <option value="As needed">As needed (PRN)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Duration</label>
                <input type="text" id="newMedicationDuration" class="form-control" placeholder="e.g., 7 days">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Special Instructions</label>
            <textarea id="newMedicationInstructions" class="form-control" rows="2" placeholder="e.g., Take with food, avoid alcohol..."></textarea>
        </div>
        
        <div class="btn-group" style="margin-top: 15px;">
            <button type="button" class="btn btn-success" onclick="addPrescription()">
                <i class="fas fa-plus"></i> Add Prescription
            </button>
            <button type="button" class="btn btn-secondary" onclick="cancelAddPrescription()">
                Cancel
            </button>
        </div>
    `;
    
    // Hide "no prescriptions" message
    noPrescriptions.style.display = 'none';
    
    // Add form to prescriptions list
    prescriptionsList.appendChild(prescriptionForm);
    
    // Focus on medication name field
    document.getElementById('newMedicationName').focus();
}

function addPrescription() {
    const name = document.getElementById('newMedicationName').value.trim();
    const dosage = document.getElementById('newMedicationDosage').value.trim();
    const frequency = document.getElementById('newMedicationFrequency').value;
    const duration = document.getElementById('newMedicationDuration').value.trim();
    const instructions = document.getElementById('newMedicationInstructions').value.trim();
    
    // Validation
    if (!name) {
        alert('Please enter medication name');
        document.getElementById('newMedicationName').focus();
        return;
    }
    
    if (!dosage) {
        alert('Please enter dosage');
        document.getElementById('newMedicationDosage').focus();
        return;
    }
    
    if (!frequency) {
        alert('Please select frequency');
        document.getElementById('newMedicationFrequency').focus();
        return;
    }
    
    // Create prescription object
    const prescription = {
        id: Date.now(),
        name: name,
        dosage: dosage,
        frequency: frequency,
        duration: duration || 'As directed',
        instructions: instructions || 'Take as directed'
    };
    
    // Add to prescriptions array
    prescriptions.push(prescription);
    
    // Display prescription
    displayPrescription(prescription);
    
    // Remove form
    cancelAddPrescription();
}

function displayPrescription(prescription) {
    const prescriptionsList = document.getElementById('prescriptionsList');
    
    const prescriptionItem = document.createElement('div');
    prescriptionItem.className = 'prescription-item';
    prescriptionItem.dataset.prescriptionId = prescription.id;
    
    prescriptionItem.innerHTML = `
        <div class="prescription-header">
            <div class="medication-name">${prescription.name}</div>
            <button type="button" class="remove-prescription" onclick="removePrescription(${prescription.id})" title="Remove prescription">
                &times;
            </button>
        </div>
        
        <div class="prescription-details">
            <div><strong>Dosage:</strong> ${prescription.dosage}</div>
            <div><strong>Frequency:</strong> ${prescription.frequency}</div>
            <div><strong>Duration:</strong> ${prescription.duration}</div>
        </div>
        
        ${prescription.instructions ? `<div style="margin-top: 8px; font-style: italic; color: #666;"><strong>Instructions:</strong> ${prescription.instructions}</div>` : ''}
    `;
    
    prescriptionsList.appendChild(prescriptionItem);
}

function removePrescription(prescriptionId) {
    if (confirm('Are you sure you want to remove this prescription?')) {
        // Remove from array
        prescriptions = prescriptions.filter(p => p.id !== prescriptionId);
        
        // Remove from DOM
        const prescriptionItem = document.querySelector(`[data-prescription-id="${prescriptionId}"]`);
        if (prescriptionItem) {
            prescriptionItem.remove();
        }
        
        // Show "no prescriptions" message if list is empty
        if (prescriptions.length === 0) {
            document.getElementById('noPrescriptions').style.display = 'block';
        }
    }
}

function cancelAddPrescription() {
    const formContainer = document.querySelector('.prescription-form-container');
    if (formContainer) {
        formContainer.remove();
    }
    
    // Show "no prescriptions" message if no prescriptions exist
    if (prescriptions.length === 0) {
        document.getElementById('noPrescriptions').style.display = 'block';
    }
}

/**
 * Modal Handlers
 */
function setupModalHandlers() {
    // Lab tests modal
    const orderLabTestsBtn = document.getElementById('orderLabTestsBtn');
    if (orderLabTestsBtn) {
        orderLabTestsBtn.addEventListener('click', () => openModal('labTestsModal'));
    }
    
    // Services modal  
    const requestServicesBtn = document.getElementById('requestServicesBtn');
    if (requestServicesBtn) {
        requestServicesBtn.addEventListener('click', () => openModal('servicesModal'));
    }
}

function toggleLabTest(element, testName) {
    const checkbox = element.querySelector('.lab-test-checkbox');
    const isSelected = selectedLabTests.includes(testName);
    
    if (isSelected) {
        // Remove from selection
        selectedLabTests = selectedLabTests.filter(test => test !== testName);
        checkbox.checked = false;
        element.classList.remove('selected');
    } else {
        // Add to selection
        selectedLabTests.push(testName);
        checkbox.checked = true;
        element.classList.add('selected');
    }
}

function confirmLabTests() {
    if (selectedLabTests.length === 0) {
        alert('Please select at least one laboratory test');
        return;
    }
    
    // Display selected tests
    const selectedLabTestsDiv = document.getElementById('selectedLabTests');
    const labTestsList = document.getElementById('labTestsList');
    
    labTestsList.innerHTML = selectedLabTests.map(test => 
        `<div class="selected-test-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #e8f4f8; border-radius: 5px; margin-bottom: 5px;">
            <span>${test}</span>
            <button type="button" onclick="removeLabTest('${test}')" style="background: #dc3545; color: white; border: none; border-radius: 3px; width: 20px; height: 20px; font-size: 12px;">&times;</button>
        </div>`
    ).join('');
    
    selectedLabTestsDiv.style.display = 'block';
    closeModal('labTestsModal');
}

function removeLabTest(testName) {
    selectedLabTests = selectedLabTests.filter(test => test !== testName);
    
    if (selectedLabTests.length === 0) {
        document.getElementById('selectedLabTests').style.display = 'none';
    } else {
        // Refresh the display
        confirmLabTests();
        openModal('labTestsModal'); // Reopen to update selection
        closeModal('labTestsModal');
    }
}

function toggleService(element, serviceName, serviceDescription) {
    const checkbox = element.querySelector('.service-checkbox');
    const isSelected = selectedServices.some(service => service.name === serviceName);
    
    if (isSelected) {
        // Remove from selection
        selectedServices = selectedServices.filter(service => service.name !== serviceName);
        checkbox.checked = false;
        element.classList.remove('selected');
    } else {
        // Add to selection
        selectedServices.push({ name: serviceName, description: serviceDescription });
        checkbox.checked = true;
        element.classList.add('selected');
    }
}

function confirmServices() {
    if (selectedServices.length === 0) {
        alert('Please select at least one service');
        return;
    }
    
    // Display selected services
    const selectedServicesDiv = document.getElementById('selectedServices');
    const servicesList = document.getElementById('servicesList');
    
    servicesList.innerHTML = selectedServices.map(service => 
        `<div class="selected-service-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #fff3cd; border-radius: 5px; margin-bottom: 8px;">
            <div>
                <div style="font-weight: 600; color: #856404;">${service.name}</div>
                <div style="font-size: 0.85rem; color: #666;">${service.description}</div>
            </div>
            <button type="button" onclick="removeService('${service.name}')" style="background: #dc3545; color: white; border: none; border-radius: 3px; width: 24px; height: 24px; font-size: 14px;">&times;</button>
        </div>`
    ).join('');
    
    selectedServicesDiv.style.display = 'block';
    closeModal('servicesModal');
}

function removeService(serviceName) {
    selectedServices = selectedServices.filter(service => service.name !== serviceName);
    
    if (selectedServices.length === 0) {
        document.getElementById('selectedServices').style.display = 'none';
    } else {
        // Refresh the display
        confirmServices();
        openModal('servicesModal'); // Reopen to update selection
        closeModal('servicesModal');
    }
}

/**
 * Status and Referral Handlers
 */
function setupStatusHandlers() {
    const encounterStatus = document.getElementById('encounterStatus');
    if (encounterStatus) {
        encounterStatus.addEventListener('change', handleStatusChange);
    }
    
    const scheduleFollowupBtn = document.getElementById('scheduleFollowupBtn');
    if (scheduleFollowupBtn) {
        scheduleFollowupBtn.addEventListener('click', handleScheduleFollowup);
    }
}

function handleStatusChange(event) {
    const status = event.target.value;
    const followupGroup = document.getElementById('followupGroup');
    const followupActions = document.getElementById('followupActions');
    
    if (status === 'pending_followup') {
        followupGroup.style.display = 'block';
        followupActions.style.display = 'block';
        document.getElementById('followupDate').required = true;
    } else {
        followupGroup.style.display = 'none';
        followupActions.style.display = 'none';
        document.getElementById('followupDate').required = false;
    }
}

function handleScheduleFollowup() {
    const followupDate = document.getElementById('followupDate').value;
    
    if (!followupDate) {
        alert('Please select a follow-up date first');
        document.getElementById('followupDate').focus();
        return;
    }
    
    // In a real application, this would open the appointment scheduling system
    alert(`Schedule follow-up appointment for ${new Date(followupDate).toLocaleDateString()}.\n\nIn a real application, this would open the appointment scheduling interface.`);
}

function setupReferralHandlers() {
    const needsReferral = document.getElementById('needsReferral');
    if (needsReferral) {
        needsReferral.addEventListener('change', function() {
            const referralSection = document.getElementById('referralSection');
            if (this.checked) {
                referralSection.style.display = 'block';
                document.getElementById('referralDestination').required = true;
                document.getElementById('referralReason').required = true;
            } else {
                referralSection.style.display = 'none';
                document.getElementById('referralDestination').required = false;
                document.getElementById('referralReason').required = false;
            }
        });
    }
}

/**
 * Form Submission
 */
function setupFormSubmission() {
    const form = document.getElementById('encounterForm');
    if (form) {
        form.addEventListener('submit', handleFormSubmission);
    }
    
    const saveAndCompleteBtn = document.getElementById('saveAndCompleteBtn');
    if (saveAndCompleteBtn) {
        saveAndCompleteBtn.addEventListener('click', handleSaveAndComplete);
    }
    
    const printEncounterBtn = document.getElementById('printEncounterBtn');
    if (printEncounterBtn) {
        printEncounterBtn.addEventListener('click', handlePrintEncounter);
    }
}

function handleFormSubmission(event) {
    event.preventDefault();
    
    if (validateForm()) {
        const formData = collectFormData();
        saveEncounter(formData, false);
    }
}

function handleSaveAndComplete() {
    if (validateForm()) {
        const formData = collectFormData();
        formData.encounter_status = 'completed';
        saveEncounter(formData, true);
    }
}

function validateForm() {
    const form = document.getElementById('encounterForm');
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!validateField({ target: field })) {
            isValid = false;
        }
    });
    
    if (!isValid) {
        alert('Please fill in all required fields correctly.');
        return false;
    }
    
    return true;
}

function collectFormData() {
    const formData = new FormData(document.getElementById('encounterForm'));
    const data = Object.fromEntries(formData.entries());
    
    // Add prescription data
    data.prescriptions = prescriptions;
    
    // Add lab tests data
    data.laboratory_tests = selectedLabTests;
    
    // Add services data
    data.additional_services = selectedServices;
    
    return data;
}

function saveEncounter(data, isComplete) {
    // Show loading state
    const submitBtn = isComplete ? 
        document.getElementById('saveAndCompleteBtn') : 
        document.querySelector('button[type="submit"]');
    
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        // Show success message
        const action = isComplete ? 'completed' : 'saved';
        alert(`Clinical encounter ${action} successfully!\n\nEncounter ID: E0001\nPatient: ${data.patientName || 'Patient Name'}\nDate: ${new Date().toLocaleDateString()}`);
        
        // In a real application, redirect to encounter history or dashboard
        if (isComplete) {
            // window.location.href = 'encounter-history.php';
        }
    }, 2000);
}

function handlePrintEncounter() {
    // In a real application, this would generate a printable encounter report
    alert('Print encounter report.\n\nIn a real application, this would generate a comprehensive encounter report including patient information, vital signs, diagnosis, treatment plan, and prescriptions.');
}

/**
 * Utility Functions
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Export functions for global access
window.doctorEncounterJS = {
    openModal,
    closeModal,
    toggleLabTest,
    confirmLabTests,
    removeLabTest,
    toggleService,
    confirmServices,
    removeService,
    addPrescription,
    removePrescription,
    cancelAddPrescription,
    handleScheduleFollowup,
    handlePrintEncounter
};