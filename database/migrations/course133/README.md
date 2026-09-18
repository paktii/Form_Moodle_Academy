# SQL Server 133 migration

Migration ชุดนี้สร้าง schema `course133` จำนวน 11 ตารางในฐาน `academy_db1447` ผ่าน connection `course133` โดยไม่แก้ฐาน `saladb` บน Server 199

คอลัมน์ `*_pers_id` และ `target_dept_id` ใช้ `int` ให้ตรงกับ `saladb.dbo.persons.person_id` และ `saladb.dbo.departments.dept_cd` แต่ไม่มี Foreign Key ข้าม Server ช่วงที่ยังไม่เชื่อม Server 199 Laravel ใช้ snapshot หน่วยงานใน `config/departments.php` เพื่อตรวจสอบค่าที่ผู้ยื่นเลือกและบันทึกรหัสหน่วยงานเป้าหมายลง Server 133

ตั้งค่า `DB_133_*` สำหรับฐานคำร้องใน `.env` แล้วรันจากโฟลเดอร์โปรเจกต์:

```powershell
php artisan config:clear
php artisan migrate --database=course133 --path=database/migrations/course133
```

ตรวจสถานะ:

```powershell
php artisan migrate:status --database=course133 --path=database/migrations/course133
```

Migration มี guard และจะหยุดทันทีหาก connection ไม่ได้ชี้ไปยังฐานชื่อ `academy_db1447` หรือถ้าพบตาราง `course133` เดิม โดยไม่ลบหรือแทนที่ตารางเดิม

หลัง migrate ระบบจะสร้าง role assignment จากค่า `COURSE133_*_PERS_ID` ชั่วคราวให้ทั้ง requester, officer และ approver ด้วย ส่วนข้อมูลคำร้อง อาจารย์ เอกสาร ผลตรวจ ผลอนุมัติ ประวัติ และคิวอีเมล จะถูกอ่าน/เขียนที่ `academy_db1447.course133` โดยตรง

ตั้งค่า `MAIL_*` เป็น SMTP จริง แล้วให้ Laravel scheduler ทำงานเพื่อส่งอีเมลจาก `notification_outbox`:

```powershell
php artisan schedule:work
```

การ rollback จะลบ view และ 11 ตารางของ migration นี้ จึงใช้เฉพาะเมื่อตั้งใจย้อน schema:

```powershell
php artisan migrate:rollback --database=course133 --path=database/migrations/course133
```
