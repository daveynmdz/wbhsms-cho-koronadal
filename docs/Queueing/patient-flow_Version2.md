# City Health Office Main District – Patient Flow (WBHSMS)

This document explains how patients move through service stations at the City Health Office of Koronadal (Main District) using the WBHSMS.  
Flow depends on PhilHealth membership and requested service (service_id). All stations referenced below are restricted to facility_id='1'.

---

## General Station Directory

| Station ID | Station Name         | Station Type   | Service ID | Description                                    |
|------------|---------------------|---------------|------------|------------------------------------------------|
| 16         | Check-In Counter    | checkin       | 10         | Patient registration and PhilHealth check      |
| 1–3        | Triage 1–3          | triage        | 1          | Triage assessment (Primary Care)               |
| 5–11       | Consult/Treat       | consultation  | various    | Medical consult, dental, TB, vaccination, etc. |
| 13         | Laboratory          | lab           | 8          | Diagnostic testing, sample collection          |
| 14–15      | Dispensing 1–2      | pharmacy      | 1          | Medicine dispensing                           |
| 4          | Billing             | billing       | 9          | Payment and invoice processing                 |
| 12         | Medical Doc Requests| document      | 9          | Certificates, documentation                   |

---

## Patient Flows

### 1. Normal Patient Flow (PhilHealth Members)  
_Applies to service_id IN ('1','2','3','4','6','7')_
#### Steps:
1. **Check-In Counter [16]**
2. **Triage [1–3]**
3. **Consultation/Treatment [5–11]**
4. **Laboratory [13]** _or_ **Dispensing [14–15]**
5. **End Queue**

_Billing station [4] is skipped since PhilHealth covers these services._

#### After consultation:
- Doctor decides next steps: Lab or Dispensing, system auto-updates queue.

---

### 2. Non-PhilHealth Patient Flow  
_Applies to service_id IN ('1','2','3','4','6','7')_
#### Steps:
1. **Check-In Counter [16]**
2. **Triage [1–3]**
3. **Consultation/Treatment [5–11]**
4. **Billing [4]**
5. **Consultation/Treatment [5–11]**
6. **Laboratory [13]** _or_ **Dispensing [14–15]**
7. **End Queue**

_Payment required before continuing medical process._

---

### 3. Laboratory Test-Only Flow  
_Applies to service_id = '8'_
#### PhilHealth Member:
1. **Check-In Counter [16]**
2. **Triage [1–3]**
3. **Laboratory [13]**
4. **End Queue**

#### Non-PhilHealth Member:
1. **Check-In Counter [16]**
2. **Triage [1–3]**
3. **Billing [4]**
4. **Laboratory [13]**
5. **End Queue**

---

### 4. Medical Document Request Flow  
_Applies to service_id = '9' (Certificates, etc.)_
#### All patients:
1. **Check-In Counter [16]**
2. **Billing [4]**
3. **Medical Document Requests [12]**
4. **End Queue**

---

## Summary Table: Patient Flows

| Service Type                           | service_id           | PhilHealth | Queue Flow                                    |
|----------------------------------------|----------------------|------------|-----------------------------------------------|
| Primary Care / Dental / TB / Vax / FP / ABT | 1,2,3,4,6,7      | ✅ Yes     | Check-In → Triage → Consult → Lab/Dispense → End |
| Primary Care / Dental / TB / Vax / FP / ABT | 1,2,3,4,6,7      | ❌ No      | Check-In → Triage → Consult → Billing → Consult → Lab/Dispense → End |
| Laboratory Test                        | 8                    | ✅ Yes     | Check-In → Triage → Laboratory → End          |
| Laboratory Test                        | 8                    | ❌ No      | Check-In → Triage → Billing → Laboratory → End|
| Medical Document Request               | 9                    | Any        | Check-In → Billing → Document → End           |

---

## Access Control

- All workflow steps and station files are restricted to **facility_id='1'** in the system.
- Employees only see and operate on patient queues according to their assigned roles and this facility.
