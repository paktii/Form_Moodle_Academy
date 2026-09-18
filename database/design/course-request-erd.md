# ERD ระบบคำร้องขอสร้างรายวิชา

> สถานะการตรวจสอบ: ฝั่ง `saladb.dbo` ของ Server 199 ตรวจชื่อตารางและคอลัมน์กับ CSV snapshot แล้ว ส่วน `course133` รัน Laravel Migration ลง `academy_db1447` สำเร็จแล้วและเป็น As-built schema ของระบบปัจจุบัน

ระบบเป้าหมายแยกเป็น 2 SQL Server databases:

- Server 199 / `saladb.dbo`: บุคลากรและหน่วยงาน
- Server 133 / `academy_db1447.course133`: สิทธิ์ คำร้อง เอกสาร การตรวจ อนุมัติ และ Course ID

`LR` หมายถึง Logical Reference ไป Server 199 ช่วงแรกใช้ค่า `int` ที่กำหนดใน Laravel โดยยังไม่ตรวจข้าม Server

> ความสัมพันธ์หน่วยงานแม่–ลูกไม่แสดงเป็นเส้นในภาพใหญ่เพื่อป้องกันเส้นวนที่อ่านยาก โดย `dbo.departments.in_dept_cd` อ้าง `dbo.departments.dept_cd` ภายในตารางเดียวกัน

## สัญลักษณ์ของ Relation

| สัญลักษณ์ | Cardinality | ความหมาย |
|---|---|---|
| `||` | `1` | ต้องมีหนึ่งรายการเท่านั้น |
| `o|` | `0..1` | ไม่มีเลยหรือมีได้หนึ่งรายการ |
| `|{` | `1..N` | ต้องมีอย่างน้อยหนึ่งรายการและมีได้หลายรายการ |
| `o{` | `0..N` | ไม่มีเลยหรือมีได้หลายรายการ |
| `--` | — | เส้นเชื่อมความสัมพันธ์ระหว่างสองตาราง |

ให้อ่านเครื่องหมายที่อยู่ติดกับตารางแต่ละฝั่ง เช่น `PROJECT_TYPE ||--o{ COURSE_REQUEST` หมายถึงคำร้องแต่ละรายการต้องมีประเภทโครงการหนึ่งประเภท และประเภทหนึ่งถูกใช้กับคำร้องได้หลายรายการ

## คำอธิบายที่อยู่บนเส้น

คำบนเส้นให้อ่านจากชื่อตารางฝั่งซ้ายไปฝั่งขวา คำเหล่านี้ช่วยบอกหน้าที่ของความสัมพันธ์และไม่ใช่ชื่อคอลัมน์

| คำบนเส้น | อ่านว่า | ความหมายในระบบนี้ |
|---|---|---|
| `contains` | ประกอบด้วย/มี | หน่วยงานมีบุคลากรอยู่ภายใน |
| `grants` | มอบสิทธิ์ | Role ถูกนำไปกำหนดให้บุคลากรผ่าน `user_role_assignment` |
| `includes` | ประกอบด้วย | คำร้องมีรายชื่ออาจารย์ผู้สอนหนึ่งคนขึ้นไป |
| `has` | มี | คำร้องมีเอกสารที่เกี่ยวข้อง เช่น PDF ที่ระบบสร้างหรือ PDF ลงนามแล้ว |
| `receives` | ได้รับ | คำร้องได้รับผลตรวจหรือผลอนุมัติ; เมื่อใช้กับ notification หมายถึงบุคลากรเป็นผู้รับแจ้งเตือน |
| `classifies` | จัดประเภท | Master ประเภทโครงการถูกเลือกใช้ในคำร้อง |
| `categorizes` | จัดหมวดหมู่ | Master หมวดหมู่รายวิชาถูกเลือกใช้ในคำร้อง |
| `records` | บันทึก | คำร้องมีประวัติสถานะหลายเหตุการณ์ |
| `records_course_id` | บันทึก Course ID | บุคลากรเป็น Officer ผู้กรอก Course ID ลงในคำร้อง |
| `triggers` | กระตุ้นให้เกิด | เหตุการณ์ของคำร้องสร้างรายการแจ้งเตือนใน outbox |
| `identifies` | ระบุตัวบุคคล | รหัสบุคลากรจาก `dbo.persons` ใช้ระบุว่า role assignment หรือผู้สอนเป็นใคร |
| `submits` | ยื่นคำร้อง | บุคลากรเป็นผู้ยื่น `course_request` |
| `target_department` | หน่วยงานเป้าหมาย | ส่วนงาน/คณะ/สำนักที่ผู้ยื่นต้องการสร้างรายวิชาให้ โดยเจ้าหน้าที่สำนักคอมพิวเตอร์ยังเป็นผู้ตรวจคำร้อง |
| `uploads` | อัปโหลด | บุคลากรเป็นผู้สร้างหรืออัปโหลดเอกสาร |
| `reviews` | ตรวจสอบ | บุคลากรที่มีบทบาท Officer เป็นผู้ตรวจคำร้อง |
| `approves` | พิจารณาอนุมัติ | บุคลากรที่มีบทบาท Approver เป็นผู้อนุมัติ ปฏิเสธ หรือส่งกลับ |
| `changes` | เปลี่ยนสถานะ | บุคลากรเป็นผู้ทำให้สถานะคำร้องเปลี่ยน |
| `parent_of` | เป็นหน่วยงานแม่ของ | ใช้กับ `departments.in_dept_cd → departments.dept_cd`; คำนี้ถูกนำออกจากภาพใหญ่เพราะเส้นวนอ่านยาก |

คำที่ขึ้นต้นด้วย `LR` หมายถึงความสัมพันธ์นั้นเป็น Logical Reference ข้ามฐานข้อมูล เช่น `LR uploads` คือรหัสผู้อัปโหลดในฐาน 133 อ้างถึงบุคลากรในฐาน 199 โดยให้ Laravel ตรวจสอบ

```mermaid
erDiagram
    DBO_DEPARTMENTS o|--o{ DBO_PERSONS : contains

    APP_ROLE ||--o{ USER_ROLE_ASSIGNMENT : grants
    PROJECT_TYPE ||--o{ COURSE_REQUEST : classifies
    COURSE_CATEGORY ||--o{ COURSE_REQUEST : categorizes
    COURSE_REQUEST ||--|{ COURSE_INSTRUCTOR : includes
    COURSE_REQUEST ||--o{ COURSE_DOCUMENT : has
    COURSE_REQUEST ||--o{ OFFICER_REVIEW : receives
    COURSE_REQUEST ||--o{ COURSE_APPROVAL : receives
    COURSE_REQUEST ||--o{ REQUEST_STATUS_HISTORY : records
    COURSE_REQUEST ||--o{ NOTIFICATION_OUTBOX : triggers

    DBO_PERSONS ||--o{ USER_ROLE_ASSIGNMENT : "LR identifies"
    DBO_PERSONS ||--o{ COURSE_REQUEST : "LR submits"
    DBO_DEPARTMENTS ||--o{ COURSE_REQUEST : "LR target_department"
    DBO_PERSONS o|--o{ COURSE_INSTRUCTOR : "LR identifies"
    DBO_PERSONS ||--o{ COURSE_DOCUMENT : "LR uploads"
    DBO_PERSONS ||--o{ OFFICER_REVIEW : "LR reviews"
    DBO_PERSONS ||--o{ COURSE_APPROVAL : "LR approves"
    DBO_PERSONS o|--o{ COURSE_REQUEST : "LR records_course_id"
    DBO_PERSONS o|--o{ REQUEST_STATUS_HISTORY : "LR changes"
    DBO_PERSONS o|--o{ NOTIFICATION_OUTBOX : "LR receives"

    DBO_DEPARTMENTS {
        integer dept_cd PK
        varchar dept_lname_th
        integer in_dept_cd FK
        varchar active_flag
    }

    DBO_PERSONS {
        integer person_id PK
        varchar buasri_id
        integer dept_cd FK
        integer in_dept_cd FK
        varchar person_fname_th
        varchar person_lname_th
        varchar active_flag
    }

    APP_ROLE {
        smallint role_id PK
        varchar role_code UK
        varchar role_name_th
    }

    USER_ROLE_ASSIGNMENT {
        bigint role_assignment_id PK
        int pers_id "LR: dbo.persons.person_id"
        smallint role_id FK
        bit is_active
    }

    PROJECT_TYPE {
        varchar project_type_code PK
        varchar project_type_name_th UK
        bit requires_detail
        bit is_active
    }

    COURSE_CATEGORY {
        varchar category_code PK
        varchar category_name_th UK
        bit is_active
    }

    COURSE_REQUEST {
        bigint request_id PK
        varchar request_no UK
        int requester_pers_id "LR: dbo.persons.person_id"
        int target_dept_id "LR: dbo.departments.dept_cd"
        varchar course_name_th
        varchar course_name_en
        varchar status
        datetime2 submitted_at
        datetime2 created_at
        datetime2 updated_at
        varchar project_name
        varchar project_type_code FK
        varchar project_other
        varchar coordinator_first_name
        varchar coordinator_last_name
        varchar coordinator_position
        varchar coordinator_phone
        varchar coordinator_email
        varchar subject_code
        varchar category_code FK
        text course_description
        varchar learning_mode
        date starts_on
        date ends_on
        varchar enrollment_method
        varchar enrollment_other
        integer expected_students
        varchar course_id
        int recorded_by_pers_id "LR: dbo.persons.person_id"
    }

    COURSE_INSTRUCTOR {
        bigint instructor_id PK
        bigint request_id FK
        int pers_id "LR: dbo.persons.person_id"
        varchar instructor_name
        varchar instructor_email
    }

    COURSE_DOCUMENT {
        bigint document_id PK
        bigint request_id FK
        varchar document_type
        varchar storage_key UK
        int uploaded_by_pers_id "LR: dbo.persons.person_id"
        datetime2 uploaded_at
    }

    OFFICER_REVIEW {
        bigint officer_review_id PK
        bigint request_id FK
        int officer_pers_id "LR: dbo.persons.person_id"
        varchar decision
        text return_reason
        datetime2 reviewed_at
    }

    COURSE_APPROVAL {
        bigint approval_id PK
        bigint request_id FK
        int approver_pers_id "LR: dbo.persons.person_id"
        varchar decision
        text comment
        datetime2 decided_at
    }

    REQUEST_STATUS_HISTORY {
        bigint history_id PK
        bigint request_id FK
        varchar status
        int changed_by_pers_id "LR: dbo.persons.person_id"
        varchar change_source
        datetime2 changed_at
    }

    NOTIFICATION_OUTBOX {
        bigint notification_id PK
        bigint request_id FK
        varchar notification_type
        int recipient_pers_id "LR: dbo.persons.person_id"
        varchar recipient_email
        varchar delivery_status
        integer attempt_count
        datetime2 created_at
    }
```

## Cardinality หลัก

| Parent | Child | Cardinality | การบังคับ |
|---|---|---:|---|
| `dbo.departments` | `dbo.departments` | 1 : 0..N | FK `in_dept_cd` ภายในฐาน 199 |
| `dbo.departments` | `dbo.persons` | บุคคล 0..1 หน่วยงาน; หน่วยงาน 0..N บุคคล | FK `dept_cd` และ `in_dept_cd` เป็น nullable |
| `course133.app_role` | `course133.user_role_assignment` | 1 : 0..N | FK |
| `course133.course_request` | `course_instructor` | 1 : 1..N | FK; Laravel บังคับอย่างน้อย 1 คน |
| `course133.course_request` | `course_document` | 1 : 0..N | FK |
| `course133.course_request` | `officer_review` | 1 : 0..N | FK |
| `course133.course_request` | `course_approval` | 1 : 0..N | FK |
| `course133.project_type` | `course133.course_request` | 1 : 0..N | FK `project_type_code` |
| `course133.course_category` | `course133.course_request` | 1 : 0..N | FK `category_code` |
| `course133.course_request` | `request_status_history` | 1 : 0..N | FK |
| `course133.course_request` | `notification_outbox` | 1 : 0..N | FK |
| ฐาน 199 | คอลัมน์ที่ระบุ `LR` ในฐาน 133 | 1 : 0..N | Laravel ตรวจผ่าน 2 connections |

รายละเอียดคอลัมน์ทั้งหมดอยู่ใน [Data Dictionary](course-request-data-dictionary-th.md)

