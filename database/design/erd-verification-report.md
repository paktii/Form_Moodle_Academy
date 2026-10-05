# รายงานตรวจ ERD เทียบฐานข้อมูลจริง

วันที่ตรวจ: 17 กันยายน 2026

## ผลสรุป

ERD ปัจจุบันเป็น As-built ของ schema `academy_db1447.course133` ที่ Laravel Migration สร้างแล้ว ร่วมกับ logical reference ไปยังข้อมูลจาก SWU MIS API

## SWU MIS API

ตรวจ schema การตอบกลับกับเอกสารบริการข้อมูลและ API จริงแล้ว:

- ข้อมูลบุคลากรอ่านจาก endpoint `Officer` โดยใช้ `personId` เป็นรหัสอ้างอิง
- ข้อมูลหน่วยงานอ่านจาก endpoint `Department` โดยใช้ `deptCd` เป็นรหัสอ้างอิง
- การเชื่อมหน่วยงานของบุคลากรใช้ `facultyId` และ `departmentId`

## Server 133 — `academy_db1447`

- Laravel Migration สร้าง 11 ตารางใน schema `course133` สำเร็จแล้ว
- ตรวจจาก connection `course133` แล้วว่าชี้ไป `academy_db1447` และมี master role, project type, category และ role assignment ครบ
- ทดสอบ insert ผ่าน Eloquent ภายใน transaction สำเร็จ และ rollback โดยไม่ทิ้งข้อมูลทดสอบ

## Logical Reference ที่ใช้ใน Laravel Migration

Laravel Migration ฝั่ง 133 ใช้ชนิด `int` ให้ตรงกับคีย์จาก API แต่ไม่สร้าง Foreign Key ไปยังข้อมูลภายนอก:

| คอลัมน์ในฐาน 133 | ชนิด | อ้างถึง |
|---|---|---|
| `pers_id` | `int` | `Officer.personId` |
| `requester_pers_id` | `int` | `Officer.personId` |
| `officer_pers_id` | `int` | `Officer.personId` |
| `approver_pers_id` | `int` | `Officer.personId` |
| `uploaded_by_pers_id` | `int` | `Officer.personId` |
| `recorded_by_pers_id` | `int` | `Officer.personId` |
| `changed_by_pers_id` | `int` | `Officer.personId` |
| `recipient_pers_id` | `int` | `Officer.personId` |
| `target_dept_id` | `int` | `Department.deptCd` |

ความสัมพันธ์เหล่านี้เป็น Logical Reference เพราะข้อมูลต้นทางมาจาก SWU MIS API จึงไม่มี Foreign Key จากฐาน Server 133 ไปยังข้อมูลภายนอก

`scope_dept_id` และ `requester_dept_id` ถูกนำออกจากแบบปัจจุบันแล้ว โดยสิทธิ์ Officer/Approver ใช้ได้ทุกหน่วยงาน และหน่วยงานของ requester อ่านจาก endpoint `Officer` ตาม `requester_pers_id`
