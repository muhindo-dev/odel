# MRU Core Portal API Requirements for ODEL Integration

> **Document version:** 1.0  
> **Date:** 8 April 2026  
> **System:** MRU ODEL (Moodle-based) — `local_mru` plugin  
> **Purpose:** Define all API endpoints needed from the MRU Core Portal to fully integrate with the ODEL Moodle system.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Authentication](#2-authentication)
3. [Identity Verification (Registration)](#3-identity-verification-registration)
4. [Student Data](#4-student-data)
5. [Staff / Lecturer Data](#5-staff--lecturer-data)
6. [Programme Data](#6-programme-data)
7. [Course Data](#7-course-data)
8. [Enrollment Data](#8-enrollment-data)
9. [Marks / Grades](#9-marks--grades)
10. [Academic Structure](#10-academic-structure)
11. [Fee / Payment Status](#11-fee--payment-status)
12. [Notifications & Webhooks](#12-notifications--webhooks)
13. [Data Format Standards](#13-data-format-standards)
14. [Error Response Format](#14-error-response-format)
15. [Rate Limiting & Security](#15-rate-limiting--security)

---

## 1. Overview

The ODEL Moodle system needs to:

- **Verify identities** during student/staff registration (Step 3 of wizard)
- **Sync student records** to auto-create Moodle accounts
- **Sync course catalogs** to auto-map Moodle courses to MRU course codes
- **Push grades/marks** from Moodle gradebook back to the core portal
- **Pull grades/marks** from the core portal into Moodle (bidirectional sync)
- **Determine user type** (student vs. lecturer vs. staff) for role assignment
- **Check enrollment & fee status** to control course access
- **Receive real-time updates** when records change in the core system

**Base URL pattern:** `https://portal.mru.ac.ug/api/v1/`  
**Auth:** Bearer token (obtained via API key + secret exchange)  
**Format:** JSON request/response throughout  

---

## 2. Authentication

### 2.1 — Obtain Access Token

| | |
|---|---|
| **Endpoint** | `POST /auth/token` |
| **Auth** | None (public) |
| **Purpose** | Exchange API credentials for a bearer token |

**Request body:**
```json
{
  "api_key": "odel_moodle_integration",
  "api_secret": "your-secret-here"
}
```

**Response:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "expires_in": 3600,
  "token_type": "Bearer"
}
```

**Notes:**
- Token should be valid for at least 1 hour
- ODEL will cache and refresh automatically
- Recommend separate API credentials for ODEL (not a user account)

### 2.2 — Validate / Refresh Token

| | |
|---|---|
| **Endpoint** | `POST /auth/refresh` |
| **Auth** | Bearer token |
| **Purpose** | Refresh an expiring token without re-authenticating |

### 2.3 — Connectivity Check

| | |
|---|---|
| **Endpoint** | `GET /ping` |
| **Auth** | None |
| **Purpose** | Health check — verify API is reachable |

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2026-04-08T10:00:00Z",
  "version": "1.0"
}
```

---

## 3. Identity Verification (Registration)

> Used during **Step 3** of the ODEL registration wizard. After a user verifies their MRU email via OTP, we look them up in the core portal to confirm their identity and pre-fill their details.

### 3.1 — Lookup Person by Email

| | |
|---|---|
| **Endpoint** | `GET /people/lookup?email={email}` |
| **Auth** | Bearer token |
| **Purpose** | Find a student or staff member by their MRU email address |
| **Priority** | **CRITICAL — blocks registration Step 3** |

**Response (found):**
```json
{
  "found": true,
  "person_type": "student",
  "data": {
    "mru_id": "2024/BSE/0142/PS",
    "student_no": "24-0142",
    "surname": "Nakamya",
    "other_names": "Grace",
    "gender": "F",
    "email": "grace.nakamya@mru.ac.ug",
    "phone": "+256700123456",
    "national_id": "CM9XXXXXXXXXXXXXXX",
    "date_of_birth": "1999-05-12",
    "programme_code": "BSE",
    "programme_name": "Bachelor of Software Engineering",
    "faculty": "Faculty of Science and Technology",
    "department": "Computer Science",
    "year_of_study": 2,
    "semester": 1,
    "academic_year": "2025/2026",
    "intake": "August 2024",
    "study_mode": "distance",
    "campus": "Main",
    "status": "active",
    "photo_url": "https://portal.mru.ac.ug/photos/24-0142.jpg"
  }
}
```

**Response (staff member found):**
```json
{
  "found": true,
  "person_type": "lecturer",
  "data": {
    "mru_id": "STAFF-0028",
    "staff_no": "MRU/STF/028",
    "surname": "Ssemakula",
    "other_names": "John",
    "gender": "M",
    "email": "john.ssemakula@mru.ac.ug",
    "phone": "+256701234567",
    "title": "Dr.",
    "qualification": "PhD Computer Science",
    "faculty": "Faculty of Science and Technology",
    "department": "Computer Science",
    "designation": "Senior Lecturer",
    "role": "lecturer",
    "status": "active",
    "photo_url": "https://portal.mru.ac.ug/photos/stf-028.jpg"
  }
}
```

**Response (not found):**
```json
{
  "found": false,
  "person_type": null,
  "message": "No active student or staff found with this email"
}
```

**Fields we need (minimum):**
| Field | Required | Used for |
|---|---|---|
| `mru_id` | **Yes** | Unique identifier, stored in `local_mru_user_map.mru_id` |
| `person_type` | **Yes** | `student` / `lecturer` / `staff` / `admin` — determines Moodle role |
| `surname` | **Yes** | Pre-fills last name during registration |
| `other_names` | **Yes** | Pre-fills first name during registration |
| `email` | **Yes** | Confirmation / cross-check |
| `gender` | Preferred | User profile |
| `programme_code` | Yes (students) | Course enrollment mapping |
| `programme_name` | Yes (students) | Display on confirmation screen |
| `year_of_study` | Yes (students) | Enrollment logic |
| `semester` | Yes (students) | Active semester courses |
| `academic_year` | Yes (students) | Academic period |
| `status` | **Yes** | Must be `active` to register — reject `suspended`, `deferred`, `graduated` |
| `faculty` | Preferred | Moodle category mapping |
| `department` | Preferred | Moodle category mapping |
| `phone` | Optional | User profile |
| `photo_url` | Optional | User profile picture |
| `student_no` / `staff_no` | Preferred | Display, alternate lookup |

### 3.2 — Verify Student by Student Number

| | |
|---|---|
| **Endpoint** | `GET /students/{mru_id}/verify` |
| **Auth** | Bearer token |
| **Purpose** | Quick verification — is this student number active? |

**Response:**
```json
{
  "verified": true,
  "mru_id": "2024/BSE/0142/PS",
  "status": "active",
  "programme_code": "BSE",
  "full_name": "Nakamya Grace"
}
```

---

## 4. Student Data

### 4.1 — Get Full Student Record

| | |
|---|---|
| **Endpoint** | `GET /students/{mru_id}` |
| **Auth** | Bearer token |
| **Purpose** | Full student profile with all details |

**Response:** Same as `data` object in 3.1 lookup, plus additional fields:
```json
{
  "mru_id": "2024/BSE/0142/PS",
  "student_no": "24-0142",
  "surname": "Nakamya",
  "other_names": "Grace",
  "gender": "F",
  "email": "grace.nakamya@mru.ac.ug",
  "phone": "+256700123456",
  "national_id": "CM9XXXXXXXXXXXXXXX",
  "date_of_birth": "1999-05-12",
  "nationality": "Ugandan",
  "district": "Kampala",
  "address": "P.O. Box 12345, Kampala",
  "programme_code": "BSE",
  "programme_name": "Bachelor of Software Engineering",
  "faculty": "Faculty of Science and Technology",
  "department": "Computer Science",
  "year_of_study": 2,
  "semester": 1,
  "academic_year": "2025/2026",
  "intake": "August 2024",
  "expected_graduation": "2028",
  "study_mode": "distance",
  "campus": "Main",
  "sponsor": "Private",
  "status": "active",
  "registration_date": "2024-08-15",
  "photo_url": "https://portal.mru.ac.ug/photos/24-0142.jpg",
  "emergency_contact": {
    "name": "Nakamya Sarah",
    "phone": "+256700654321",
    "relationship": "Mother"
  }
}
```

### 4.2 — Get Students by Programme

| | |
|---|---|
| **Endpoint** | `GET /programmes/{code}/students?academic_year={year}&semester={sem}&status=active` |
| **Auth** | Bearer token |
| **Purpose** | Bulk fetch — all students in a programme for a given period |

**Query parameters:**
| Param | Required | Example |
|---|---|---|
| `academic_year` | Optional | `2025/2026` |
| `semester` | Optional | `1` or `2` |
| `status` | Optional | `active` (default), `all`, `deferred`, `suspended` |
| `page` | Optional | Pagination page (default 1) |
| `per_page` | Optional | Records per page (default 100, max 500) |

**Response:**
```json
{
  "programme_code": "BSE",
  "programme_name": "Bachelor of Software Engineering",
  "academic_year": "2025/2026",
  "semester": 1,
  "total": 245,
  "page": 1,
  "per_page": 100,
  "students": [
    {
      "mru_id": "2024/BSE/0142/PS",
      "student_no": "24-0142",
      "surname": "Nakamya",
      "other_names": "Grace",
      "email": "grace.nakamya@mru.ac.ug",
      "year_of_study": 2,
      "status": "active"
    }
  ]
}
```

### 4.3 — Search Students

| | |
|---|---|
| **Endpoint** | `GET /students/search?q={query}&type={field}` |
| **Auth** | Bearer token |
| **Purpose** | Search students by name, email, or student number |

**Query parameters:**
| Param | Required | Example |
|---|---|---|
| `q` | **Yes** | `nakamya` or `24-0142` or `grace@mru.ac.ug` |
| `type` | Optional | `name`, `email`, `student_no`, `any` (default) |
| `limit` | Optional | Max results (default 25) |

### 4.4 — Get Recently Updated Students

| | |
|---|---|
| **Endpoint** | `GET /students/updated?since={timestamp}` |
| **Auth** | Bearer token |
| **Purpose** | Delta sync — only students whose records changed since last sync |

**Query parameters:**
| Param | Required | Example |
|---|---|---|
| `since` | **Yes** | ISO 8601 timestamp `2026-04-01T00:00:00Z` |
| `page` / `per_page` | Optional | Pagination |

---

## 5. Staff / Lecturer Data

### 5.1 — Get Staff Member

| | |
|---|---|
| **Endpoint** | `GET /staff/{mru_id}` |
| **Auth** | Bearer token |
| **Purpose** | Full staff profile |

**Response:**
```json
{
  "mru_id": "STAFF-0028",
  "staff_no": "MRU/STF/028",
  "surname": "Ssemakula",
  "other_names": "John",
  "title": "Dr.",
  "gender": "M",
  "email": "john.ssemakula@mru.ac.ug",
  "phone": "+256701234567",
  "faculty": "Faculty of Science and Technology",
  "department": "Computer Science",
  "designation": "Senior Lecturer",
  "role": "lecturer",
  "qualification": "PhD Computer Science",
  "status": "active",
  "employment_date": "2018-09-01",
  "photo_url": "https://portal.mru.ac.ug/photos/stf-028.jpg"
}
```

### 5.2 — Get Staff by Department

| | |
|---|---|
| **Endpoint** | `GET /departments/{code}/staff?role={role}` |
| **Auth** | Bearer token |
| **Purpose** | List all staff in a department |

**Query parameters:**
| Param | Required | Example |
|---|---|---|
| `role` | Optional | `lecturer`, `hod`, `tutor`, `admin`, `all` (default) |
| `status` | Optional | `active` (default) |

### 5.3 — Get Lecturer's Courses

| | |
|---|---|
| **Endpoint** | `GET /staff/{mru_id}/courses?academic_year={year}&semester={sem}` |
| **Auth** | Bearer token |
| **Purpose** | Which courses does this lecturer teach? Used to auto-enroll as teacher in Moodle |

**Response:**
```json
{
  "staff_id": "STAFF-0028",
  "academic_year": "2025/2026",
  "semester": 1,
  "courses": [
    {
      "course_code": "CSC1101",
      "course_name": "Introduction to Programming",
      "programme_code": "BSE",
      "programme_name": "Bachelor of Software Engineering",
      "credit_units": 4,
      "role": "main_lecturer"
    },
    {
      "course_code": "CSC2201",
      "course_name": "Data Structures and Algorithms",
      "programme_code": "BSE",
      "programme_name": "Bachelor of Software Engineering",
      "credit_units": 4,
      "role": "main_lecturer"
    }
  ]
}
```

---

## 6. Programme Data

### 6.1 — List All Programmes

| | |
|---|---|
| **Endpoint** | `GET /programmes` |
| **Auth** | Bearer token |
| **Purpose** | Full catalogue of academic programmes for Moodle category structure |

**Response:**
```json
{
  "programmes": [
    {
      "code": "BSE",
      "name": "Bachelor of Software Engineering",
      "short_name": "BSE",
      "faculty": "Faculty of Science and Technology",
      "faculty_code": "FST",
      "department": "Computer Science",
      "department_code": "CS",
      "level": "undergraduate",
      "duration_years": 4,
      "total_semesters": 8,
      "award": "Bachelor of Science",
      "study_modes": ["full_time", "distance"],
      "status": "active"
    }
  ]
}
```

### 6.2 — Get Programme Details with Curriculum

| | |
|---|---|
| **Endpoint** | `GET /programmes/{code}` |
| **Auth** | Bearer token |
| **Purpose** | Full programme info including all courses in the curriculum |

**Response:** Programme object above, plus:
```json
{
  "code": "BSE",
  "name": "Bachelor of Software Engineering",
  "curriculum": [
    {
      "year": 1,
      "semester": 1,
      "courses": [
        {
          "course_code": "CSC1101",
          "course_name": "Introduction to Programming",
          "credit_units": 4,
          "course_type": "core",
          "prerequisite": null
        },
        {
          "course_code": "MTH1101",
          "course_name": "Calculus I",
          "credit_units": 3,
          "course_type": "core",
          "prerequisite": null
        }
      ]
    },
    {
      "year": 1,
      "semester": 2,
      "courses": [...]
    }
  ]
}
```

---

## 7. Course Data

### 7.1 — Get Course Details

| | |
|---|---|
| **Endpoint** | `GET /courses/{code}` |
| **Auth** | Bearer token |
| **Purpose** | Full course metadata |

**Response:**
```json
{
  "course_code": "CSC1101",
  "course_name": "Introduction to Programming",
  "credit_units": 4,
  "description": "An introductory course covering programming fundamentals...",
  "department": "Computer Science",
  "department_code": "CS",
  "level": "100",
  "course_type": "core",
  "prerequisites": [],
  "programmes": ["BSE", "BIT", "BCSF"],
  "grading_scheme": "percentage",
  "pass_mark": 50,
  "status": "active"
}
```

### 7.2 — List Courses by Programme & Semester

| | |
|---|---|
| **Endpoint** | `GET /programmes/{code}/courses?year={year}&semester={sem}` |
| **Auth** | Bearer token |
| **Purpose** | Courses offered in a specific programme, year, and semester |

### 7.3 — Get All Active Courses for Current Semester

| | |
|---|---|
| **Endpoint** | `GET /courses?academic_year={year}&semester={sem}` |
| **Auth** | Bearer token |
| **Purpose** | Bulk course list for auto-mapping with Moodle courses |

---

## 8. Enrollment Data

### 8.1 — Get Student's Enrolled Courses

| | |
|---|---|
| **Endpoint** | `GET /students/{mru_id}/enrollments?academic_year={year}&semester={sem}` |
| **Auth** | Bearer token |
| **Purpose** | Which courses is a student registered for this semester? Used to auto-enroll in Moodle. |
| **Priority** | **HIGH — critical for automatic Moodle enrollment** |

**Response:**
```json
{
  "mru_id": "2024/BSE/0142/PS",
  "academic_year": "2025/2026",
  "semester": 1,
  "enrollments": [
    {
      "course_code": "CSC2101",
      "course_name": "Database Systems",
      "credit_units": 4,
      "registration_date": "2025-09-01",
      "status": "registered",
      "fee_status": "cleared",
      "retake": false
    },
    {
      "course_code": "CSC2103",
      "course_name": "Operating Systems",
      "credit_units": 4,
      "registration_date": "2025-09-01",
      "status": "registered",
      "fee_status": "cleared",
      "retake": false
    }
  ]
}
```

**Enrollment statuses:** `registered`, `provisional`, `dropped`, `retake`  
**Fee statuses:** `cleared`, `partial`, `not_cleared`

### 8.2 — Get Course Enrollment List

| | |
|---|---|
| **Endpoint** | `GET /courses/{code}/enrollments?academic_year={year}&semester={sem}` |
| **Auth** | Bearer token |
| **Purpose** | All students enrolled in a specific course — for bulk Moodle enrollment |

**Response:**
```json
{
  "course_code": "CSC2101",
  "academic_year": "2025/2026",
  "semester": 1,
  "total": 48,
  "students": [
    {
      "mru_id": "2024/BSE/0142/PS",
      "student_no": "24-0142",
      "surname": "Nakamya",
      "other_names": "Grace",
      "email": "grace.nakamya@mru.ac.ug",
      "programme_code": "BSE",
      "fee_status": "cleared",
      "status": "registered"
    }
  ]
}
```

### 8.3 — Get Enrollment Changes (Delta)

| | |
|---|---|
| **Endpoint** | `GET /enrollments/changes?since={timestamp}` |
| **Auth** | Bearer token |
| **Purpose** | New enrollments, drops, status changes since last sync |

**Response:**
```json
{
  "since": "2026-04-01T00:00:00Z",
  "changes": [
    {
      "type": "new_enrollment",
      "mru_id": "2024/BSE/0142/PS",
      "course_code": "CSC2101",
      "academic_year": "2025/2026",
      "semester": 1,
      "timestamp": "2026-04-05T14:30:00Z"
    },
    {
      "type": "dropped",
      "mru_id": "2023/BIT/0089/PS",
      "course_code": "CSC2101",
      "academic_year": "2025/2026",
      "semester": 1,
      "timestamp": "2026-04-06T09:00:00Z"
    }
  ]
}
```

---

## 9. Marks / Grades

### 9.1 — Submit Marks (Moodle → Portal)

| | |
|---|---|
| **Endpoint** | `POST /marks/submit` |
| **Auth** | Bearer token |
| **Purpose** | Push final grades from Moodle gradebook to the core portal |
| **Priority** | **CRITICAL — core sync feature** |

**Request body:**
```json
{
  "course_code": "CSC2101",
  "academic_year": "2025/2026",
  "semester": 1,
  "submitted_by": "john.ssemakula@mru.ac.ug",
  "marks": [
    {
      "student_id": "2024/BSE/0142/PS",
      "coursework_mark": 30.5,
      "coursework_max": 40,
      "exam_mark": 52.0,
      "exam_max": 60,
      "total_mark": 82.5,
      "total_max": 100,
      "grade_letter": "A",
      "grade_point": 5.0,
      "remark": "pass"
    },
    {
      "student_id": "2024/BSE/0098/PS",
      "coursework_mark": 18.0,
      "coursework_max": 40,
      "exam_mark": 25.0,
      "exam_max": 60,
      "total_mark": 43.0,
      "total_max": 100,
      "grade_letter": "E",
      "grade_point": 1.0,
      "remark": "retake"
    }
  ]
}
```

**Response:**
```json
{
  "status": "accepted",
  "course_code": "CSC2101",
  "total_submitted": 48,
  "accepted": 47,
  "rejected": 1,
  "errors": [
    {
      "student_id": "2023/BIT/0200/PS",
      "error": "Student not enrolled in this course"
    }
  ],
  "submission_id": "SUB-2026-04-08-0001",
  "timestamp": "2026-04-08T12:00:00Z"
}
```

### 9.2 — Get Marks for a Course (Portal → Moodle)

| | |
|---|---|
| **Endpoint** | `GET /marks/{course_code}?academic_year={year}&semester={sem}` |
| **Auth** | Bearer token |
| **Purpose** | Pull marks from portal into Moodle (reverse sync or comparison) |

**Response:**
```json
{
  "course_code": "CSC2101",
  "academic_year": "2025/2026",
  "semester": 1,
  "marks": [
    {
      "student_id": "2024/BSE/0142/PS",
      "coursework_mark": 30.5,
      "exam_mark": 52.0,
      "total_mark": 82.5,
      "grade_letter": "A",
      "grade_point": 5.0,
      "remark": "pass",
      "submitted_at": "2026-04-08T12:00:00Z",
      "approved": true,
      "approved_by": "hod.cs@mru.ac.ug"
    }
  ]
}
```

### 9.3 — Get Student's Academic Transcript

| | |
|---|---|
| **Endpoint** | `GET /students/{mru_id}/transcript` |
| **Auth** | Bearer token |
| **Purpose** | Full academic history — all semesters, GPA, CGPA |

**Response:**
```json
{
  "mru_id": "2024/BSE/0142/PS",
  "programme": "BSE",
  "cgpa": 4.2,
  "total_credit_units": 45,
  "semesters": [
    {
      "academic_year": "2024/2025",
      "semester": 1,
      "gpa": 4.0,
      "credit_units": 20,
      "courses": [
        {
          "course_code": "CSC1101",
          "course_name": "Introduction to Programming",
          "credit_units": 4,
          "mark": 75.0,
          "grade": "B",
          "grade_point": 4.0,
          "remark": "pass"
        }
      ]
    }
  ]
}
```

### 9.4 — Get Grading Scheme

| | |
|---|---|
| **Endpoint** | `GET /grading-scheme` |
| **Auth** | Bearer token |
| **Purpose** | Official MRU grading scale — needed to convert Moodle percentage grades to letters/points |

**Response:**
```json
{
  "scheme": "MRU Standard",
  "grades": [
    { "letter": "A", "min_mark": 80, "max_mark": 100, "grade_point": 5.0, "remark": "Excellent" },
    { "letter": "B", "min_mark": 70, "max_mark": 79, "grade_point": 4.0, "remark": "Very Good" },
    { "letter": "C", "min_mark": 60, "max_mark": 69, "grade_point": 3.0, "remark": "Good" },
    { "letter": "D", "min_mark": 50, "max_mark": 59, "grade_point": 2.0, "remark": "Pass" },
    { "letter": "E", "min_mark": 40, "max_mark": 49, "grade_point": 1.0, "remark": "Retake" },
    { "letter": "F", "min_mark": 0, "max_mark": 39, "grade_point": 0.0, "remark": "Fail" }
  ]
}
```

---

## 10. Academic Structure

### 10.1 — List Faculties / Schools

| | |
|---|---|
| **Endpoint** | `GET /faculties` |
| **Auth** | Bearer token |
| **Purpose** | Faculty list — maps to Moodle top-level course categories |

**Response:**
```json
{
  "faculties": [
    {
      "code": "FST",
      "name": "Faculty of Science and Technology",
      "dean": "Prof. Kibuuka Robert",
      "departments": [
        { "code": "CS", "name": "Computer Science" },
        { "code": "IT", "name": "Information Technology" },
        { "code": "ENG", "name": "Engineering" }
      ]
    },
    {
      "code": "FBA",
      "name": "Faculty of Business Administration",
      "dean": "Dr. Mugisha Alice",
      "departments": [
        { "code": "ACC", "name": "Accounting and Finance" },
        { "code": "MGT", "name": "Management" }
      ]
    }
  ]
}
```

### 10.2 — Get Current Academic Calendar

| | |
|---|---|
| **Endpoint** | `GET /academic-calendar?academic_year={year}` |
| **Auth** | Bearer token |
| **Purpose** | Academic periods, start/end dates — used for enrollment timing |

**Response:**
```json
{
  "academic_year": "2025/2026",
  "current_semester": 2,
  "semesters": [
    {
      "number": 1,
      "name": "Semester I",
      "start_date": "2025-08-12",
      "end_date": "2025-12-15",
      "exam_start": "2025-12-01",
      "exam_end": "2025-12-15",
      "results_release": "2026-01-15",
      "registration_deadline": "2025-09-01"
    },
    {
      "number": 2,
      "name": "Semester II",
      "start_date": "2026-01-20",
      "end_date": "2026-05-30",
      "exam_start": "2026-05-15",
      "exam_end": "2026-05-30",
      "results_release": "2026-06-20",
      "registration_deadline": "2026-02-10"
    }
  ],
  "recess_term": {
    "start_date": "2026-06-15",
    "end_date": "2026-08-10"
  }
}
```

### 10.3 — Get Current Academic Period

| | |
|---|---|
| **Endpoint** | `GET /academic-calendar/current` |
| **Auth** | Bearer token |
| **Purpose** | Quick check — what is the current year/semester? |

**Response:**
```json
{
  "academic_year": "2025/2026",
  "semester": 2,
  "period_name": "Semester II 2025/2026",
  "registration_open": true,
  "exam_period": false
}
```

---

## 11. Fee / Payment Status

### 11.1 — Check Student Fee Status

| | |
|---|---|
| **Endpoint** | `GET /students/{mru_id}/fees?academic_year={year}&semester={sem}` |
| **Auth** | Bearer token |
| **Purpose** | Determine if a student has cleared fees — controls access to Moodle courses |
| **Priority** | **HIGH — may block course access** |

**Response:**
```json
{
  "mru_id": "2024/BSE/0142/PS",
  "academic_year": "2025/2026",
  "semester": 1,
  "fee_status": "cleared",
  "total_fees": 1500000,
  "amount_paid": 1500000,
  "balance": 0,
  "currency": "UGX",
  "last_payment_date": "2025-08-20",
  "clearance_status": "cleared",
  "payment_plan": null
}
```

**Fee statuses:** `cleared`, `partial`, `not_cleared`, `sponsored`, `bursary`

**Note:** ODEL may restrict course access or show warnings for students who haven't cleared fees. This endpoint is essential for that logic.

### 11.2 — Bulk Fee Status Check

| | |
|---|---|
| **Endpoint** | `POST /fees/bulk-check` |
| **Auth** | Bearer token |
| **Purpose** | Check multiple students at once during enrollment sync |

**Request:**
```json
{
  "academic_year": "2025/2026",
  "semester": 1,
  "student_ids": ["2024/BSE/0142/PS", "2024/BSE/0098/PS", "2023/BIT/0089/PS"]
}
```

**Response:**
```json
{
  "results": [
    { "mru_id": "2024/BSE/0142/PS", "fee_status": "cleared" },
    { "mru_id": "2024/BSE/0098/PS", "fee_status": "partial", "balance": 500000 },
    { "mru_id": "2023/BIT/0089/PS", "fee_status": "not_cleared", "balance": 1500000 }
  ]
}
```

---

## 12. Notifications & Webhooks

> Optional but highly recommended — allows ODEL to react in real-time instead of polling.

### 12.1 — Register Webhook

| | |
|---|---|
| **Endpoint** | `POST /webhooks/register` |
| **Auth** | Bearer token |
| **Purpose** | Subscribe ODEL to events from the core portal |

**Request:**
```json
{
  "url": "https://odel.mru.ac.ug/local/mru/webhook.php",
  "secret": "shared-hmac-secret",
  "events": [
    "student.status_changed",
    "student.enrolled",
    "student.dropped",
    "student.fee_cleared",
    "student.fee_revoked",
    "marks.approved",
    "marks.rejected",
    "course.updated",
    "semester.started",
    "semester.ended"
  ]
}
```

### 12.2 — Webhook Payload Format

**Headers:**
```
Content-Type: application/json
X-MRU-Signature: sha256=<HMAC of body with shared secret>
X-MRU-Event: student.enrolled
X-MRU-Delivery: <unique delivery ID>
```

**Body example (`student.enrolled`):**
```json
{
  "event": "student.enrolled",
  "timestamp": "2026-04-08T14:30:00Z",
  "data": {
    "mru_id": "2024/BSE/0142/PS",
    "course_code": "CSC2201",
    "academic_year": "2025/2026",
    "semester": 2,
    "fee_status": "cleared"
  }
}
```

### Webhook Events We Need

| Event | Trigger | ODEL Action |
|---|---|---|
| `student.status_changed` | Student suspended/deferred/graduated | Suspend/update Moodle account |
| `student.enrolled` | New course registration | Auto-enroll in Moodle course |
| `student.dropped` | Course dropped | Unenroll from Moodle course |
| `student.fee_cleared` | Fees paid | Grant full course access |
| `student.fee_revoked` | Fee clearance revoked | Restrict course access |
| `marks.approved` | HOD/Registrar approves marks | Update sync status |
| `marks.rejected` | Marks rejected for correction | Flag for re-submission |
| `course.updated` | Course name/credits changed | Update Moodle course metadata |
| `semester.started` | New semester opened | Trigger bulk enrollment sync |
| `semester.ended` | Semester closed | Archive, trigger marks push |

---

## 13. Data Format Standards

| Aspect | Standard |
|---|---|
| **Date/time** | ISO 8601 (`2026-04-08T14:30:00Z`) |
| **Date only** | `YYYY-MM-DD` (`2026-04-08`) |
| **Academic year** | `YYYY/YYYY` (`2025/2026`) |
| **Semester** | Integer: `1`, `2`, `3` (recess) |
| **Character encoding** | UTF-8 |
| **IDs** | Strings (not integers) — student numbers may contain slashes/letters |
| **Currency** | UGX, integer amounts (no decimals for Ugandan Shillings) |
| **Marks** | Decimal with up to 2 places (`82.50`) |
| **Phone numbers** | E.164 format (`+256700123456`) |
| **Pagination** | `page` (1-based) + `per_page` + `total` in response |
| **Empty/null** | Use `null` for absent values, not empty strings |
| **Booleans** | `true` / `false` (JSON native) |

---

## 14. Error Response Format

All errors should follow a consistent structure:

```json
{
  "error": true,
  "code": "STUDENT_NOT_FOUND",
  "message": "No student found with the given ID",
  "details": null
}
```

### Standard Error Codes

| HTTP Status | Code | Meaning |
|---|---|---|
| 400 | `INVALID_REQUEST` | Malformed request or missing required fields |
| 401 | `UNAUTHORIZED` | Missing or invalid token |
| 403 | `FORBIDDEN` | Valid token but insufficient permissions |
| 404 | `NOT_FOUND` | Resource does not exist |
| 409 | `CONFLICT` | Duplicate submission or data conflict |
| 422 | `VALIDATION_ERROR` | Request understood but data invalid |
| 429 | `RATE_LIMITED` | Too many requests |
| 500 | `INTERNAL_ERROR` | Server-side failure |
| 503 | `SERVICE_UNAVAILABLE` | Maintenance or temporary outage |

---

## 15. Rate Limiting & Security

### Requirements from ODEL side:

| Concern | Requirement |
|---|---|
| **Rate limits** | ODEL will make at most ~100 requests/minute during bulk sync. Please allow at least 120 req/min. |
| **Token lifetime** | At least 1 hour per token. ODEL caches tokens. |
| **HTTPS** | All endpoints must be HTTPS (TLS 1.2+) |
| **IP whitelist** | ODEL server IP can be whitelisted if needed |
| **HMAC for webhooks** | Webhook payloads must be signed with HMAC-SHA256 |
| **Data sensitivity** | National IDs, phone numbers, and financial data should only be returned when explicitly requested |
| **Audit logging** | API should log all mark submissions with the submitter identity |
| **Idempotency** | Mark submissions should be idempotent — re-submitting same data should not create duplicates |

---

## Summary: Priority Endpoints

| # | Endpoint | Priority | Blocks |
|---|---|---|---|
| 1 | `GET /people/lookup?email=` | **P0 — Critical** | Registration wizard Step 3 |
| 2 | `GET /students/{id}/verify` | **P0 — Critical** | Student verification |
| 3 | `GET /students/{id}` | **P0 — Critical** | Full student profile |
| 4 | `POST /marks/submit` | **P0 — Critical** | Grade sync to portal |
| 5 | `GET /students/{id}/enrollments` | **P1 — High** | Auto Moodle enrollment |
| 6 | `GET /courses/{code}/enrollments` | **P1 — High** | Bulk Moodle enrollment |
| 7 | `GET /students/{id}/fees` | **P1 — High** | Fee-based course access |
| 8 | `GET /programmes/{code}/students` | **P1 — High** | Bulk student import |
| 9 | `GET /programmes` | **P2 — Medium** | Category structure |
| 10 | `GET /programmes/{code}` | **P2 — Medium** | Curriculum mapping |
| 11 | `GET /courses/{code}` | **P2 — Medium** | Course metadata |
| 12 | `GET /staff/{id}` | **P2 — Medium** | Staff accounts |
| 13 | `GET /staff/{id}/courses` | **P2 — Medium** | Teacher enrollment |
| 14 | `GET /academic-calendar/current` | **P2 — Medium** | Semester awareness |
| 15 | `GET /grading-scheme` | **P2 — Medium** | Grade conversion |
| 16 | `GET /marks/{code}` | **P3 — Nice to have** | Reverse mark sync |
| 17 | `GET /students/{id}/transcript` | **P3 — Nice to have** | Academic history |
| 18 | `GET /faculties` | **P3 — Nice to have** | Category mapping |
| 19 | `POST /webhooks/register` | **P3 — Nice to have** | Real-time sync |
| 20 | Delta endpoints (`/updated`, `/changes`) | **P3 — Nice to have** | Efficient sync |

---

> **Next step:** Once the portal team confirms which endpoints they can provide and the response formats, we will wire them into the `local_mru` plugin's `api_client.php` and activate registration Step 3 identity lookup.
