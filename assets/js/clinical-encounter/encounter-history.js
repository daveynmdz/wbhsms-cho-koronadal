/**
 * Encounter History JavaScript
 * Clinical Encounter Module - CHO Koronadal Healthcare Management System
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeEncounterHistory();
});

function initializeEncounterHistory() {
    setupSearchAndFilters();
    setupTableInteractions();
    setupExportFunctions();
}

/**
 * Search and Filter Setup
 */
function setupSearchAndFilters() {
    const searchInput = document.getElementById('searchEncounters');
    const statusFilter = document.getElementById('statusFilter');
    const doctorFilter = document.getElementById('doctorFilter');
    const dateFilter = document.getElementById('dateFilter');
    const resetBtn = document.getElementById('resetFiltersBtn');
    
    // Real-time search
    let searchTimeout;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filterEncounters();
            }, 300);
        });
    }
    
    // Filter change handlers
    if (statusFilter) statusFilter.addEventListener('change', filterEncounters);
    if (doctorFilter) doctorFilter.addEventListener('change', filterEncounters);
    if (dateFilter) dateFilter.addEventListener('change', filterEncounters);
    
    // Reset filters
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            resetAllFilters();
        });
    }
}

function filterEncounters() {
    const searchTerm = document.getElementById('searchEncounters').value.toLowerCase();
    const selectedStatus = document.getElementById('statusFilter').value;
    const selectedDoctor = document.getElementById('doctorFilter').value;
    const selectedDate = document.getElementById('dateFilter').value;
    
    const rows = document.querySelectorAll('#encountersTable tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const matchesSearch = checkSearchMatch(row, searchTerm);
        const matchesStatus = !selectedStatus || row.dataset.status === selectedStatus;
        const matchesDoctor = !selectedDoctor || row.dataset.doctor === selectedDoctor;
        const matchesDate = !selectedDate || row.dataset.date === selectedDate;
        
        const shouldShow = matchesSearch && matchesStatus && matchesDoctor && matchesDate;
        
        if (shouldShow) {
            row.style.display = '';
            visibleCount++;
            
            // Highlight search terms
            if (searchTerm) {
                highlightSearchTerm(row, searchTerm);
            } else {
                removeHighlights(row);
            }
        } else {
            row.style.display = 'none';
            removeHighlights(row);
        }
    });
    
    updateEncounterCount(visibleCount);
    updateNoResultsMessage(visibleCount);
}

function checkSearchMatch(row, searchTerm) {
    if (!searchTerm) return true;
    
    const searchableColumns = [1, 2, 3, 4]; // Date, Patient, Doctor, Diagnosis columns
    
    for (let colIndex of searchableColumns) {
        const cell = row.cells[colIndex];
        if (cell && cell.textContent.toLowerCase().includes(searchTerm)) {
            return true;
        }
    }
    
    return false;
}

function highlightSearchTerm(row, searchTerm) {
    const searchableColumns = [1, 2, 3, 4];
    
    searchableColumns.forEach(colIndex => {
        const cell = row.cells[colIndex];
        if (cell) {
            const originalText = cell.textContent;
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            const highlightedText = originalText.replace(regex, '<mark style="background: yellow; padding: 2px;">$1</mark>');
            
            if (highlightedText !== originalText) {
                cell.innerHTML = highlightedText;
            }
        }
    });
}

function removeHighlights(row) {
    const marks = row.querySelectorAll('mark');
    marks.forEach(mark => {
        mark.outerHTML = mark.textContent;
    });
}

function resetAllFilters() {
    document.getElementById('searchEncounters').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('doctorFilter').value = '';
    document.getElementById('dateFilter').value = '';
    
    filterEncounters();
}

function updateEncounterCount(count) {
    const counterElement = document.getElementById('encounterCount');
    if (counterElement) {
        counterElement.textContent = `Showing ${count} encounters`;
    }
}

function updateNoResultsMessage(count) {
    let noResultsMsg = document.getElementById('noResultsMessage');
    
    if (count === 0) {
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('tr');
            noResultsMsg.id = 'noResultsMessage';
            noResultsMsg.innerHTML = `
                <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                    <div style="font-size: 1.1rem; margin-bottom: 8px;">No encounters found</div>
                    <div style="font-size: 0.9rem;">Try adjusting your search criteria or filters</div>
                </td>
            `;
            document.querySelector('#encountersTable tbody').appendChild(noResultsMsg);
        }
        noResultsMsg.style.display = '';
    } else {
        if (noResultsMsg) {
            noResultsMsg.style.display = 'none';
        }
    }
}

/**
 * Table Interactions Setup
 */
function setupTableInteractions() {
    // Add hover effects and click handlers
    const tableRows = document.querySelectorAll('#encountersTable tbody tr[data-encounter-id]');
    
    tableRows.forEach(row => {
        // Double-click to view encounter
        row.addEventListener('dblclick', function() {
            const encounterId = this.dataset.encounterId;
            viewEncounter(encounterId);
        });
        
        // Context menu (right-click) for quick actions
        row.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            showContextMenu(e, this.dataset.encounterId);
        });
    });
    
    // Sort functionality
    setupTableSorting();
}

function setupTableSorting() {
    const headers = document.querySelectorAll('#encountersTable th');
    
    headers.forEach((header, index) => {
        if (index === 8) return; // Skip actions column
        
        header.style.cursor = 'pointer';
        header.style.userSelect = 'none';
        
        header.addEventListener('click', function() {
            sortTable(index, this);
        });
        
        // Add sort indicators
        const sortIcon = document.createElement('i');
        sortIcon.className = 'fas fa-sort sort-icon';
        sortIcon.style.marginLeft = '8px';
        sortIcon.style.opacity = '0.5';
        header.appendChild(sortIcon);
    });
}

function sortTable(columnIndex, headerElement) {
    const table = document.getElementById('encountersTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr[data-encounter-id]'));
    
    // Determine sort direction
    const currentSort = headerElement.dataset.sort || 'asc';
    const newSort = currentSort === 'asc' ? 'desc' : 'asc';
    headerElement.dataset.sort = newSort;
    
    // Update sort icons
    document.querySelectorAll('.sort-icon').forEach(icon => {
        icon.className = 'fas fa-sort sort-icon';
        icon.style.opacity = '0.5';
    });
    
    const sortIcon = headerElement.querySelector('.sort-icon');
    sortIcon.className = `fas fa-sort-${newSort === 'asc' ? 'up' : 'down'} sort-icon`;
    sortIcon.style.opacity = '1';
    
    // Sort rows
    rows.sort((a, b) => {
        const aValue = getCellValue(a, columnIndex);
        const bValue = getCellValue(b, columnIndex);
        
        let comparison = 0;
        if (aValue > bValue) comparison = 1;
        if (aValue < bValue) comparison = -1;
        
        return newSort === 'asc' ? comparison : -comparison;
    });
    
    // Reorder table rows
    rows.forEach(row => tbody.appendChild(row));
}

function getCellValue(row, columnIndex) {
    const cell = row.cells[columnIndex];
    const text = cell.textContent.trim();
    
    // Handle date columns
    if (columnIndex === 1) {
        return new Date(cell.querySelector('div').textContent).getTime();
    }
    
    // Handle numeric values
    if (!isNaN(text) && text !== '') {
        return parseFloat(text);
    }
    
    return text.toLowerCase();
}

function showContextMenu(event, encounterId) {
    // Remove existing context menu
    const existingMenu = document.getElementById('contextMenu');
    if (existingMenu) existingMenu.remove();
    
    const contextMenu = document.createElement('div');
    contextMenu.id = 'contextMenu';
    contextMenu.style.position = 'fixed';
    contextMenu.style.top = event.clientY + 'px';
    contextMenu.style.left = event.clientX + 'px';
    contextMenu.style.background = 'white';
    contextMenu.style.border = '1px solid #ddd';
    contextMenu.style.borderRadius = '5px';
    contextMenu.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    contextMenu.style.zIndex = '1000';
    contextMenu.style.minWidth = '150px';
    
    contextMenu.innerHTML = `
        <div class="context-menu-item" onclick="viewEncounter(${encounterId})">
            <i class="fas fa-eye"></i> View Details
        </div>
        <div class="context-menu-item" onclick="editEncounter(${encounterId})">
            <i class="fas fa-edit"></i> Edit Encounter
        </div>
        <div class="context-menu-item" onclick="printEncounter(${encounterId})">
            <i class="fas fa-print"></i> Print Report
        </div>
        <hr style="margin: 5px 0;">
        <div class="context-menu-item" onclick="scheduleFollowup(${encounterId})">
            <i class="fas fa-calendar-plus"></i> Schedule Follow-up
        </div>
    `;
    
    // Add context menu styles
    const style = document.createElement('style');
    style.textContent = `
        .context-menu-item {
            padding: 10px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        .context-menu-item:hover {
            background: #f8f9fa;
        }
    `;
    
    if (!document.getElementById('contextMenuStyles')) {
        style.id = 'contextMenuStyles';
        document.head.appendChild(style);
    }
    
    document.body.appendChild(contextMenu);
    
    // Remove context menu when clicking elsewhere
    setTimeout(() => {
        document.addEventListener('click', function removeContextMenu() {
            contextMenu.remove();
            document.removeEventListener('click', removeContextMenu);
        });
    }, 100);
}

/**
 * Export Functions Setup
 */
function setupExportFunctions() {
    const exportBtn = document.getElementById('exportEncountersBtn');
    const printBtn = document.getElementById('printReportBtn');
    
    if (exportBtn) {
        exportBtn.addEventListener('click', handleExportData);
    }
    
    if (printBtn) {
        printBtn.addEventListener('click', handlePrintReport);
    }
}

function handleExportData() {
    // Show export options modal
    showExportModal();
}

function showExportModal() {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.style.display = 'flex';
    
    modal.innerHTML = `
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <span>Export Encounter Data</span>
                <button type="button" class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
            </div>
            <div class="modal-body">
                <h4 style="margin: 0 0 15px 0;">Select Export Format</h4>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; margin-bottom: 10px; cursor: pointer;">
                        <input type="radio" name="exportFormat" value="csv" checked style="margin-right: 10px;">
                        <div>
                            <div style="font-weight: 600;">CSV (Excel Compatible)</div>
                            <div style="font-size: 0.85rem; color: #666;">Comma-separated values for spreadsheet applications</div>
                        </div>
                    </label>
                    
                    <label style="display: flex; align-items: center; margin-bottom: 10px; cursor: pointer;">
                        <input type="radio" name="exportFormat" value="pdf" style="margin-right: 10px;">
                        <div>
                            <div style="font-weight: 600;">PDF Report</div>
                            <div style="font-size: 0.85rem; color: #666;">Formatted document for printing or sharing</div>
                        </div>
                    </label>
                    
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="radio" name="exportFormat" value="json" style="margin-right: 10px;">
                        <div>
                            <div style="font-weight: 600;">JSON Data</div>
                            <div style="font-size: 0.85rem; color: #666;">Structured data for system integration</div>
                        </div>
                    </label>
                </div>
                
                <h4 style="margin: 20px 0 15px 0;">Export Options</h4>
                
                <label style="display: flex; align-items: center; margin-bottom: 10px; cursor: pointer;">
                    <input type="checkbox" id="includeFiltered" checked style="margin-right: 10px;">
                    Export only filtered results
                </label>
                
                <label style="display: flex; align-items: center; margin-bottom: 10px; cursor: pointer;">
                    <input type="checkbox" id="includeDetails" style="margin-right: 10px;">
                    Include detailed encounter information
                </label>
                
                <div class="btn-group" style="margin-top: 25px;">
                    <button type="button" class="btn btn-primary" onclick="processExport()">
                        <i class="fas fa-download"></i> Export Data
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function processExport() {
    const format = document.querySelector('input[name="exportFormat"]:checked').value;
    const includeFiltered = document.getElementById('includeFiltered').checked;
    const includeDetails = document.getElementById('includeDetails').checked;
    
    // Close modal
    document.querySelector('.modal-overlay').remove();
    
    // Show progress
    showExportProgress(format, includeFiltered, includeDetails);
}

function showExportProgress(format, includeFiltered, includeDetails) {
    const progressModal = document.createElement('div');
    progressModal.className = 'modal-overlay';
    progressModal.style.display = 'flex';
    
    progressModal.innerHTML = `
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <span>Exporting Data</span>
            </div>
            <div class="modal-body" style="text-align: center;">
                <i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: #03045e; margin-bottom: 20px;"></i>
                <div style="margin-bottom: 15px;">Preparing ${format.toUpperCase()} export...</div>
                <div class="progress-bar" style="background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden;">
                    <div class="progress-fill" style="background: #03045e; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                </div>
                <div style="margin-top: 15px; font-size: 0.9rem; color: #666;">
                    <span id="progressText">Collecting encounter data...</span>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(progressModal);
    
    // Simulate export process
    const progressFill = progressModal.querySelector('.progress-fill');
    const progressText = progressModal.getElementById('progressText');
    
    const steps = [
        { progress: 20, text: 'Collecting encounter data...' },
        { progress: 40, text: 'Processing patient information...' },
        { progress: 60, text: 'Formatting export data...' },
        { progress: 80, text: 'Generating file...' },
        { progress: 100, text: 'Export complete!' }
    ];
    
    let currentStep = 0;
    const stepInterval = setInterval(() => {
        const step = steps[currentStep];
        progressFill.style.width = step.progress + '%';
        progressText.textContent = step.text;
        
        currentStep++;
        if (currentStep >= steps.length) {
            clearInterval(stepInterval);
            
            setTimeout(() => {
                progressModal.remove();
                showExportComplete(format);
            }, 1000);
        }
    }, 800);
}

function showExportComplete(format) {
    const fileName = `encounter_history_${new Date().toISOString().slice(0, 10)}.${format}`;
    
    alert(`Export completed successfully!\n\nFile: ${fileName}\nFormat: ${format.toUpperCase()}\n\nIn a real application, the file would be downloaded automatically.`);
}

function handlePrintReport() {
    // Prepare print-friendly version
    const printWindow = window.open('', '_blank');
    const printContent = generatePrintContent();
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

function generatePrintContent() {
    const visibleRows = Array.from(document.querySelectorAll('#encountersTable tbody tr[data-encounter-id]'))
        .filter(row => row.style.display !== 'none');
    
    const tableRows = visibleRows.map(row => {
        const cells = Array.from(row.cells).slice(0, -1); // Exclude actions column
        return '<tr>' + cells.map(cell => `<td>${cell.textContent}</td>`).join('') + '</tr>';
    }).join('');
    
    return `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Clinical Encounters Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { font-size: 1.5rem; color: #03045e; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .footer { margin-top: 20px; text-align: center; font-size: 0.9rem; color: #666; }
                @media print {
                    body { margin: 0; }
                    .header, .footer { font-size: 0.8rem; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="logo">CHO Koronadal - Clinical Encounters Report</div>
                <div>Generated on: ${new Date().toLocaleDateString()}</div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Encounter ID</th>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Primary Diagnosis</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Follow-up</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
            
            <div class="footer">
                <div>CHO Koronadal Healthcare Management System</div>
                <div>This report contains ${visibleRows.length} clinical encounter records</div>
            </div>
        </body>
        </html>
    `;
}

// Global encounter action functions
function viewEncounter(encounterId) {
    // In a real application, this would load encounter details from the server
    const modalContent = `
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-file-medical" style="font-size: 3rem; color: #03045e; margin-bottom: 15px;"></i>
            <h3>Encounter E${String(encounterId).padStart(4, '0')}</h3>
            <p>Loading detailed encounter information...</p>
            <div style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="editEncounter(${encounterId})">
                    <i class="fas fa-edit"></i> Edit Encounter
                </button>
                <button class="btn btn-secondary" onclick="closeModal('encounterDetailsModal')">
                    Close
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('encounterDetailsContent').innerHTML = modalContent;
    openModal('encounterDetailsModal');
}

function editEncounter(encounterId) {
    window.location.href = `doctor-encounter.php?encounter_id=${encounterId}`;
}

function printEncounter(encounterId) {
    alert(`Print encounter E${String(encounterId).padStart(4, '0')}\n\nIn a real application, this would generate a printable encounter report.`);
}

function scheduleFollowup(patientId) {
    alert(`Schedule follow-up for Patient P${String(patientId).padStart(4, '0')}\n\nIn a real application, this would open the appointment scheduling interface.`);
}

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
window.encounterHistoryJS = {
    filterEncounters,
    resetAllFilters,
    viewEncounter,
    editEncounter,
    printEncounter,
    scheduleFollowup,
    openModal,
    closeModal,
    handleExportData,
    handlePrintReport
};