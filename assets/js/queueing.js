/**
 * Queueing Module JavaScript
 * Handles client-side functionality for queue management system
 * Integrates with queue_api.php for real-time operations
 */

// Queue Management API Client
const QueueAPI = {
    baseUrl: '/pages/queueing/queue_api.php',
    
    // Make API request
    async request(action, data = {}) {
        try {
            const response = await fetch(this.baseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action, ...data })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'API request failed');
            }
            
            return result.data;
        } catch (error) {
            console.error('Queue API Error:', error);
            throw error;
        }
    },
    
    // Get queue data for station
    async getQueue(stationId) {
        return await this.request('get_queue', { station_id: stationId });
    },
    
    // Get queue statistics
    async getStats(stationId = null, date = null) {
        return await this.request('get_stats', { station_id: stationId, date });
    },
    
    // Call next patient
    async callNext(stationId) {
        return await this.request('call_next', { station_id: stationId });
    },
    
    // Skip patient
    async skipPatient(queueEntryId, reason) {
        return await this.request('skip_patient', { 
            queue_entry_id: queueEntryId, 
            reason 
        });
    },
    
    // Complete patient
    async completePatient(queueEntryId, notes = '') {
        return await this.request('complete_patient', { 
            queue_entry_id: queueEntryId, 
            notes 
        });
    },
    
    // Route patient to another station
    async routePatient(queueEntryId, toStationType, notes = '') {
        return await this.request('route_patient', { 
            queue_entry_id: queueEntryId, 
            to_station_type: toStationType, 
            notes 
        });
    },
    
    // Recall skipped patient
    async recallPatient(queueEntryId) {
        return await this.request('recall_patient', { 
            queue_entry_id: queueEntryId 
        });
    },
    
    // Toggle station status
    async toggleStation(stationId) {
        return await this.request('toggle_station', { 
            station_id: stationId 
        });
    },
    
    // Get patient details
    async getPatientDetails(patientId, appointmentId = null) {
        return await this.request('get_patient_details', { 
            patient_id: patientId, 
            appointment_id: appointmentId 
        });
    }
};

// Queue Manager - Main functionality
const QueueManager = {
    currentStationId: null,
    refreshInterval: null,
    
    // Initialize queue management interface
    init: function(stationId = null) {
        this.currentStationId = stationId;
        
        // Bind event handlers
        this.bindEventHandlers();
        
        // Start auto-refresh if station ID is provided
        if (stationId) {
            this.startAutoRefresh();
        }
        
        console.log('Queue Manager initialized for station:', stationId);
    },
    
    // Bind event handlers for queue actions
    bindEventHandlers: function() {
        // Call next patient button
        document.addEventListener('click', async (e) => {
            if (e.target.matches('[data-action="call-next"]')) {
                e.preventDefault();
                await this.handleCallNext();
            }
        });
        
        // Skip patient buttons
        document.addEventListener('click', async (e) => {
            if (e.target.matches('[data-action="skip-patient"]')) {
                e.preventDefault();
                const queueEntryId = e.target.dataset.queueId;
                const reason = prompt('Reason for skipping patient:') || 'No reason provided';
                await this.handleSkipPatient(queueEntryId, reason);
            }
        });
        
        // Complete patient buttons
        document.addEventListener('click', async (e) => {
            if (e.target.matches('[data-action="complete-patient"]')) {
                e.preventDefault();
                const queueEntryId = e.target.dataset.queueId;
                await this.handleCompletePatient(queueEntryId);
            }
        });
        
        // Route patient buttons
        document.addEventListener('click', async (e) => {
            if (e.target.matches('[data-action="route-patient"]')) {
                e.preventDefault();
                const queueEntryId = e.target.dataset.queueId;
                const toStation = e.target.dataset.toStation;
                await this.handleRoutePatient(queueEntryId, toStation);
            }
        });
        
        // Recall patient buttons
        document.addEventListener('click', async (e) => {
            if (e.target.matches('[data-action="recall-patient"]')) {
                e.preventDefault();
                const queueEntryId = e.target.dataset.queueId;
                await this.handleRecallPatient(queueEntryId);
            }
        });
        
        // Toggle station buttons
        document.addEventListener('click', async (e) => {
            if (e.target.matches('[data-action="toggle-station"]')) {
                e.preventDefault();
                const stationId = e.target.dataset.stationId || this.currentStationId;
                await this.handleToggleStation(stationId);
            }
        });
    },
    
    // Handle call next patient
    async handleCallNext() {
        try {
            this.showLoading('Calling next patient...');
            const result = await QueueAPI.callNext(this.currentStationId);
            this.showSuccess('Next patient called successfully');
            await this.refreshDisplay();
        } catch (error) {
            this.showError('Failed to call next patient: ' + error.message);
        }
    },
    
    // Handle skip patient
    async handleSkipPatient(queueEntryId, reason) {
        try {
            this.showLoading('Skipping patient...');
            await QueueAPI.skipPatient(queueEntryId, reason);
            this.showSuccess('Patient skipped successfully');
            await this.refreshDisplay();
        } catch (error) {
            this.showError('Failed to skip patient: ' + error.message);
        }
    },
    
    // Handle complete patient
    async handleCompletePatient(queueEntryId) {
        try {
            this.showLoading('Completing patient...');
            await QueueAPI.completePatient(queueEntryId);
            this.showSuccess('Patient completed successfully');
            await this.refreshDisplay();
        } catch (error) {
            this.showError('Failed to complete patient: ' + error.message);
        }
    },
    
    // Handle route patient
    async handleRoutePatient(queueEntryId, toStation) {
        try {
            this.showLoading('Routing patient...');
            await QueueAPI.routePatient(queueEntryId, toStation);
            this.showSuccess(`Patient routed to ${toStation} successfully`);
            await this.refreshDisplay();
        } catch (error) {
            this.showError('Failed to route patient: ' + error.message);
        }
    },
    
    // Handle recall patient
    async handleRecallPatient(queueEntryId) {
        try {
            this.showLoading('Recalling patient...');
            await QueueAPI.recallPatient(queueEntryId);
            this.showSuccess('Patient recalled successfully');
            await this.refreshDisplay();
        } catch (error) {
            this.showError('Failed to recall patient: ' + error.message);
        }
    },
    
    // Handle toggle station
    async handleToggleStation(stationId) {
        try {
            this.showLoading('Toggling station status...');
            const result = await QueueAPI.toggleStation(stationId);
            this.showSuccess(`Station ${result.action} successfully`);
            await this.refreshDisplay();
        } catch (error) {
            this.showError('Failed to toggle station: ' + error.message);
        }
    },
    
    // Refresh queue display
    async refreshDisplay() {
        if (!this.currentStationId) return;
        
        try {
            const queueData = await QueueAPI.getQueue(this.currentStationId);
            const stats = await QueueAPI.getStats(this.currentStationId);
            
            // Update UI elements
            this.updateQueueDisplay(queueData);
            this.updateStatsDisplay(stats);
            
        } catch (error) {
            console.error('Failed to refresh display:', error);
        }
    },
    
    // Update queue display elements
    updateQueueDisplay: function(queueData) {
        // Update waiting queue
        const waitingContainer = document.getElementById('waiting-queue');
        if (waitingContainer && queueData.waiting_queue) {
            waitingContainer.innerHTML = this.renderQueueList(queueData.waiting_queue, 'waiting');
        }
        
        // Update skipped queue
        const skippedContainer = document.getElementById('skipped-queue');
        if (skippedContainer && queueData.skipped_queue) {
            skippedContainer.innerHTML = this.renderQueueList(queueData.skipped_queue, 'skipped');
        }
        
        // Update completed queue
        const completedContainer = document.getElementById('completed-queue');
        if (completedContainer && queueData.completed_today) {
            completedContainer.innerHTML = this.renderQueueList(queueData.completed_today, 'completed');
        }
    },
    
    // Update statistics display
    updateStatsDisplay: function(stats) {
        // Update stat counters
        const statElements = {
            'waiting-count': stats.waiting_count || 0,
            'in-progress-count': stats.in_progress_count || 0,
            'completed-count': stats.completed_count || 0,
            'skipped-count': stats.skipped_count || 0
        };
        
        Object.entries(statElements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    },
    
    // Render queue list HTML
    renderQueueList: function(queueItems, type) {
        if (!queueItems || queueItems.length === 0) {
            return '<p class="no-patients">No patients in queue</p>';
        }
        
        return queueItems.map(item => `
            <div class="queue-item" data-queue-id="${item.queue_entry_id}">
                <div class="patient-info">
                    <strong>${item.queue_code}</strong> - ${item.patient_name || 'Unknown'}
                    <small>Priority: ${item.priority_level}</small>
                </div>
                <div class="queue-actions">
                    ${this.renderActionButtons(item, type)}
                </div>
            </div>
        `).join('');
    },
    
    // Render action buttons for queue items
    renderActionButtons: function(item, type) {
        switch (type) {
            case 'waiting':
                return `
                    <button class="btn btn-sm btn-warning" data-action="skip-patient" data-queue-id="${item.queue_entry_id}">Skip</button>
                `;
            case 'skipped':
                return `
                    <button class="btn btn-sm btn-success" data-action="recall-patient" data-queue-id="${item.queue_entry_id}">Recall</button>
                `;
            default:
                return '';
        }
    },
    
    // Start auto-refresh
    startAutoRefresh: function(interval = 30000) {
        this.stopAutoRefresh();
        this.refreshInterval = setInterval(() => {
            this.refreshDisplay();
        }, interval);
    },
    
    // Stop auto-refresh
    stopAutoRefresh: function() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    },
    
    // Show loading message
    showLoading: function(message) {
        this.showMessage(message, 'info');
    },
    
    // Show success message
    showSuccess: function(message) {
        this.showMessage(message, 'success');
    },
    
    // Show error message
    showError: function(message) {
        this.showMessage(message, 'error');
    },
    
    // Show message (generic)
    showMessage: function(message, type) {
        // Create or update message element
        let messageEl = document.getElementById('queue-message');
        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.id = 'queue-message';
            messageEl.className = 'queue-message';
            document.body.appendChild(messageEl);
        }
        
        messageEl.textContent = message;
        messageEl.className = `queue-message ${type}`;
        messageEl.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            messageEl.style.display = 'none';
        }, 5000);
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Auto-initialize if station ID is found
    const stationIdElement = document.querySelector('[data-station-id]');
    if (stationIdElement) {
        const stationId = stationIdElement.dataset.stationId;
        QueueManager.init(stationId);
    }
    
    console.log('Queueing module loaded');
});

// Export for manual initialization
window.QueueAPI = QueueAPI;
window.QueueManager = QueueManager;