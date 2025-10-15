/**
 * Queue Synchronization System
 * Purpose: Real-time synchronization between station interfaces and public displays
 * Features: Event-driven updates, cross-station communication, public display refresh
 */

class QueueSyncManager {
    constructor() {
        this.syncInterval = 5000; // 5 seconds for public display sync
        this.eventBus = new EventTarget();
        this.publicDisplays = [];
        this.activeStations = new Map();
        this.lastSyncTime = Date.now();
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.startSyncLoop();
        this.detectPublicDisplays();
        
        console.log('QueueSyncManager initialized');
    }
    
    /**
     * Setup event listeners for queue changes
     */
    setupEventListeners() {
        // Listen for station manager events
        this.eventBus.addEventListener('patient-called', this.handlePatientCalled.bind(this));
        this.eventBus.addEventListener('patient-pushed', this.handlePatientPushed.bind(this));
        this.eventBus.addEventListener('patient-skipped', this.handlePatientSkipped.bind(this));
        this.eventBus.addEventListener('patient-recalled', this.handlePatientRecalled.bind(this));
        this.eventBus.addEventListener('patient-completed', this.handlePatientCompleted.bind(this));
        
        // Listen for public display registration
        this.eventBus.addEventListener('display-register', this.handleDisplayRegister.bind(this));
    }
    
    /**
     * Register a station manager
     */
    registerStation(stationType, stationId, stationManager) {
        const key = `${stationType}-${stationId}`;
        this.activeStations.set(key, stationManager);
        
        console.log(`Station registered: ${key}`);
        
        // Notify other stations of registration
        this.broadcastStationEvent('station-registered', {
            stationType,
            stationId,
            timestamp: Date.now()
        });
    }
    
    /**
     * Unregister a station manager
     */
    unregisterStation(stationType, stationId) {
        const key = `${stationType}-${stationId}`;
        this.activeStations.delete(key);
        
        console.log(`Station unregistered: ${key}`);
    }
    
    /**
     * Broadcast queue event to all registered stations and displays
     */
    broadcastQueueEvent(eventType, data) {
        // Add timestamp and event ID
        const eventData = {
            ...data,
            eventId: this.generateEventId(),
            timestamp: Date.now(),
            eventType
        };
        
        // Dispatch to event bus
        this.eventBus.dispatchEvent(new CustomEvent(eventType, { detail: eventData }));
        
        // Update all active stations if they're affected
        this.updateAffectedStations(eventData);
        
        // Schedule public display updates
        this.schedulePublicDisplayUpdate(eventData);
        
        console.log(`Queue event broadcasted: ${eventType}`, eventData);
    }
    
    /**
     * Update stations affected by the queue event
     */
    updateAffectedStations(eventData) {
        const affectedStations = this.getAffectedStations(eventData);
        
        affectedStations.forEach(stationKey => {
            const stationManager = this.activeStations.get(stationKey);
            if (stationManager) {
                // Trigger refresh on affected station
                setTimeout(() => {
                    stationManager.refreshData();
                }, 100); // Small delay to ensure backend is updated
            }
        });
    }
    
    /**
     * Determine which stations are affected by an event
     */
    getAffectedStations(eventData) {
        const affected = new Set();
        
        switch (eventData.eventType) {
            case 'patient-called':
            case 'patient-skipped':
            case 'patient-recalled':
                // Affects the source station
                if (eventData.sourceStation && eventData.sourceStationId) {
                    affected.add(`${eventData.sourceStation}-${eventData.sourceStationId}`);
                }
                break;
                
            case 'patient-pushed':
                // Affects both source and target stations
                if (eventData.sourceStation && eventData.sourceStationId) {
                    affected.add(`${eventData.sourceStation}-${eventData.sourceStationId}`);
                }
                if (eventData.targetStation) {
                    // Update all stations of target type
                    this.activeStations.forEach((manager, key) => {
                        if (key.startsWith(eventData.targetStation + '-')) {
                            affected.add(key);
                        }
                    });
                }
                break;
                
            case 'patient-completed':
                // Affects the station where patient completed
                if (eventData.sourceStation && eventData.sourceStationId) {
                    affected.add(`${eventData.sourceStation}-${eventData.sourceStationId}`);
                }
                break;
        }
        
        return Array.from(affected);
    }
    
    /**
     * Schedule public display update
     */
    schedulePublicDisplayUpdate(eventData) {
        // Immediate update for critical events
        const criticalEvents = ['patient-called', 'patient-pushed', 'patient-completed'];
        
        if (criticalEvents.includes(eventData.eventType)) {
            this.updatePublicDisplays(eventData);
        } else {
            // Batched update for less critical events
            clearTimeout(this.publicDisplayUpdateTimer);
            this.publicDisplayUpdateTimer = setTimeout(() => {
                this.updatePublicDisplays(eventData);
            }, 2000);
        }
    }
    
    /**
     * Update public displays
     */
    async updatePublicDisplays(eventData) {
        // Get affected station types
        const stationTypes = this.getAffectedStationTypes(eventData);
        
        for (const stationType of stationTypes) {
            await this.refreshPublicDisplay(stationType);
        }
    }
    
    /**
     * Get station types affected by event
     */
    getAffectedStationTypes(eventData) {
        const types = new Set();
        
        if (eventData.sourceStation) {
            types.add(eventData.sourceStation);
        }
        
        if (eventData.targetStation) {
            types.add(eventData.targetStation);
        }
        
        // If no specific stations, update all
        if (types.size === 0) {
            types.add('triage');
            types.add('consultation');
            types.add('lab');
            types.add('billing');
            types.add('pharmacy');
            types.add('document');
        }
        
        return Array.from(types);
    }
    
    /**
     * Refresh public display for a specific station type
     */
    async refreshPublicDisplay(stationType) {
        // Send update signal to public display windows
        this.publicDisplays.forEach(display => {
            if (display.stationType === stationType && !display.window.closed) {
                try {
                    display.window.postMessage({
                        type: 'queue-update',
                        stationType: stationType,
                        timestamp: Date.now()
                    }, '*');
                } catch (error) {
                    console.warn('Failed to update public display:', error);
                }
            }
        });
        
        // Also trigger server-side refresh for displays without JavaScript communication
        try {
            await fetch(`../queueing/public_display_${stationType}.php?refresh=1`, {
                method: 'GET',
                headers: {
                    'X-Refresh-Trigger': 'queue-sync'
                }
            });
        } catch (error) {
            console.warn(`Failed to trigger server refresh for ${stationType}:`, error);
        }
    }
    
    /**
     * Handle patient called event
     */
    handlePatientCalled(event) {
        const data = event.detail;
        console.log('Patient called event:', data);
        
        // Additional processing for patient called
        this.logQueueEvent('patient-called', data);
    }
    
    /**
     * Handle patient pushed event
     */
    handlePatientPushed(event) {
        const data = event.detail;
        console.log('Patient pushed event:', data);
        
        // Additional processing for patient pushed
        this.logQueueEvent('patient-pushed', data);
    }
    
    /**
     * Handle patient skipped event
     */
    handlePatientSkipped(event) {
        const data = event.detail;
        console.log('Patient skipped event:', data);
        
        // Additional processing for patient skipped
        this.logQueueEvent('patient-skipped', data);
    }
    
    /**
     * Handle patient recalled event
     */
    handlePatientRecalled(event) {
        const data = event.detail;
        console.log('Patient recalled event:', data);
        
        // Additional processing for patient recalled
        this.logQueueEvent('patient-recalled', data);
    }
    
    /**
     * Handle patient completed event
     */
    handlePatientCompleted(event) {
        const data = event.detail;
        console.log('Patient completed event:', data);
        
        // Additional processing for patient completed
        this.logQueueEvent('patient-completed', data);
    }
    
    /**
     * Handle public display registration
     */
    handleDisplayRegister(event) {
        const data = event.detail;
        console.log('Public display registered:', data);
        
        this.publicDisplays.push(data);
    }
    
    /**
     * Log queue event for audit trail
     */
    async logQueueEvent(eventType, data) {
        try {
            await fetch('/api/queue_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'log_event',
                    event_type: eventType,
                    event_data: data,
                    timestamp: Date.now()
                })
            });
        } catch (error) {
            console.warn('Failed to log queue event:', error);
        }
    }
    
    /**
     * Start synchronization loop
     */
    startSyncLoop() {
        this.syncTimer = setInterval(() => {
            this.performPeriodicSync();
        }, this.syncInterval);
    }
    
    /**
     * Perform periodic synchronization
     */
    async performPeriodicSync() {
        // Update station data
        this.activeStations.forEach(async (stationManager, key) => {
            try {
                await stationManager.refreshData();
            } catch (error) {
                console.warn(`Failed to sync station ${key}:`, error);
            }
        });
        
        // Clean up closed public display windows
        this.cleanupPublicDisplays();
        
        this.lastSyncTime = Date.now();
    }
    
    /**
     * Clean up closed public display windows
     */
    cleanupPublicDisplays() {
        this.publicDisplays = this.publicDisplays.filter(display => {
            return display.window && !display.window.closed;
        });
    }
    
    /**
     * Detect and register public displays
     */
    detectPublicDisplays() {
        // Listen for messages from public display windows
        window.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'public-display-ready') {
                this.registerPublicDisplay(event.data);
            }
        });
    }
    
    /**
     * Register a public display window
     */
    registerPublicDisplay(displayData) {
        const display = {
            stationType: displayData.stationType,
            window: displayData.window || event.source,
            id: displayData.id || this.generateEventId(),
            registeredAt: Date.now()
        };
        
        this.publicDisplays.push(display);
        
        console.log(`Public display registered: ${display.stationType}`);
    }
    
    /**
     * Broadcast station event (for cross-station communication)
     */
    broadcastStationEvent(eventType, data) {
        this.activeStations.forEach((stationManager, key) => {
            try {
                stationManager.handleStationEvent(eventType, data);
            } catch (error) {
                // Ignore stations that don't implement this method
            }
        });
    }
    
    /**
     * Generate unique event ID
     */
    generateEventId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }
    
    /**
     * Get sync status
     */
    getSyncStatus() {
        return {
            lastSyncTime: this.lastSyncTime,
            activeStations: this.activeStations.size,
            publicDisplays: this.publicDisplays.length,
            isRunning: !!this.syncTimer
        };
    }
    
    /**
     * Pause synchronization
     */
    pauseSync() {
        if (this.syncTimer) {
            clearInterval(this.syncTimer);
            this.syncTimer = null;
        }
    }
    
    /**
     * Resume synchronization
     */
    resumeSync() {
        if (!this.syncTimer) {
            this.startSyncLoop();
        }
    }
    
    /**
     * Destroy sync manager
     */
    destroy() {
        this.pauseSync();
        
        // Clear timers
        if (this.publicDisplayUpdateTimer) {
            clearTimeout(this.publicDisplayUpdateTimer);
        }
        
        // Close public display windows
        this.publicDisplays.forEach(display => {
            if (display.window && !display.window.closed) {
                try {
                    display.window.close();
                } catch (error) {
                    // Ignore errors
                }
            }
        });
        
        // Clear collections
        this.activeStations.clear();
        this.publicDisplays = [];
        
        console.log('QueueSyncManager destroyed');
    }
}

// Global queue synchronization manager
window.queueSyncManager = new QueueSyncManager();

/**
 * Helper functions for station managers to trigger events
 */
function triggerQueueEvent(eventType, data) {
    if (window.queueSyncManager) {
        window.queueSyncManager.broadcastQueueEvent(eventType, data);
    }
}

function registerStationManager(stationType, stationId, stationManager) {
    if (window.queueSyncManager) {
        window.queueSyncManager.registerStation(stationType, stationId, stationManager);
    }
}

function unregisterStationManager(stationType, stationId) {
    if (window.queueSyncManager) {
        window.queueSyncManager.unregisterStation(stationType, stationId);
    }
}

/**
 * Public display helper functions
 */
function registerPublicDisplay(stationType, windowRef = null) {
    if (window.queueSyncManager) {
        window.queueSyncManager.registerPublicDisplay({
            stationType: stationType,
            window: windowRef,
            id: Date.now().toString()
        });
    }
}

function openPublicDisplay(stationType) {
    const url = `public_display_${stationType}.php`;
    const displayWindow = window.open(url, `public_${stationType}`, 'fullscreen=yes,scrollbars=no');
    
    if (displayWindow) {
        registerPublicDisplay(stationType, displayWindow);
        return displayWindow;
    }
    
    return null;
}