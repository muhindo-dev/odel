# MRU Campus Dynamics API v2.2 — Official Documentation

> **Source:** https://eadmin.mru.ac.ug/API/v2/docs.aspx  
> **Fetched:** 8 April 2026  
> **Version:** 2.2 — ODEL Integration  
> **Total Endpoints:** 58 (all LIVE)  
> **Base URL:** `https://eadmin.mru.ac.ug/API/v2/{endpoint}.aspx?action={action}`

---

## Table of Contents

1. [Overview](#1-overview)
2. [Authentication](#2-authentication)
3. [Error Handling](#3-error-handling)
4. [Student Profile](#4-student-profile)
5. [Academic Results](#5-academic-results)
6. [Course Registration](#6-course-registration)
7. [Finance / Fees](#7-finance--fees)
8. [Timetable](#8-timetable)
9. [Staff Profile](#9-staff-profile)
10. [Staff — My Classes](#10-staff--my-classes)
11. [Staff — Grading](#11-staff--grading)
12. [Staff — Marks Workflow](#12-staff--marks-workflow)
13. [Notices & Announcements](#13-notices--announcements)
14. [Directory](#14-directory)
15. [Campus Information](#15-campus-information)
16. [Enrollment Verification](#16-enrollment-verification)
17. [ODEL — Identity & Lookup](#17-odel--identity--lookup)
18. [ODEL — Courses & Curriculum](#18-odel--courses--curriculum)
19. [ODEL — Fee Clearance](#19-odel--fee-clearance)
20. [ODEL — Academic Calendar](#20-odel--academic-calendar)

---

## 1. Overview

The Campus Dynamics API v2 provides programmatic access to student records,
academic results, financial data, timetables, staff information, and campus
services. All endpoints return JSON.

### Request Format

```
https://eadmin.mru.ac.ug/API/v2/{endpoint}.aspx?action={action}&token={token}&{params}
```

### Response Structure

All responses follow this structure:

```json
{
  "success": true,
  "message": "OK",
  "data": { ... },
  "timestamp": "2026-03-12T10:30:00Z"
}
```

Error response:

```json
{
  "success": false,
  "message": "Invalid token",
  "error_code": "AUTH_INVALID_TOKEN",
  "data": null
}
```

All requests (except login and some public endpoints) require a `token` parameter.
Tokens are obtained via the `/auth.aspx?action=login` endpoint.

---

## 2. Authentication

Token-based authentication for all API access. Call the login endpoint with
credentials, receive a token, then pass it on all subsequent requests as a
query parameter `token=`.

### 2.1 — Login

| | |
|---|---|
| **Endpoint** | `POST /API/v2/auth.aspx?action=login` |
| **Auth** | None (public) |
| **Purpose** | Authenticate a student or staff member and receive an access token |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| username | string | Yes | Registration number (student) or staff ID |
| password | string | Yes | Account password |

**Request:**
```
POST /API/v2/auth.aspx?action=login
Content-Type: application/x-www-form-urlencoded

username=MRU/2023/001&password=mypassword
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "a1b2c3d4e5f6...",
    "user_type": "student",
    "user_id": "MRU/2023/001",
    "full_name": "JOHN DOE",
    "expires": "2026-03-13T10:30:00Z"
  }
}
```

### 2.2 — Logout

| | |
|---|---|
| **Endpoint** | `POST /API/v2/auth.aspx?action=logout` |
| **Auth** | Bearer token |
| **Purpose** | Invalidate the current token |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| token | string | Yes | Active token to invalidate |

### 2.3 — Validate Token

| | |
|---|---|
| **Endpoint** | `GET /API/v2/auth.aspx?action=validate&token=...` |
| **Auth** | Token |
| **Purpose** | Check if a token is still valid |

### 2.4 — Ping (Health Check)

| | |
|---|---|
| **Endpoint** | `GET /API/v2/auth.aspx?action=ping` |
| **Auth** | None |
| **Purpose** | Health check / connectivity test. No auth required. |

**Response:**
```json
{
  "status": "success",
  "data": {
    "status": "ok",
    "timestamp": "2026-04-08T10:30:00.0000000Z",
    "version": "2.1",
    "server": "CampusDynamics API v2"
  }
}
```

---

## 3. Error Handling

Standard error codes returned by the API:

| Error Code | Description |
|------------|-------------|
| `AUTH_MISSING_TOKEN` | No token provided in the request |
| `AUTH_INVALID_TOKEN` | Token is expired or invalid |
| `AUTH_LOGIN_FAILED` | Invalid username or password |
| `INVALID_ACTION` | Unknown action parameter |
| `MISSING_PARAM` | A required parameter is missing |
| `NOT_FOUND` | Requested record does not exist |
| `ACCESS_DENIED` | Token owner cannot access this resource |
| `SERVER_ERROR` | Unexpected internal server error |

---

## 4. Student Profile

Retrieve student biographical and academic information.

### 4.1 — Get Profile

| | |
|---|---|
| **Endpoint** | `GET /API/v2/student.aspx?action=profile&token=...` |
| **Auth** | Token (student) |
| **Purpose** | Full profile of the authenticated student |

**Response:**
```json
{
  "success": true,
  "data": {
    "regno": "MRU/2023/001",
    "surname": "DOE",
    "othername": "JOHN",
    "gender": "Male",
    "programme": "BACHELOR OF SCIENCE IN COMPUTER SCIENCE",
    "progcode": "BCS",
    "campus": "Main Campus",
    "study_year": 2,
    "entry_year": 2023,
    "intake": "AUGUST",
    "session": "Full time",
    "status": "Active",
    "nationality": "Ugandan",
    "phone": "+256700000000",
    "email": "john@example.com",
    "photo_url": "/API/student_photo.aspx?id=MRU/2023/001"
  }
}
```

### 4.2 — Get Photo

| | |
|---|---|
| **Endpoint** | `GET /API/v2/student.aspx?action=photo&token=...` |
| **Purpose** | Returns the student photo as a binary image (JPEG) |

### 4.3 — Lock Status

| | |
|---|---|
| **Endpoint** | `GET /API/v2/student.aspx?action=lock_status&token=...` |
| **Purpose** | Check if the student account is locked (financial hold, exam hold, etc.) |

### 4.4 — Dashboard Summary

| | |
|---|---|
| **Endpoint** | `GET /API/v2/student.aspx?action=summary&token=...` |
| **Purpose** | Dashboard summary: GPA, balance, registered courses count, notices count |

---

## 5. Academic Results

Access examination and coursework results.

### 5.1 — Get Results

| | |
|---|---|
| **Endpoint** | `GET /API/v2/academic.aspx?action=results&token=...` |
| **Purpose** | All results for the authenticated student, grouped by academic year and semester |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| acad_year | string | No | Filter by academic year (e.g. 2025/2026). Omit for all years. |
| semester | int | No | Filter by semester (1 or 2) |

### 5.2 — Transcript

| | |
|---|---|
| **Endpoint** | `GET /API/v2/academic.aspx?action=transcript&token=...` |
| **Purpose** | Full academic transcript with cumulative GPA, credits earned, and classification |

### 5.3 — GPA

| | |
|---|---|
| **Endpoint** | `GET /API/v2/academic.aspx?action=gpa&token=...` |
| **Purpose** | Semester-by-semester GPA and cumulative GPA calculation |

---

## 6. Course Registration

View available courses, register, and manage course list.

### 6.1 — Available Courses

| | |
|---|---|
| **Endpoint** | `GET /API/v2/academic.aspx?action=available_courses&token=...&acad_year=...&semester=...` |
| **Purpose** | List courses available for registration in the given semester |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| acad_year | string | Yes | Academic year (e.g. 2025/2026) |
| semester | int | Yes | Semester number |

### 6.2 — Registered Courses

| | |
|---|---|
| **Endpoint** | `GET /API/v2/academic.aspx?action=registered_courses&token=...&acad_year=...&semester=...` |
| **Purpose** | List courses the student has registered for in the given semester |

### 6.3 — Register for Course

| | |
|---|---|
| **Endpoint** | `POST /API/v2/academic.aspx?action=register_course` |
| **Purpose** | Register for a course in the current semester |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| token | string | Yes | Auth token |
| course_id | string | Yes | Course code |
| acad_year | string | Yes | Academic year |
| semester | int | Yes | Semester number |

### 6.4 — Drop Course

| | |
|---|---|
| **Endpoint** | `DELETE /API/v2/academic.aspx?action=drop_course` |
| **Purpose** | Remove a registered course (before deadline) |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| token | string | Yes | Auth token |
| registration_id | int | Yes | Course registration ID to remove |

### 6.5 — Semester Registration

| | |
|---|---|
| **Endpoint** | `POST /API/v2/academic.aspx?action=semester_registration` |
| **Purpose** | Complete semester registration (confirms enrollment, triggers billing) |

### 6.6 — Registration History

| | |
|---|---|
| **Endpoint** | `GET /API/v2/academic.aspx?action=registration_history&token=...` |
| **Purpose** | View all past semester registrations with status |

---

## 7. Finance / Fees

Fees ledger, balances, and payment history.

### 7.1 — Full Ledger

| | |
|---|---|
| **Endpoint** | `GET /API/v2/finance.aspx?action=ledger&token=...` |
| **Purpose** | Full student fees ledger — all charges and payments |

**Response:**
```json
{
  "success": true,
  "data": {
    "balance": -1250000,
    "currency": "UGX",
    "entries": [
      {
        "date": "2025-09-01",
        "description": "Tuition - Semester 1",
        "debit": 2500000,
        "credit": 0,
        "balance": 2500000
      },
      {
        "date": "2025-09-15",
        "description": "Payment - Bank Deposit",
        "debit": 0,
        "credit": 1250000,
        "balance": 1250000
      }
    ]
  }
}
```

### 7.2 — Balance

| | |
|---|---|
| **Endpoint** | `GET /API/v2/finance.aspx?action=balance&token=...` |
| **Purpose** | Quick balance check — returns current outstanding balance only |

### 7.3 — Fees Structure

| | |
|---|---|
| **Endpoint** | `GET /API/v2/finance.aspx?action=fees_structure&token=...` |
| **Purpose** | Get the fees structure for the student's programme and study year |

### 7.4 — Payment History

| | |
|---|---|
| **Endpoint** | `GET /API/v2/finance.aspx?action=payment_history&token=...` |
| **Purpose** | Payment receipts only — filters ledger to credit entries |

### 7.5 — Billing Summary

| | |
|---|---|
| **Endpoint** | `GET /API/v2/finance.aspx?action=billing_summary&token=...` |
| **Purpose** | Billing summary grouped by academic year and semester |

**Response:**
```json
{
  "status": "success",
  "data": {
    "overall_balance": 1000000,
    "currency": "UGX",
    "periods": [
      { "period": "2024/2025_S1", "charges": 1750000, "payments": 1500000 },
      { "period": "2024/2025_S2", "charges": 1750000, "payments": 1000000 }
    ]
  }
}
```

---

## 8. Timetable

Lecture and examination schedules.

### 8.1 — Lectures

| | |
|---|---|
| **Endpoint** | `GET /API/v2/timetable.aspx?action=lectures&token=...` |
| **Purpose** | Lecture timetable for the student's registered courses |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| acad_year | string | No | Academic year (defaults to current) |
| semester | int | No | Semester (defaults to current) |

### 8.2 — Exams

| | |
|---|---|
| **Endpoint** | `GET /API/v2/timetable.aspx?action=exams&token=...` |
| **Purpose** | Exam timetable for the student's registered courses |

---

## 9. Staff Profile

Staff biographical and employment information.

### 9.1 — Profile

| | |
|---|---|
| **Endpoint** | `GET /API/v2/staff.aspx?action=profile&token=...` |
| **Purpose** | Staff member's profile — name, department, title, contract details |

### 9.2 — Photo

| | |
|---|---|
| **Endpoint** | `GET /API/v2/staff.aspx?action=photo&token=...` |
| **Purpose** | Staff photo as binary image |

---

## 10. Staff — My Classes

Teaching allocations, class lists, and attendance.

### 10.1 — My Courses

| | |
|---|---|
| **Endpoint** | `GET /API/v2/staff.aspx?action=my_courses&token=...&acad_year=...&semester=...` |
| **Purpose** | Courses allocated to this lecturer in the given semester |

### 10.2 — Class List

| | |
|---|---|
| **Endpoint** | `GET /API/v2/staff.aspx?action=class_list&token=...&course_id=...&acad_year=...&semester=...` |
| **Purpose** | List of students registered for a specific course |

---

## 11. Staff — Grading

Submit and view coursework and exam marks.

### 11.1 — Get Marks

| | |
|---|---|
| **Endpoint** | `GET /API/v2/staff.aspx?action=marks&token=...&course_id=...&acad_year=...&semester=...` |
| **Purpose** | Get existing coursework and exam marks for a course |

### 11.2 — Submit Marks

| | |
|---|---|
| **Endpoint** | `POST /API/v2/staff.aspx?action=submit_marks` |
| **Purpose** | Submit or update marks for students in a course |

Accepts a JSON array of `{regno, coursework, exam}` objects.

---

## 12. Staff — Marks Workflow

Entry-level marks, workflow submission, approvals, and deadlines.

Marks flow through a workflow: **DRAFT → SUBMITTED → DEAN_APPROVED → PUBLISHED**.

### 12.1 — Teaching Assignments

| | |
|---|---|
| **Endpoint** | `GET /API/v2/staff.aspx?action=teaching_assignments&token=...&acad_year=...&semester=...` |
| **Purpose** | Courses assigned to this teacher. Uses new assignment table with legacy fallback. |

### 12.2 — Mark Sheet

| | |
|---|---|
| **Endpoint** | `GET /API/v2/staff.aspx?action=mark_sheet&token=...&course_id=...&progid=...&acad_year=...` |
| **Purpose** | Load the entry-level mark sheet with raw marks, weighted marks, ratios, grades, and workflow status |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| course_id | string | Yes | Course code |
| progid | string | Yes | Programme code |
| acad_year | string | Yes | Academic year |
| semester | int | No | Semester (default: 1) |
| study_year | int | No | Study year (default: 1) |

**Response:**
```json
{
  "status": "success",
  "data": {
    "ratios": { "coursework": 30, "test": 10, "exam": 60 },
    "status": "DRAFT",
    "total_students": 45,
    "marks_entered": 40,
    "students": [
      {
        "entry_id": 1023,
        "regno": "MRU2025003204",
        "cw_entered": 85,
        "total_mark": 71.5,
        "grade": "B"
      }
    ]
  }
}
```

### 12.3 — Save Entry Marks

| | |
|---|---|
| **Endpoint** | `POST /API/v2/staff.aspx?action=save_entry_marks` |
| **Purpose** | Save entry-level marks (draft). Max 200 per request. |

Accepts JSON array: `[{"entry_id":123, "cw_entered":85, "test_entered":70, "exam_entered":65}]`

### 12.4 — Submit for Approval

| | |
|---|---|
| **Endpoint** | `POST /API/v2/staff.aspx?action=submit_for_approval` |
| **Purpose** | Submit a mark sheet for dean approval. Only DRAFT sheets can be submitted. |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| course_id | string | Yes | Course code |
| progid | string | Yes | Programme code |
| acad_year | string | Yes | Academic year |
| semester | int | No | Semester |

### 12.5 — Sheet Status

| | |
|---|---|
| **Endpoint** | `GET /API/v2/staff.aspx?action=sheet_status&token=...&course_id=...&progid=...&acad_year=...` |
| **Purpose** | Check workflow status: DRAFT / SUBMITTED / DEAN_APPROVED / PUBLISHED |

### 12.6 — Deadlines

| | |
|---|---|
| **Endpoint** | `GET /API/v2/staff.aspx?action=deadlines&token=...&acad_year=...&semester=...` |
| **Purpose** | Mark submission deadlines with hours remaining and past-due indicators |

---

## 13. Notices & Announcements

Campus-wide and targeted announcements.

### 13.1 — Get Notices

| | |
|---|---|
| **Endpoint** | `GET /API/v2/campus.aspx?action=notices&token=...` |
| **Purpose** | Active notices and announcements. Supports pagination. |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| page | int | No | Page number (default 1) |
| limit | int | No | Items per page (default 20, max 50) |

---

## 14. Directory

Staff and department directory.

### 14.1 — Search Directory

| | |
|---|---|
| **Endpoint** | `GET /API/v2/campus.aspx?action=directory&token=...&category=...` |
| **Purpose** | Search the campus directory by category (department, faculty, admin, etc.) |

---

## 15. Campus Information

General campus data — academic years, programmes, campuses.

### 15.1 — Academic Years

| | |
|---|---|
| **Endpoint** | `GET /API/v2/campus.aspx?action=academic_years` |
| **Auth** | None |
| **Purpose** | List all academic years |

### 15.2 — Current Semester

| | |
|---|---|
| **Endpoint** | `GET /API/v2/campus.aspx?action=current_semester` |
| **Auth** | None |
| **Purpose** | Current academic year and semester |

### 15.3 — Programmes

| | |
|---|---|
| **Endpoint** | `GET /API/v2/campus.aspx?action=programmes&faculty_code=...&level=...` |
| **Auth** | None |
| **Purpose** | Enhanced for ODEL. List all programmes with faculty, department, level, duration, study mode. |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| faculty_code | string | No | Filter by faculty code |
| level | string | No | Filter by level (e.g. Undergraduate) |

### 15.4 — Campuses

| | |
|---|---|
| **Endpoint** | `GET /API/v2/campus.aspx?action=campuses` |
| **Auth** | None |
| **Purpose** | List all campus locations |

### 15.5 — Faculties

| | |
|---|---|
| **Endpoint** | `GET /API/v2/campus.aspx?action=faculties` |
| **Auth** | None |
| **Purpose** | Enhanced for ODEL. List all faculties with nested departments array. |

### 15.6 — Departments

| | |
|---|---|
| **Endpoint** | `GET /API/v2/campus.aspx?action=departments&faculty_code=...` |
| **Auth** | None |
| **Purpose** | List all departments, optionally filtered by faculty. |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| faculty_code | string | No | Filter by faculty code (e.g. FBMSE) |

---

## 16. Enrollment Verification

Verify student enrollment status for third parties.

### 16.1 — Enrollment Status

| | |
|---|---|
| **Endpoint** | `GET /API/v2/academic.aspx?action=enrollment_status&token=...` |
| **Purpose** | Enrollment verification: student biodata, registration status, programme info |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| acad_year | string | No | Filter by academic year |
| semester | int | No | Filter by semester |

**Response:**
```json
{
  "status": "success",
  "data": {
    "student": { "regno": "MRU2025003204", "firstname": "RITAH", "..." },
    "is_enrolled": true,
    "total_semesters_registered": 2,
    "registrations": [
      { "acad_year": "2024/2025", "semester": "2", "reg_status": "active" }
    ]
  }
}
```

---

## 17. ODEL — Identity & Lookup

> Endpoints designed for the ODEL/Moodle integration plugin to verify users, sync rosters, and map roles.  
> **All require a valid token (use a system staff account for Moodle).**

### 17.1 — Lookup Person by Email ⭐

| | |
|---|---|
| **Endpoint** | `GET /API/v2/student.aspx?action=lookup&token=...&email=...` |
| **Auth** | Token (staff) |
| **Purpose** | Find a person (student or staff) by email. Returns `person_type` of "student" or "staff". Primary endpoint for Moodle registration identity matching. |
| **Priority** | **CRITICAL — Registration Step 3** |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| token | string | Yes | Auth token |
| email | string | Yes | Email address to look up |

**Response — Student found:**
```json
{
  "status": "success",
  "message": "Person found as student",
  "data": {
    "found": true,
    "person_type": "student",
    "mru_id": "2024/BSC/001",
    "data": {
      "regno": "2024/BSC/001",
      "firstname": "John",
      "othername": "Doe",
      "programme": "Bachelor of Science in IT",
      "email": "john@example.com",
      "status": "Active"
    }
  }
}
```

**Response — Not found:**
```json
{
  "data": { "found": false, "person_type": null, "mru_id": null }
}
```

### 17.2 — Verify Student by ID ⭐

| | |
|---|---|
| **Endpoint** | `GET /API/v2/student.aspx?action=verify&token=...&id=...` |
| **Auth** | Token |
| **Purpose** | Quick student verification by reg number or entry number |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| token | string | Yes | Auth token |
| id | string | Yes | Student reg no or entry no |

**Response:**
```json
{
  "data": {
    "verified": true,
    "mru_id": "2024/BSC/001",
    "full_name": "John Doe",
    "status": "Active",
    "programme_code": "BSC-IT"
  }
}
```

### 17.3 — Search Students (Staff Only)

| | |
|---|---|
| **Endpoint** | `GET /API/v2/student.aspx?action=search&token=...&q=...&type=...` |
| **Auth** | Token (staff) |
| **Purpose** | Search students by name, email, or student number |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| q | string | Yes | Search query |
| type | string | No | `name`, `email`, `student_no`, or `any` (default) |
| limit | int | No | Max results (default 50, max 200) |

### 17.4 — Students by Programme (Staff Only)

| | |
|---|---|
| **Endpoint** | `GET /API/v2/student.aspx?action=by_programme&token=...&progcode=...` |
| **Auth** | Token (staff) |
| **Purpose** | Bulk student list by programme with pagination. Used for Moodle cohort sync. |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| progcode | string | Yes | Programme code |
| status | string | No | Filter: Active, Graduated, etc. |
| acad_year | string | No | Filter by academic year |
| page | int | No | Page number (default 1) |
| per_page | int | No | Per page (default 100, max 500) |

**Response — paginated:**
```json
{
  "data": {
    "programme_code": "BSC-IT",
    "total": 125,
    "page": 1,
    "per_page": 50,
    "total_pages": 3,
    "students": [ "..." ]
  }
}
```

### 17.5 — Staff Lookup by Email

| | |
|---|---|
| **Endpoint** | `GET /API/v2/staff.aspx?action=lookup&token=...&email=...` |
| **Auth** | Token |
| **Purpose** | Find a staff member by email. Returns profile with department, faculty, qualifications, and photo URL. |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| token | string | Yes | Auth token |
| email | string | Yes | Staff email address |

### 17.6 — Staff by Department

| | |
|---|---|
| **Endpoint** | `GET /API/v2/staff.aspx?action=by_department&token=...&department_id=...` |
| **Auth** | Token |
| **Purpose** | List all staff in a department |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| department_id | string | Yes | Department ID |
| role | string | No | Filter by emp type (e.g. academic) |
| status | string | No | Contract status (default: Active) |

---

## 18. ODEL — Courses & Curriculum

Course metadata, enrollments, curriculum structure, and grading for Moodle sync.

### 18.1 — Course Details

| | |
|---|---|
| **Endpoint** | `GET /API/v2/academic.aspx?action=course_details&token=...&course_code=...` |
| **Auth** | Token |
| **Purpose** | Complete metadata for a single course |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| course_code | string | Yes | Course code (e.g. CSC101) |

**Response:**
```json
{
  "data": {
    "course_code": "CSC101",
    "course_name": "Introduction to Computer Science",
    "credit_units": 4,
    "category": "Core",
    "department": "Computer Science",
    "faculty": "Faculty of Science",
    "programmes": [
      { "progcode": "BSC-IT", "study_year": 1, "semester": 1 }
    ],
    "prerequisites": [ { "course_code": "MTH100" } ]
  }
}
```

### 18.2 — Course Enrollments (Staff Only)

| | |
|---|---|
| **Endpoint** | `GET /API/v2/academic.aspx?action=course_enrollments&token=...&course_code=...&acad_year=...&semester=...` |
| **Auth** | Token (staff) |
| **Purpose** | All students enrolled in a course for a given semester. Used by Moodle for course roster sync. |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| course_code | string | Yes | Course code |
| acad_year | string | Yes | Academic year (e.g. 2024/2025) |
| semester | string | Yes | Semester number |

**Response:**
```json
{
  "data": {
    "course_code": "CSC101",
    "course_name": "Intro to CS",
    "academic_year": "2024/2025",
    "semester": "1",
    "total_enrolled": 45,
    "students": [
      { "regno": "2024/BSC/001", "firstname": "John", "email": "...", "status": "Registered" }
    ]
  }
}
```

### 18.3 — Programme Curriculum

| | |
|---|---|
| **Endpoint** | `GET /API/v2/academic.aspx?action=programme_curriculum&token=...&progcode=...` |
| **Auth** | Token |
| **Purpose** | Full programme curriculum grouped by year and semester. Used by Moodle to auto-create course categories. |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| progcode | string | Yes | Programme code |

**Response:**
```json
{
  "data": {
    "programme": { "progcode": "BSC-IT", "progname": "BSc IT", "level": "Undergraduate" },
    "total_courses": 36,
    "total_credit_units": 144,
    "curriculum": {
      "Year 1 - Semester 1": [
        { "course_code": "CSC101", "course_name": "Intro to CS", "credit_units": "4", "course_type": "Core" }
      ],
      "Year 1 - Semester 2": [ "..." ]
    }
  }
}
```

### 18.4 — Grading Scheme (No Auth)

| | |
|---|---|
| **Endpoint** | `GET /API/v2/academic.aspx?action=grading_scheme` |
| **Auth** | None |
| **Purpose** | MRU grading scale — letter grades, min/max scores, grade points, remarks. Used by Moodle to configure grade mappings. |

**Response:**
```json
{
  "data": {
    "institution": "Mountains of the Moon University",
    "pass_mark": 50,
    "max_gpa": 5.0,
    "scale": [
      { "letter": "A",  "min_score": 90, "max_score": 100, "grade_point": 5.0, "remark": "Excellent" },
      { "letter": "B+", "min_score": 80, "max_score": 89,  "grade_point": 4.5, "remark": "Very Good" },
      { "letter": "B",  "min_score": 70, "max_score": 79,  "grade_point": 4.0, "remark": "Good" },
      { "letter": "C+", "min_score": 60, "max_score": 69,  "grade_point": 3.5, "remark": "Fairly Good" },
      { "letter": "C",  "min_score": 50, "max_score": 59,  "grade_point": 3.0, "remark": "Pass" },
      { "letter": "D+", "min_score": 45, "max_score": 49,  "grade_point": 2.5, "remark": "Marginal Pass" },
      { "letter": "D",  "min_score": 40, "max_score": 44,  "grade_point": 2.0, "remark": "Marginal Fail" },
      { "letter": "F",  "min_score": 0,  "max_score": 39,  "grade_point": 0.0, "remark": "Fail" }
    ]
  }
}
```

---

## 19. ODEL — Fee Clearance

Fee status checks and bulk clearance verification for Moodle access control.

### 19.1 — Fee Status

| | |
|---|---|
| **Endpoint** | `GET /API/v2/finance.aspx?action=fee_status&token=...&acad_year=...` |
| **Auth** | Token |
| **Purpose** | Fee clearance status: `cleared` / `partial` / `not_cleared`. Staff can check any student via `?regno=`. |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| token | string | Yes | Auth token |
| regno | string | Staff only | Student reg number (staff only) |
| acad_year | string | No | Filter by academic year |
| semester | string | No | Filter by semester |

**Response:**
```json
{
  "data": {
    "regno": "2024/BSC/001",
    "fee_status": "partial",
    "total_fees": 2500000,
    "amount_paid": 1800000,
    "balance": 700000,
    "currency": "UGX",
    "last_payment_date": "2025-06-15",
    "has_financial_lock": false
  }
}
```

**Fee status values:** `"cleared"` | `"partial"` | `"not_cleared"`

### 19.2 — Bulk Fee Check (Staff Only)

| | |
|---|---|
| **Endpoint** | `POST /API/v2/finance.aspx?action=bulk_fee_check&token=...` |
| **Auth** | Token (staff) |
| **Purpose** | Check fee status for multiple students at once (max 200). Used by Moodle for bulk enrollment clearance. |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| token | string | Yes | Auth token (staff) |
| acad_year | string | No | Filter by academic year |

**Request body:**
```json
{ "students": ["2024/BSC/001", "2024/BSC/002", "2024/BSC/003"] }
```

**Response:**
```json
{
  "data": {
    "total_checked": 3,
    "currency": "UGX",
    "results": [
      { "regno": "2024/BSC/001", "fee_status": "cleared", "balance": 0 },
      { "regno": "2024/BSC/002", "fee_status": "partial", "balance": 700000 }
    ]
  }
}
```

---

## 20. ODEL — Academic Calendar

Semester dates, exam periods, and registration deadlines.

### 20.1 — Academic Calendar (No Auth)

| | |
|---|---|
| **Endpoint** | `GET /API/v2/campus.aspx?action=academic_calendar&acad_year=...` |
| **Auth** | None |
| **Purpose** | Academic calendar with semester dates, exam periods, registration deadlines, and current period indicator |

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| acad_year | string | No | Filter by academic year (e.g. 2024/2025) |

**Response:**
```json
{
  "data": {
    "current_academic_year": "2024/2025",
    "current_semester": "2",
    "total_periods": 2,
    "periods": [
      {
        "acad_year": "2024/2025",
        "semester": "1",
        "semester_start": "2024-08-15",
        "semester_end": "2024-12-15",
        "exam_start": "2024-12-01",
        "exam_end": "2024-12-15",
        "registration_deadline": "2024-09-01",
        "is_current": 0
      },
      {
        "acad_year": "2024/2025",
        "semester": "2",
        "semester_start": "2025-01-15",
        "semester_end": "2025-06-15",
        "exam_start": "2025-06-01",
        "exam_end": "2025-06-15",
        "registration_deadline": "2025-02-01",
        "is_current": 1
      }
    ]
  }
}
```

---

## Quick Reference: Key Differences from Our Requirements Doc

| Aspect | Our Requirements (v1) | Actual API (v2.2) |
|--------|----------------------|-------------------|
| **Base URL** | `https://portal.mru.ac.ug/api/v1/` | `https://eadmin.mru.ac.ug/API/v2/{endpoint}.aspx?action={action}` |
| **Auth** | API key/secret → Bearer token | Username/password → token query param |
| **Token passing** | `Authorization: Bearer ...` header | `?token=...` query parameter |
| **Response wrapper** | `{data: ...}` | `{success: true, message: "OK", data: {...}, timestamp: "..."}` |
| **Field names** | `surname`, `other_names` | `surname`, `othername` (no 's') |
| **Student ID field** | `mru_id`, `student_no` | `regno`, `mru_id` |
| **Person lookup** | `GET /people/lookup?email=` | `GET /student.aspx?action=lookup&email=` |
| **Verify** | `GET /students/{id}/verify` | `GET /student.aspx?action=verify&id=` |
| **Course enrollments** | `GET /courses/{code}/enrollments` | `GET /academic.aspx?action=course_enrollments&course_code=` |
| **Marks submit** | `POST /marks/submit` | `POST /staff.aspx?action=submit_marks` |
| **Marks workflow** | Not anticipated | Full workflow: DRAFT → SUBMITTED → DEAN_APPROVED → PUBLISHED |
| **Fee status** | `GET /students/{id}/fees` | `GET /finance.aspx?action=fee_status&regno=` |
| **Grading scheme** | Custom scale (6 grades A-F) | 8-grade scale: A, B+, B, C+, C, D+, D, F |
| **No auth endpoints** | Only `/ping` | `ping`, `academic_years`, `current_semester`, `programmes`, `campuses`, `faculties`, `departments`, `grading_scheme`, `academic_calendar` |

---

## ODEL Integration — Endpoint Mapping for `api_client.php`

These are the endpoints we need to wire into the `local_mru` plugin:

| Our Function | Actual Endpoint | Auth | Notes |
|-------------|----------------|------|-------|
| `authenticate()` | `POST /auth.aspx?action=login` | username/password | Need a system staff account |
| `ping()` | `GET /auth.aspx?action=ping` | None | Health check |
| `lookup_person($email)` | `GET /student.aspx?action=lookup&email=` | Token | Returns student or staff |
| `verify_student($id)` | `GET /student.aspx?action=verify&id=` | Token | Quick verification |
| `search_students($q)` | `GET /student.aspx?action=search&q=` | Token (staff) | Name/email/no search |
| `get_students_by_programme($code)` | `GET /student.aspx?action=by_programme&progcode=` | Token (staff) | Paginated |
| `get_staff_by_email($email)` | `GET /staff.aspx?action=lookup&email=` | Token | Staff lookup |
| `get_staff_by_department($id)` | `GET /staff.aspx?action=by_department&department_id=` | Token | Dept roster |
| `get_course_details($code)` | `GET /academic.aspx?action=course_details&course_code=` | Token | Full metadata |
| `get_course_enrollments($code, $year, $sem)` | `GET /academic.aspx?action=course_enrollments&course_code=&acad_year=&semester=` | Token (staff) | Roster sync |
| `get_programme_curriculum($code)` | `GET /academic.aspx?action=programme_curriculum&progcode=` | Token | Curriculum |
| `get_grading_scheme()` | `GET /academic.aspx?action=grading_scheme` | None | Grade mapping |
| `get_fee_status($regno)` | `GET /finance.aspx?action=fee_status&regno=` | Token (staff) | Fee clearance |
| `bulk_fee_check($regnos)` | `POST /finance.aspx?action=bulk_fee_check` | Token (staff) | Bulk check |
| `submit_marks($data)` | `POST /staff.aspx?action=submit_marks` | Token (staff) | Grade push |
| `save_entry_marks($data)` | `POST /staff.aspx?action=save_entry_marks` | Token (staff) | Draft marks |
| `get_mark_sheet($course, $prog, $year)` | `GET /staff.aspx?action=mark_sheet&course_id=&progid=&acad_year=` | Token (staff) | Full sheet |
| `submit_for_approval($course, $prog, $year)` | `POST /staff.aspx?action=submit_for_approval` | Token (staff) | Workflow |
| `get_programmes()` | `GET /campus.aspx?action=programmes` | None | Category mapping |
| `get_faculties()` | `GET /campus.aspx?action=faculties` | None | Faculty tree |
| `get_current_semester()` | `GET /campus.aspx?action=current_semester` | None | Period info |
| `get_academic_calendar($year)` | `GET /campus.aspx?action=academic_calendar&acad_year=` | None | Calendar |
