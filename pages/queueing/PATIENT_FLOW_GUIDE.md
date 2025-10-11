# City Health Office Main District – Patient Flow Guide

## 🔄 Complete Patient Journey Workflows

The CHO Koronadal WBHSMS implements comprehensive healthcare workflows based on **PhilHealth membership status** and **service type** (service_id). All stations operate within facility_id='1' for the Main District office.

## 🏥 General Station Directory

| Station ID | Station Name         | Station Type   | Service ID | Description                                    |
|------------|---------------------|---------------|------------|------------------------------------------------|
| 16         | Check-In Counter    | checkin       | 10         | Patient registration and PhilHealth verification |
| 1–3        | Triage 1–3          | triage        | 1          | Triage assessment and vital signs collection   |
| 5–11       | Consultation/Treatment | consultation | various    | Medical consult, dental, TB, vaccination, etc. |
| 13         | Laboratory          | lab           | 8          | Diagnostic testing and sample collection       |
| 14–15      | Dispensing 1–2      | pharmacy      | 1          | Medicine dispensing and prescription services  |
| 4          | Billing             | billing       | 9          | Payment processing and invoice generation      |
| 12         | Medical Documents   | document      | 9          | Certificates and medical documentation         |

## 📋 Patient Flow Workflows

### 1. Normal Patient Flow (PhilHealth Members)  
**Applies to**: Primary Care, Dental, TB Treatment, Vaccination, Family Planning (service_id: 1,2,3,4,6,7)

#### Workflow Steps:
```
1. Check-In Counter [16] → PhilHealth verification and appointment validation
2. Triage [1–3] → Vital signs collection and initial assessment  
3. Consultation/Treatment [5–11] → Medical examination and treatment planning
4. Laboratory [13] OR Dispensing [14–15] → Based on doctor's decision
5. End Queue → Patient care completed
```

**Key Points**: 
- Billing station [4] is **skipped** since PhilHealth covers these services
- Doctor determines next step: Laboratory tests or medication dispensing
- System automatically routes based on treatment decisions

### 2. Non-PhilHealth Patient Flow  
**Applies to**: Primary Care, Dental, TB Treatment, Vaccination, Family Planning (service_id: 1,2,3,4,6,7)

#### Workflow Steps:
```
1. Check-In Counter [16] → Registration and service type identification
2. Triage [1–3] → Initial assessment and prioritization
3. Consultation/Treatment [5–11] → Medical examination and treatment planning  
4. Billing [4] → Payment processing for services and medications
5. Consultation/Treatment [5–11] → Return for treatment after payment
6. Laboratory [13] OR Dispensing [14–15] → Complete prescribed services
7. End Queue → Patient care completed
```

**Key Points**:
- **Payment required** before continuing medical process
- Patient returns to consultation after billing for treatment completion
- All fees processed before laboratory tests or medication dispensing

### 3. Laboratory Test-Only Flow  
**Applies to**: Laboratory Tests and Diagnostic Services (service_id: 8)

#### PhilHealth Member Workflow:
```
1. Check-In Counter [16] → Appointment verification and test requisition review
2. Triage [1–3] → Basic assessment for test preparation
3. Laboratory [13] → Sample collection and test processing
4. End Queue → Test completion, results available through system
```

#### Non-PhilHealth Member Workflow:
```
1. Check-In Counter [16] → Registration and test requisition review
2. Triage [1–3] → Pre-test assessment and preparation
3. Billing [4] → Payment for laboratory services
4. Laboratory [13] → Sample collection and test processing after payment
5. End Queue → Test completion, results available through system
```

### 4. Medical Document Request Flow  
**Applies to**: Certificates, Medical Clearances, Documentation (service_id: 9)

#### Universal Workflow (All Patients):
```
1. Check-In Counter [16] → Document request verification and requirements check
2. Billing [4] → Payment for document processing fees
3. Medical Document Requests [12] → Document preparation and approval
4. End Queue → Document issued and patient notified
```

**Key Points**:
- **No clinical assessment required** for document requests
- **Direct billing** - skips triage and consultation stations
- **Document verification** ensures proper authorization and signatures

## 🎯 Priority Management System

### Priority Levels:
- **Emergency**: Life-threatening conditions, immediate attention required
- **Priority**: PWD (Person with Disability), Senior Citizens, Pregnant patients
- **Normal**: Standard queue processing

### Priority Processing Rules:
1. **Emergency patients** bypass normal queue order at all stations
2. **Priority patients** are served before normal patients within each station
3. **Queue codes** reflect priority level for staff identification
4. **Real-time updates** ensure priority status is maintained throughout workflow

## 📊 Queue Management Features

### Station-Specific Actions:

#### 🏥 **Check-In Counter Actions**
- QR code scanning for appointment verification
- PhilHealth membership validation
- Priority classification (PWD, Senior, Pregnant)
- Service type identification and routing
- Queue code generation and ticket printing

#### 🩺 **Triage Station Actions**  
- Vital signs collection (BP, heart rate, temperature, weight, height)
- Initial health assessment and risk evaluation
- Route to appropriate consultation station
- Emergency escalation protocols
- Patient preparation for consultation

#### 👨‍⚕️ **Consultation Station Actions**
- Medical examination and diagnosis
- Treatment plan development
- Route to Laboratory for diagnostic tests
- Route to Pharmacy for medication dispensing  
- Route to Billing for payment processing
- Route to Document station for certificates
- Complete visit (no further treatment needed)

#### 🔬 **Laboratory Station Actions**
- Sample collection and processing
- Test result entry and validation
- Return to Doctor with results for follow-up
- Complete visit (if no doctor consultation needed)
- Route to Pharmacy (if medication prescribed based on results)

#### 💊 **Pharmacy Station Actions**
- Prescription verification and validation
- Medication dispensing and counseling
- Route to Billing (for medication payments)
- Complete visit (final medication dispensing)
- Patient education on medication usage

#### 💰 **Billing Station Actions**
- Invoice creation and payment processing
- Route to original Consultation for treatment continuation
- Route to Laboratory (after payment for tests)
- Route to Document station (after payment for certificates)
- Payment verification and receipt generation

#### 📋 **Document Station Actions**
- Medical certificate preparation
- Document verification and approval
- Digital signature and authorization
- Complete visit (document issued)
- Patient notification of document completion

## 🔄 System Benefits & Features

### ✅ **Comprehensive Workflow Management**
- **PhilHealth Integration**: Automatic routing based on membership status
- **Service-Specific Flows**: Tailored workflows for different medical services  
- **Payment Integration**: Seamless billing integration for non-PhilHealth patients
- **Priority Handling**: Systematic priority patient management

### ✅ **Enhanced Patient Experience**
- **Clear Progress Tracking**: Patients can monitor their queue status in real-time
- **Reduced Wait Times**: Efficient routing minimizes unnecessary delays
- **Priority Recognition**: Special handling for PWD, seniors, and pregnant patients
- **Digital Integration**: QR codes and mobile-friendly patient interfaces

### ✅ **Staff Efficiency & Control**
- **Role-Based Access**: Station-specific interfaces for assigned staff
- **Queue Override Capabilities**: Manual patient routing for special cases
- **Real-Time Monitoring**: Live queue status across all stations
- **Comprehensive Logging**: Full audit trail for compliance and analytics

### ✅ **Administrative Oversight**
- **Multi-Station Dashboard**: Central control and monitoring system
- **Performance Analytics**: Station efficiency and wait time metrics
- **Staff Assignment Management**: Dynamic staff-to-station assignments
- **System Configuration**: Station open/close controls and service routing

## 🛠️ Technical Implementation

### Database Integration
- **Queue Entries**: Linked to `visits` table for clinical encounter tracking
- **Station Management**: Uses `stations` and `station_assignments` tables
- **Patient Tracking**: Integration with `patient_flags` for priority classification
- **Audit Logging**: Comprehensive action logging in `queue_logs` table

### Real-Time Updates
- **AJAX Polling**: Live queue status updates across all interfaces
- **Public Displays**: Real-time waiting area monitors for each station type
- **Mobile Compatibility**: Responsive design for tablet and mobile access
- **WebSocket Support**: Planned enhancement for instant status updates

This comprehensive patient flow system ensures efficient healthcare delivery while maintaining clinical quality and administrative oversight.