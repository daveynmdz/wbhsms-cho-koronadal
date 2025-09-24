<?php
// Use patient session configuration
require_once __DIR__ . '/../../../config/session/patient_session.php';
require_once __DIR__ . '/../../../config/db.php';

// Only allow logged-in patients
$patient_id = isset($_SESSION['patient_id']) ? $_SESSION['patient_id'] : null;
if (!$patient_id) {
    header('Location: ../auth/patient_login.php');
    exit();
}

// Fetch patient info
$stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    die('Patient not found.');
}


// Fetch medical history for display
// Add immunizations to the medical_history array
$medical_history = [
    'past_conditions' => [],
    'chronic_illnesses' => [],
    'family_history' => [],
    'surgical_history' => [],
    'allergies' => [],
    'current_medications' => [],
    'immunizations' => []
];
// Immunizations
$stmt = $pdo->prepare("SELECT id, vaccine, year_received, doses_completed, status FROM immunizations WHERE patient_id = ?");
$stmt->execute([$patient_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $medical_history['immunizations'][] = $row;
}

// Past Medical Conditions
$stmt = $pdo->prepare("SELECT id, `condition`, year_diagnosed, status FROM past_medical_conditions WHERE patient_id = ?");
$stmt->execute([$patient_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $medical_history['past_conditions'][] = $row;
}

// Chronic Illnesses
$stmt = $pdo->prepare("SELECT id, illness, year_diagnosed, management FROM chronic_illnesses WHERE patient_id = ?");
$stmt->execute([$patient_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $medical_history['chronic_illnesses'][] = $row;
}

// Family History
$stmt = $pdo->prepare("SELECT id, family_member, `condition`, age_diagnosed, current_status FROM family_history WHERE patient_id = ?");
$stmt->execute([$patient_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $medical_history['family_history'][] = $row;
}

// Surgical History
$stmt = $pdo->prepare("SELECT id, surgery, year, hospital FROM surgical_history WHERE patient_id = ?");
$stmt->execute([$patient_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $medical_history['surgical_history'][] = $row;
}

// Current Medications
$stmt = $pdo->prepare("SELECT id, medication, dosage, frequency, prescribed_by FROM current_medications WHERE patient_id = ?");
$stmt->execute([$patient_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $medical_history['current_medications'][] = $row;
}

// Allergies
$stmt = $pdo->prepare("SELECT id, allergen, reaction, severity FROM allergies WHERE patient_id = ?");
$stmt->execute([$patient_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $medical_history['allergies'][] = $row;
}

function h($v)
{
    return htmlspecialchars($v ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Edit Medical History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../../assets/css/medical-history-edit.css">
</head>

<body>

    <!-- Top Navigation Bar -->
    <header class="topbar" disabled>
        <div>
            <a href="../dashboard.php" class="topbar-logo" style="pointer-events: none; cursor: default;">
                <picture>
                    <source media="(max-width: 600px)"
                        srcset="https://ik.imagekit.io/wbhsmslogo/Nav_LogoClosed.png?updatedAt=1751197276128">
                    <img src="https://ik.imagekit.io/wbhsmslogo/Nav_Logo.png?updatedAt=1750422462527"
                        alt="City Health Logo" class="responsive-logo" />
                </picture>
            </a>
        </div>
        <div class="topbar-title" style="color: #ffffff;">Edit Medical History</div>
        <div class="topbar-userinfo">
            <div class="topbar-usertext">
                <strong style="color: #ffffff;">
                    <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?>
                </strong><br>
                <small style="color: #ffffff;">Patient</small>
            </div>
            <img src="../../../vendor/photo_controller.php?patient_id=<?= urlencode($patient_id) ?>" alt="User Profile"
                class="topbar-userphoto"
                onerror="this.onerror=null;this.src='https://ik.imagekit.io/wbhsmslogo/user.png?updatedAt=1750423429172';" />
        </div>
    </header>

    <section class="homepage">

        <!-- Snackbar notification -->
        <div id="snackbar" style="display:none;position:fixed;left:50%;bottom:40px;transform:translateX(-50%);background:#323232;color:#fff;padding:1em 2em;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.18);font-size:1.1em;z-index:99999;opacity:0;transition:opacity 0.3s;">
            <span id="snackbar-text"></span>
        </div>

        <!-- Go Back to Patient Profile Button -->
        <div class="edit-profile-toolbar-flex">
            <button type="button" class="btn btn-cancel floating-back-btn" id="backCancelBtn"> Back /
                Cancel</button>
            <!-- Custom Back/Cancel Confirmation Modal -->
            <div id="backCancelModal" class="custom-modal" style="display:none;">
                <div class="custom-modal-content">
                    <h3>Cancel Editing?</h3>
                    <p>Are you sure you want to go back/cancel? Unsaved changes will be lost.</p>
                    <div class="custom-modal-actions">
                        <button type="button" class="btn btn-danger" id="modalCancelBtn">Yes, Cancel</button>
                        <button type="button" class="btn btn-secondary" id="modalStayBtn">Stay</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-wrapper">

            <!-- Reminders Box -->
            <div class="reminders-box">
                <strong>Reminders:</strong>
                <ul>
                    <li>Double-check your information before saving.</li>
                    <li>Fields marked with * are required.</li>
                    <li>Click 'Save' after editing each section.</li>
                    <li>To edit your name, date of birth, age, sex, contact number, or email, please go to User
                        Settings.</li>
                </ul>
            </div>

            <div class="profile-cards-grid">

                <!-- 1st Row of Medical History Tables -->
                <div class="profile-row three-cols">

                    <!-- Allergies Table -->
                    <div class="profile-photo-col">
                        <div class="profile-card">
                            <h3 style="margin-top:0;color:#333;display:flex;align-items:center;justify-content:space-between;">
                                Allergies
                                <label class="na-checkbox-label">
                                    <input type="checkbox" onchange="toggleNAStatus('allergies', this)" 
                                           <?php 
                                           // Check if section has "Not Applicable" data
                                           $hasNA = false;
                                           foreach($medical_history['allergies'] as $allergy) {
                                               if(strtolower($allergy['allergen']) === 'not applicable') {
                                                   $hasNA = true;
                                                   break;
                                               }
                                           }
                                           echo $hasNA ? 'checked' : '';
                                           ?>>
                                    N/A
                                </label>
                            </h3>
                            <table class="medical-history-table">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:0.5em;">Allergen</th>
                                        <th style="padding:0.5em;">Reaction</th>
                                        <th style="padding:0.5em;">Severity</th>
                                        <th style="padding:0.5em;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($medical_history['allergies'])): ?>
                                        <?php foreach ($medical_history['allergies'] as $idx => $allergy): ?>
                                            <tr>
                                                <td><?= h($allergy['allergen']) ?></td>
                                                <td><?= h($allergy['reaction']) ?></td>
                                                <td><?= h($allergy['severity']) ?></td>
                                                <td style="text-align:center; vertical-align:middle;">
                                                    <div class="action-btn-group">
                                                        <button type="button" class="action-btn edit" title="Edit"
                                                            onclick="openEditAllergyModal('editAllergyModal<?= $idx ?>', <?= htmlspecialchars(json_encode($allergy), ENT_QUOTES, 'UTF-8') ?>)">
                                                            <i class='fas fa-edit icon'></i> Edit
                                                        </button>
                                                        <button type="button" class="action-btn delete" title="Delete"
                                                            onclick="openCustomDeletePopup('allergies', <?= h($allergy['id']) ?>, this)">
                                                            <i class="fas fa-trash icon"></i> Delete
                                                        </button>
                                                    </div>

                                                    <!-- Edit Allergy Modal -->
                                                    <div id="editAllergyModal<?= $idx ?>" class="modern-modal-overlay" style="display:none;">
                                                        <div class="modern-modal">
                                                            <h3>Edit Allergy</h3>
                                                            <form class="modern-form editAllergyForm">
                                                                <input type="hidden" name="table" value="allergies">
                                                                <input type="hidden" name="id" value="<?= h($allergy['id']) ?>">
                                                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

                                                                <div class="form-group">
                                                                    <label for="edit-allergen-select-<?= $idx ?>">Allergen<span class="required">*</span></label>
                                                                    <select name="allergen_dropdown"
                                                                        id="edit-allergen-select-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required
                                                                        onchange="toggleOtherField(this, 'edit-allergen-other-input-<?= $idx ?>')">
                                                                        <option value="">Select Allergen</option>
                                                                        <option value="Peanuts">Peanuts</option>
                                                                        <option value="Tree Nuts (Almonds, Walnuts, Cashews, Pistachios)">Tree Nuts (Almonds, Walnuts, Cashews, Pistachios)</option>
                                                                        <option value="Shellfish (Shrimp, Crab, Lobster)">Shellfish (Shrimp, Crab, Lobster)</option>
                                                                        <option value="Fish (Salmon, Tuna, Cod)">Fish (Salmon, Tuna, Cod)</option>
                                                                        <option value="Eggs">Eggs</option>
                                                                        <option value="Milk / Dairy">Milk / Dairy</option>
                                                                        <option value="Soy">Soy</option>
                                                                        <option value="Wheat / Gluten">Wheat / Gluten</option>
                                                                        <option value="Sesame">Sesame</option>
                                                                        <option value="Penicillin">Penicillin</option>
                                                                        <option value="Amoxicillin">Amoxicillin</option>
                                                                        <option value="Sulfa Drugs">Sulfa Drugs</option>
                                                                        <option value="NSAIDs (Ibuprofen, Naproxen)">NSAIDs (Ibuprofen, Naproxen)</option>
                                                                        <option value="Aspirin">Aspirin</option>
                                                                        <option value="Cephalosporins">Cephalosporins</option>
                                                                        <option value="Anesthetics">Anesthetics</option>
                                                                        <option value="Pollen (Grass, Tree, Weed)">Pollen (Grass, Tree, Weed)</option>
                                                                        <option value="Dust Mites">Dust Mites</option>
                                                                        <option value="Mold / Fungi">Mold / Fungi</option>
                                                                        <option value="Animal Dander (Cat, Dog, Rodent)">Animal Dander (Cat, Dog, Rodent)</option>
                                                                        <option value="Latex">Latex</option>
                                                                        <option value="Cockroach">Cockroach</option>
                                                                        <option value="Insect Stings (Bee, Wasp, Hornet)">Insect Stings (Bee, Wasp, Hornet)</option>
                                                                        <option value="Nickel / Metal">Nickel / Metal</option>
                                                                        <option value="Perfumes / Fragrances">Perfumes / Fragrances</option>
                                                                        <option value="Food Additives (MSG, Artificial Colors, Preservatives)">Food Additives (MSG, Artificial Colors, Preservatives)</option>
                                                                        <option value="Others">Others (specify)</option>
                                                                    </select>
                                                                    <input type="text"
                                                                        name="allergen_other"
                                                                        id="edit-allergen-other-input-<?= $idx ?>"
                                                                        class="modern-input"
                                                                        placeholder="Specify Allergen"
                                                                        style="display:none; margin-top:0.4em;" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-reaction-select-<?= $idx ?>">Reaction<span class="required">*</span></label>
                                                                    <select name="reaction_dropdown"
                                                                        id="edit-reaction-select-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required
                                                                        onchange="toggleOtherField(this, 'edit-reaction-other-input-<?= $idx ?>')">
                                                                        <option value="">Select Reaction</option>
                                                                        <option value="Rash">Rash</option>
                                                                        <option value="Anaphylaxis">Anaphylaxis</option>
                                                                        <option value="Itching">Itching</option>
                                                                        <option value="Swelling">Swelling</option>
                                                                        <option value="Nausea">Nausea</option>
                                                                        <option value="Hives">Hives</option>
                                                                        <option value="Others">Others (specify)</option>
                                                                    </select>
                                                                    <input type="text"
                                                                        name="reaction_other"
                                                                        id="edit-reaction-other-input-<?= $idx ?>"
                                                                        class="modern-input"
                                                                        placeholder="Specify Reaction"
                                                                        style="display:none; margin-top:0.4em;" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-severity-<?= $idx ?>">Severity<span class="required">*</span></label>
                                                                    <select name="severity"
                                                                        id="edit-severity-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required>
                                                                        <option value="">Select Severity</option>
                                                                        <option value="Mild">Mild</option>
                                                                        <option value="Moderate">Moderate</option>
                                                                        <option value="Severe">Severe</option>
                                                                    </select>
                                                                </div>

                                                                <div class="form-actions">
                                                                    <button type="submit" class="primary-btn">Save</button>
                                                                    <button type="button" class="secondary-btn" onclick="closeModal('editAllergyModal<?= $idx ?>')">Cancel</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <!-- End of Edit Allergy -->

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align:center;color:#888;">No records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- Add Allergy Form -->
                            <form id="addAllergyForm" class="neat-add-form">
                                <input type="hidden" name="table" value="allergies">
                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                                <h4>Add New Allergy</h4>
                                <div class="form-group">
                                    <label for="add-allergen-select">Allergen*</label>
                                    <select name="allergen_dropdown" id="add-allergen-select" required onchange="toggleOtherField(this, 'add-allergen-other-input')">
                                        <option value="">Select Allergen</option>
                                        <option value="Peanuts">Peanuts</option>
                                        <option value="Tree Nuts (Almonds, Walnuts, Cashews, Pistachios)">Tree Nuts (Almonds, Walnuts, Cashews, Pistachios)</option>
                                        <option value="Shellfish (Shrimp, Crab, Lobster)">Shellfish (Shrimp, Crab, Lobster)</option>
                                        <option value="Fish (Salmon, Tuna, Cod)">Fish (Salmon, Tuna, Cod)</option>
                                        <option value="Eggs">Eggs</option>
                                        <option value="Milk / Dairy">Milk / Dairy</option>
                                        <option value="Soy">Soy</option>
                                        <option value="Wheat / Gluten">Wheat / Gluten</option>
                                        <option value="Sesame">Sesame</option>
                                        <option value="Penicillin">Penicillin</option>
                                        <option value="Amoxicillin">Amoxicillin</option>
                                        <option value="Sulfa Drugs">Sulfa Drugs</option>
                                        <option value="NSAIDs (Ibuprofen, Naproxen)">NSAIDs (Ibuprofen, Naproxen)</option>
                                        <option value="Aspirin">Aspirin</option>
                                        <option value="Cephalosporins">Cephalosporins</option>
                                        <option value="Anesthetics">Anesthetics</option>
                                        <option value="Pollen (Grass, Tree, Weed)">Pollen (Grass, Tree, Weed)</option>
                                        <option value="Dust Mites">Dust Mites</option>
                                        <option value="Mold / Fungi">Mold / Fungi</option>
                                        <option value="Animal Dander (Cat, Dog, Rodent)">Animal Dander (Cat, Dog, Rodent)</option>
                                        <option value="Latex">Latex</option>
                                        <option value="Cockroach">Cockroach</option>
                                        <option value="Insect Stings (Bee, Wasp, Hornet)">Insect Stings (Bee, Wasp, Hornet)</option>
                                        <option value="Nickel / Metal">Nickel / Metal</option>
                                        <option value="Perfumes / Fragrances">Perfumes / Fragrances</option>
                                        <option value="Food Additives (MSG, Artificial Colors, Preservatives)">Food Additives (MSG, Artificial Colors, Preservatives)</option>
                                        <option value="Others">Others (specify)</option>
                                    </select>
                                    <input type="text" name="allergen_other" id="add-allergen-other-input" placeholder="Specify Allergen" style="display:none;">
                                </div>
                                <div class="form-group">
                                    <label for="add-reaction-select">Reaction*</label>
                                    <select name="reaction_dropdown" id="add-reaction-select" required onchange="toggleOtherField(this, 'add-reaction-other-input')">
                                        <option value="">Select Reaction</option>
                                        <option value="Rash">Rash</option>
                                        <option value="Anaphylaxis">Anaphylaxis</option>
                                        <option value="Itching">Itching</option>
                                        <option value="Swelling">Swelling</option>
                                        <option value="Nausea">Nausea</option>
                                        <option value="Hives">Hives</option>
                                        <option value="Others">Others (specify)</option>
                                    </select>
                                    <input type="text" name="reaction_other" id="add-reaction-other-input" placeholder="Specify Reaction" style="display:none;">
                                </div>
                                <div class="form-group">
                                    <label for="add-severity">Severity*</label>
                                    <select name="severity" id="add-severity" required>
                                        <option value="">Select Severity</option>
                                        <option value="Mild">Mild</option>
                                        <option value="Moderate">Moderate</option>
                                        <option value="Severe">Severe</option>
                                    </select>
                                </div>
                                <button type="submit" style="background:#27ae60;color:#fff;border:none;padding:0.5em 1.2em;border-radius:5px;cursor:pointer;font-weight:600;">Add</button>
                            </form>
                            <!-- End of Add Allergy Form -->

                        </div>
                    </div>

                    <!-- Past Medical Condition Table -->
                    <div class="profile-photo-col">
                        <div class="profile-card">
                            <h3 style="margin-top:0;color:#333;display:flex;align-items:center;justify-content:space-between;">
                                Past Medical Conditions
                                <label class="na-checkbox-label">
                                    <input type="checkbox" onchange="toggleNAStatus('past_medical_conditions', this)" 
                                           <?php 
                                           // Check if section has "Not Applicable" data
                                           $hasNA = false;
                                           foreach($medical_history['past_conditions'] as $condition) {
                                               if(strtolower($condition['condition']) === 'not applicable') {
                                                   $hasNA = true;
                                                   break;
                                               }
                                           }
                                           echo $hasNA ? 'checked' : '';
                                           ?>>
                                    N/A
                                </label>
                            </h3>
                            <table class="medical-history-table">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:0.5em;">Condition</th>
                                        <th style="padding:0.5em;">Year Diagnosed</th>
                                        <th style="padding:0.5em;">Status</th>
                                        <th style="padding:0.5em;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($medical_history['past_conditions'])): ?>
                                        <?php foreach ($medical_history['past_conditions'] as $idx => $cond): ?>
                                            <tr>
                                                <td><?= h($cond['condition']) ?></td>
                                                <td><?= h($cond['year_diagnosed']) ?></td>
                                                <td><?= h($cond['status']) ?></td>
                                                <td style="text-align:center; vertical-align:middle;">
                                                    <div class="action-btn-group">
                                                        <button type="button" class="action-btn edit" title="Edit"
                                                            onclick="openEditPastCondModal('editPastCondModal<?= $idx ?>', <?= htmlspecialchars(json_encode($cond), ENT_QUOTES, 'UTF-8') ?>)">
                                                            <i class='fas fa-edit icon'></i> Edit
                                                        </button>
                                                        <button type="button" class="action-btn delete" title="Delete"
                                                            onclick="openCustomDeletePopup('past_medical_conditions', <?= h($cond['id']) ?>, this)">
                                                            <i class="fas fa-trash icon"></i> Delete
                                                        </button>
                                                    </div>

                                                    <!-- Edit Past Medical Condition Modal -->

                                                    <div id="editPastCondModal<?= $idx ?>" class="modern-modal-overlay" style="display:none;">
                                                        <div class="modern-modal">
                                                            <h3>Edit Past Medical Condition</h3>
                                                            <form class="modern-form editPastCondForm">
                                                                <input type="hidden" name="table" value="past_medical_conditions">
                                                                <input type="hidden" name="id" value="<?= h($cond['id']) ?>">
                                                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

                                                                <div class="form-group">
                                                                    <label for="edit-condition-select-<?= $idx ?>">Condition<span class="required">*</span></label>
                                                                    <select name="condition_dropdown"
                                                                        id="edit-condition-select-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required
                                                                        onchange="toggleOtherField(this, 'edit-condition-other-input-<?= $idx ?>')">
                                                                        <option value="">Select Condition</option>
                                                                        <option value="Cancer">Cancer</option>
                                                                        <option value="Stroke">Stroke</option>
                                                                        <option value="Heart Attack">Heart Attack</option>
                                                                        <option value="Tuberculosis">Tuberculosis</option>
                                                                        <option value="Pneumonia">Pneumonia</option>
                                                                        <option value="Peptic Ulcer Disease">Peptic Ulcer Disease</option>
                                                                        <option value="Rheumatic Heart Disease">Rheumatic Heart Disease</option>
                                                                        <option value="Hepatitis (B/C)">Hepatitis (B/C)</option>
                                                                        <option value="Others">Others (specify)</option>
                                                                    </select>
                                                                    <input type="text"
                                                                        name="condition_other"
                                                                        id="edit-condition-other-input-<?= $idx ?>"
                                                                        class="modern-input"
                                                                        placeholder="Specify Condition"
                                                                        style="display:none;margin-top:0.4em;" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-year-diagnosed-<?= $idx ?>">Year Diagnosed<span class="required">*</span></label>
                                                                    <input type="number"
                                                                        name="year_diagnosed"
                                                                        id="edit-year-diagnosed-<?= $idx ?>"
                                                                        min="1920"
                                                                        max="<?= date('Y') ?>"
                                                                        value="<?= h($cond['year_diagnosed']) ?>"
                                                                        required
                                                                        class="modern-input" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-status-<?= $idx ?>">Status<span class="required">*</span></label>
                                                                    <select name="status"
                                                                        id="edit-status-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required>
                                                                        <option value="">Select Status</option>
                                                                        <option value="Active" <?= h($cond['status']) == 'Active' ? 'selected' : '' ?>>Active</option>
                                                                        <option value="Resolved" <?= h($cond['status']) == 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                                                        <option value="Unknown" <?= h($cond['status']) == 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                                                                    </select>
                                                                </div>

                                                                <div class="form-actions">
                                                                    <button type="submit" class="primary-btn">Save</button>
                                                                    <button type="button" class="secondary-btn" onclick="closeModal('editPastCondModal<?= $idx ?>')">Cancel</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <!-- End of Edit Past Medical Condition Modal -->

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align:center;color:#888;">No records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- Add Past Medical Condition Form -->
                            <form id="addPastCondForm" class="neat-add-form">
                                <input type="hidden" name="table" value="past_medical_conditions">
                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                                <h4>Add New Past Medical Condition</h4>
                                <div class="form-group">
                                    <label for="add-condition-select">Condition*</label>
                                    <select name="condition_dropdown" id="add-condition-select" required onchange="toggleOtherField(this, 'add-condition-other-input')">
                                        <option value="">Select Condition</option>
                                        <option value="Cancer">Cancer</option>
                                        <option value="Stroke">Stroke</option>
                                        <option value="Heart Attack">Heart Attack</option>
                                        <option value="Tuberculosis">Tuberculosis</option>
                                        <option value="Pneumonia">Pneumonia</option>
                                        <option value="Peptic Ulcer Disease">Peptic Ulcer Disease</option>
                                        <option value="Rheumatic Heart Disease">Rheumatic Heart Disease</option>
                                        <option value="Hepatitis (B/C)">Hepatitis (B/C)</option>
                                        <option value="Others">Others (specify)</option>
                                    </select>
                                    <input type="text" name="condition_other" id="add-condition-other-input" placeholder="Specify Condition" style="display:none;">
                                </div>
                                <div class="form-group">
                                    <label for="add-year-diagnosed">Year Diagnosed*</label>
                                    <input type="number" name="year_diagnosed" id="add-year-diagnosed" min="1900" max="<?= date('Y') ?>" required style="width:100%;">
                                </div>
                                <div class="form-group">
                                    <label for="add-status">Status*</label>
                                    <select name="status" id="add-status" required>
                                        <option value="">Select Status</option>
                                        <option value="Active">Active</option>
                                        <option value="Resolved">Resolved</option>
                                        <option value="Unknown">Unknown</option>
                                    </select>
                                </div>
                                <button type="submit" style="background:#27ae60;color:#fff;border:none;padding:0.5em 1.2em;border-radius:5px;cursor:pointer;font-weight:600;">Add</button>
                            </form>
                            <!-- End of Add Past Medical Condition Form -->

                        </div>
                    </div>

                    <!-- Chronic Illnesses Table -->
                    <div class="profile-photo-col">
                        <div class="profile-card">
                            <h3 style="margin-top:0;color:#333;display:flex;align-items:center;justify-content:space-between;">
                                Chronic Illnesses
                                <label class="na-checkbox-label">
                                    <input type="checkbox" onchange="toggleNAStatus('chronic_illnesses', this)" 
                                           <?php 
                                           // Check if section has "Not Applicable" data
                                           $hasNA = false;
                                           foreach($medical_history['chronic_illnesses'] as $illness) {
                                               if(strtolower($illness['illness']) === 'not applicable') {
                                                   $hasNA = true;
                                                   break;
                                               }
                                           }
                                           echo $hasNA ? 'checked' : '';
                                           ?>>
                                    N/A
                                </label>
                            </h3>
                            <table class="medical-history-table">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:0.5em;">Illness</th>
                                        <th style="padding:0.5em;">Year Diagnosed</th>
                                        <th style="padding:0.5em;">Management</th>
                                        <th style="padding:0.5em;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($medical_history['chronic_illnesses'])): ?>
                                        <?php foreach ($medical_history['chronic_illnesses'] as $idx => $ill): ?>
                                            <tr>
                                                <td><?= h($ill['illness']) ?></td>
                                                <td><?= h($ill['year_diagnosed']) ?></td>
                                                <td><?= h($ill['management']) ?></td>
                                                <td style="text-align:center; vertical-align:middle;">
                                                    <div class="action-btn-group">
                                                        <button type="button" class="action-btn edit" title="Edit"
                                                            onclick="openEditChronicIllModal('editChronicIllModal<?= $idx ?>', <?= htmlspecialchars(json_encode($ill), ENT_QUOTES, 'UTF-8') ?>)">
                                                            <i class='fas fa-edit icon'></i> Edit
                                                        </button>
                                                        <button type="button" class="action-btn delete" title="Delete"
                                                            onclick="openCustomDeletePopup('chronic_illnesses', <?= h($ill['id']) ?>, this)">
                                                            <i class="fas fa-trash icon"></i> Delete
                                                        </button>
                                                    </div>

                                                    <!-- Edit Chronic Illness Modal -->
                                                    <div id="editChronicIllModal<?= $idx ?>" class="modern-modal-overlay" style="display:none;">
                                                        <div class="modern-modal">
                                                            <h3>Edit Chronic Illness</h3>
                                                            <form class="modern-form editChronicIllForm">
                                                                <input type="hidden" name="table" value="chronic_illnesses">
                                                                <input type="hidden" name="id" value="<?= h($ill['id']) ?>">
                                                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

                                                                <div class="form-group">
                                                                    <label for="edit-illness-select-<?= $idx ?>">Illness<span class="required">*</span></label>
                                                                    <select name="illness_dropdown"
                                                                        id="edit-illness-select-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required
                                                                        onchange="toggleOtherField(this, 'edit-illness-other-input-<?= $idx ?>')">
                                                                        <option value="">Select Illness</option>
                                                                        <option value="Diabetes Mellitus">Diabetes Mellitus</option>
                                                                        <option value="Asthma">Asthma</option>
                                                                        <option value="COPD">COPD</option>
                                                                        <option value="Cancer">Cancer</option>
                                                                        <option value="Heart Disease">Heart Disease</option>
                                                                        <option value="Kidney Disease">Kidney Disease</option>
                                                                        <option value="Hypertension">Hypertension</option>
                                                                        <option value="Epilepsy">Epilepsy</option>
                                                                        <option value="Thyroid Disorder">Thyroid Disorder</option>
                                                                        <option value="HIV/AIDS">HIV/AIDS</option>
                                                                        <option value="Chronic Kidney Disease">Chronic Kidney Disease</option>
                                                                        <option value="Coronary Artery Disease">Coronary Artery Disease</option>
                                                                        <option value="Congestive Heart Failure">Congestive Heart Failure</option>
                                                                        <option value="Osteoarthritis">Osteoarthritis</option>
                                                                        <option value="Rheumatoid Arthritis">Rheumatoid Arthritis</option>
                                                                        <option value="Parkinson’s Disease">Parkinson’s Disease</option>
                                                                        <option value="Dementia/Alzheimer’s">Dementia/Alzheimer’s</option>
                                                                        <option value="Others">Others (specify)</option>
                                                                    </select>
                                                                    <input type="text"
                                                                        name="illness_other"
                                                                        id="edit-illness-other-input-<?= $idx ?>"
                                                                        class="modern-input"
                                                                        placeholder="Specify Illness"
                                                                        style="display:none;margin-top:0.4em;" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-year-diagnosed-<?= $idx ?>">Year Diagnosed<span class="required">*</span></label>
                                                                    <input type="number"
                                                                        name="year_diagnosed"
                                                                        id="edit-year-diagnosed-<?= $idx ?>"
                                                                        min="1920"
                                                                        max="<?= date('Y') ?>"
                                                                        value="<?= h($ill['year_diagnosed']) ?>"
                                                                        required
                                                                        class="modern-input" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-management-<?= $idx ?>">Management<span class="required">*</span></label>
                                                                    <input type="text"
                                                                        name="management"
                                                                        id="edit-management-<?= $idx ?>"
                                                                        value="<?= h($ill['management']) ?>"
                                                                        required
                                                                        class="modern-input" />
                                                                </div>

                                                                <div class="form-actions">
                                                                    <button type="submit" class="primary-btn">Save</button>
                                                                    <button type="button" class="secondary-btn" onclick="closeModal('editChronicIllModal<?= $idx ?>')">Cancel</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <!-- End of Edit Chronic Illness Modal -->

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align:center;color:#888;">No records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- Add Chronic Illness Form -->
                            <form id="addChronicIllForm" class="neat-add-form">
                                <input type="hidden" name="table" value="chronic_illnesses">
                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                                <h4>Add New Chronic Illness</h4>
                                <div class="form-group">
                                    <label for="add-illness-select">Illness*</label>
                                    <select name="illness_dropdown" id="add-illness-select" required onchange="toggleOtherField(this, 'add-illness-other-input')">
                                        <option value="">Select Illness</option>
                                        <option value="Diabetes Mellitus">Diabetes Mellitus</option>
                                        <option value="Asthma">Asthma</option>
                                        <option value="COPD">COPD</option>
                                        <option value="Cancer">Cancer</option>
                                        <option value="Heart Disease">Heart Disease</option>
                                        <option value="Kidney Disease">Kidney Disease</option>
                                        <option value="Hypertension">Hypertension</option>
                                        <option value="Epilepsy">Epilepsy</option>
                                        <option value="Thyroid Disorder">Thyroid Disorder</option>
                                        <option value="HIV/AIDS">HIV/AIDS</option>
                                        <option value="Chronic Kidney Disease">Chronic Kidney Disease</option>
                                        <option value="Coronary Artery Disease">Coronary Artery Disease</option>
                                        <option value="Congestive Heart Failure">Congestive Heart Failure</option>
                                        <option value="Osteoarthritis">Osteoarthritis</option>
                                        <option value="Rheumatoid Arthritis">Rheumatoid Arthritis</option>
                                        <option value="Parkinson’s Disease">Parkinson’s Disease</option>
                                        <option value="Dementia/Alzheimer’s">Dementia/Alzheimer’s</option>
                                        <option value="Others">Others (specify)</option>
                                    </select>
                                    <input type="text" name="illness_other" id="add-illness-other-input" placeholder="Specify Illness" style="display:none;">
                                </div>
                                <div class="form-group">
                                    <label for="add-year-diagnosed">Year Diagnosed*</label>
                                    <input type="number" name="year_diagnosed" id="add-year-diagnosed" min="1900" max="<?= date('Y') ?>" required style="width:100%;">
                                </div>
                                <div class="form-group">
                                    <label for="add-management">Management*</label>
                                    <input type="text" name="management" id="add-management" required style="width:100%;">
                                </div>
                                <button type="submit" style="background:#27ae60;color:#fff;border:none;padding:0.5em 1.2em;border-radius:5px;cursor:pointer;font-weight:600;">Add</button>
                            </form>
                            <!-- End of Add Chronic Illness Form -->

                        </div>
                    </div>

                </div>
                <!-- End of 1st Row of Medical History Tables -->

                <!-- 2nd Row of Medical History Tables -->
                <div class="profile-row two-cols">

                    <!-- Family History Table -->
                    <div class="profile-photo-col">
                        <div class="profile-card">
                            <h3 style="margin-top:0;color:#333;display:flex;align-items:center;justify-content:space-between;">
                                Family History
                                <label class="na-checkbox-label">
                                    <input type="checkbox" onchange="toggleNAStatus('family_history', this)" 
                                           
                                           <?php 
                                           // Check if section has "Not Applicable" data
                                           $hasNA = false;
                                           foreach($medical_history['family_history'] as $family) {
                                               if(strtolower($family['condition']) === 'not applicable') {
                                                   $hasNA = true;
                                                   break;
                                               }
                                           }
                                           echo $hasNA ? 'checked' : '';
                                           ?>>
                                    N/A
                                </label>
                            </h3>
                            <table class="medical-history-table">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:0.5em;">Family Member</th>
                                        <th style="padding:0.5em;">Condition</th>
                                        <th style="padding:0.5em;">Age Diagnosed</th>
                                        <th style="padding:0.5em;">Current Status</th>
                                        <th style="padding:0.5em;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($medical_history['family_history'])): ?>
                                        <?php foreach ($medical_history['family_history'] as $idx => $fh): ?>
                                            <tr>
                                                <td><?= h($fh['family_member']) ?></td>
                                                <td><?= h($fh['condition']) ?></td>
                                                <td><?= h($fh['age_diagnosed']) ?></td>
                                                <td><?= h($fh['current_status']) ?></td>
                                                <td style="text-align:center; vertical-align:middle;">
                                                    <div class="action-btn-group">
                                                        <button type="button" class="action-btn edit" title="Edit"
                                                            onclick="openEditFamilyHistModal('editFamilyHistModal<?= $idx ?>', <?= htmlspecialchars(json_encode($fh), ENT_QUOTES, 'UTF-8') ?>)">
                                                            <i class='fas fa-edit icon'></i> Edit
                                                        </button>
                                                        <button type="button" class="action-btn delete" title="Delete"
                                                            onclick="openCustomDeletePopup('family_history', <?= h($fh['id']) ?>, this)">
                                                            <i class="fas fa-trash icon"></i> Delete
                                                        </button>
                                                    </div>

                                                    <!-- Edit Family History Modal -->
                                                    <div id="editFamilyHistModal<?= $idx ?>" class="modern-modal-overlay" style="display:none;">
                                                        <div class="modern-modal">
                                                            <h3>Edit Family History</h3>
                                                            <form class="modern-form editFamilyHistForm">
                                                                <input type="hidden" name="table" value="family_history">
                                                                <input type="hidden" name="id" value="<?= h($fh['id']) ?>">
                                                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

                                                                <div class="form-group">
                                                                    <label for="edit-family-member-select-<?= $idx ?>">Family Member<span class="required">*</span></label>
                                                                    <select name="family_member_dropdown"
                                                                        id="edit-family-member-select-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required
                                                                        onchange="toggleOtherField(this, 'edit-family-member-other-input-<?= $idx ?>')">
                                                                        <option value="">Select Family Member</option>
                                                                        <option value="Father">Father</option>
                                                                        <option value="Mother">Mother</option>
                                                                        <option value="Sibling">Sibling</option>
                                                                        <option value="Cousin">Cousin</option>
                                                                        <option value="Aunt">Aunt</option>
                                                                        <option value="Uncle">Uncle</option>
                                                                        <option value="Grandparent">Grandparent</option>
                                                                        <option value="Child">Child</option>
                                                                        <option value="Others">Others (specify)</option>
                                                                    </select>
                                                                    <input type="text"
                                                                        name="family_member_other"
                                                                        id="edit-family-member-other-input-<?= $idx ?>"
                                                                        class="modern-input"
                                                                        placeholder="Specify Family Member"
                                                                        style="display:none;margin-top:0.4em;" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-family-condition-select-<?= $idx ?>">Condition<span class="required">*</span></label>
                                                                    <select name="condition_dropdown"
                                                                        id="edit-family-condition-select-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required
                                                                        onchange="toggleOtherField(this, 'edit-family-condition-other-input-<?= $idx ?>')">
                                                                        <option value="">Select Condition</option>
                                                                        <option value="Hypertension">Hypertension</option>
                                                                        <option value="Diabetes Mellitus">Diabetes Mellitus</option>
                                                                        <option value="Cancer">Cancer</option>
                                                                        <option value="Heart Disease">Heart Disease</option>
                                                                        <option value="Stroke">Stroke</option>
                                                                        <option value="Others">Others (specify)</option>
                                                                    </select>
                                                                    <input type="text"
                                                                        name="condition_other"
                                                                        id="edit-family-condition-other-input-<?= $idx ?>"
                                                                        class="modern-input"
                                                                        placeholder="Specify Condition"
                                                                        style="display:none;margin-top:0.4em;" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-age-diagnosed-<?= $idx ?>">Age Diagnosed<span class="required">*</span></label>
                                                                    <input type="number"
                                                                        name="age_diagnosed"
                                                                        id="edit-age-diagnosed-<?= $idx ?>"
                                                                        min="0"
                                                                        value="<?= h($fh['age_diagnosed']) ?>"
                                                                        required
                                                                        class="modern-input" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-current-status-<?= $idx ?>">Current Status<span class="required">*</span></label>
                                                                    <select name="current_status"
                                                                        id="edit-current-status-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required>
                                                                        <option value="">Select Status</option>
                                                                        <option value="Living" <?= h($fh['current_status']) == 'Living' ? 'selected' : '' ?>>Living</option>
                                                                        <option value="Deceased" <?= h($fh['current_status']) == 'Deceased' ? 'selected' : '' ?>>Deceased</option>
                                                                        <option value="Unknown" <?= h($fh['current_status']) == 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                                                                    </select>
                                                                </div>

                                                                <div class="form-actions">
                                                                    <button type="submit" class="primary-btn">Save</button>
                                                                    <button type="button" class="secondary-btn" onclick="closeModal('editFamilyHistModal<?= $idx ?>')">Cancel</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <!-- End of Edit Family History Modal -->

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align:center;color:#888;">No records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- Add Family History Form -->
                            <form id="addFamilyHistForm" class="neat-add-form">
                                <input type="hidden" name="table" value="family_history">
                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                                <h4>Add New Family History</h4>
                                <div class="form-group">
                                    <label for="add-family-member-select">Family Member*</label>
                                    <select name="family_member_dropdown" id="add-family-member-select" required onchange="toggleOtherField(this, 'add-family-member-other-input')">
                                        <option value="">Select Family Member</option>
                                        <option value="Father">Father</option>
                                        <option value="Mother">Mother</option>
                                        <option value="Sibling">Sibling</option>
                                        <option value="Cousin">Cousin</option>
                                        <option value="Aunt">Aunt</option>
                                        <option value="Uncle">Uncle</option>
                                        <option value="Grandparent">Grandparent</option>
                                        <option value="Child">Child</option>
                                        <option value="Others">Others (specify)</option>
                                    </select>
                                    <input type="text" name="family_member_other" id="add-family-member-other-input" placeholder="Specify Family Member" style="display:none;">
                                </div>
                                <div class="form-group">
                                    <label for="add-family-condition-select">Condition*</label>
                                    <select name="condition_dropdown" id="add-family-condition-select" required onchange="toggleOtherField(this, 'add-family-condition-other-input')">
                                        <option value="">Select Condition</option>
                                        <option value="Hypertension">Hypertension</option>
                                        <option value="Diabetes Mellitus">Diabetes Mellitus</option>
                                        <option value="Cancer">Cancer</option>
                                        <option value="Heart Disease">Heart Disease</option>
                                        <option value="Stroke">Stroke</option>
                                        <option value="Others">Others (specify)</option>
                                    </select>
                                    <input type="text" name="condition_other" id="add-family-condition-other-input" placeholder="Specify Condition" style="display:none;">
                                </div>
                                <div class="form-group">
                                    <label for="add-age-diagnosed">Age Diagnosed*</label>
                                    <input type="number" name="age_diagnosed" id="add-age-diagnosed" min="0" required style="width:100%;">
                                </div>
                                <div class="form-group">
                                    <label for="add-current-status">Current Status*</label>
                                    <select name="current_status" id="add-current-status" required>
                                        <option value="">Select Status</option>
                                        <option value="Living">Living</option>
                                        <option value="Deceased">Deceased</option>
                                        <option value="Unknown">Unknown</option>
                                    </select>
                                </div>
                                <button type="submit" style="background:#27ae60;color:#fff;border:none;padding:0.5em 1.2em;border-radius:5px;cursor:pointer;font-weight:600;">Add</button>
                            </form>
                            <!-- End of Add Family History Form -->

                        </div>
                    </div>

                    <!-- Surgical History Table -->
                    <div class="profile-photo-col">
                        <div class="profile-card">
                            <h3 style="margin-top:0;color:#333;display:flex;align-items:center;justify-content:space-between;">
                                Surgical History
                                <label class="na-checkbox-label">
                                    <input type="checkbox" onchange="toggleNAStatus('surgical_history', this)" 
                                           
                                           <?php 
                                           // Check if section has "Not Applicable" data
                                           $hasNA = false;
                                           foreach($medical_history['surgical_history'] as $surgery) {
                                               if(strtolower($surgery['surgery']) === 'not applicable') {
                                                   $hasNA = true;
                                                   break;
                                               }
                                           }
                                           echo $hasNA ? 'checked' : '';
                                           ?>>
                                    N/A
                                </label>
                            </h3>
                            <table class="medical-history-table">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:0.5em;">Surgery</th>
                                        <th style="padding:0.5em;">Year</th>
                                        <th style="padding:0.5em;">Hospital</th>
                                        <th style="padding:0.5em;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($medical_history['surgical_history'])): ?>
                                        <?php foreach ($medical_history['surgical_history'] as $idx => $surg): ?>
                                            <tr>
                                                <td><?= h($surg['surgery']) ?></td>
                                                <td><?= h($surg['year']) ?></td>
                                                <td><?= h($surg['hospital']) ?></td>
                                                <td style="text-align:center; vertical-align:middle;">
                                                    <div class="action-btn-group">
                                                        <button type="button" class="action-btn edit" title="Edit"
                                                            onclick="openEditSurgHistModal('editSurgHistModal<?= $idx ?>', <?= htmlspecialchars(json_encode($surg), ENT_QUOTES, 'UTF-8') ?>)">
                                                            <i class='fas fa-edit icon'></i> Edit
                                                        </button>
                                                        <button type="button" class="action-btn delete" title="Delete"
                                                            onclick="openCustomDeletePopup('surgical_history', <?= h($surg['id']) ?>, this)">
                                                            <i class="fas fa-trash icon"></i> Delete
                                                        </button>
                                                    </div>

                                                    <!-- Edit Surgical History Modal -->
                                                    <div id="editSurgHistModal<?= $idx ?>" class="modern-modal-overlay" style="display:none;">
                                                        <div class="modern-modal">
                                                            <h3>Edit Surgical History</h3>
                                                            <form class="modern-form editSurgHistForm">
                                                                <input type="hidden" name="table" value="surgical_history">
                                                                <input type="hidden" name="id" value="<?= h($surg['id']) ?>">
                                                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

                                                                <div class="form-group">
                                                                    <label for="edit-surgery-select-<?= $idx ?>">Surgery<span class="required">*</span></label>
                                                                    <select name="surgery_dropdown"
                                                                        id="edit-surgery-select-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required
                                                                        onchange="toggleOtherField(this, 'edit-surgery-other-input-<?= $idx ?>')">
                                                                        <option value="">Select Surgery</option>
                                                                        <option value="Appendectomy">Appendectomy</option>
                                                                        <option value="Cholecystectomy">Cholecystectomy</option>
                                                                        <option value="Caesarean Section">Caesarean Section</option>
                                                                        <option value="Hernia Repair">Hernia Repair</option>
                                                                        <option value="Tonsillectomy">Tonsillectomy</option>
                                                                        <option value="Mastectomy">Mastectomy</option>
                                                                        <option value="Coronary Bypass">Coronary Bypass</option>
                                                                        <option value="Hip Replacement">Hip Replacement</option>
                                                                        <option value="Others">Others (specify)</option>
                                                                    </select>
                                                                    <input type="text"
                                                                        name="surgery_other"
                                                                        id="edit-surgery-other-input-<?= $idx ?>"
                                                                        class="modern-input"
                                                                        placeholder="Specify Surgery"
                                                                        style="display:none;margin-top:0.4em;" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-year-<?= $idx ?>">Year<span class="required">*</span></label>
                                                                    <input type="number"
                                                                        name="year"
                                                                        id="edit-year-<?= $idx ?>"
                                                                        min="1900"
                                                                        max="<?= date('Y') ?>"
                                                                        value="<?= h($surg['year']) ?>"
                                                                        required
                                                                        class="modern-input" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-hospital-select-<?= $idx ?>">Hospital<span class="required">*</span></label>
                                                                    <select name="hospital_dropdown"
                                                                        id="edit-hospital-select-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required
                                                                        onchange="toggleOtherField(this, 'edit-hospital-other-input-<?= $idx ?>')">
                                                                        <option value="">Select Hospital</option>
                                                                        <option value="South Cotabato Provincial Hospital">South Cotabato Provincial Hospital</option>
                                                                        <option value="Dr. Arturo P. Pingoy Medical Center (DAPPMC)">Dr. Arturo P. Pingoy Medical Center (DAPPMC)</option>
                                                                        <option value="Allah Valley Medical Specialists' Center, Inc. (AVMSCI)">Allah Valley Medical Specialists' Center, Inc. (AVMSCI)</option>
                                                                        <option value="Socomedics Medical Center">Socomedics Medical Center</option>
                                                                        <option value="Others">Others (specify)</option>
                                                                    </select>
                                                                    <input type="text"
                                                                        name="hospital_other"
                                                                        id="edit-hospital-other-input-<?= $idx ?>"
                                                                        class="modern-input"
                                                                        placeholder="Specify Hospital"
                                                                        style="display:none;margin-top:0.4em;" />
                                                                </div>

                                                                <div class="form-actions">
                                                                    <button type="submit" class="primary-btn">Save</button>
                                                                    <button type="button" class="secondary-btn" onclick="closeModal('editSurgHistModal<?= $idx ?>')">Cancel</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <!-- End of Edit Surgical History Modal -->

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align:center;color:#888;">No records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- Add Surgical History Form -->
                            <form id="addSurgHistForm" class="neat-add-form">
                                <input type="hidden" name="table" value="surgical_history">
                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                                <h4>Add New Surgical History</h4>
                                <div class="form-group">
                                    <label for="add-surgery-select">Surgery*</label>
                                    <select name="surgery_dropdown" id="add-surgery-select" required onchange="toggleOtherField(this, 'add-surgery-other-input')" style="width:100%;">
                                        <option value="">Select Surgery</option>
                                        <option value="Appendectomy">Appendectomy</option>
                                        <option value="Cholecystectomy">Cholecystectomy</option>
                                        <option value="Caesarean Section">Caesarean Section</option>
                                        <option value="Hernia Repair">Hernia Repair</option>
                                        <option value="Tonsillectomy">Tonsillectomy</option>
                                        <option value="Mastectomy">Mastectomy</option>
                                        <option value="Coronary Bypass">Coronary Bypass</option>
                                        <option value="Hip Replacement">Hip Replacement</option>
                                        <option value="Others">Others (specify)</option>
                                    </select>
                                    <input type="text" name="surgery_other" id="add-surgery-other-input" placeholder="Specify Surgery" style="display:none;">
                                </div>
                                <div class="form-group">
                                    <label for="add-year">Year*</label>
                                    <input type="number" name="year" id="add-year" min="1900" max="<?= date('Y') ?>" required style="width:100%;">
                                </div>
                                <div class="form-group">
                                    <label for="add-hospital-select">Hospital*</label>
                                    <select name="hospital_dropdown" id="add-hospital-select" required onchange="toggleOtherField(this, 'add-hospital-other-input')" style="width:100%;">
                                        <option value="">Select Hospital</option>
                                        <option value="South Cotabato Provincial Hospital">South Cotabato Provincial Hospital</option>
                                        <option value="Dr. Arturo P. Pingoy Medical Center (DAPPMC)">Dr. Arturo P. Pingoy Medical Center (DAPPMC)</option>
                                        <option value="Allah Valley Medical Specialists' Center, Inc. (AVMSCI)">Allah Valley Medical Specialists' Center, Inc. (AVMSCI)</option>
                                        <option value="Socomedics Medical Center">Socomedics Medical Center</option>
                                        <option value="Others">Others (specify)</option>
                                    </select>
                                    <input type="text" name="hospital_other" id="add-hospital-other-input" placeholder="Specify Hospital" style="display:none;">
                                </div>
                                <button type="submit" style="background:#27ae60;color:#fff;border:none;padding:0.5em 1.2em;border-radius:5px;cursor:pointer;font-weight:600;">Add</button>
                            </form>
                            <!-- End of Add Surgical History Form -->

                        </div>
                    </div>

                </div>
                <!-- End of 2nd Row of Medical History Tables -->

                <!-- 3rd Row of Medical History Tables -->
                <div class="profile-row two-cols">

                    <!-- Current Medications Table -->
                    <div class="profile-photo-col">
                        <div class="profile-card">
                            <h3 style="margin-top:0;color:#333;display:flex;align-items:center;justify-content:space-between;">
                                Current Medications
                                <label class="na-checkbox-label">
                                    <input type="checkbox" onchange="toggleNAStatus('current_medications', this)" 
                                           
                                           <?php 
                                           // Check if section has "Not Applicable" data
                                           $hasNA = false;
                                           foreach($medical_history['current_medications'] as $medication) {
                                               if(strtolower($medication['medication']) === 'not applicable') {
                                                   $hasNA = true;
                                                   break;
                                               }
                                           }
                                           echo $hasNA ? 'checked' : '';
                                           ?>>
                                    N/A
                                </label>
                            </h3>
                            <table class="medical-history-table">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:0.5em;">Medication</th>
                                        <th style="padding:0.5em;">Dosage</th>
                                        <th style="padding:0.5em;">Frequency</th>
                                        <th style="padding:0.5em;">Prescribed By</th>
                                        <th style="padding:0.5em;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($medical_history['current_medications'])): ?>
                                        <?php foreach ($medical_history['current_medications'] as $idx => $med): ?>
                                            <tr>
                                                <td><?= h($med['medication']) ?></td>
                                                <td><?= h($med['dosage']) ?></td>
                                                <td><?= h($med['frequency']) ?></td>
                                                <td><?= h($med['prescribed_by']) ?></td>
                                                <td style="text-align:center; vertical-align:middle;">
                                                    <div class="action-btn-group">
                                                        <button type="button" class="action-btn edit" title="Edit"
                                                            onclick="openEditMedModal('editMedModal<?= $idx ?>', <?= htmlspecialchars(json_encode($med), ENT_QUOTES, 'UTF-8') ?>)">
                                                            <i class='fas fa-edit icon'></i> Edit
                                                        </button>
                                                        <button type="button" class="action-btn delete" title="Delete"
                                                            onclick="openCustomDeletePopup('current_medications', <?= h($med['id']) ?>, this)">
                                                            <i class="fas fa-trash icon"></i> Delete
                                                        </button>
                                                    </div>

                                                    <!-- Edit Medication Modal -->
                                                    <div id="editMedModal<?= $idx ?>" class="modern-modal-overlay" style="display:none;">
                                                        <div class="modern-modal">
                                                            <h3>Edit Medication</h3>
                                                            <form class="modern-form editMedForm">
                                                                <input type="hidden" name="table" value="current_medications">
                                                                <input type="hidden" name="id" value="<?= h($med['id']) ?>">
                                                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

                                                                <div class="form-group">
                                                                    <label for="edit-medication-select-<?= $idx ?>">Medication<span class="required">*</span></label>
                                                                    <select name="medication_dropdown"
                                                                        id="edit-medication-select-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required
                                                                        onchange="toggleOtherField(this, 'edit-medication-other-input-<?= $idx ?>')">
                                                                        <option value="">Select Medication</option>
                                                                        <option value="Metformin">Metformin</option>
                                                                        <option value="Lisinopril">Lisinopril</option>
                                                                        <option value="Amlodipine">Amlodipine</option>
                                                                        <option value="Atorvastatin">Atorvastatin</option>
                                                                        <option value="Paracetamol">Paracetamol</option>
                                                                        <option value="Ibuprofen">Ibuprofen</option>
                                                                        <option value="Others">Others (specify)</option>
                                                                    </select>
                                                                    <input type="text"
                                                                        name="medication_other"
                                                                        id="edit-medication-other-input-<?= $idx ?>"
                                                                        class="modern-input"
                                                                        placeholder="Specify Medication"
                                                                        style="display:none;margin-top:0.4em;" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-dosage-<?= $idx ?>">Dosage<span class="required">*</span></label>
                                                                    <input type="text"
                                                                        name="dosage"
                                                                        id="edit-dosage-<?= $idx ?>"
                                                                        value="<?= h($med['dosage']) ?>"
                                                                        required
                                                                        class="modern-input" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-frequency-select-<?= $idx ?>">Frequency<span class="required">*</span></label>
                                                                    <select name="frequency_dropdown"
                                                                        id="edit-frequency-select-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required
                                                                        onchange="toggleOtherField(this, 'edit-frequency-other-input-<?= $idx ?>')">
                                                                        <option value="">Select Frequency</option>
                                                                        <option value="Once Daily">Once Daily</option>
                                                                        <option value="Twice Daily">Twice Daily</option>
                                                                        <option value="Three Times Daily">Three Times Daily</option>
                                                                        <option value="Every Other Day">Every Other Day</option>
                                                                        <option value="As Needed">As Needed</option>
                                                                        <option value="Others">Others (specify)</option>
                                                                    </select>
                                                                    <input type="text"
                                                                        name="frequency_other"
                                                                        id="edit-frequency-other-input-<?= $idx ?>"
                                                                        class="modern-input"
                                                                        placeholder="Specify Frequency"
                                                                        style="display:none;margin-top:0.4em;" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-prescribed-by-<?= $idx ?>">Prescribed By</label>
                                                                    <input type="text"
                                                                        name="prescribed_by"
                                                                        id="edit-prescribed-by-<?= $idx ?>"
                                                                        value="<?= h($med['prescribed_by']) ?>"
                                                                        class="modern-input" />
                                                                </div>

                                                                <div class="form-actions">
                                                                    <button type="submit" class="primary-btn">Save</button>
                                                                    <button type="button" class="secondary-btn" onclick="closeModal('editMedModal<?= $idx ?>')">Cancel</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <!-- End of Edit Medication Modal -->

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align:center;color:#888;">No records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- Add Current Medication Form -->
                            <form id="addMedForm" class="neat-add-form">
                                <input type="hidden" name="table" value="current_medications">
                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                                <h4>Add New Medication</h4>
                                <div class="form-group">
                                    <label for="add-medication-select">Medication*</label>
                                    <select name="medication_dropdown" id="add-medication-select" required onchange="toggleOtherField(this, 'add-medication-other-input')">
                                        <option value="">Select Medication</option>
                                        <option value="Metformin">Metformin</option>
                                        <option value="Lisinopril">Lisinopril</option>
                                        <option value="Amlodipine">Amlodipine</option>
                                        <option value="Atorvastatin">Atorvastatin</option>
                                        <option value="Paracetamol">Paracetamol</option>
                                        <option value="Ibuprofen">Ibuprofen</option>
                                        <option value="Others">Others (specify)</option>
                                    </select>
                                    <input type="text" name="medication_other" id="add-medication-other-input" placeholder="Specify Medication" style="display:none;">
                                </div>
                                <div class="form-group">
                                    <label for="add-dosage">Dosage*</label>
                                    <input type="text" name="dosage" id="add-dosage" required style="width:100%;">
                                </div>
                                <div class="form-group">
                                    <label for="add-frequency-select">Frequency*</label>
                                    <select name="frequency_dropdown" id="add-frequency-select" required onchange="toggleOtherField(this, 'add-frequency-other-input')">
                                        <option value="">Select Frequency</option>
                                        <option value="Once Daily">Once Daily</option>
                                        <option value="Twice Daily">Twice Daily</option>
                                        <option value="Three Times Daily">Three Times Daily</option>
                                        <option value="Every Other Day">Every Other Day</option>
                                        <option value="As Needed">As Needed</option>
                                        <option value="Others">Others (specify)</option>
                                    </select>
                                    <input type="text" name="frequency_other" id="add-frequency-other-input" placeholder="Specify Frequency" style="display:none;">
                                </div>
                                <div class="form-group">
                                    <label for="add-prescribed-by">Prescribed By</label>
                                    <input type="text" name="prescribed_by" id="add-prescribed-by" style="width:100%;">
                                </div>
                                <button type="submit" style="background:#27ae60;color:#fff;border:none;padding:0.5em 1.2em;border-radius:5px;cursor:pointer;font-weight:600;">Add</button>
                            </form>
                            <!-- End of Add Current Medication Form -->

                        </div>
                    </div>

                    <!-- Immunizations Table -->
                    <div class="profile-photo-col">
                        <div class="profile-card">
                            <h3 style="margin-top:0;color:#333;display:flex;align-items:center;justify-content:space-between;">
                                Immunizations
                                <label class="na-checkbox-label">
                                    <input type="checkbox" onchange="toggleNAStatus('immunizations', this)" 
                                           
                                           <?php 
                                           // Check if section has "Not Applicable" data
                                           $hasNA = false;
                                           foreach($medical_history['immunizations'] as $immunization) {
                                               if(strtolower($immunization['vaccine']) === 'not applicable') {
                                                   $hasNA = true;
                                                   break;
                                               }
                                           }
                                           echo $hasNA ? 'checked' : '';
                                           ?>>
                                    N/A
                                </label>
                            </h3>
                            <table class="medical-history-table">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:0.5em;">Vaccine</th>
                                        <th style="padding:0.5em;">Year Received</th>
                                        <th style="padding:0.5em;">Doses Completed</th>
                                        <th style="padding:0.5em;">Status</th>
                                        <th style="padding:0.5em;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($medical_history['immunizations'])): ?>
                                        <?php foreach ($medical_history['immunizations'] as $idx => $imm): ?>
                                            <tr>
                                                <td><?= h($imm['vaccine']) ?></td>
                                                <td><?= h($imm['year_received']) ?></td>
                                                <td><?= h($imm['doses_completed']) ?></td>
                                                <td><?= h($imm['status']) ?></td>
                                                <td style="text-align:center; vertical-align:middle;">
                                                    <div class="action-btn-group">
                                                        <button type="button" class="action-btn edit" title="Edit"
                                                            onclick="openEditImmunModal('editImmunModal<?= $idx ?>', <?= htmlspecialchars(json_encode($imm), ENT_QUOTES, 'UTF-8') ?>)">
                                                            <i class='fas fa-edit icon'></i> Edit
                                                        </button>
                                                        <button type="button" class="action-btn delete" title="Delete"
                                                            onclick="openCustomDeletePopup('immunizations', <?= h($imm['id']) ?>, this)">
                                                            <i class="fas fa-trash icon"></i> Delete
                                                        </button>
                                                    </div>

                                                    <!-- Edit Immunization Modal -->
                                                    <div id="editImmunModal<?= $idx ?>" class="modern-modal-overlay" style="display:none;">
                                                        <div class="modern-modal">
                                                            <h3>Edit Immunization</h3>
                                                            <form class="modern-form editImmunForm">
                                                                <input type="hidden" name="table" value="immunizations">
                                                                <input type="hidden" name="id" value="<?= h($imm['id']) ?>">
                                                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">

                                                                <div class="form-group">
                                                                    <label for="edit-vaccine-select-<?= $idx ?>">Vaccine<span class="required">*</span></label>
                                                                    <select name="vaccine_dropdown"
                                                                        id="edit-vaccine-select-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required
                                                                        onchange="toggleOtherField(this, 'edit-vaccine-other-input-<?= $idx ?>')">
                                                                        <option value="">Select Vaccine</option>
                                                                        <option value="COVID-19">COVID-19</option>
                                                                        <option value="Influenza">Influenza</option>
                                                                        <option value="Hepatitis B">Hepatitis B</option>
                                                                        <option value="Tetanus">Tetanus</option>
                                                                        <option value="Measles/MMR">Measles/MMR</option>
                                                                        <option value="Varicella">Varicella</option>
                                                                        <option value="Polio">Polio</option>
                                                                        <option value="Pneumococcal">Pneumococcal</option>
                                                                        <option value="HPV">HPV</option>
                                                                        <option value="Rabies">Rabies</option>
                                                                        <option value="Typhoid">Typhoid</option>
                                                                        <option value="Others">Others (specify)</option>
                                                                    </select>
                                                                    <input type="text"
                                                                        name="vaccine_other"
                                                                        id="edit-vaccine-other-input-<?= $idx ?>"
                                                                        class="modern-input"
                                                                        placeholder="Specify Vaccine"
                                                                        style="display:none;margin-top:0.4em;" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-year-received-<?= $idx ?>">Year Received<span class="required">*</span></label>
                                                                    <input type="number"
                                                                        name="year_received"
                                                                        id="edit-year-received-<?= $idx ?>"
                                                                        min="1900"
                                                                        max="<?= date('Y') ?>"
                                                                        value="<?= h($imm['year_received']) ?>"
                                                                        required
                                                                        class="modern-input" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-doses-completed-<?= $idx ?>">Doses Completed<span class="required">*</span></label>
                                                                    <input type="number"
                                                                        name="doses_completed"
                                                                        id="edit-doses-completed-<?= $idx ?>"
                                                                        min="0"
                                                                        value="<?= h($imm['doses_completed']) ?>"
                                                                        required
                                                                        class="modern-input" />
                                                                </div>

                                                                <div class="form-group">
                                                                    <label for="edit-status-<?= $idx ?>">Status<span class="required">*</span></label>
                                                                    <select name="status"
                                                                        id="edit-status-<?= $idx ?>"
                                                                        class="modern-select"
                                                                        required>
                                                                        <option value="">Select Status</option>
                                                                        <option value="Complete" <?= h($imm['status']) == 'Complete' ? 'selected' : '' ?>>Complete</option>
                                                                        <option value="Incomplete" <?= h($imm['status']) == 'Incomplete' ? 'selected' : '' ?>>Incomplete</option>
                                                                        <option value="Pending" <?= h($imm['status']) == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                                        <option value="Unknown" <?= h($imm['status']) == 'Unknown' ? 'selected' : '' ?>>Unknown</option>
                                                                    </select>
                                                                </div>

                                                                <div class="form-actions">
                                                                    <button type="submit" class="primary-btn">Save</button>
                                                                    <button type="button" class="secondary-btn" onclick="closeModal('editImmunModal<?= $idx ?>')">Cancel</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <!-- End of Edit Immunization Modal -->

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align:center;color:#888;">No records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <!-- Add Immunization Form -->
                            <form id="addImmunForm" class="neat-add-form">
                                <input type="hidden" name="table" value="immunizations">
                                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                                <h4>Add New Immunization</h4>
                                <div class="form-group">
                                    <label for="add-vaccine-select">Vaccine*</label>
                                    <select name="vaccine_dropdown" id="add-vaccine-select" required onchange="toggleOtherField(this, 'add-vaccine-other-input')">
                                        <option value="">Select Vaccine</option>
                                        <option value="COVID-19">COVID-19</option>
                                        <option value="Influenza">Influenza</option>
                                        <option value="Hepatitis B">Hepatitis B</option>
                                        <option value="Tetanus">Tetanus</option>
                                        <option value="Measles/MMR">Measles/MMR</option>
                                        <option value="Varicella">Varicella</option>
                                        <option value="Polio">Polio</option>
                                        <option value="Pneumococcal">Pneumococcal</option>
                                        <option value="HPV">HPV</option>
                                        <option value="Rabies">Rabies</option>
                                        <option value="Typhoid">Typhoid</option>
                                        <option value="Others">Others (specify)</option>
                                    </select>
                                    <input type="text" name="vaccine_other" id="add-vaccine-other-input" placeholder="Specify Vaccine" style="display:none;">
                                </div>
                                <div class="form-group">
                                    <label for="add-year-received">Year Received*</label>
                                    <input type="number" name="year_received" id="add-year-received" min="1900" max="<?= date('Y') ?>" required style="width:100%;">
                                </div>
                                <div class="form-group">
                                    <label for="add-doses-completed">Doses Completed*</label>
                                    <input type="number" name="doses_completed" id="add-doses-completed" min="0" required style="width:100%;">
                                </div>
                                <div class="form-group">
                                    <label for="add-status">Status*</label>
                                    <select name="status" id="add-status" required>
                                        <option value="">Select Status</option>
                                        <option value="Complete">Complete</option>
                                        <option value="Incomplete">Incomplete</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Unknown">Unknown</option>
                                    </select>
                                </div>
                                <button type="submit" style="background:#27ae60;color:#fff;border:none;padding:0.5em 1.2em;border-radius:5px;cursor:pointer;font-weight:600;">Add</button>
                            </form>
                            <!-- End of Add Immunization Form -->

                        </div>
                    </div>

                </div>
                <!-- End of 3rd Row of Medical History Tables -->

            </div>

        </div>

    </section>
    <!-- Universal Success Modal -->
    <div id="successModal" class="custom-modal" style="display:none;">
        <div class="custom-modal-content" style="max-width:350px;text-align:center;">
            <h3 style="color:#27ae60;">Success!</h3>
            <p id="successModalMsg"></p>
            <button type="button" onclick="closeSuccessModal()" style="background:#27ae60;color:#fff;border:none;padding:0.5em 1.2em;border-radius:5px;font-weight:600;margin-top:1em;">OK</button>
        </div>
    </div>
    <!-- Global Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="custom-modal" style="display:none;">
        <div class="custom-modal-content">
            <h3>Confirm Deletion</h3>
            <p id="deleteConfirmMsg">Are you sure you want to delete this record?</p>
            <div class="custom-modal-actions">
                <button type="button" id="deleteConfirmYesBtn">Delete</button>
                <button type="button" id="deleteConfirmCancelBtn">Cancel</button>
            </div>
        </div>
    </div>

    <script src="actions/medicalHistory.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Custom Back/Cancel modal logic
            const backBtn = document.getElementById('backCancelBtn');
            const modal = document.getElementById('backCancelModal');
            const modalCancel = document.getElementById('modalCancelBtn');
            const modalStay = document.getElementById('modalStayBtn');
            if (backBtn && modal && modalCancel && modalStay) {
                backBtn.addEventListener('click', function() {
                    modal.style.display = 'flex';
                });
                
                modalCancel.addEventListener('click', function() {
                    modal.style.display = 'none';
                    window.location.href = "profile.php";
                });
                
                modalStay.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                
                // Close modal on outside click
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) modal.style.display = 'none';
                });
            }
        });

        // N/A Status Toggle Function
        function toggleNAStatus(table, checkbox) {
            console.log('Toggle N/A Status called for table:', table, 'checked:', checkbox.checked);
            
            if (checkbox.checked) {
                // Add N/A record
                addNARecord(table, checkbox);
            } else {
                // Remove N/A record
                removeNARecord(table, checkbox);
            }
        }

        function addNARecord(table, checkbox) {
            const formData = new FormData();
            formData.append('table', table);
            formData.append('patient_id', '<?= $patient_id ?>');
            
            // Set appropriate N/A values based on table
            switch(table) {
                case 'allergies':
                    formData.append('allergen_dropdown', 'Others');
                    formData.append('allergen_other', 'Not Applicable');
                    formData.append('reaction_dropdown', 'Others');
                    formData.append('reaction_other', 'N/A');
                    formData.append('severity', 'N/A');
                    break;
                case 'past_medical_conditions':
                    formData.append('condition_dropdown', 'Others');
                    formData.append('condition_other', 'Not Applicable');
                    formData.append('year_diagnosed', new Date().getFullYear());
                    formData.append('status', 'N/A');
                    break;
                case 'chronic_illnesses':
                    formData.append('illness_dropdown', 'Others');
                    formData.append('illness_other', 'Not Applicable');
                    formData.append('year_diagnosed', new Date().getFullYear());
                    formData.append('management', 'N/A');
                    break;
                case 'family_history':
                    formData.append('family_member_dropdown', 'Others');
                    formData.append('family_member_other', 'Not Applicable');
                    formData.append('condition_dropdown', 'Others');
                    formData.append('condition_other', 'Not Applicable');
                    formData.append('age_diagnosed', 'N/A');
                    formData.append('current_status', 'N/A');
                    break;
                case 'surgical_history':
                    formData.append('surgery_dropdown', 'Others');
                    formData.append('surgery_other', 'Not Applicable');
                    formData.append('year', new Date().getFullYear());
                    formData.append('hospital_dropdown', 'Others');
                    formData.append('hospital_other', 'N/A');
                    break;
                case 'current_medications':
                    formData.append('medication_dropdown', 'Others');
                    formData.append('medication_other', 'Not Applicable');
                    formData.append('dosage', 'N/A');
                    formData.append('frequency_dropdown', 'Others');
                    formData.append('frequency_other', 'N/A');
                    formData.append('prescribed_by', 'N/A');
                    break;
                case 'immunizations':
                    formData.append('vaccine_dropdown', 'Others');
                    formData.append('vaccine_other', 'Not Applicable');
                    formData.append('year_received', new Date().getFullYear());
                    formData.append('doses_completed', '0');
                    formData.append('status', 'N/A');
                    break;
            }
            
            fetch('actions/add_medical_history.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    showSnackbar('Section marked as Not Applicable');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    checkbox.checked = false;
                    showSnackbar('Error: ' + (data.error || 'Failed to mark as N/A'), 'error');
                }
            })
            .catch(error => {
                checkbox.checked = false;
                showSnackbar('Network error: ' + error.message, 'error');
                console.error('Detailed error:', error);
            });
        }

        function removeNARecord(table, checkbox) {
            // Find and remove the N/A record
            fetch('actions/delete_medical_history.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `table=${table}&na_removal=true`
            })
            .then(response => {
                console.log('Delete response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Delete response data:', data);
                if (data.success) {
                    showSnackbar('N/A status removed');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    checkbox.checked = true;
                    showSnackbar('Error: ' + (data.error || 'Failed to remove N/A status'), 'error');
                }
            })
            .catch(error => {
                checkbox.checked = true;
                showSnackbar('Delete error: ' + error.message, 'error');
                console.error('Delete error details:', error);
            });
        }

        function showSnackbar(message, type = 'success') {
            const snackbar = document.getElementById('snackbar');
            const snackbarText = document.getElementById('snackbar-text');
            
            snackbarText.textContent = message;
            snackbar.style.background = type === 'error' ? '#f44336' : '#27ae60';
            snackbar.style.display = 'block';
            snackbar.style.opacity = '1';
            
            setTimeout(() => {
                snackbar.style.opacity = '0';
                setTimeout(() => {
                    snackbar.style.display = 'none';
                }, 300);
            }, 3000);
        }
    </script>
</body>

</html>
