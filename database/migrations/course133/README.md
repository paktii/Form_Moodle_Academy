# SQL Server 133 migration

ระบบใช้ connection ชื่อ `course133` เพื่อเชื่อมต่อฐาน `academy_db1447` แต่ข้อมูลของแอปอยู่ใน schema `service_portal` โดยข้อมูลบุคลากรและหน่วยงานอ่านจาก SWU MIS API

ลิงก์รับรองของคณบดีมีอายุ 1 เดือนตาม `COURSE133_DEAN_REVIEW_LINK_MONTHS` อีเมลทุกประเภทใน environment `local`/`uat` จะถูกส่งไปยัง `COURSE133_TEST_EMAIL` เพื่อทดสอบโดยไม่ส่งถึงผู้รับจริง ส่วน production จะใช้ผู้รับจริงทุกบทบาท

คอลัมน์ `*_pers_id` และ `target_dept_id` ใช้ `int` ให้ตรงกับ `personId` และ `deptCd` จาก SWU MIS API แต่ไม่มี Foreign Key ไปยังข้อมูลภายนอก ข้อมูลหน่วยงานสำหรับตรวจสอบค่าที่ผู้ยื่นเลือกต้องมาจาก endpoint `Department` โดยตรงก่อนบันทึกรหัสหน่วยงานเป้าหมายลง Server 133

ตั้งค่า `DB_133_*` สำหรับฐานคำร้องใน `.env` แล้วรันจากโฟลเดอร์โปรเจกต์:

```powershell
php artisan config:clear
php artisan migrate --database=course133 --path=database/migrations/course133
```

ตรวจสถานะ:

```powershell
php artisan migrate:status --database=course133 --path=database/migrations/course133
```

## Schema ระบบรวมแบบฟอร์มขอรับบริการ

Migration `2026_10_22_000000_create_service_portal_schema.php` สร้าง schema `service_portal` และ migration วันที่ `2026_10_26` สลับระบบมาใช้ schema นี้ พร้อมลบตารางและ view ทดสอบเดิมใน schema `course133`

Schema ใหม่นี้ประกอบด้วย Master, คำร้องกลาง, ผู้ติดต่อ, เอกสาร, ผลรับรอง, ผลดำเนินการของเจ้าหน้าที่, ประวัติสถานะ, notification outbox และตารางรายละเอียดของเว็บไซต์ e-Meeting และ Moodle Academy ตาม Data Dictionary ที่ `database/design/service-request-data-dictionary-th.md`

คอลัมน์ `is_active` ใน schema `service_portal` ใช้ `char(1)` และรับเฉพาะ `Y` หรือ `N` Migration จะ seed แบบฟอร์ม `CC-ITED-01`, `CC-ITED-02`, `CC-ITED-04`, `CC-ITED-05`, `CC-ITED-06` รวมทั้งสถานะและ Master ที่เกี่ยวข้อง

Migration มี guard แยกสำหรับ `service_portal` และจะหยุดโดยไม่เขียนทับหากพบตารางใด ๆ อยู่ใน schema นี้แล้ว

Migration มี guard และจะหยุดทันทีหาก connection ไม่ได้ชี้ไปยังฐานชื่อ `academy_db1447` ส่วน migration cutover จะคัดลอก role assignment และรายการคณบดีก่อนลบ schema ทดสอบเดิม

หลัง cutover ข้อมูลคำร้อง อาจารย์ เอกสาร ผลตรวจ ประวัติ และคิวอีเมล จะถูกอ่าน/เขียนที่ `academy_db1447.service_portal` โดยตรง ส่วน `APPROVER` เป็นผู้รับอีเมลแจ้งเพื่อทราบเท่านั้น

กำหนดหัวหน้าส่วนงาน คณบดี หรือผู้อำนวยการของส่วนงานเป้าหมายใน `service_portal.faculty_dean` โดยใช้ `target_dept_id` จาก `deptCd` และ `dean_pers_id` จาก `personId` ของ SWU MIS API ตัวอย่าง:

```sql
INSERT INTO [service_portal].[faculty_dean] ([target_dept_id], [dean_pers_id], [is_active])
VALUES (1000, 123456, 'Y');
```

ตั้งค่า `MAIL_*` เป็น SMTP จริง แล้วให้ Laravel scheduler ทำงานเพื่อส่งอีเมลจาก `notification_outbox`:

```powershell
php artisan schedule:work
```

Migration cutover เป็นแบบทำลายข้อมูลทดสอบเดิมและไม่รองรับ rollback อัตโนมัติ หากต้องย้อนระบบต้องกู้คืนจาก backup เท่านั้น
