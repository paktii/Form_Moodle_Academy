# รายงานตรวจ ERD เทียบฐานข้อมูลจริง

วันที่ตรวจ: 17 กันยายน 2026

## ผลสรุป

ERD ปัจจุบันเป็น As-built ของ schema `academy_db1447.course133` ที่ Laravel Migration สร้างแล้ว ร่วมกับ logical reference ไปยัง snapshot ของ `saladb.dbo` บน Server 199

## Server 199 — `saladb.dbo`

ตรวจจาก `persons.csv`, `departments.csv` และ metadata ที่ export จาก SSMS:

- `dbo.persons`: ตรงกับ snapshot 52 คอลัมน์
- `dbo.departments`: ตรงกับ snapshot 18 คอลัมน์
- `persons.person_id`: `int`
- `persons.dept_cd`, `persons.in_dept_cd`: `int`, nullable
- `departments.dept_cd`: `int`
- `departments.in_dept_cd`: `int`, nullable
- ERD แสดงเฉพาะคอลัมน์หลักเพื่อให้อ่านง่าย รายละเอียดครบอยู่ใน `sqlserver199-snapshot-data-dictionary.md`
- ยังต้องใช้ผลลัพธ์หมวด Foreign Keys จาก `inspect-sqlserver199-schema.sql` เพื่อยืนยันว่าเส้น `departments → persons` และ self-reference เป็น constraint จริงใน SQL Server ต้นทาง หรือเป็นเพียง logical relationship

## Server 133 — `academy_db1447`

- Laravel Migration สร้าง 11 ตารางใน schema `course133` สำเร็จแล้ว
- ตรวจจาก connection `course133` แล้วว่าชี้ไป `academy_db1447` และมี master role, project type, category และ role assignment ครบ
- ทดสอบ insert ผ่าน Eloquent ภายใน transaction สำเร็จ และ rollback โดยไม่ทิ้งข้อมูลทดสอบ

## Logical Reference ที่ใช้ใน Laravel Migration

Laravel Migration ฝั่ง 133 ใช้ชนิด `int` ให้ตรงกับคีย์ต้นทางจริง แต่ยังไม่สร้าง Foreign Key ข้าม Server:

| คอลัมน์ในฐาน 133 | ชนิด | อ้างถึง |
|---|---|---|
| `pers_id` | `int` | `saladb.dbo.persons.person_id` |
| `requester_pers_id` | `int` | `saladb.dbo.persons.person_id` |
| `officer_pers_id` | `int` | `saladb.dbo.persons.person_id` |
| `approver_pers_id` | `int` | `saladb.dbo.persons.person_id` |
| `uploaded_by_pers_id` | `int` | `saladb.dbo.persons.person_id` |
| `recorded_by_pers_id` | `int` | `saladb.dbo.persons.person_id` |
| `changed_by_pers_id` | `int` | `saladb.dbo.persons.person_id` |
| `recipient_pers_id` | `int` | `saladb.dbo.persons.person_id` |
| `target_dept_id` | `int` | `saladb.dbo.departments.dept_cd` |

ความสัมพันธ์เหล่านี้ยังคงเป็น Logical Reference เพราะ Server 133 และ 199 อยู่คนละ SQL Server จึงไม่มี Foreign Key ข้าม Server

`scope_dept_id` และ `requester_dept_id` ถูกนำออกจากแบบปัจจุบันแล้ว โดยสิทธิ์ Officer/Approver ใช้ได้ทุกหน่วยงาน และหน่วยงานของ requester อ่านจาก `saladb.dbo.persons` ตาม `requester_pers_id`
