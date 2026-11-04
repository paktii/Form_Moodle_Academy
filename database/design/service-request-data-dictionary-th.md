# ระบบแบบฟอร์มขอรับบริการ — Data Dictionary

เอกสารนี้เป็นโครงสร้างฐานข้อมูลเป้าหมายสำหรับรวมแบบฟอร์มขอรับบริการหลายประเภทไว้ในระบบเดียวกันบน Server 133 โดยครอบคลุมแบบฟอร์มต่อไปนี้

ERD และคำอธิบาย workflow ปัจจุบันอยู่ที่ [service-portal-current-erd-and-workflow-th.md](./service-portal-current-erd-and-workflow-th.md)

| รหัสแบบฟอร์ม | ชื่อแบบฟอร์ม |
|---|---|
| `CC-ITED-01` | แบบฟอร์มขอใช้พื้นที่สำหรับจัดทำเว็บไซต์ |
| `CC-ITED-02` | แบบฟอร์มขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์ |
| `CC-ITED-04` | แบบฟอร์มขอเพิ่มพื้นที่บนเว็บไซต์ |
| `CC-ITED-05` | แบบฟอร์มขอสิทธิ์ผู้ดูแลกรอบการประชุมของส่วนงานบนระบบ e-Meeting |
| `CC-ITED-06` | แบบฟอร์มขอสร้างรายวิชาในระบบ SWU Moodle Academy |

โครงสร้างนี้ deploy แล้วใน schema `service_portal` ของฐาน `academy_db1447` และเป็นแหล่งข้อมูลหลักของระบบ ตารางทดสอบเดิมใน schema `course133` ถูกลบด้วย migration วันที่ 26 ต.ค. 2569

## Workflow ปัจจุบัน

```text
ผู้ยื่นส่งคำร้อง
→ หัวหน้าส่วนงาน/คณบดี/ผู้อำนวยการรับรอง
→ เจ้าหน้าที่ตรวจและบันทึกผลดำเนินการในครั้งเดียว
→ COMPLETED
→ ส่งอีเมลแจ้งผลให้ผู้ยื่น
```

- ไม่มีขั้นตอนและ role `APPROVER` ใน workflow ปัจจุบัน
- ไม่มีสถานะ `PENDING_APPROVAL`
- ไม่มีสถานะ `PENDING_COURSE_ID`
- แบบฟอร์ม `CC-ITED-06` บังคับกรอก Course ID เมื่อเจ้าหน้าที่อนุมัติ
- ไม่มีการสร้าง notification ถึง Approver เมื่อคำร้องเสร็จสมบูรณ์
- ไม่มีตาราง `approver_review` ใน schema ปัจจุบัน

## สัญลักษณ์

| สัญลักษณ์ | ความหมาย |
|---|---|
| PK | Primary Key |
| UK | Unique Key |
| FK | Foreign Key ภายในฐานข้อมูลเดียวกัน |
| LR | Logical Reference ไปยัง SWU MIS API โดยไม่มี Foreign Key |
| NULL | ไม่บังคับมีค่า |
| NOT NULL | บังคับมีค่า |
| Conditional | บังคับตามประเภทแบบฟอร์มหรือค่าของฟิลด์อื่น |

## แหล่งข้อมูลและ schema

| แหล่งข้อมูล | หน้าที่ |
|---|---|
| SWU MIS API `Officer` | บุคลากร ชื่อ ตำแหน่ง อีเมล และ `personId` |
| SWU MIS API `Department` | ส่วนงาน/คณะ/สำนัก และ `deptCd` |
| `academy_db1447.service_portal` | Master คำร้อง รายละเอียด เอกสาร ประวัติ และ notification |

คอลัมน์ `*_pers_id` และ `*_dept_id` เป็น LR ไปยัง SWU MIS API แอปพลิเคชันต้องตรวจสอบว่ารหัสยังใช้งานได้ก่อนบันทึก

คอลัมน์ `is_active` ทุกตารางใช้ `char(1)` โดยรับเฉพาะ `Y` หรือ `N` และใช้ `Y` เป็นค่าเริ่มต้น

## ภาพรวมกลุ่มตาราง

### Master และ Configuration

1. `form_type`
2. `request_status`
3. `contact_role`
4. `document_type`
5. `notification_type`
6. `website_platform`
7. `app_role`
8. `user_role_assignment`
9. `faculty_dean`
10. `form_notification_recipient`
11. `form_head_assignment`
12. `form_officer_assignment`
13. `project_type`
14. `course_category`

### Transaction กลาง

1. `service_request`
2. `request_contact`
3. `request_document`
4. `dean_review`
5. `officer_review`
6. `request_status_history`
7. `notification_outbox`

### รายละเอียดเฉพาะแบบฟอร์ม

1. `cc_ited_01_request_detail` สำหรับ `CC-ITED-01`
2. `cc_ited_02_request_detail` สำหรับ `CC-ITED-02`
3. `cc_ited_04_request_detail` สำหรับ `CC-ITED-04`
4. `cc_ited_05_request_detail` สำหรับ `CC-ITED-05`
5. `cc_ited_06_request_detail` สำหรับ `CC-ITED-06`
6. `course_instructor` สำหรับอาจารย์ผู้สอนของ `CC-ITED-06`

---

# Master และ Configuration

## `service_portal.form_type`

Master รายการแบบฟอร์ม ใช้สร้างเมนูด้านซ้าย ตัวกรองหน้า Home และกำหนด route ของแต่ละแบบฟอร์ม

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `form_type_code` | varchar(20) | PK | — | รหัสแบบฟอร์ม เช่น `CC-ITED-01` |
| `form_name_th` | nvarchar(500) | UK, NOT NULL | — | ชื่อแบบฟอร์มเต็มภาษาไทย |
| `short_name_th` | nvarchar(200) | NOT NULL | — | ชื่อย่อสำหรับเมนูและรายการคำร้อง |
| `route_name` | varchar(150) | NOT NULL | — | Laravel route สำหรับเริ่มกรอกแบบฟอร์ม |
| `current_version` | smallint | NOT NULL | `1` | เวอร์ชันแบบฟอร์มที่เปิดใช้งาน |
| `sort_order` | smallint | NOT NULL | `0` | ลำดับในเมนู |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดรับคำร้องใหม่, `N` ปิดรับคำร้องใหม่ |
| `created_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้าง |
| `updated_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาแก้ไขล่าสุด |

ค่าเริ่มต้นที่ต้อง seed คือ `CC-ITED-01`, `CC-ITED-02`, `CC-ITED-04`, `CC-ITED-05` และ `CC-ITED-06`

## `service_portal.request_status`

Master สถานะกลางสำหรับทุกแบบฟอร์ม สีในหน้า Home ต้องอ่านจาก `badge_style` หรือ mapping ของ UI เดียวกัน

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `status_code` | varchar(40) | PK | — | รหัสสถานะ |
| `status_name_th` | nvarchar(100) | UK, NOT NULL | — | ชื่อสถานะที่แสดงต่อผู้ใช้ |
| `badge_style` | varchar(30) | NOT NULL | `NEUTRAL` | `NEUTRAL`, `INFO`, `WARNING`, `SUCCESS`, `DANGER` |
| `is_terminal` | bit | NOT NULL | `0` | เป็นสถานะสิ้นสุดหรือไม่ |
| `sort_order` | smallint | NOT NULL | `0` | ลำดับในตัวกรอง |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดใช้สถานะ, `N` ปิดใช้สถานะ |

ค่าที่ต้อง seed:

| `status_code` | ชื่อแสดง | Terminal |
|---|---|---:|
| `DRAFT` | แบบร่าง | 0 |
| `PENDING_DEAN_REVIEW` | รอผู้รับรอง | 0 |
| `UNDER_OFFICER_REVIEW` | รอเจ้าหน้าที่ดำเนินการ | 0 |
| `REJECTED` | ไม่อนุมัติ | 1 |
| `COMPLETED` | ดำเนินการเสร็จสิ้น | 1 |

## `service_portal.contact_role`

Master บทบาทของบุคคลที่ระบุอยู่ในคำร้อง หนึ่งคำร้องมีผู้เกี่ยวข้องได้หลายคน

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `contact_role_code` | varchar(50) | PK | — | รหัสบทบาทในคำร้อง |
| `contact_role_name_th` | nvarchar(150) | UK, NOT NULL | — | ชื่อบทบาทภาษาไทย |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดใช้บทบาท, `N` ปิดใช้บทบาท |

ค่าที่ต้อง seed:

| Code | ความหมาย |
|---|---|
| `WEBSITE_CONTACT` | ผู้ดูแลเว็บไซต์ที่ระบุเป็นข้อมูลติดต่อในคำร้อง |
| `CURRENT_WEBSITE_CONTACT` | ผู้ดูแลเว็บไซต์เดิมที่ระบุในคำร้อง |
| `NEW_WEBSITE_CONTACT` | ผู้ดูแลเว็บไซต์คนใหม่ที่ระบุในคำร้อง |
| `EMEETING_CONTACT` | ผู้ดูแลกรอบการประชุมที่ระบุเป็นผู้รับสิทธิ์ในคำร้อง |
| `CURRENT_EMEETING_CONTACT` | ผู้ดูแลกรอบการประชุมเดิมที่ระบุในคำร้อง |
| `COORDINATOR` | ผู้ประสานงานโครงการ Moodle |
| `INSTRUCTOR` | อาจารย์ผู้สอน ใช้เมื่อไม่แยกไป `course_instructor` |

## `service_portal.document_type`

Master ประเภทไฟล์ที่แนบกับคำร้อง ไฟล์จริงไม่เก็บในฐานข้อมูล

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `document_type_code` | varchar(50) | PK | — | รหัสประเภทเอกสาร |
| `document_type_name_th` | nvarchar(150) | UK, NOT NULL | — | ชื่อประเภทเอกสาร |
| `allowed_mime_types` | nvarchar(1000) | NULL | `NULL` | รายการ MIME type ที่อนุญาต คั่นด้วย comma |
| `max_file_size_bytes` | bigint | NULL | `NULL` | ขนาดสูงสุดต่อไฟล์; NULL ให้ใช้ค่า config กลาง |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดใช้ประเภทเอกสาร, `N` ปิดใช้ |

ค่าหลักที่ต้อง seed: `GENERATED_FORM`, `SIGNED_FORM`, `ADDITIONAL_DOCUMENT`, `STUDENT_ROSTER`

## `service_portal.notification_type`

Master เหตุการณ์ที่สร้างอีเมลหรือการแจ้งเตือน

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `notification_type_code` | varchar(60) | PK | — | รหัสเหตุการณ์ |
| `notification_type_name_th` | nvarchar(200) | UK, NOT NULL | — | ชื่อเหตุการณ์ |
| `template_key` | varchar(150) | NOT NULL | — | key ของ email template ในแอปพลิเคชัน |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดใช้เหตุการณ์, `N` ปิดใช้ |

ค่าหลักที่ต้อง seed:

| Code | เหตุการณ์ |
|---|---|
| `DEAN_REVIEW_REQUIRED` | ส่งคำร้องให้ผู้รับรอง |
| `DEAN_REJECTED` | ผู้รับรองไม่รับรอง |
| `OFFICER_REVIEW_REQUIRED` | ส่งคำร้องให้เจ้าหน้าที่ดำเนินการ |
| `REQUEST_REJECTED` | เจ้าหน้าที่ไม่อนุมัติคำร้อง |
| `REQUEST_COMPLETED_REQUESTER` | แจ้งผู้ยื่นเมื่อดำเนินการเสร็จ |
| `REQUEST_COMPLETED_FORM_HEAD` | แจ้งหัวหน้าผู้รับผิดชอบของแบบฟอร์มเมื่อดำเนินการเสร็จ |
| `REQUEST_RESULT_UPDATED` | แจ้งผู้ยื่นเมื่อเจ้าหน้าที่แก้ไขผลดำเนินการหลังคำร้องเสร็จ |

`REQUEST_COMPLETED_APPROVER` เป็น notification type เดิมที่ยังอยู่ใน master จาก schema เริ่มต้น แต่ workflow ปัจจุบันไม่สร้าง outbox ชนิดนี้

## `service_portal.website_platform`

Master ประเภทระบบเว็บไซต์สำหรับ `CC-ITED-01`

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `platform_code` | varchar(30) | PK | — | รหัสประเภทระบบ |
| `platform_name_th` | nvarchar(150) | UK, NOT NULL | — | ชื่อที่แสดงในฟอร์ม |
| `requires_detail` | bit | NOT NULL | `0` | ต้องกรอกรายละเอียดเพิ่มเติมหรือไม่ |
| `sort_order` | smallint | NOT NULL | `0` | ลำดับในตัวเลือก |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดใช้ตัวเลือก, `N` ปิดใช้ |

ค่าเริ่มต้น: `PHP_MYSQL`, `DNN`, `OTHER`

## `service_portal.app_role`

Master บทบาทผู้ใช้ในระบบ

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `role_id` | smallint identity | PK | auto | รหัสบทบาท |
| `role_code` | varchar(30) | UK, NOT NULL | — | `USER` หรือ `OFFICER` |
| `role_name_th` | nvarchar(100) | NOT NULL | — | ชื่อบทบาทภาษาไทย |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดใช้บทบาท, `N` ปิดใช้บทบาท |

ระบบไม่มี role `ADMIN` คำว่า “ผู้ดูแลเว็บไซต์” และ “ผู้ดูแลกรอบการประชุม” เป็นข้อมูลผู้รับบริการภายในคำร้อง ไม่ใช่สิทธิ์ผู้ดูแลระบบ

## `service_portal.user_role_assignment`

กำหนดบทบาทให้บุคลากร โดยอ้างบุคลากรจาก SWU MIS API

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `role_assignment_id` | bigint identity | PK | auto | รหัสรายการ |
| `pers_id` | int | LR, NOT NULL | — | `Officer.personId` |
| `role_id` | smallint | FK, NOT NULL | — | อ้าง `app_role.role_id` |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดใช้สิทธิ์, `N` ปิดใช้สิทธิ์ |
| `created_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้าง |
| `updated_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาแก้ไขล่าสุด |

กำหนด UK ร่วมที่ `(pers_id, role_id)` เพื่อป้องกันการกำหนดบทบาทซ้ำ

## `service_portal.faculty_dean`

Master ผู้รับรองประจำส่วนงาน ใช้สร้างตัวเลือกหัวหน้าส่วนงาน/คณบดี/ผู้อำนวยการ

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `faculty_dean_id` | bigint identity | PK | auto | รหัสรายการ |
| `target_dept_id` | int | LR, NOT NULL | — | `Department.deptCd` |
| `dean_pers_id` | int | LR, NOT NULL | — | `Officer.personId` ของผู้รับรอง |
| `position_name` | nvarchar(300) | NULL | `NULL` | ตำแหน่งบริหารจากทำเนียบคณบดีและผู้อำนวยการของมหาวิทยาลัย |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดใช้ผู้รับรอง, `N` ปิดใช้ |
| `created_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้าง |
| `updated_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาแก้ไขล่าสุด |

กำหนด UK ร่วมที่ `(target_dept_id, dean_pers_id)`

## `service_portal.form_notification_recipient`

กำหนดกลุ่มผู้รับ notification ตามแบบฟอร์มและเหตุการณ์ ระบบต้อง resolve เป็นอีเมลจริงก่อนสร้าง `notification_outbox`

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `recipient_rule_id` | bigint identity | PK | auto | รหัสกฎ |
| `form_type_code` | varchar(20) | FK, NOT NULL | — | อ้าง `form_type.form_type_code` |
| `notification_type_code` | varchar(60) | FK, NOT NULL | — | อ้าง `notification_type.notification_type_code` |
| `recipient_source` | varchar(20) | NOT NULL | — | `APP_ROLE`, `PERSON`, `STATIC_EMAIL` |
| `role_id` | smallint | FK, NULL | `NULL` | บังคับเมื่อ `recipient_source = APP_ROLE` |
| `recipient_pers_id` | int | LR, NULL | `NULL` | บังคับเมื่อ `recipient_source = PERSON` |
| `recipient_email` | varchar(254) | NULL | `NULL` | บังคับเมื่อ `recipient_source = STATIC_EMAIL` |
| `recipient_name` | nvarchar(300) | NULL | `NULL` | ชื่อแสดงสำหรับอีเมลคงที่ |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดใช้กฎ, `N` ปิดใช้กฎ |

ต้องมีข้อมูลปลายทางตาม `recipient_source` เพียงชนิดเดียว ปัจจุบันกฎแบบ `APP_ROLE` ใช้ resolve กลุ่มเจ้าหน้าที่ เช่น `OFFICER_REVIEW_REQUIRED`

## `service_portal.form_head_assignment`

กำหนดหัวหน้าผู้รับผิดชอบของแต่ละแบบฟอร์ม โดยอ้างบุคลากรจาก SWU MIS API แต่ละแบบฟอร์มมีหัวหน้าที่ `is_active = Y` ได้เพียงหนึ่งคน ระบบใช้รายการนี้เป็นผู้รับเมล `REQUEST_COMPLETED_FORM_HEAD`

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `head_assignment_id` | bigint identity | PK | auto | รหัสรายการ |
| `form_type_code` | varchar(20) | FK, NOT NULL | — | แบบฟอร์มที่รับผิดชอบ อ้าง `form_type.form_type_code` |
| `head_pers_id` | int | LR, NOT NULL | — | `Officer.personId` ของหัวหน้าผู้รับผิดชอบ |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` รับผิดชอบอยู่, `N` ยกเลิกการรับผิดชอบ |
| `created_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้าง |
| `updated_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาแก้ไขล่าสุด |

กำหนด UK ร่วมที่ `(form_type_code, head_pers_id)` และ filtered unique index ที่ `form_type_code` เมื่อ `is_active = Y` เพื่อให้แต่ละแบบฟอร์มมีหัวหน้าปัจจุบันเพียงหนึ่งคน

## `service_portal.form_officer_assignment`

กำหนดเจ้าหน้าที่ผู้รับผิดชอบของแต่ละแบบฟอร์ม หนึ่งแบบฟอร์มมีเจ้าหน้าที่ active ได้หลายคน และเจ้าหน้าที่หนึ่งคนรับผิดชอบได้หลายแบบฟอร์ม ระบบใช้รายการนี้เป็นผู้รับเมล `OFFICER_REVIEW_REQUIRED` และใช้ให้สิทธิ/redirect ไปหน้า officer ตอน login ระหว่างที่ฟอร์มยังไม่มี assignment ระบบจะ fallback ไปกลุ่ม `OFFICER` เดิมเพื่อไม่ให้ workflow หยุด

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `officer_assignment_id` | bigint identity | PK | auto | รหัสรายการ |
| `form_type_code` | varchar(20) | FK, NOT NULL | — | แบบฟอร์มที่รับผิดชอบ อ้าง `form_type.form_type_code` |
| `officer_pers_id` | int | LR, NOT NULL | — | `Officer.personId` ของเจ้าหน้าที่ผู้รับผิดชอบ |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` รับผิดชอบอยู่, `N` ยกเลิกการรับผิดชอบ |
| `created_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้าง |
| `updated_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาแก้ไขล่าสุด |

กำหนด UK ร่วมที่ `(form_type_code, officer_pers_id)` เพื่อป้องกันการกำหนดเจ้าหน้าที่คนเดิมซ้ำในแบบฟอร์มเดียวกัน

## `service_portal.project_type`

Master ประเภทโครงการของ `CC-ITED-06`

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `project_type_code` | varchar(30) | PK | — | รหัสประเภทโครงการ |
| `project_type_name_th` | nvarchar(200) | UK, NOT NULL | — | ชื่อประเภทโครงการ |
| `requires_detail` | bit | NOT NULL | `0` | ต้องกรอกรายละเอียดเพิ่มเติมหรือไม่ |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดใช้ตัวเลือก, `N` ปิดใช้ |

## `service_portal.course_category`

Master หมวดหมู่รายวิชาของ `CC-ITED-06`

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `category_code` | varchar(30) | PK | — | รหัสหมวดหมู่ |
| `category_name_th` | nvarchar(200) | UK, NOT NULL | — | ชื่อหมวดหมู่ |
| `is_active` | char(1) | NOT NULL | `Y` | `Y` เปิดใช้ตัวเลือก, `N` ปิดใช้ |

---

# Transaction กลาง

## `service_portal.service_request`

Header กลางของทุกคำร้อง หน้า Home ต้องอ่านข้อมูลหลักจากตารางนี้และ join `form_type` โดยไม่ union ตารางรายละเอียดแต่ละแบบฟอร์ม

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `request_id` | bigint identity | PK | auto | รหัสคำร้องภายในระบบ |
| `request_no` | varchar(40) | UK, NOT NULL | — | เลขคำร้องที่ไม่ซ้ำทั้งระบบ |
| `form_type_code` | varchar(20) | FK, NOT NULL | — | ประเภทแบบฟอร์ม |
| `requester_pers_id` | int | LR, NOT NULL | — | ผู้ยื่นจาก `Officer.personId` |
| `target_dept_id` | int | LR, NOT NULL | — | ส่วนงานที่เป็นเจ้าของคำร้องจาก `Department.deptCd` |
| `dean_pers_id` | int | LR, NOT NULL | — | ผู้รับรองที่เลือกจาก `faculty_dean` |
| `dean_position_name` | nvarchar(300) | NULL | `NULL` | Snapshot ตำแหน่งบริหารของผู้รับรอง ณ เวลายื่นคำร้อง |
| `request_title` | nvarchar(500) | NOT NULL | — | ข้อความสรุปที่แสดงในหน้า Home เช่นชื่อเว็บไซต์หรือชื่อโครงการ |
| `status_code` | varchar(40) | FK, NOT NULL | `DRAFT` | สถานะปัจจุบัน อ้าง `request_status` |
| `submitted_at` | datetime2 | NULL | `NULL` | เวลายื่นคำร้องครั้งแรก |
| `completed_at` | datetime2 | NULL | `NULL` | เวลาที่เจ้าหน้าที่อนุมัติและดำเนินการเสร็จ |
| `completed_by_pers_id` | int | LR, NULL | `NULL` | เจ้าหน้าที่ผู้ทำให้คำร้องเป็น `COMPLETED` |
| `created_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้าง |
| `updated_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาแก้ไขล่าสุด |

กฎสำคัญ:

- `completed_at` และ `completed_by_pers_id` ต้องมีเมื่อ `status_code = COMPLETED`
- `request_title` ต้องสร้างตามประเภทฟอร์มและเก็บเป็น snapshot เพื่อให้หน้า Home ค้นหาได้รวดเร็ว
- ไม่เก็บภาคเรียนเป็นข้อมูลกลาง เพราะใช้กับ Moodle เท่านั้น ตัวกรองกลางควรใช้ปีหรือช่วงวันที่ยื่น

## `service_portal.request_contact`

บุคคลที่ระบุในเนื้อหาคำร้อง เก็บ snapshot ชื่อและข้อมูลติดต่อเพื่อรักษาข้อมูลตามวันที่ยื่น แม้ข้อมูลใน SWU MIS เปลี่ยนภายหลัง

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `contact_id` | bigint identity | PK | auto | รหัสผู้เกี่ยวข้อง |
| `request_id` | bigint | FK, NOT NULL | — | อ้าง `service_request.request_id` |
| `contact_role_code` | varchar(50) | FK, NOT NULL | — | อ้าง `contact_role.contact_role_code` |
| `pers_id` | int | LR, NULL | `NULL` | `Officer.personId` เมื่อเป็นบุคลากรภายใน |
| `full_name` | nvarchar(300) | NOT NULL | — | ชื่อ-นามสกุล snapshot |
| `position_name` | nvarchar(200) | NULL | `NULL` | ตำแหน่ง snapshot |
| `email` | varchar(254) | Conditional | `NULL` | อีเมลสำหรับติดต่อและแจ้งผล; บังคับสำหรับผู้ดูแลหรือผู้ประสานงานคนใหม่ แต่ผู้ดูแลเดิมมีเพียงชื่อได้ |
| `phone` | varchar(50) | NULL | `NULL` | โทรศัพท์หรือเบอร์ภายใน |
| `is_primary` | bit | NOT NULL | `1` | ผู้ติดต่อหลักของบทบาทนั้น |
| `created_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้าง |

กำหนด index ที่ `(request_id, contact_role_code)` ห้ามมี `is_primary = 1` ซ้ำในบทบาทเดียวกันต่อคำร้อง

อีเมลต้องมีสำหรับ `WEBSITE_CONTACT`, `NEW_WEBSITE_CONTACT`, `EMEETING_CONTACT` และ `COORDINATOR` ส่วน `CURRENT_WEBSITE_CONTACT` และ `CURRENT_EMEETING_CONTACT` อนุญาตให้ว่างได้ตามแบบฟอร์มต้นฉบับ

## `service_portal.request_document`

Metadata ของไฟล์คำร้อง ไฟล์จริงอยู่ใน private storage

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `document_id` | bigint identity | PK | auto | รหัสเอกสาร |
| `request_id` | bigint | FK, NOT NULL | — | อ้าง `service_request.request_id` |
| `document_type_code` | varchar(50) | FK, NOT NULL | — | อ้าง `document_type` |
| `storage_key` | varchar(1000) | UK, NOT NULL | — | path หรือ object key |
| `original_filename` | nvarchar(500) | NOT NULL | — | ชื่อไฟล์ต้นฉบับ |
| `mime_type` | varchar(150) | NOT NULL | — | MIME type ที่ตรวจจากไฟล์จริง |
| `file_size_bytes` | bigint | NOT NULL | — | ขนาดไฟล์ ต้องมากกว่า 0 |
| `version_no` | integer | NOT NULL | `1` | ลำดับเวอร์ชันในประเภทเอกสารเดียวกัน |
| `uploaded_by_pers_id` | int | LR, NOT NULL | — | ผู้สร้างหรืออัปโหลดไฟล์ |
| `uploaded_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาอัปโหลด |

กำหนด UK ร่วมที่ `(request_id, document_type_code, version_no)`

## `service_portal.dean_review`

ประวัติผลรับรองของหัวหน้าส่วนงาน/คณบดี/ผู้อำนวยการ เก็บหลายรอบได้เมื่อมีการแก้ไขและส่งใหม่

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `dean_review_id` | bigint identity | PK | auto | รหัสผลรับรอง |
| `request_id` | bigint | FK, NOT NULL | — | อ้าง `service_request.request_id` |
| `dean_pers_id` | int | LR, NOT NULL | — | ผู้รับรอง |
| `decision` | varchar(20) | NOT NULL | — | `APPROVED` หรือ `REJECTED` |
| `comment` | nvarchar(max) | Conditional | `NULL` | บังคับเมื่อ `decision = REJECTED` |
| `decided_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาบันทึกผล |

## `service_portal.officer_review`

ผลตรวจและผลดำเนินการของเจ้าหน้าที่ การอนุมัติและบันทึกผลเฉพาะฟอร์มต้องเกิดใน transaction เดียวกัน

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `officer_review_id` | bigint identity | PK | auto | รหัสผลตรวจ |
| `request_id` | bigint | FK, NOT NULL | — | อ้าง `service_request.request_id` |
| `officer_pers_id` | int | LR, NOT NULL | — | เจ้าหน้าที่ผู้ดำเนินการ |
| `decision` | varchar(20) | NOT NULL | — | `APPROVED` หรือ `REJECTED` |
| `rejection_reason` | nvarchar(max) | Conditional | `NULL` | บังคับเมื่อ `decision = REJECTED` |
| `reviewed_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาตรวจและดำเนินการ |

เมื่อ `decision = APPROVED` ต้องบันทึกผลลัพธ์เฉพาะแบบฟอร์ม เปลี่ยน `service_request.status_code` เป็น `COMPLETED` เพิ่มประวัติ และสร้าง notification ถึงผู้ยื่นภายใน transaction เดียวกัน

## `service_portal.request_status_history`

Audit trail แบบ append-only ของการเปลี่ยนสถานะ

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `history_id` | bigint identity | PK | auto | รหัสประวัติ |
| `request_id` | bigint | FK, NOT NULL | — | อ้าง `service_request.request_id` |
| `from_status_code` | varchar(40) | FK, NULL | `NULL` | สถานะก่อนเหตุการณ์; ว่างเมื่อสร้างคำร้อง |
| `to_status_code` | varchar(40) | FK, NOT NULL | — | สถานะหลังเหตุการณ์ |
| `changed_by_pers_id` | int | LR, NULL | `NULL` | ผู้ทำรายการ; ว่างเมื่อระบบทำเอง |
| `change_source` | varchar(30) | NOT NULL | — | `REQUESTER`, `DEAN`, `OFFICER`, `SYSTEM` |
| `comment` | nvarchar(max) | NULL | `NULL` | หมายเหตุของเหตุการณ์ |
| `changed_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาเปลี่ยนสถานะ |

## `service_portal.notification_outbox`

คิวส่งอีเมล ตารางนี้เก็บผู้รับที่ resolve แล้ว ไม่ใช่กฎผู้รับ

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `notification_id` | bigint identity | PK | auto | รหัสคิว |
| `request_id` | bigint | FK, NOT NULL | — | อ้าง `service_request.request_id` |
| `notification_type_code` | varchar(60) | FK, NOT NULL | — | อ้าง `notification_type` |
| `recipient_pers_id` | int | LR, NULL | `NULL` | ผู้รับจาก SWU MIS ถ้ามี |
| `recipient_email` | varchar(254) | NOT NULL | — | อีเมลปลายทางที่ resolve แล้ว |
| `recipient_name` | nvarchar(300) | NULL | `NULL` | ชื่อผู้รับ snapshot |
| `reply_to_email` | varchar(254) | NULL | `NULL` | Reply-To |
| `reply_to_name` | nvarchar(300) | NULL | `NULL` | ชื่อ Reply-To |
| `delivery_status` | varchar(20) | NOT NULL | `PENDING` | `PENDING`, `SENDING`, `SENT`, `FAILED` |
| `attempt_count` | integer | NOT NULL | `0` | จำนวนครั้งที่พยายามส่ง ต้องไม่ติดลบ |
| `next_attempt_at` | datetime2 | NULL | `NULL` | เวลาที่อนุญาตให้ retry รอบถัดไป |
| `claimed_at` | datetime2 | NULL | `NULL` | เวลาที่ worker claim งาน |
| `sent_at` | datetime2 | NULL | `NULL` | เวลาส่งสำเร็จ |
| `last_error` | nvarchar(max) | NULL | `NULL` | ข้อผิดพลาดล่าสุด |
| `created_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้างคิว |

เมื่อคำร้อง `COMPLETED` ต้องมีอย่างน้อยหนึ่งแถวชนิด `REQUEST_COMPLETED_REQUESTER` เพื่อแจ้งผลให้ผู้ยื่นคำร้อง

---

# รายละเอียดเฉพาะแบบฟอร์ม

## `service_portal.cc_ited_01_request_detail`

รายละเอียดแบบฟอร์ม `CC-ITED-01` หนึ่งคำร้องมีได้หนึ่งแถว และต้องมี contact role `WEBSITE_CONTACT`

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `request_id` | bigint | PK, FK | — | อ้าง `service_request.request_id` |
| `website_name` | nvarchar(500) | NOT NULL | — | ชื่อเว็บไซต์ |
| `domain_url` | varchar(1000) | NOT NULL | — | URL ที่ขอ |
| `site_scope_type` | varchar(30) | Conditional | `NULL` | `DEPARTMENT`, `PROJECT`, `CONFERENCE` |
| `service_starts_on` | date | Conditional | `NULL` | วันเริ่มต้น |
| `service_ends_on` | date | Conditional | `NULL` | วันสิ้นสุด |
| `platform_code` | varchar(30) | FK, Conditional | `NULL` | อ้าง `website_platform` |
| `additional_detail` | nvarchar(max) | NULL | `NULL` | รายละเอียดเพิ่มเติม |
| `allocated_domain` | nvarchar(max) | Conditional | `NULL` | โดเมนและข้อมูลการจัดสรรพื้นที่เว็บไซต์ที่เจ้าหน้าที่บันทึก |
| `created_at`, `updated_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้างและแก้ไข |

## `service_portal.cc_ited_02_request_detail`

รายละเอียดแบบฟอร์ม `CC-ITED-02` และต้องมี contact role `CURRENT_WEBSITE_CONTACT` กับ `NEW_WEBSITE_CONTACT`

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `request_id` | bigint | PK, FK | — | อ้าง `service_request.request_id` |
| `website_name` | nvarchar(500) | NOT NULL | — | ชื่อเว็บไซต์ |
| `domain_url` | varchar(1000) | NULL | `NULL` | URL ปัจจุบัน |
| `execution_detail` | nvarchar(max) | NULL | `NULL` | ข้อมูลการดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์ที่เจ้าหน้าที่บันทึกก่อนอนุมัติ |
| `additional_detail` | nvarchar(max) | NULL | `NULL` | รายละเอียดเพิ่มเติม |
| `created_at`, `updated_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้างและแก้ไข |

## `service_portal.cc_ited_04_request_detail`

รายละเอียดแบบฟอร์ม `CC-ITED-04` และต้องมี contact role `WEBSITE_CONTACT`

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `request_id` | bigint | PK, FK | — | อ้าง `service_request.request_id` |
| `website_name` | nvarchar(500) | NOT NULL | — | ชื่อเว็บไซต์ |
| `domain_url` | varchar(1000) | NULL | `NULL` | URL ปัจจุบัน |
| `requested_space_gb` | decimal(8,2) | Conditional | `NULL` | พื้นที่ที่ขอเพิ่ม |
| `granted_space_gb` | decimal(8,2) | Conditional | `NULL` | พื้นที่ที่เจ้าหน้าที่เพิ่มจริง |
| `execution_detail` | nvarchar(max) | NULL | `NULL` | ข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์ที่เจ้าหน้าที่บันทึกก่อนอนุมัติ |
| `additional_detail` | nvarchar(max) | NULL | `NULL` | รายละเอียดเพิ่มเติม |
| `created_at`, `updated_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้างและแก้ไข |

## `service_portal.cc_ited_05_request_detail`

รายละเอียดของ `CC-ITED-05`

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `request_id` | bigint | PK, FK | — | อ้าง `service_request.request_id` |
| `action_type` | varchar(20) | NOT NULL | — | `NEW_ACCESS` หรือ `CHANGE_CONTACT` |
| `execution_detail` | nvarchar(max) | NULL | `NULL` | ข้อมูลการดำเนินการกำหนดหรือเปลี่ยนสิทธิ์ผู้ดูแลกรอบการประชุมที่เจ้าหน้าที่บันทึกก่อนอนุมัติ |
| `created_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้าง |
| `updated_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาแก้ไขล่าสุด |

กฎ contact:

- ต้องมี `EMEETING_CONTACT` เป็นผู้ที่จะได้รับสิทธิ์
- เมื่อ `action_type = CHANGE_CONTACT` ต้องมี `CURRENT_EMEETING_CONTACT`
- แอปพลิเคชันต้องตรวจสอบว่าหนึ่งส่วนงานมีผู้ดูแลกรอบการประชุม active ได้เพียงหนึ่งคนหลังดำเนินการ

## `service_portal.cc_ited_06_request_detail`

รายละเอียดของ `CC-ITED-06` แยกจาก header กลาง และเก็บ Course ID พร้อมรายละเอียดการดำเนินการที่เจ้าหน้าที่สามารถระบุเพื่อแจ้งผู้ยื่นคำร้อง

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `request_id` | bigint | PK, FK | — | อ้าง `service_request.request_id` |
| `project_name` | nvarchar(500) | NOT NULL | — | ชื่อโครงการ |
| `project_type_code` | varchar(30) | FK, NOT NULL | — | อ้าง `project_type` |
| `project_other` | nvarchar(500) | Conditional | `NULL` | บังคับเมื่อเลือกประเภทอื่น |
| `course_name_th` | nvarchar(500) | NOT NULL | — | ชื่อรายวิชาภาษาไทย |
| `course_name_en` | nvarchar(500) | NOT NULL | — | ชื่อรายวิชาภาษาอังกฤษ |
| `category_code` | varchar(30) | FK, NOT NULL | — | อ้าง `course_category` |
| `category_other` | nvarchar(500) | Conditional | `NULL` | บังคับเมื่อเลือกหมวดอื่น |
| `course_description` | nvarchar(max) | NOT NULL | — | คำอธิบายและวัตถุประสงค์ |
| `learning_mode` | varchar(100) | NOT NULL | — | รูปแบบการดำเนินกิจกรรม |
| `activity_round` | nvarchar(500) | Conditional | `NULL` | วงรอบ/รุ่น |
| `activity_phase` | nvarchar(250) | Conditional | `NULL` | เฟส |
| `starts_on` | date | NOT NULL | — | วันเริ่มกิจกรรม |
| `ends_on` | date | NOT NULL | — | วันสิ้นสุด ต้องไม่ก่อน `starts_on` |
| `enrollment_method` | varchar(100) | NOT NULL | — | วิธีเข้ารายวิชา |
| `enrollment_other` | nvarchar(500) | Conditional | `NULL` | บังคับเมื่อเลือกอื่น ๆ |
| `expected_students` | integer | NOT NULL | — | จำนวนผู้เรียนที่คาดการณ์ ต้องมากกว่า 0 |
| `course_id` | varchar(100) | Conditional | `NULL` | บังคับเมื่อเจ้าหน้าที่อนุมัติ |
| `execution_detail` | nvarchar(max) | NULL | `NULL` | รายละเอียดการดำเนินการที่แจ้งกลับผู้ยื่นคำร้อง (ถ้ามี) |
| `created_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาสร้าง |
| `updated_at` | datetime2 | NOT NULL | `SYSDATETIME()` | เวลาแก้ไขล่าสุด |

ข้อมูลผู้ประสานงานเก็บใน `request_contact` ด้วย role `COORDINATOR` เจ้าหน้าที่ต้องบันทึก `course_id` และอาจระบุ `execution_detail`, สร้าง `officer_review` แบบ `APPROVED` และเปลี่ยนสถานะเป็น `COMPLETED` ใน transaction เดียวกัน

## `service_portal.course_instructor`

อาจารย์ผู้สอนของ `CC-ITED-06` หนึ่งคำร้องมีอย่างน้อยหนึ่งคน

| Column | Type | Key / Null | Default | ความหมาย |
|---|---|---|---|---|
| `instructor_id` | bigint identity | PK | auto | รหัสรายการ |
| `request_id` | bigint | FK, NOT NULL | — | อ้าง `cc_ited_06_request_detail.request_id` |
| `pers_id` | int | LR, NULL | `NULL` | `Officer.personId` เมื่อเป็นบุคลากรภายใน |
| `instructor_name` | nvarchar(300) | NOT NULL | — | ชื่อ-นามสกุล snapshot |
| `instructor_email` | varchar(254) | NOT NULL | — | อีเมลผู้สอน |
| `sort_order` | smallint | NOT NULL | `0` | ลำดับแสดงผล |

---

# Mapping แบบฟอร์มกับข้อมูลบังคับ

| Form | Detail table | Contact บังคับ | ผลที่เจ้าหน้าที่ต้องบันทึกก่อนอนุมัติ |
|---|---|---|---|
| `CC-ITED-01` | `cc_ited_01_request_detail` | `WEBSITE_CONTACT` | `allocated_domain` |
| `CC-ITED-02` | `cc_ited_02_request_detail` | `CURRENT_WEBSITE_CONTACT`, `NEW_WEBSITE_CONTACT` | `execution_detail` |
| `CC-ITED-04` | `cc_ited_04_request_detail` | `WEBSITE_CONTACT` | `execution_detail`; `granted_space_gb` ถูกกำหนดคงที่ 1 GB ต่อคำร้อง |
| `CC-ITED-05` | `cc_ited_05_request_detail` | `EMEETING_CONTACT`; และผู้ดูแลเดิมเมื่อเปลี่ยนสิทธิ์ | `execution_detail` |
| `CC-ITED-06` | `cc_ited_06_request_detail` | `COORDINATOR` และ `course_instructor` อย่างน้อย 1 คน | `course_id`; `execution_detail` ระบุได้ถ้ามี |

# สถานะและ Transition

| From | Action | To | ผู้ดำเนินการ |
|---|---|---|---|
| `DRAFT` | ส่งคำร้อง | `PENDING_DEAN_REVIEW` | Requester |
| `PENDING_DEAN_REVIEW` | รับรอง | `UNDER_OFFICER_REVIEW` | Dean |
| `PENDING_DEAN_REVIEW` | ไม่รับรอง | `REJECTED` | Dean |
| `UNDER_OFFICER_REVIEW` | ไม่อนุมัติ | `REJECTED` | Officer |
| `UNDER_OFFICER_REVIEW` | อนุมัติและบันทึกผลดำเนินการ | `COMPLETED` | Officer |

ระบบต้องไม่เปลี่ยนเป็น `COMPLETED` หากข้อมูลผลลัพธ์บังคับของแบบฟอร์มนั้นยังไม่ครบ

# Notification เมื่อเสร็จสมบูรณ์

เมื่อ Officer อนุมัติ ระบบต้องทำงานใน transaction เดียวกันดังนี้

1. บันทึกผลลัพธ์เฉพาะแบบฟอร์ม เช่น Course ID หรือโดเมนที่จัดสรร
2. เพิ่ม `officer_review` ด้วย `decision = APPROVED`
3. เปลี่ยน `service_request.status_code` เป็น `COMPLETED`
4. กำหนด `completed_at` และ `completed_by_pers_id`
5. เพิ่ม `request_status_history`
6. สร้าง `notification_outbox` ชนิด `REQUEST_COMPLETED_REQUESTER`
7. Resolve หัวหน้าจาก `form_head_assignment` ตาม `form_type_code` แล้วสร้าง `REQUEST_COMPLETED_FORM_HEAD` เมื่อมีหัวหน้า active; หากยังไม่กำหนดหัวหน้า คำร้องยังเสร็จสมบูรณ์ได้โดยไม่สร้างเมลชนิดนี้
การส่งอีเมลล้มเหลวไม่ย้อนสถานะ `COMPLETED`; worker ต้อง retry จาก outbox

# Index และ Constraint ที่แนะนำ

| Table | Index / Constraint | วัตถุประสงค์ |
|---|---|---|
| `service_request` | UK `request_no` | ป้องกันเลขคำร้องซ้ำ |
| `service_request` | IX `(requester_pers_id, created_at DESC)` | หน้า Home ของผู้ยื่น |
| `service_request` | IX `(status_code, created_at)` | คิวงานเจ้าหน้าที่และตัวกรองสถานะ |
| `service_request` | IX `(form_type_code, submitted_at DESC)` | ตัวกรองประเภทคำร้อง |
| `request_contact` | IX `(request_id, contact_role_code)` | อ่านผู้เกี่ยวข้องของคำร้อง |
| `request_document` | UK `(request_id, document_type_code, version_no)` | ควบคุมเวอร์ชันไฟล์ |
| `dean_review` | IX `(request_id, decided_at DESC)` | ผลรับรองล่าสุด |
| `officer_review` | IX `(request_id, reviewed_at DESC)` | ผลตรวจล่าสุด |
| `request_status_history` | IX `(request_id, changed_at, history_id)` | แสดง timeline ตามลำดับ |
| `notification_outbox` | IX `(delivery_status, next_attempt_at, notification_id)` | worker claim งาน |
| `form_notification_recipient` | IX `(form_type_code, notification_type_code, is_active)` | resolve ผู้รับแจ้งเตือน |
| `form_head_assignment` | Filtered UK `(form_type_code)` เมื่อ `is_active = Y` | บังคับให้แต่ละแบบฟอร์มมีหัวหน้าปัจจุบันได้เพียงหนึ่งคน |
| `form_officer_assignment` | UK `(form_type_code, officer_pers_id)` และ IX `(form_type_code, is_active, officer_pers_id)` | ป้องกันรายการซ้ำและค้นหาเจ้าหน้าที่ active ของแต่ละแบบฟอร์ม |

# กฎข้อมูลสำคัญ

- คำร้องหนึ่งรายการต้องมี detail row ที่ตรงกับ `form_type_code` เพียงกลุ่มเดียว
- ข้อมูลชื่อ อีเมล โทรศัพท์ และตำแหน่งในคำร้องเป็น snapshot ไม่ควรเปลี่ยนตาม SWU MIS ย้อนหลัง
- ไฟล์จริงไม่เก็บในฐานข้อมูลและต้องอยู่ใน private storage
- ทุกการเปลี่ยนสถานะต้องเพิ่ม `request_status_history`; ห้ามแก้ประวัติเก่า
- ไม่มี role หรือตารางผลตัดสินของ Approver ใน workflow ปัจจุบัน
- Officer ต้องกรอกผลลัพธ์บังคับและกดอนุมัติใน action เดียวกัน
- สำหรับ `CC-ITED-06` ต้องมี `course_id` ก่อนเปลี่ยนเป็น `COMPLETED`; `execution_detail` เป็นข้อมูลเสริมและเป็น `NULL` ได้
- สำหรับ `CC-ITED-01` ต้องมี `allocated_domain` ก่อนเปลี่ยนเป็น `COMPLETED`
- สำหรับ `CC-ITED-04` ต้องมี `execution_detail` ก่อนเปลี่ยนเป็น `COMPLETED` และระบบกำหนด `granted_space_gb` เป็น 1 GB เสมอ
- สำหรับ `CC-ITED-05` ต้องมี `execution_detail` ก่อนเปลี่ยนเป็น `COMPLETED`
- การเปลี่ยนสถานะ บันทึกผลตรวจ บันทึกผลลัพธ์ ประวัติ และ outbox ต้องอยู่ใน database transaction เดียวกัน
- หน้า Home ใช้ `service_request.request_title` เป็นข้อความกลาง และใช้ `form_type_code`, `status_code`, `submitted_at` เป็นตัวกรองหลัก

# ผลการ Cutover

- ระบบอ่านและเขียนคำร้องผ่าน schema `service_portal` เท่านั้น
- คัดลอกเฉพาะ role assignment และรายการคณบดี/ผู้รับรองที่จำเป็นต่อการใช้งานจากชุดเดิม
- ไม่ย้ายคำร้องเดิม 31 รายการ เนื่องจากเป็นข้อมูลทดสอบตามการยืนยันของเจ้าของระบบ
- ลบตารางและ view เดิมทั้งหมด รวมถึง schema `course133`; ชื่อ connection `course133` ยังคงใช้เป็นชื่อการเชื่อมต่อฐาน `academy_db1447`

---

# ตัวอย่างข้อมูลสมมติ

ข้อมูลส่วนนี้ใช้สำหรับอธิบายความสัมพันธ์เท่านั้น รหัสบุคลากร ชื่อ อีเมล เลขคำร้อง Course ID และข้อมูลส่วนงานทั้งหมดเป็นข้อมูลสมมติ ห้ามนำไปส่งอีเมลหรือใช้เป็นข้อมูล production

## ตัวอย่าง Master `form_type`

| form_type_code | short_name_th | route_name | current_version | sort_order | is_active |
|---|---|---|---:|---:|---|
| `CC-ITED-01` | ขอใช้พื้นที่จัดทำเว็บไซต์ | `website-space.create` | 1 | 10 | `Y` |
| `CC-ITED-02` | ขอเปลี่ยนผู้ดูแลเว็บไซต์ | `website-contact-change.create` | 1 | 20 | `Y` |
| `CC-ITED-04` | ขอเพิ่มพื้นที่เว็บไซต์ | `website-storage.create` | 1 | 30 | `Y` |
| `CC-ITED-05` | ขอสิทธิ์ผู้ดูแล e-Meeting | `emeeting-contact.create` | 1 | 40 | `Y` |
| `CC-ITED-06` | ขอสร้างรายวิชา Moodle Academy | `moodle-course.create` | 1 | 50 | `Y` |

## ตัวอย่าง Master `request_status`

| status_code | status_name_th | badge_style | is_terminal | sort_order |
|---|---|---|---:|---:|
| `DRAFT` | แบบร่าง | `NEUTRAL` | 0 | 10 |
| `PENDING_DEAN_REVIEW` | รอผู้รับรอง | `WARNING` | 0 | 20 |
| `UNDER_OFFICER_REVIEW` | รอเจ้าหน้าที่ดำเนินการ | `INFO` | 0 | 40 |
| `REJECTED` | ไม่อนุมัติ | `DANGER` | 1 | 60 |
| `COMPLETED` | ดำเนินการเสร็จสิ้น | `SUCCESS` | 1 | 70 |

## ตัวอย่างรายการ `service_request` สำหรับหน้า Home

| request_id | request_no | form_type_code | request_title | target_dept_id | status_code | submitted_at | completed_at |
|---:|---|---|---|---:|---|---|---|
| 1001 | `SR-2026-000001` | `CC-ITED-01` | เว็บไซต์โครงการอบรมทักษะดิจิทัล | 2001 | `COMPLETED` | 2026-10-01 09:10 | 2026-10-03 14:25 |
| 1002 | `SR-2026-000002` | `CC-ITED-02` | เปลี่ยนผู้ดูแลเว็บไซต์คณะตัวอย่าง | 2002 | `UNDER_OFFICER_REVIEW` | 2026-10-05 10:30 | `NULL` |
| 1003 | `SR-2026-000003` | `CC-ITED-04` | เพิ่มพื้นที่เว็บไซต์หน่วยงานตัวอย่าง | 2003 | `PENDING_DEAN_REVIEW` | 2026-10-08 13:45 | `NULL` |
| 1004 | `SR-2026-000004` | `CC-ITED-05` | เปลี่ยนผู้ดูแลกรอบการประชุม e-Meeting | 2004 | `REJECTED` | 2026-10-10 11:05 | `NULL` |
| 1005 | `SR-2026-000005` | `CC-ITED-06` | รายวิชาการรู้เท่าทันสื่อดิจิทัล | 2005 | `COMPLETED` | 2026-10-15 08:50 | 2026-10-17 15:40 |

หน้า Home แสดงข้อมูลหลักจากแถวเหล่านี้ แล้ว join `form_type` เพื่อแสดงชื่อแบบฟอร์มและ join `request_status` เพื่อแสดง badge สถานะ

## ตัวอย่างรายละเอียด `CC-ITED-01`

### `cc_ited_01_request_detail`

| request_id | website_name | domain_url | site_scope_type | platform_code | requested_space_gb | allocated_domain |
|---:|---|---|---|---|---:|---|
| 1001 | เว็บไซต์โครงการอบรมทักษะดิจิทัล | `https://digital-training.example.invalid` | `PROJECT` | `PHP_MYSQL` | `NULL` | `digital-training.example.invalid` |

### `request_contact`

| contact_id | request_id | contact_role_code | pers_id | full_name | email | phone | is_primary |
|---:|---:|---|---:|---|---|---|---:|
| 5001 | 1001 | `WEBSITE_CONTACT` | 990011 | ผู้ดูแล เว็บไซต์ตัวอย่าง | `website.contact@example.invalid` | `02-000-0001` | 1 |

## ตัวอย่างรายละเอียด `CC-ITED-02`

### `cc_ited_02_request_detail`

| request_id | website_name | domain_url |
|---:|---|---|
| 1002 | เว็บไซต์คณะตัวอย่าง | `https://faculty.example.invalid` |

### `request_contact`

| contact_id | request_id | contact_role_code | pers_id | full_name | email | phone | is_primary |
|---:|---:|---|---:|---|---|---|---:|
| 5002 | 1002 | `CURRENT_WEBSITE_CONTACT` | `NULL` | ผู้ดูแล เดิมตัวอย่าง | `NULL` | `NULL` | 1 |
| 5003 | 1002 | `NEW_WEBSITE_CONTACT` | 990012 | ผู้ดูแล ใหม่ตัวอย่าง | `new.contact@example.invalid` | `02-000-0002` | 1 |

## ตัวอย่างรายละเอียด `CC-ITED-04`

### `cc_ited_04_request_detail`

| request_id | website_name | domain_url | requested_space_gb | granted_space_gb | execution_detail |
|---:|---|---|---:|---:|---|
| 1003 | เว็บไซต์หน่วยงานตัวอย่าง | `https://department.example.invalid` | 1.00 | `NULL` | `NULL` |

`granted_space_gb` และ `execution_detail` ยังเป็น `NULL` เพราะคำร้องยังรอผู้รับรอง เมื่อเจ้าหน้าที่อนุมัติ ระบบจะบันทึกพื้นที่เป็น `1.00` และบังคับให้ระบุข้อมูลการดำเนินการ

## ตัวอย่างรายละเอียด `CC-ITED-05`

### `cc_ited_05_request_detail`

| request_id | action_type | execution_detail |
|---:|---|---|
| 1004 | `CHANGE_CONTACT` | `NULL` |

`execution_detail` ยังเป็น `NULL` ระหว่างรอเจ้าหน้าที่ เมื่อเจ้าหน้าที่อนุมัติ ระบบจะบังคับให้ระบุและบันทึกข้อมูลการดำเนินการ

### `request_contact`

| contact_id | request_id | contact_role_code | pers_id | full_name | email | phone | is_primary |
|---:|---:|---|---:|---|---|---|---:|
| 5004 | 1004 | `CURRENT_EMEETING_CONTACT` | `NULL` | ผู้ดูแลกรอบ เดิมตัวอย่าง | `NULL` | `NULL` | 1 |
| 5005 | 1004 | `EMEETING_CONTACT` | 990013 | ผู้ดูแลกรอบ ใหม่ตัวอย่าง | `meeting.contact@example.invalid` | `02-000-0003` | 1 |

## ตัวอย่างคำร้อง `CC-ITED-06` แบบครบความสัมพันธ์

ตัวอย่างนี้แสดง flow ที่เจ้าหน้าที่ตรวจ อนุมัติ และกรอก Course ID ใน action เดียวกัน

### `service_request`

| request_id | request_no | form_type_code | requester_pers_id | target_dept_id | dean_pers_id | request_title | status_code | submitted_at | completed_at | completed_by_pers_id |
|---:|---|---|---:|---:|---:|---|---|---|---|---:|
| 1005 | `SR-2026-000005` | `CC-ITED-06` | 990001 | 2005 | 990101 | รายวิชาการรู้เท่าทันสื่อดิจิทัล | `COMPLETED` | 2026-10-15 08:50 | 2026-10-17 15:40 | 990201 |

### `request_contact`

| contact_id | request_id | contact_role_code | pers_id | full_name | position_name | email | phone | is_primary |
|---:|---:|---|---:|---|---|---|---|---:|
| 5006 | 1005 | `COORDINATOR` | 990001 | ผู้ประสานงาน ตัวอย่าง | นักวิชาการศึกษา | `requester@example.invalid` | `02-000-0004` | 1 |

### `cc_ited_06_request_detail`

| request_id | project_name | project_type_code | course_name_th | course_name_en | category_code | learning_mode | starts_on | ends_on | enrollment_method | expected_students | course_id |
|---:|---|---|---|---|---|---|---|---|---|---:|---|
| 1005 | โครงการพัฒนาทักษะดิจิทัล | `ACADEMIC_SERVICE` | การรู้เท่าทันสื่อดิจิทัล | Digital Media Literacy | `GENERAL` | เปิดตามกรอบระยะเวลาโครงการ | 2026-11-01 | 2026-12-15 | ใช้รหัสผ่าน | 120 | `4832` |

### `course_instructor`

| instructor_id | request_id | pers_id | instructor_name | instructor_email | sort_order |
|---:|---:|---:|---|---|---:|
| 6001 | 1005 | 990301 | อาจารย์ หนึ่งตัวอย่าง | `instructor.one@example.invalid` | 1 |
| 6002 | 1005 | `NULL` | วิทยากร สองตัวอย่าง | `instructor.two@example.invalid` | 2 |

### `request_document`

| document_id | request_id | document_type_code | storage_key | original_filename | mime_type | version_no | uploaded_by_pers_id |
|---:|---:|---|---|---|---|---:|---:|
| 7001 | 1005 | `GENERATED_FORM` | `requests/1005/generated/v1.pdf` | `SR-2026-000005.pdf` | `application/pdf` | 1 | 990001 |
| 7002 | 1005 | `SIGNED_FORM` | `requests/1005/signed/v1.pdf` | `SR-2026-000005-signed.pdf` | `application/pdf` | 1 | 990001 |

### `dean_review`

| dean_review_id | request_id | dean_pers_id | decision | comment | decided_at |
|---:|---:|---:|---|---|---|
| 8001 | 1005 | 990101 | `APPROVED` | `NULL` | 2026-10-15 14:20 |

### `officer_review`

| officer_review_id | request_id | officer_pers_id | decision | rejection_reason | reviewed_at |
|---:|---:|---:|---|---|---|
| 9001 | 1005 | 990201 | `APPROVED` | `NULL` | 2026-10-17 15:40 |

แถว `officer_review` นี้และค่า `course_id = 4832` ต้องถูกบันทึกใน database transaction เดียวกันกับการเปลี่ยนสถานะเป็น `COMPLETED`

### `request_status_history`

| history_id | request_id | from_status_code | to_status_code | changed_by_pers_id | change_source | changed_at |
|---:|---:|---|---|---:|---|---|
| 10001 | 1005 | `NULL` | `DRAFT` | 990001 | `REQUESTER` | 2026-10-14 16:10 |
| 10002 | 1005 | `DRAFT` | `PENDING_DEAN_REVIEW` | 990001 | `REQUESTER` | 2026-10-15 08:50 |
| 10005 | 1005 | `UNDER_OFFICER_REVIEW` | `COMPLETED` | 990201 | `OFFICER` | 2026-10-17 15:40 |

### `form_notification_recipient`

| recipient_rule_id | form_type_code | notification_type_code | recipient_source | role_id | is_active |
|---:|---|---|---|---:|---|
| 11001 | `CC-ITED-06` | `OFFICER_REVIEW_REQUIRED` | `APP_ROLE` | 2 | `Y` |

สมมติว่า `role_id = 2` คือ role `OFFICER` ระบบจะค้นสมาชิก active ของ role นี้ก่อนสร้าง outbox เมื่อผู้รับรองรับรองคำร้อง

### `notification_outbox`

| notification_id | request_id | notification_type_code | recipient_pers_id | recipient_email | delivery_status | attempt_count | created_at |
|---:|---:|---|---:|---|---|---:|---|
| 12001 | 1005 | `REQUEST_COMPLETED_REQUESTER` | 990001 | `requester@example.invalid` | `PENDING` | 0 | 2026-10-17 15:40 |

แถวนี้หมายถึงคำร้องเสร็จสมบูรณ์แล้วและรอแจ้งผู้ยื่น ส่วนการส่งอีเมลเป็นหน้าที่ของ worker หากส่งไม่สำเร็จ สถานะคำร้องยังคงเป็น `COMPLETED` และ worker จะ retry จาก outbox
