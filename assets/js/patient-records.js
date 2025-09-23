// Patient Records JavaScript Functions

// Modal functions
function showContactModal(patientId) {
    // Find patient data from current page
    const patients = window.patientData || [];
    const patient = patients.find(p => p.patient_id == patientId);
    
    if (!patient) {
        alert('Patient information not found.');
        return;
    }

    // Update modal content
    const modal = document.getElementById('contactModal');
    const profileImage = modal.querySelector('.id-card-photo img');
    const patientName = modal.querySelector('.patient-name');
    const contactInfo = modal.querySelector('.contact-info');
    const emergencySection = modal.querySelector('.emergency-section');

    // Set profile photo
    if (patient.profile_photo) {
        profileImage.src = '../../../uploads/profile_photos/' + patient.profile_photo;
        profileImage.style.display = 'block';
        profileImage.onerror = function() {
            this.src = '../../../assets/images/user-default.png';
        };
    } else {
        profileImage.src = '../../../assets/images/user-default.png';
    }

    // Set patient name
    const fullName = [patient.first_name, patient.middle_name, patient.last_name]
        .filter(name => name && name !== '-')
        .join(' ');
    patientName.textContent = fullName || 'Unknown Patient';

    // Set contact information
    contactInfo.innerHTML = `
        <div class="info-item">
            <span class="label">Phone:</span>
            <span class="value">${patient.contact_number || 'N/A'}</span>
        </div>
        <div class="info-item">
            <span class="label">Email:</span>
            <span class="value">${patient.email || 'N/A'}</span>
        </div>
        <div class="info-item">
            <span class="label">Date of Birth:</span>
            <span class="value">${patient.date_of_birth ? new Date(patient.date_of_birth).toLocaleDateString() : 'N/A'}</span>
        </div>
        <div class="info-item">
            <span class="label">Barangay:</span>
            <span class="value">${patient.barangay_name || 'N/A'}</span>
        </div>
    `;

    // Load emergency contact data via AJAX
    fetch(`patient_records.php?emergency_contact=1&patient_id=${patientId}`)
        .then(response => response.json())
        .then(emergencyContact => {
            if (emergencyContact) {
                emergencySection.innerHTML = `
                    <h4>Emergency Contact</h4>
                    <div class="info-item">
                        <span class="label">Name:</span>
                        <span class="value">${emergencyContact.emergency_contact_name || 'N/A'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Relationship:</span>
                        <span class="value">${emergencyContact.relationship || 'N/A'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Phone:</span>
                        <span class="value">${emergencyContact.emergency_contact_number || 'N/A'}</span>
                    </div>
                `;
            } else {
                emergencySection.innerHTML = `
                    <h4>Emergency Contact</h4>
                    <p class="no-emergency">No emergency contact information available.</p>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading emergency contact:', error);
            emergencySection.innerHTML = `
                <h4>Emergency Contact</h4>
                <p class="no-emergency">Unable to load emergency contact information.</p>
            `;
        });

    // Show modal
    modal.style.display = 'block';
}

function closeContactModal() {
    document.getElementById('contactModal').style.display = 'none';
}

// AJAX pagination function
function loadPage(page) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('page', page);
    currentUrl.searchParams.set('ajax', '1');

    fetch(currentUrl.toString())
        .then(response => response.text())
        .then(html => {
            // Parse the response and update different sections
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;

            // Update table
            const newTableContainer = tempDiv.querySelector('.clean-table-container');
            if (newTableContainer) {
                document.querySelector('.clean-table-container').outerHTML = newTableContainer.outerHTML;
            }

            // Update pagination
            const newPagination = tempDiv.querySelector('.pagination');
            const currentPagination = document.querySelector('.pagination');
            if (newPagination && currentPagination) {
                currentPagination.outerHTML = newPagination.outerHTML;
            }

            // Update record count
            const newRecordCount = tempDiv.querySelector('.record-count');
            const currentRecordCount = document.querySelector('.record-count');
            if (newRecordCount && currentRecordCount) {
                currentRecordCount.textContent = newRecordCount.textContent;
            }

            // Update summary
            const newStatDetails = tempDiv.querySelector('.stat-details');
            const currentStatDetails = document.querySelector('.stat-details');
            if (newStatDetails && currentStatDetails) {
                currentStatDetails.innerHTML = newStatDetails.innerHTML;
            }

            // Update URL without page refresh
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('page', page);
            window.history.pushState({}, '', newUrl.toString());
        })
        .catch(error => {
            console.error('Error loading page:', error);
            alert('Error loading page. Please try again.');
        });
}

// Collapsible filters
function toggleFilters() {
    const filtersContent = document.querySelector('.filters-content');
    const toggleBtn = document.querySelector('.filters-toggle');
    
    if (filtersContent.style.display === 'none' || filtersContent.style.display === '') {
        filtersContent.style.display = 'flex';
        toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Filters';
    } else {
        filtersContent.style.display = 'none';
        toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i> Show Filters';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if we should show filters by default
    const urlParams = new URLSearchParams(window.location.search);
    const hasFilters = urlParams.get('search') || urlParams.get('status') || urlParams.get('barangay');
    
    if (!hasFilters) {
        // Hide filters by default if no active filters
        const filtersContent = document.querySelector('.filters-content');
        if (filtersContent) {
            filtersContent.style.display = 'none';
        }
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('contactModal');
    if (event.target === modal) {
        closeContactModal();
    }
}