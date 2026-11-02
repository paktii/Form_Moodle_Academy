<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'course133';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50110, 'This migration may run only on academy_db1447.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;
BEGIN TRY
    IF COL_LENGTH(N'service_portal.faculty_dean', N'position_name') IS NULL
        ALTER TABLE [service_portal].[faculty_dean] ADD [position_name] nvarchar(300) NULL;

    IF COL_LENGTH(N'service_portal.service_request', N'dean_position_name') IS NULL
        ALTER TABLE [service_portal].[service_request] ADD [dean_position_name] nvarchar(300) NULL;

    -- Administrative titles published by https://www.swu.ac.th/deans (7 October 2026).
    EXEC(N'UPDATE dean_master
    SET [position_name] = source.[position_name],
        [updated_at] = SYSDATETIME()
    FROM [service_portal].[faculty_dean] AS dean_master
    INNER JOIN (VALUES
        (100, 712662, N''คณบดีคณะมนุษยศาสตร์''),
        (200, 103252, N''คณบดีคณะวิทยาศาสตร์''),
        (300, 703936, N''คณบดีคณะสังคมศาสตร์''),
        (400, 707727, N''คณบดีคณะพลศึกษา''),
        (500, 712661, N''คณบดีคณะศึกษาศาสตร์''),
        (600, 711741, N''คณบดีคณะพยาบาลศาสตร์''),
        (700, 708064, N''คณบดีคณะแพทยศาสตร์''),
        (900, 103128, N''คณบดีคณะวิศวกรรมศาสตร์''),
        (1000, 701009, N''คณบดีคณะศิลปกรรมศาสตร์''),
        (1100, 700092, N''คณบดีคณะทันตแพทยศาสตร์''),
        (1200, 708075, N''คณบดีคณะเภสัชศาสตร์''),
        (1700, 700169, N''ผู้อำนวยการสำนักสื่อและเทคโนโลยีการศึกษา''),
        (2600, 700026, N''ผู้อำนวยการสถาบันวัฒนธรรมและศิลปะ''),
        (2800, 705169, N''ผู้อำนวยการสำนักนวัตกรรมการเรียนรู้''),
        (3000, 702961, N''คณบดีวิทยาลัยนวัตกรรมสื่อสารสังคม''),
        (4700, 705751, N''คณบดีวิทยาลัยโพธิวิชชาลัย''),
        (4900, 700736, N''คณบดีคณะเศรษฐศาสตร์''),
        (5000, 700581, N''ผู้อำนวยการสำนักงานอธิการบดี''),
        (5100, 712483, N''ผู้อำนวยการสำนักงานสภามหาวิทยาลัย''),
        (5200, 712618, N''ผู้อำนวยการสถาบันวิจัยพฤติกรรมศาสตร์''),
        (5500, 708702, N''รักษาการแทนผู้อำนวยการสำนักทดสอบทางการศึกษาและจิตวิทยา''),
        (5600, 700213, N''ผู้อำนวยการสำนักหอสมุดกลาง''),
        (5900, 702363, N''ผู้อำนวยการสำนักคอมพิวเตอร์''),
        (6400, 707524, N''รักษาการแทนผู้อำนวยการสถาบันยุทธศาสตร์ทางปัญญาและวิจัย''),
        (6600, 704430, N''รักษาการแทนคณบดีคณะวัฒนธรรมสิ่งแวดล้อมและการท่องเที่ยวเชิงนิเวศ''),
        (6800, 708656, N''คณบดีคณะบริหารธุรกิจเพื่อสังคม''),
        (6900, 708044, N''คณบดีวิทยาลัยอุตสาหกรรมสร้างสรรค์''),
        (7000, 703910, N''คณบดีวิทยาลัยนานาชาติเพื่อศึกษาความยั่งยืน''),
        (7100, 713351, N''คณบดีคณะเทคโนโลยีและนวัตกรรมผลิตภัณฑ์การเกษตร''),
        (7400, 800185, N''ผู้อำนวยการสถาบันวิจัย พัฒนา และสาธิตการศึกษา''),
        (8200, 708400, N''รักษาการแทนผู้อำนวยการศูนย์การแพทย์ปัญญานันทภิกขุ ชลประทาน''),
        (9900, 704494, N''รักษาการแทนคณบดีบัณฑิตวิทยาลัย'')
    ) AS source([target_dept_id], [dean_pers_id], [position_name])
        ON source.[target_dept_id] = dean_master.[target_dept_id]
       AND source.[dean_pers_id] = dean_master.[dean_pers_id];');

    EXEC(N'UPDATE request_header
    SET [dean_position_name] = dean_master.[position_name],
        [updated_at] = SYSDATETIME()
    FROM [service_portal].[service_request] AS request_header
    INNER JOIN [service_portal].[faculty_dean] AS dean_master
        ON dean_master.[target_dept_id] = request_header.[target_dept_id]
       AND dean_master.[dean_pers_id] = request_header.[dean_pers_id]
    WHERE request_header.[dean_position_name] IS NULL
      AND dean_master.[position_name] IS NOT NULL;');

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50110, 'This migration may run only on academy_db1447.', 1;

IF COL_LENGTH(N'service_portal.service_request', N'dean_position_name') IS NOT NULL
    ALTER TABLE [service_portal].[service_request] DROP COLUMN [dean_position_name];

IF COL_LENGTH(N'service_portal.faculty_dean', N'position_name') IS NOT NULL
    ALTER TABLE [service_portal].[faculty_dean] DROP COLUMN [position_name];
SQL);
    }
};
