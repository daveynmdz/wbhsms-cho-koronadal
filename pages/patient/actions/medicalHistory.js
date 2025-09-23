/**
 * medicalHistory.js
 * Utility scripts for medical_history_edit.php
 * ------------------------------------------------
 * Handles modal logic, table row management, form field toggling,
 * AJAX add/edit/delete, accessibility, and modal utilities for all medical history tables:
 * - Allergies
 * - Past Medical Conditions
 * - Chronic Illness
 * - Family History
 * - Surgical History
 * - Current Medications
 * - Immunizations
 */

// ===============================
// Utility function to show snackbar notifications
// ===============================
function showSnackbar(message, type = 'success') {
    const snackbar = document.getElementById('snackbar');
    const snackbarText = document.getElementById('snackbar-text');
    
    if (snackbar && snackbarText) {
        snackbarText.textContent = message;
        
        // Set background color based on type
        if (type === 'error') {
            snackbar.style.backgroundColor = '#f44336';
        } else if (type === 'warning') {
            snackbar.style.backgroundColor = '#ff9800';
        } else {
            snackbar.style.backgroundColor = '#4caf50';
        }
        
        snackbar.style.display = 'block';
        snackbar.style.opacity = '1';
        
        // Hide after 3 seconds
        setTimeout(() => {
            snackbar.style.opacity = '0';
            setTimeout(() => {
                snackbar.style.display = 'none';
            }, 300);
        }, 3000);
    }
}

// ===============================
// 1. Back/Cancel Modal Logic
// ===============================
document.addEventListener('DOMContentLoaded', function () {
    // Back/Cancel button modal logic
    const backBtn = document.getElementById('backCancelBtn');
    const modal = document.getElementById('backCancelModal');
    const modalCancel = document.getElementById('modalCancelBtn');
    const modalStay = document.getElementById('modalStayBtn');

    if (backBtn && modal && modalCancel && modalStay) {
        backBtn.addEventListener('click', function () {
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            modal.querySelector('button').focus();
        });
        modalCancel.addEventListener('click', function () {
            window.location.href = 'profile.php';
        });
        modalStay.addEventListener('click', function () {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            backBtn.focus();
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.style.display = 'none';
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
            }
        });
    }

    // ===============================
    // 2. Table Row Utility Functions (for dynamic forms)
    // ===============================
    window.addRow = function (tableId) {
        const list = document.getElementById(tableId);
        const firstCard = list.querySelector('.history-card');
        if (!firstCard) return;
        const newCard = firstCard.cloneNode(true);
        newCard.querySelectorAll('input').forEach(input => input.value = '');
        list.appendChild(newCard);
    };

    window.deleteRow = function (btn) {
        const card = btn.closest('.history-card');
        if (card) {
            const list = card.parentElement;
            if (list.querySelectorAll('.history-card').length > 1) {
                card.remove();
            } else {
                alert('You must keep at least one entry.');
            }
        }
    };

    // ===============================
    // 3. Show/Hide "Other" Field Logic
    // ===============================
    window.toggleOtherField = function (select, otherFieldId) {
        var otherField = document.getElementById(otherFieldId);
        if (!otherField) return;
        // Use "block" for vertical alignment
        if (select.value === "Other" || select.value === "Others") {
            otherField.style.display = "block";
            otherField.required = true;
            otherField.focus();
        } else {
            otherField.style.display = "none";
            otherField.required = false;
            otherField.value = "";
        }
    };


    // ===============================
    // 4. Accessibility & Focus Trap for Modals
    // ===============================
    document.querySelectorAll('.custom-modal').forEach(modal => {
        modal.addEventListener('keydown', function (e) {
            if (e.key === 'Tab') {
                const focusable = modal.querySelectorAll('button, [tabindex]:not([tabindex="-1"]), input, select');
                if (focusable.length === 0) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (e.shiftKey) {
                    if (document.activeElement === first) {
                        last.focus(); e.preventDefault();
                    }
                } else {
                    if (document.activeElement === last) {
                        first.focus(); e.preventDefault();
                    }
                }
            }
        });
    });

    // ===============================
    // 5. Universal Success Modal (Updated to use Snackbar)
    // ===============================
    window.showSuccessModal = function (message, type = 'success') {
        // Use snackbar for better user experience
        showSnackbar(message, type);
    };

    window.closeSuccessModal = function () {
        // Legacy function kept for compatibility
        var modal = document.getElementById('successModal');
        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
        if (window._successModalTimeout) clearTimeout(window._successModalTimeout);
    };

    // ===============================
    // 6. Global Delete Confirmation Modal Logic (all tables)
    // ===============================
    let pendingDelete = { table: null, id: null, btn: null };

    window.openCustomDeletePopup = function (table, id, btn) {
        pendingDelete = { table, id, btn };
        var modal = document.getElementById('deleteConfirmModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            var msg = document.getElementById('deleteConfirmMsg');
            // Optionally, customize message per table
            msg.textContent = 'Are you sure you want to delete this record?';
            modal.querySelector('button').focus();
        }
    };

    window.closeCustomDeletePopup = function () {
        var modal = document.getElementById('deleteConfirmModal');
        if (modal) {
            // Move focus out before hiding for accessibility
            document.body.focus();
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
        pendingDelete = { table: null, id: null, btn: null };
    };

    var yesBtn = document.getElementById('deleteConfirmYesBtn');
    var cancelBtn = document.getElementById('deleteConfirmCancelBtn');
    if (yesBtn) {
        yesBtn.onclick = function () {
            if (pendingDelete.table && pendingDelete.id && pendingDelete.btn) {
                window.proceedDelete(pendingDelete.table, pendingDelete.id, pendingDelete.btn);
            }
            window.closeCustomDeletePopup();
        };
    }
    if (cancelBtn) {
        cancelBtn.onclick = function () {
            window.closeCustomDeletePopup();
        };
    }

    // ===============================
    // 7. AJAX Delete Logic with Password Confirmation (all tables)
    // ===============================
    window.proceedDelete = function (table, id, btn) {
        // Show password confirmation modal
        showPasswordConfirmModal(table, id, btn);
    };

    // Password confirmation modal for delete
    function showPasswordConfirmModal(table, id, btn) {
        const modal = document.createElement('div');
        modal.className = 'custom-modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="custom-modal-content">
                <h3>Confirm Deletion</h3>
                <p>Please enter your password to confirm deletion of this record:</p>
                <div class="form-group">
                    <input type="password" id="deletePassword" placeholder="Enter your password" style="width: 100%; margin-bottom: 16px; padding: 10px;">
                </div>
                <div class="custom-modal-actions">
                    <button type="button" id="confirmDeleteBtn" style="background: #fd79a8; color: white; padding: 10px 20px; border: none; border-radius: 6px; margin-right: 8px;">Delete</button>
                    <button type="button" id="cancelDeleteBtn" style="background: #ddd; color: #2d3436; padding: 10px 20px; border: none; border-radius: 6px;">Cancel</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const passwordInput = modal.querySelector('#deletePassword');
        const confirmBtn = modal.querySelector('#confirmDeleteBtn');
        const cancelBtn = modal.querySelector('#cancelDeleteBtn');
        
        passwordInput.focus();
        
        confirmBtn.onclick = function() {
            const password = passwordInput.value;
            if (!password) {
                alert('Please enter your password.');
                passwordInput.focus();
                return;
            }
            
            performDelete(table, id, btn, password, modal);
        };
        
        cancelBtn.onclick = function() {
            document.body.removeChild(modal);
            btn.disabled = false;
        };
        
        passwordInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                confirmBtn.click();
            }
        });
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                document.body.removeChild(modal);
                btn.disabled = false;
            }
        });
    }

    function performDelete(table, id, btn, password, modal) {
        btn.disabled = true;
        
        fetch('actions/delete_medical_history.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `table=${encodeURIComponent(table)}&id=${encodeURIComponent(id)}&password=${encodeURIComponent(password)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const row = btn.closest('tr');
                if (row) row.remove();
                showSuccessModal('Record deleted successfully!');
                document.body.removeChild(modal);
                // Refresh the page to show updated table
                setTimeout(() => window.location.reload(), 1200);
            } else {
                alert('Delete failed: ' + (data.error || 'Unknown error'));
                btn.disabled = false;
                const passwordInput = modal.querySelector('#deletePassword');
                passwordInput.value = '';
                passwordInput.focus();
            }
        })
        .catch(() => {
            alert('Delete failed due to network error.');
            btn.disabled = false;
            const passwordInput = modal.querySelector('#deletePassword');
            passwordInput.value = '';
            passwordInput.focus();
        })
        .finally(() => {
            window.closeCustomDeletePopup();
        });
    }

    // ===============================
    // 8. General Edit Modal Utility (Opener & Closer)
    // ===============================
    window.openEditModal = function (modalId, record) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            modal.querySelector('input, select, button').focus();
            // Optionally fill modal fields dynamically
        }
    };

    window.closeModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            // Move focus out before hiding for accessibility
            document.body.focus();
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
    };

    // ===============================
    // 9. Allergies Table Logic
    // ===============================
    window.openEditAllergyModal = function (modalId, allergy) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        var idx = modalId.replace('editAllergyModal', '');

        // Allergen
        var allergenSel = document.getElementById('edit-allergen-select-' + idx);
        var allergenOther = document.getElementById('edit-allergen-other-input-' + idx);
        allergenSel.value = '';
        allergenOther.style.display = 'none';
        allergenOther.value = '';
        var foundAllergen = false;
        for (var i = 0; i < allergenSel.options.length; i++) {
            if (allergenSel.options[i].value === allergy.allergen) {
                allergenSel.selectedIndex = i;
                foundAllergen = true;
                break;
            }
        }
        if (!foundAllergen && allergy.allergen) {
            allergenSel.value = 'Others';
            allergenOther.style.display = 'block';
            allergenOther.value = allergy.allergen;
        }

        // Reaction
        var reactionSel = document.getElementById('edit-reaction-select-' + idx);
        var reactionOther = document.getElementById('edit-reaction-other-input-' + idx);
        reactionSel.value = '';
        reactionOther.style.display = 'none';
        reactionOther.value = '';
        var foundReaction = false;
        for (var j = 0; j < reactionSel.options.length; j++) {
            if (reactionSel.options[j].value === allergy.reaction) {
                reactionSel.selectedIndex = j;
                foundReaction = true;
                break;
            }
        }
        if (!foundReaction && allergy.reaction) {
            reactionSel.value = 'Others';
            reactionOther.style.display = 'block';
            reactionOther.value = allergy.reaction;
        }

        // Severity
        document.getElementById('edit-severity-' + idx).value = allergy.severity || '';
    };

    // AJAX Add Allergy
    const addAllergyForm = document.getElementById('addAllergyForm');
    if (addAllergyForm) {
        addAllergyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(addAllergyForm);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            
            console.log('Submitting allergy form with data:', params.toString());
            
            // First test the connection
            fetch('actions/test_connection.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(res => res.json())
            .then(testData => {
                console.log('Connection test result:', testData);
                
                // Now try the actual add operation
                return fetch('actions/add_medical_history.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                });
            })
            .then(res => {
                console.log('Response status:', res.status);
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.text(); // Get as text first to debug
            })
            .then(text => {
                console.log('Raw response:', text);
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + text);
                }
                if (data.success) {
                    showSuccessModal('Allergy added successfully!');
                    setTimeout(() => window.location.reload(), 1200);
                    addAllergyForm.reset();
                } else {
                    console.error('Server error:', data);
                    alert('Add failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch((error) => {
                console.error('Fetch error:', error);
                alert('Add failed due to network error: ' + error.message);
            });
        });
    }
    // AJAX Edit Allergy
    document.querySelectorAll('.editAllergyForm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/update_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json()
                    .catch(err => {
                        console.log('JSON parse error:', err);
                        throw err;
                    })
                )
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Allergy updated successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        const modal = form.closest('.custom-modal');
                        if (modal) {
                            modal.style.display = 'none';
                            modal.setAttribute('aria-hidden', 'true');
                        }
                    } else {
                        let msg = 'Update failed: ' + (data.error || 'Unknown error');
                        if (data.fields) {
                            msg += '\nMissing/invalid fields: ' + Object.keys(data.fields).filter(k => !data.fields[k]).join(', ');
                        }
                        showSuccessModal(msg);
                        console.log('Server error:', data);
                    }
                })
                .catch((err) => {
                    console.log('Fetch or network error:', err);
                    showSuccessModal('Network error. Please try again.');
                });
        });
    });

    // ===============================
    // 10. Past Medical Conditions Table Logic
    // ===============================
    window.openEditPastCondModal = function (modalId, cond) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');

        var idx = modalId.replace('editPastCondModal', '');
        var condSel = document.getElementById('edit-condition-select-' + idx);
        var condOther = document.getElementById('edit-condition-other-input-' + idx);

        condSel.value = '';
        condOther.style.display = 'none';
        condOther.value = '';
        var foundCond = false;
        for (var i = 0; i < condSel.options.length; i++) {
            if (condSel.options[i].value === cond.condition) {
                condSel.selectedIndex = i;
                foundCond = true;
                break;
            }
        }
        if (!foundCond && cond.condition) {
            condSel.value = 'Others';
            condOther.style.display = 'block';
            condOther.value = cond.condition;
        }

        // Year and status fields
        modal.querySelector('input[name="year_diagnosed"]').value = cond.year_diagnosed || '';
        modal.querySelector('select[name="status"]').value = cond.status || '';
    };

    // AJAX Add Past Medical Condition
    const addPastCondForm = document.getElementById('addPastCondForm');
    if (addPastCondForm) {
        addPastCondForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(addPastCondForm);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/add_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Past medical condition added successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        addPastCondForm.reset();
                    } else {
                        alert('Add failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(() => {
                    alert('Add failed due to network error.');
                });
        });
    }
    // AJAX Edit Past Medical Condition
    document.querySelectorAll('.editPastCondForm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/update_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json()
                    .catch(err => {
                        console.log('JSON parse error:', err);
                        throw err;
                    })
                )
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Past medical condition updated successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        const modal = form.closest('.custom-modal');
                        if (modal) {
                            modal.style.display = 'none';
                            modal.setAttribute('aria-hidden', 'true');
                        }
                    } else {
                        let msg = 'Update failed: ' + (data.error || 'Unknown error');
                        if (data.fields) {
                            msg += '\nMissing/invalid fields: ' + Object.keys(data.fields).filter(k => !data.fields[k]).join(', ');
                        }
                        showSuccessModal(msg);
                        console.log('Server error:', data);
                    }
                })
                .catch((err) => {
                    console.log('Fetch or network error:', err);
                    showSuccessModal('Network error. Please try again.');
                });
        });
    });

    // ===============================
    // 11. Chronic Illnesses Table Logic
    // ===============================

    // Open Edit Chronic Illness Modal and populate fields
    window.openEditChronicIllModal = function (modalId, ill) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        var idx = modalId.replace('editChronicIllModal', '');

        // Illness
        var illSel = document.getElementById('edit-illness-select-' + idx);
        var illOther = document.getElementById('edit-illness-other-input-' + idx);
        illSel.value = '';
        illOther.style.display = 'none';
        illOther.value = '';
        var foundIll = false;
        for (var i = 0; i < illSel.options.length; i++) {
            if (illSel.options[i].value === ill.illness) {
                illSel.selectedIndex = i;
                foundIll = true;
                break;
            }
        }
        if (!foundIll && ill.illness) {
            illSel.value = 'Others';
            illOther.style.display = 'block';
            illOther.value = ill.illness;
        }
        // Year and Management
        modal.querySelector('input[name="year_diagnosed"]').value = ill.year_diagnosed || '';
        modal.querySelector('input[name="management"]').value = ill.management || '';
    };

    // AJAX Add Chronic Illness
    const addChronicIllForm = document.getElementById('addChronicIllForm');
    if (addChronicIllForm) {
        addChronicIllForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(addChronicIllForm);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/add_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Chronic illness added successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        addChronicIllForm.reset();
                    } else {
                        alert('Add failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(() => {
                    alert('Add failed due to network error.');
                });
        });
    }

    // Edit Chronic Illness handler (delegated for all edit modals)
    document.querySelectorAll('.editChronicIllForm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/update_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json()
                    .catch(err => {
                        console.log('JSON parse error:', err);
                        throw err;
                    })
                )
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Chronic illness updated successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        const modal = form.closest('.custom-modal');
                        if (modal) {
                            modal.style.display = 'none';
                            modal.setAttribute('aria-hidden', 'true');
                        }
                    } else {
                        let msg = 'Update failed: ' + (data.error || 'Unknown error');
                        if (data.fields) {
                            msg += '\nMissing/invalid fields: ' + Object.keys(data.fields).filter(k => !data.fields[k]).join(', ');
                        }
                        showSuccessModal(msg);
                        console.log('Server error:', data);
                    }
                })
                .catch((err) => {
                    console.log('Fetch or network error:', err);
                    showSuccessModal('Network error. Please try again.');
                });
        });
    });

    // ===============================
    // 12. Family History Table Logic
    // ===============================

    // Open Edit Family History Modal and populate fields
    window.openEditFamilyHistModal = function (modalId, fh) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        var idx = modalId.replace('editFamilyHistModal', '');

        // Family Member
        var famSel = document.getElementById('edit-family-member-select-' + idx);
        var famOther = document.getElementById('edit-family-member-other-input-' + idx);
        famSel.value = '';
        famOther.style.display = 'none';
        famOther.value = '';
        var foundFam = false;
        for (var i = 0; i < famSel.options.length; i++) {
            if (famSel.options[i].value === fh.family_member) {
                famSel.selectedIndex = i;
                foundFam = true;
                break;
            }
        }
        if (!foundFam && fh.family_member) {
            famSel.value = 'Others';
            famOther.style.display = 'block';
            famOther.value = fh.family_member;
        }
        // Condition
        var condSel = document.getElementById('edit-family-condition-select-' + idx);
        var condOther = document.getElementById('edit-family-condition-other-input-' + idx);
        condSel.value = '';
        condOther.style.display = 'none';
        condOther.value = '';
        var foundCond = false;
        for (var j = 0; j < condSel.options.length; j++) {
            if (condSel.options[j].value === fh.condition) {
                condSel.selectedIndex = j;
                foundCond = true;
                break;
            }
        }
        if (!foundCond && fh.condition) {
            condSel.value = 'Others';
            condOther.style.display = 'block';
            condOther.value = fh.condition;
        }
        // Age Diagnosed
        modal.querySelector('input[name="age_diagnosed"]').value = fh.age_diagnosed || '';
        // Current Status
        modal.querySelector('select[name="current_status"]').value = fh.current_status || '';
    };

    // AJAX Add Family History
    const addFamilyHistForm = document.getElementById('addFamilyHistForm');
    if (addFamilyHistForm) {
        addFamilyHistForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(addFamilyHistForm);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/add_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Family history added successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        addFamilyHistForm.reset();
                    } else {
                        alert('Add failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(() => {
                    alert('Add failed due to network error.');
                });
        });
    }

    // Edit Family History handler (delegated for all edit modals)
    document.querySelectorAll('.editFamilyHistForm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/update_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json()
                    .catch(err => {
                        console.log('JSON parse error:', err);
                        throw err;
                    })
                )
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Family history updated successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        const modal = form.closest('.custom-modal');
                        if (modal) {
                            modal.style.display = 'none';
                            modal.setAttribute('aria-hidden', 'true');
                        }
                    } else {
                        let msg = 'Update failed: ' + (data.error || 'Unknown error');
                        if (data.fields) {
                            msg += '\nMissing/invalid fields: ' + Object.keys(data.fields).filter(k => !data.fields[k]).join(', ');
                        }
                        showSuccessModal(msg);
                        console.log('Server error:', data);
                    }
                })
                .catch((err) => {
                    console.log('Fetch or network error:', err);
                    showSuccessModal('Network error. Please try again.');
                });
        });
    });

    // ===============================
    // 13. Surgical History Table Logic
    // ===============================

    // Open Edit Surgical History Modal and populate fields
    window.openEditSurgHistModal = function (modalId, surg) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        var idx = modalId.replace('editSurgHistModal', '');

        // Surgery Dropdown
        var surgSel = document.getElementById('edit-surgery-select-' + idx);
        var surgOther = document.getElementById('edit-surgery-other-input-' + idx);
        surgSel.value = '';
        surgOther.style.display = 'none';
        surgOther.value = '';
        var foundSurg = false;
        for (var i = 0; i < surgSel.options.length; i++) {
            if (surgSel.options[i].value === surg.surgery) {
                surgSel.selectedIndex = i;
                foundSurg = true;
                break;
            }
        }
        if (!foundSurg && surg.surgery) {
            surgSel.value = 'Others';
            surgOther.style.display = 'block';
            surgOther.value = surg.surgery;
        }

        // Year
        var yearInput = modal.querySelector('input[name="year"]');
        if (yearInput) yearInput.value = surg.year || '';

        // Hospital Dropdown
        var hospSel = document.getElementById('edit-hospital-select-' + idx);
        var hospOther = document.getElementById('edit-hospital-other-input-' + idx);
        hospSel.value = '';
        hospOther.style.display = 'none';
        hospOther.value = '';
        var foundHosp = false;
        for (var j = 0; j < hospSel.options.length; j++) {
            if (hospSel.options[j].value === surg.hospital) {
                hospSel.selectedIndex = j;
                foundHosp = true;
                break;
            }
        }
        if (!foundHosp && surg.hospital) {
            hospSel.value = 'Others';
            hospOther.style.display = 'block';
            hospOther.value = surg.hospital;
        }
    };

    // AJAX Add Surgical History
    const addSurgHistForm = document.getElementById('addSurgHistForm');
    if (addSurgHistForm) {
        addSurgHistForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(addSurgHistForm);
            // Combine dropdown/other fields like backend expects
            const surgery = formData.get('surgery_dropdown') === 'Others' ? formData.get('surgery_other') : formData.get('surgery_dropdown');
            const hospital = formData.get('hospital_dropdown') === 'Others' ? formData.get('hospital_other') : formData.get('hospital_dropdown');
            const year = formData.get('year');
            const table = 'surgical_history';
            const patient_id = formData.get('patient_id');

            const params = new URLSearchParams();
            params.append('table', table);
            params.append('patient_id', patient_id);
            params.append('surgery', surgery);
            params.append('year', year);
            params.append('hospital', hospital);

            fetch('actions/add_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Surgical history added successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        addSurgHistForm.reset();
                    } else {
                        alert('Add failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(() => {
                    alert('Add failed due to network error.');
                });
        });
    }

    // Edit Surgical History handler (delegated for all edit modals)
    document.querySelectorAll('.editSurgHistForm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/update_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json()
                    .catch(err => {
                        console.log('JSON parse error:', err);
                        throw err;
                    })
                )
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Surgical history updated successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        const modal = form.closest('.custom-modal');
                        if (modal) {
                            modal.style.display = 'none';
                            modal.setAttribute('aria-hidden', 'true');
                        }
                    } else {
                        let msg = 'Update failed: ' + (data.error || 'Unknown error');
                        if (data.fields) {
                            msg += '\nMissing/invalid fields: ' + Object.keys(data.fields).filter(k => !data.fields[k]).join(', ');
                        }
                        showSuccessModal(msg);
                        console.log('Server error:', data);
                    }
                })
                .catch((err) => {
                    console.log('Fetch or network error:', err);
                    showSuccessModal('Network error. Please try again.');
                });
        });
    });

    // ===============================
    // 14. Current Medications Table Logic
    // ===============================

    // Open Edit Medication Modal and populate fields
    window.openEditMedModal = function (modalId, med) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        var idx = modalId.replace('editMedModal', '');

        // Medication Dropdown
        var medSel = document.getElementById('edit-medication-select-' + idx);
        var medOther = document.getElementById('edit-medication-other-input-' + idx);
        medSel.value = '';
        medOther.style.display = 'none';
        medOther.value = '';
        var foundMed = false;
        for (var i = 0; i < medSel.options.length; i++) {
            if (medSel.options[i].value === med.medication) {
                medSel.selectedIndex = i;
                foundMed = true;
                break;
            }
        }
        if (!foundMed && med.medication) {
            medSel.value = 'Others';
            medOther.style.display = 'block';
            medOther.value = med.medication;
        }

        // Frequency Dropdown
        var freqSel = document.getElementById('edit-frequency-select-' + idx);
        var freqOther = document.getElementById('edit-frequency-other-input-' + idx);
        freqSel.value = '';
        freqOther.style.display = 'none';
        freqOther.value = '';
        var foundFreq = false;
        for (var j = 0; j < freqSel.options.length; j++) {
            if (freqSel.options[j].value === med.frequency) {
                freqSel.selectedIndex = j;
                foundFreq = true;
                break;
            }
        }
        if (!foundFreq && med.frequency) {
            freqSel.value = 'Others';
            freqOther.style.display = 'block';
            freqOther.value = med.frequency;
        }

        // Dosage and Prescribed By
        modal.querySelector('input[name="dosage"]').value = med.dosage || '';
        modal.querySelector('input[name="prescribed_by"]').value = med.prescribed_by || '';
    };

    // AJAX Add Current Medication
    const addMedForm = document.getElementById('addMedForm');
    if (addMedForm) {
        addMedForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(addMedForm);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/add_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Medication added successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        addMedForm.reset();
                    } else {
                        alert('Add failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(() => {
                    alert('Add failed due to network error.');
                });
        });
    }

    // Edit Medication handler (delegated for all edit modals)
    document.querySelectorAll('.editMedForm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/update_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json()
                    .catch(err => {
                        console.log('JSON parse error:', err);
                        throw err;
                    })
                )
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Medication updated successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        const modal = form.closest('.custom-modal');
                        if (modal) {
                            modal.style.display = 'none';
                            modal.setAttribute('aria-hidden', 'true');
                        }
                    } else {
                        let msg = 'Update failed: ' + (data.error || 'Unknown error');
                        if (data.fields) {
                            msg += '\nMissing/invalid fields: ' + Object.keys(data.fields).filter(k => !data.fields[k]).join(', ');
                        }
                        showSuccessModal(msg);
                        console.log('Server error:', data);
                    }
                })
                .catch((err) => {
                    console.log('Fetch or network error:', err);
                    showSuccessModal('Network error. Please try again.');
                });
        });
    });

    // ===============================
    // 15. Immunizations Table Logic
    // ===============================

    // Open Edit Immunization Modal and populate fields
    window.openEditImmunModal = function (modalId, imm) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        var idx = modalId.replace('editImmunModal', '');

        // Vaccine
        var vacSel = document.getElementById('edit-vaccine-select-' + idx);
        var vacOther = document.getElementById('edit-vaccine-other-input-' + idx);
        vacSel.value = '';
        vacOther.style.display = 'none';
        vacOther.value = '';
        var foundVac = false;
        for (var i = 0; i < vacSel.options.length; i++) {
            if (vacSel.options[i].value === imm.vaccine) {
                vacSel.selectedIndex = i;
                foundVac = true;
                break;
            }
        }
        if (!foundVac && imm.vaccine) {
            vacSel.value = 'Others';
            vacOther.style.display = 'block';
            vacOther.value = imm.vaccine;
        }

        // Year Received, Doses Completed, Status
        modal.querySelector('input[name="year_received"]').value = imm.year_received || '';
        modal.querySelector('input[name="doses_completed"]').value = imm.doses_completed || '';
        modal.querySelector('select[name="status"]').value = imm.status || '';
    };

    // AJAX Add Immunization
    const addImmunForm = document.getElementById('addImmunForm');
    if (addImmunForm) {
        addImmunForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(addImmunForm);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/add_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Immunization added successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        addImmunForm.reset();
                    } else {
                        alert('Add failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(() => {
                    alert('Add failed due to network error.');
                });
        });
    }

    // Edit Immunization handler (delegated for all edit modals)
    document.querySelectorAll('.editImmunForm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(form);
            const params = new URLSearchParams();
            for (const pair of formData) { params.append(pair[0], pair[1]); }
            fetch('actions/update_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
                .then(res => res.json()
                    .catch(err => {
                        console.log('JSON parse error:', err);
                        throw err;
                    })
                )
                .then(data => {
                    if (data.success) {
                        showSuccessModal('Immunization updated successfully!');
                        setTimeout(() => window.location.reload(), 1200);
                        const modal = form.closest('.custom-modal');
                        if (modal) {
                            modal.style.display = 'none';
                            modal.setAttribute('aria-hidden', 'true');
                        }
                    } else {
                        let msg = 'Update failed: ' + (data.error || 'Unknown error');
                        if (data.fields) {
                            msg += '\nMissing/invalid fields: ' + Object.keys(data.fields).filter(k => !data.fields[k]).join(', ');
                        }
                        showSuccessModal(msg);
                        console.log('Server error:', data);
                    }
                })
                .catch((err) => {
                    console.log('Fetch or network error:', err);
                    showSuccessModal('Network error. Please try again.');
                });
        });
    });

}); // End DOMContentLoaded