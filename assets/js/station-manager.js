/**
 * Universal Station Management Framework
 * Purpose: Reusable JavaScript framework for managing queue stations (triage, consultation, lab, etc.)
 * Features: Real-time updates, robust DOM manipulation, error handling, and cross-station synchronization
 */

class StationManager {
    constructor(config) {
        this.stationType = config.stationType || 'general';
        this.stationId = config.stationId;
        this.employeeId = config.employeeId;
        this.refreshInterval = config.refreshInterval || 10000; // 10 seconds default
        this.currentPatient = null;
        this.queueData = {
            waiting: [],
            skipped: [],
            completed: [],
            inProgress: []
        };
        
        // DOM element references
        this.elements = {
            container: null,
            currentPatientDiv: null,
            actionsDiv: null,
            waitingDiv: null,
            skippedDiv: null,
            completedDiv: null
        };
        
        this.init();
    }
    
    /**
     * Initialize the station manager
     */
    init() {
        this.findDOMElements();
        this.setupEventListeners();
        this.startAutoRefresh();
        this.loadInitialData();
        
        console.log(`StationManager initialized for ${this.stationType} station`);
    }
    
    /**
     * Find and cache DOM elements with robust error handling
     */
    findDOMElements() {
        // Main container - try multiple selectors
        const containerSelectors = [
            '.queue-dashboard-container',
            '.triage-container', 
            '.consultation-container',
            '.lab-container',
            '.billing-container',
            '.pharmacy-container',
            '.document-container'
        ];
        
        for (const selector of containerSelectors) {
            this.elements.container = document.querySelector(selector);
            if (this.elements.container) break;
        }
        
        if (!this.elements.container) {
            console.error('Station container not found');
            return false;
        }
        
        // Find div3-div7 elements
        this.elements.currentPatientDiv = document.querySelector('.div3');
        this.elements.actionsDiv = document.querySelector('.div4');
        this.elements.waitingDiv = document.querySelector('.div5');
        this.elements.skippedDiv = document.querySelector('.div6');
        this.elements.completedDiv = document.querySelector('.div7');
        
        // Validate critical elements exist
        const criticalElements = ['currentPatientDiv', 'actionsDiv', 'waitingDiv'];
        for (const elementName of criticalElements) {
            if (!this.elements[elementName]) {
                console.error(`Critical element ${elementName} not found`);
            }
        }
        
        return true;
    }
    
    /**
     * Setup event listeners for buttons and actions
     */
    setupEventListeners() {
        // Delegate event handling to container to handle dynamic content
        if (this.elements.container) {
            this.elements.container.addEventListener('click', (e) => {
                this.handleButtonClick(e);
            });
        }
        
        // Handle page visibility changes for refresh control
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseAutoRefresh();
            } else {
                this.resumeAutoRefresh();
            }
        });
    }
    
    /**
     * Handle button clicks with action routing
     */
    handleButtonClick(e) {
        const button = e.target.closest('button');
        if (!button) return;
        
        const action = this.getButtonAction(button);
        if (!action) return;
        
        e.preventDefault();
        
        // Check if button is disabled
        if (button.disabled || button.classList.contains('disabled')) {
            this.showAlert('Action not available at this time', 'warning');
            return;
        }
        
        // Execute action
        this.executeAction(action, button);
    }
    
    /**
     * Determine action from button attributes and onclick
     */
    getButtonAction(button) {
        // Check data-action attribute first
        if (button.dataset.action) {
            return button.dataset.action;
        }
        
        // Parse onclick attribute
        const onclick = button.getAttribute('onclick');
        if (onclick) {
            // Extract function name from onclick
            const match = onclick.match(/^(\w+)\s*\(/);
            if (match) {
                return match[1];
            }
        }
        
        return null;
    }
    
    /**
     * Execute station actions
     */
    async executeAction(action, button) {
        this.showLoading(button);
        
        try {
            switch (action) {
                case 'callNextPatient':
                    await this.callNextPatient();
                    break;
                case 'skipPatient':
                    await this.skipPatient();
                    break;
                case 'recallPatient':
                    await this.recallPatient();
                    break;
                case 'pushToConsultation':
                case 'pushToLab':
                case 'pushToBilling':
                case 'pushToPharmacy':
                case 'pushToDocument':
                    await this.pushToNextStation(action);
                    break;
                case 'forceCallPatient':
                    await this.forceCallPatient();
                    break;
                case 'completePatient':
                    await this.completePatient();
                    break;
                case 'openVitalsModal':
                    this.openVitalsModal();
                    break;
                case 'viewPatientProfile':
                    this.viewPatientProfile();
                    break;
                case 'viewReferral':
                    this.viewReferral();
                    break;
                default:
                    // Handle specific patient actions (with IDs)
                    if (action.startsWith('forceCallSpecificPatient')) {
                        const patientId = button.dataset.patientId;
                        await this.forceCallSpecificPatient(patientId);
                    } else if (action.startsWith('recallSpecificPatient')) {
                        const patientId = button.dataset.patientId;
                        await this.recallSpecificPatient(patientId);
                    } else {
                        console.warn('Unknown action:', action);
                    }
            }
        } catch (error) {
            console.error('Action failed:', error);
            this.showAlert('Action failed: ' + error.message, 'error');
        } finally {
            this.hideLoading(button);
        }
    }
    
    /**
     * Call next patient in queue
     */
    async callNextPatient() {
        const response = await this.makeAjaxRequest('call_next', {});
        if (response.success) {
            this.showAlert('Patient called successfully', 'success');
            await this.refreshData();
        } else {
            throw new Error(response.message || 'Failed to call next patient');
        }
    }
    
    /**
     * Skip current patient
     */
    async skipPatient() {
        if (!this.currentPatient) {
            throw new Error('No patient to skip');
        }
        
        const response = await this.makeAjaxRequest('skip_patient', {
            queue_entry_id: this.currentPatient.queue_entry_id
        });
        
        if (response.success) {
            this.showAlert('Patient skipped successfully', 'success');
            await this.refreshData();
        } else {
            throw new Error(response.message || 'Failed to skip patient');
        }
    }
    
    /**
     * Recall patient from skipped queue
     */
    async recallPatient() {
        // Get first skipped patient
        if (this.queueData.skipped.length === 0) {
            throw new Error('No skipped patients to recall');
        }
        
        const patient = this.queueData.skipped[0];
        await this.recallSpecificPatient(patient.queue_entry_id);
    }
    
    /**
     * Recall specific patient
     */
    async recallSpecificPatient(patientId) {
        const response = await this.makeAjaxRequest('recall_patient', {
            queue_entry_id: patientId
        });
        
        if (response.success) {
            this.showAlert('Patient recalled successfully', 'success');
            await this.refreshData();
        } else {
            throw new Error(response.message || 'Failed to recall patient');
        }
    }
    
    /**
     * Push patient to next station
     */
    async pushToNextStation(action) {
        if (!this.currentPatient) {
            throw new Error('No patient to push');
        }
        
        // Extract target station from action name
        const stationMap = {
            'pushToConsultation': 'consultation',
            'pushToLab': 'lab', 
            'pushToBilling': 'billing',
            'pushToPharmacy': 'pharmacy',
            'pushToDocument': 'document'
        };
        
        const targetStation = stationMap[action];
        if (!targetStation) {
            throw new Error('Invalid target station');
        }
        
        const response = await this.makeAjaxRequest('push_to_station', {
            queue_entry_id: this.currentPatient.queue_entry_id,
            target_station: targetStation
        });
        
        if (response.success) {
            this.showAlert(`Patient pushed to ${targetStation} successfully`, 'success');
            await this.refreshData();
        } else {
            throw new Error(response.message || 'Failed to push patient');
        }
    }
    
    /**
     * Force call specific patient
     */
    async forceCallSpecificPatient(patientId) {
        const response = await this.makeAjaxRequest('force_call', {
            queue_entry_id: patientId
        });
        
        if (response.success) {
            this.showAlert('Patient force called successfully', 'success');
            await this.refreshData();
        } else {
            throw new Error(response.message || 'Failed to force call patient');
        }
    }
    
    /**
     * Complete current patient
     */
    async completePatient() {
        if (!this.currentPatient) {
            throw new Error('No patient to complete');
        }
        
        const response = await this.makeAjaxRequest('complete_patient', {
            queue_entry_id: this.currentPatient.queue_entry_id
        });
        
        if (response.success) {
            this.showAlert('Patient completed successfully', 'success');
            await this.refreshData();
        } else {
            throw new Error(response.message || 'Failed to complete patient');
        }
    }
    
    /**
     * Make AJAX request to station endpoint
     */
    async makeAjaxRequest(action, data = {}) {
        const requestData = {
            ajax: true,
            action: action,
            station_type: this.stationType,
            station_id: this.stationId,
            employee_id: this.employeeId,
            ...data
        };
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(requestData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('AJAX request failed:', error);
            throw error;
        }
    }
    
    /**
     * Refresh queue data and update UI
     */
    async refreshData() {
        try {
            const response = await this.makeAjaxRequest('get_queue_data', {});
            if (response.success) {
                this.updateQueueData(response.data);
                this.renderCurrentPatient();
                this.renderQueues();
                this.updateActionButtons();
            }
        } catch (error) {
            console.error('Failed to refresh data:', error);
        }
    }
    
    /**
     * Update internal queue data
     */
    updateQueueData(data) {
        this.currentPatient = data.current_patient;
        this.queueData = {
            waiting: data.waiting_queue || [],
            skipped: data.skipped_queue || [],
            completed: data.completed_queue || [],
            inProgress: data.in_progress_queue || []
        };
    }
    
    /**
     * Render current patient (div3)
     */
    renderCurrentPatient() {
        if (!this.elements.currentPatientDiv) return;
        
        const sectionBody = this.elements.currentPatientDiv.querySelector('.section-body');
        if (!sectionBody) return;
        
        if (this.currentPatient) {
            sectionBody.innerHTML = this.generateCurrentPatientHTML(this.currentPatient);
        } else {
            sectionBody.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>No patient currently at this station</span>
                </div>
            `;
        }
    }
    
    /**
     * Generate HTML for current patient
     */
    generateCurrentPatientHTML(patient) {
        const queueCode = this.formatQueueCode(patient.queue_code);
        
        return `
            <div class="patient-card">
                <div class="patient-header">
                    <div class="patient-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="patient-info">
                        <h4>${this.escapeHtml(patient.patient_name)}</h4>
                        <div style="color: #7f8c8d; font-size: 14px;">
                            Queue: ${queueCode}
                        </div>
                    </div>
                </div>
                <div class="patient-details">
                    <div class="detail-item">
                        <span class="detail-label">Patient ID:</span>
                        <span>${this.escapeHtml(patient.patient_id)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">DOB:</span>
                        <span>${this.escapeHtml(patient.date_of_birth || 'N/A')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Barangay:</span>
                        <span>${this.escapeHtml(patient.barangay || 'N/A')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Priority:</span>
                        <span class="priority-${patient.priority_level || 'normal'}">
                            ${(patient.priority_level || 'NORMAL').toUpperCase()}
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Service:</span>
                        <span>${this.escapeHtml(patient.service_name || 'General')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Time Started:</span>
                        <span>${patient.time_started ? this.formatTime(patient.time_started) : 'N/A'}</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Render queue tables (div5, div6, div7)
     */
    renderQueues() {
        this.renderWaitingQueue();
        this.renderSkippedQueue();
        this.renderCompletedQueue();
    }
    
    /**
     * Render waiting queue (div5)
     */
    renderWaitingQueue() {
        if (!this.elements.waitingDiv) return;
        
        const sectionBody = this.elements.waitingDiv.querySelector('.section-body');
        if (!sectionBody) return;
        
        if (this.queueData.waiting.length > 0) {
            sectionBody.innerHTML = this.generateQueueTableHTML(this.queueData.waiting, 'waiting');
        } else {
            sectionBody.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>No patients in waiting queue</span>
                </div>
            `;
        }
    }
    
    /**
     * Render skipped queue (div6)
     */
    renderSkippedQueue() {
        if (!this.elements.skippedDiv) return;
        
        const sectionBody = this.elements.skippedDiv.querySelector('.section-body');
        if (!sectionBody) return;
        
        if (this.queueData.skipped.length > 0) {
            sectionBody.innerHTML = this.generateQueueTableHTML(this.queueData.skipped, 'skipped');
        } else {
            sectionBody.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>No skipped patients</span>
                </div>
            `;
        }
    }
    
    /**
     * Render completed queue (div7)
     */
    renderCompletedQueue() {
        if (!this.elements.completedDiv) return;
        
        const sectionBody = this.elements.completedDiv.querySelector('.section-body');
        if (!sectionBody) return;
        
        if (this.queueData.completed.length > 0) {
            sectionBody.innerHTML = this.generateQueueTableHTML(this.queueData.completed, 'completed');
        } else {
            sectionBody.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>No completed patients today</span>
                </div>
            `;
        }
    }
    
    /**
     * Generate HTML for queue tables
     */
    generateQueueTableHTML(patients, queueType) {
        let tableHTML = '<table class="queue-table"><thead><tr>';
        
        // Generate headers based on queue type
        if (queueType === 'waiting') {
            tableHTML += `
                <th>Queue Code</th>
                <th>Patient Name</th>
                <th>Priority</th>
                <th>Time In</th>
                <th>ETA</th>
                <th>Actions</th>
            `;
        } else if (queueType === 'skipped') {
            tableHTML += `
                <th>Queue Code</th>
                <th>Patient Name</th>
                <th>Skipped Time</th>
                <th>Actions</th>
            `;
        } else if (queueType === 'completed') {
            tableHTML += `
                <th>Queue Code</th>
                <th>Patient Name</th>
                <th>Completed</th>
                <th>Next Station</th>
            `;
        }
        
        tableHTML += '</tr></thead><tbody>';
        
        // Generate rows
        patients.forEach(patient => {
            tableHTML += '<tr>';
            
            const queueCode = this.formatQueueCode(patient.queue_code);
            
            if (queueType === 'waiting') {
                tableHTML += `
                    <td>${queueCode}</td>
                    <td>${this.escapeHtml(patient.patient_name)}</td>
                    <td class="priority-${patient.priority_level || 'normal'}">
                        ${(patient.priority_level || 'NORMAL').toUpperCase()}
                    </td>
                    <td>${this.formatTime(patient.time_in)}</td>
                    <td>${patient.estimated_wait || 'N/A'}</td>
                    <td class="queue-actions">
                        <button class="btn-primary" data-action="forceCallSpecificPatient" data-patient-id="${patient.queue_entry_id}">
                            Force Call
                        </button>
                    </td>
                `;
            } else if (queueType === 'skipped') {
                tableHTML += `
                    <td>${queueCode}</td>
                    <td>${this.escapeHtml(patient.patient_name)}</td>
                    <td>${patient.time_started ? this.formatTime(patient.time_started) : 'N/A'}</td>
                    <td class="queue-actions">
                        <button class="btn-success" data-action="recallSpecificPatient" data-patient-id="${patient.queue_entry_id}">
                            Recall
                        </button>
                    </td>
                `;
            } else if (queueType === 'completed') {
                tableHTML += `
                    <td>${queueCode}</td>
                    <td>${this.escapeHtml(patient.patient_name)}</td>
                    <td>${patient.time_completed ? this.formatTime(patient.time_completed) : 'N/A'}</td>
                    <td>${this.getNextStationName(patient.next_station)}</td>
                `;
            }
            
            tableHTML += '</tr>';
        });
        
        tableHTML += '</tbody></table>';
        return tableHTML;
    }
    
    /**
     * Update action buttons based on current state
     */
    updateActionButtons() {
        if (!this.elements.actionsDiv) return;
        
        const buttons = this.elements.actionsDiv.querySelectorAll('button');
        
        buttons.forEach(button => {
            const action = this.getButtonAction(button);
            
            // Enable/disable buttons based on current state
            switch (action) {
                case 'openVitalsModal':
                case 'viewPatientProfile':
                case 'viewReferral':
                case 'skipPatient':
                case 'completePatient':
                case 'pushToConsultation':
                case 'pushToLab':
                case 'pushToBilling':
                case 'pushToPharmacy':
                case 'pushToDocument':
                    button.disabled = !this.currentPatient;
                    break;
                case 'recallPatient':
                    button.disabled = this.queueData.skipped.length === 0;
                    break;
                case 'callNextPatient':
                case 'forceCallPatient':
                    // These are always enabled
                    button.disabled = false;
                    break;
            }
        });
    }
    
    /**
     * Format queue code for display (use existing formatter)
     */
    formatQueueCode(queueCode) {
        if (typeof formatQueueCodeForPublicDisplay === 'function') {
            return formatQueueCodeForPublicDisplay(queueCode);
        }
        return queueCode; // Fallback to original if formatter not available
    }
    
    /**
     * Format time for display
     */
    formatTime(timeString) {
        if (!timeString) return 'N/A';
        
        try {
            const date = new Date(timeString);
            return date.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: false 
            });
        } catch (error) {
            return timeString;
        }
    }
    
    /**
     * Get next station display name
     */
    getNextStationName(stationType) {
        const stationNames = {
            'consultation': 'Consultation',
            'lab': 'Laboratory',
            'billing': 'Billing',
            'pharmacy': 'Pharmacy',
            'document': 'Document Services',
            'triage': 'Triage'
        };
        
        return stationNames[stationType] || (stationType ? stationType.charAt(0).toUpperCase() + stationType.slice(1) : 'N/A');
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    /**
     * Show loading state on button
     */
    showLoading(button) {
        button.disabled = true;
        button.dataset.originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    }
    
    /**
     * Hide loading state on button
     */
    hideLoading(button) {
        button.disabled = false;
        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
            delete button.dataset.originalText;
        }
    }
    
    /**
     * Show alert message with improved DOM handling
     */
    showAlert(message, type = 'info') {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => {
            if (alert.parentElement) {
                alert.remove();
            }
        });
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        
        let icon = 'fa-info-circle';
        if (type === 'success') icon = 'fa-check-circle';
        else if (type === 'error') icon = 'fa-exclamation-triangle';
        else if (type === 'warning') icon = 'fa-exclamation-circle';
        
        alertDiv.innerHTML = `
            <i class="fas ${icon}"></i>
            <span>${this.escapeHtml(message)}</span>
            <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
        `;
        
        // Try to find the best place to insert the alert
        let insertLocation = null;
        const header = document.querySelector('.page-header');
        const breadcrumb = document.querySelector('.breadcrumb');
        
        if (header && this.elements.container) {
            insertLocation = { parent: this.elements.container, referenceNode: header.nextSibling };
        } else if (breadcrumb && this.elements.container) {
            insertLocation = { parent: this.elements.container, referenceNode: breadcrumb.nextSibling };
        } else if (this.elements.container) {
            insertLocation = { parent: this.elements.container, referenceNode: this.elements.container.firstChild };
        } else {
            // Fallback to body
            insertLocation = { parent: document.body, referenceNode: document.body.firstChild };
        }
        
        try {
            insertLocation.parent.insertBefore(alertDiv, insertLocation.referenceNode);
        } catch (error) {
            // Fallback: append to container or body
            (this.elements.container || document.body).appendChild(alertDiv);
        }
        
        // Auto-remove success and info alerts after 5 seconds
        if (type === 'success' || type === 'info') {
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    }
    
    /**
     * Load initial data
     */
    async loadInitialData() {
        await this.refreshData();
    }
    
    /**
     * Start auto-refresh timer
     */
    startAutoRefresh() {
        this.refreshTimer = setInterval(() => {
            this.refreshData();
        }, this.refreshInterval);
    }
    
    /**
     * Pause auto-refresh
     */
    pauseAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }
    
    /**
     * Resume auto-refresh
     */
    resumeAutoRefresh() {
        if (!this.refreshTimer) {
            this.startAutoRefresh();
        }
    }
    
    /**
     * Modal functions (to be implemented per station needs)
     */
    openVitalsModal() {
        // This should be overridden by specific station implementations
        console.log('Opening vitals modal for:', this.currentPatient);
    }
    
    viewPatientProfile() {
        if (!this.currentPatient) return;
        // Open patient profile in new window/modal
        window.open(`../management/admin/patient-records/patient_profile.php?id=${this.currentPatient.patient_id}`, '_blank');
    }
    
    viewReferral() {
        if (!this.currentPatient) return;
        // View referral details
        console.log('Viewing referral for:', this.currentPatient);
    }
    
    /**
     * Force call patient (general)
     */
    async forceCallPatient() {
        // Get list of waiting patients and show selection modal
        if (this.queueData.waiting.length === 0) {
            this.showAlert('No patients in waiting queue', 'warning');
            return;
        }
        
        // For now, force call the first waiting patient
        await this.forceCallSpecificPatient(this.queueData.waiting[0].queue_entry_id);
    }
    
    /**
     * Destroy station manager (cleanup)
     */
    destroy() {
        this.pauseAutoRefresh();
        
        // Remove event listeners
        if (this.elements.container) {
            this.elements.container.removeEventListener('click', this.handleButtonClick);
        }
        
        console.log(`StationManager destroyed for ${this.stationType} station`);
    }
}

// Global station manager instance
window.stationManager = null;

/**
 * Initialize station manager with configuration
 */
function initializeStationManager(config) {
    if (window.stationManager) {
        window.stationManager.destroy();
    }
    
    window.stationManager = new StationManager(config);
}

/**
 * Legacy function wrappers for backward compatibility
 */
function callNextPatient() {
    if (window.stationManager) {
        window.stationManager.callNextPatient();
    }
}

function skipPatient() {
    if (window.stationManager) {
        window.stationManager.skipPatient();
    }
}

function recallPatient() {
    if (window.stationManager) {
        window.stationManager.recallPatient();
    }
}

function pushToConsultation() {
    if (window.stationManager) {
        window.stationManager.pushToNextStation('pushToConsultation');
    }
}

function pushToLab() {
    if (window.stationManager) {
        window.stationManager.pushToNextStation('pushToLab');
    }
}

function pushToBilling() {
    if (window.stationManager) {
        window.stationManager.pushToNextStation('pushToBilling');
    }
}

function pushToPharmacy() {
    if (window.stationManager) {
        window.stationManager.pushToNextStation('pushToPharmacy');
    }
}

function pushToDocument() {
    if (window.stationManager) {
        window.stationManager.pushToNextStation('pushToDocument');
    }
}

function openVitalsModal() {
    if (window.stationManager) {
        window.stationManager.openVitalsModal();
    }
}

function viewPatientProfile() {
    if (window.stationManager) {
        window.stationManager.viewPatientProfile();
    }
}

function viewReferral() {
    if (window.stationManager) {
        window.stationManager.viewReferral();
    }
}

function forceCallPatient() {
    if (window.stationManager) {
        window.stationManager.forceCallPatient();
    }
}

function forceCallSpecificPatient(patientId) {
    if (window.stationManager) {
        window.stationManager.forceCallSpecificPatient(patientId);
    }
}

function recallSpecificPatient(patientId) {
    if (window.stationManager) {
        window.stationManager.recallSpecificPatient(patientId);
    }
}