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
    THROW 50102, 'This migration may run only on academy_db1447.', 1;

BEGIN TRANSACTION;
BEGIN TRY
    IF COL_LENGTH(N'service_portal.service_request', N'target_subdept_id') IS NULL
        ALTER TABLE [service_portal].[service_request] ADD [target_subdept_id] int NULL;
    IF COL_LENGTH(N'service_portal.request_document', N'roster_acknowledged_at') IS NULL
        ALTER TABLE [service_portal].[request_document] ADD [roster_acknowledged_at] datetime2 NULL;
    IF COL_LENGTH(N'service_portal.request_document', N'roster_acknowledged_by_pers_id') IS NULL
        ALTER TABLE [service_portal].[request_document] ADD [roster_acknowledged_by_pers_id] int NULL;

    INSERT INTO [service_portal].[notification_type]
        ([notification_type_code], [notification_type_name_th], [template_key])
    SELECT source.[code], source.[name], source.[template]
    FROM (VALUES
        ('STUDENT_ROSTER_SUBMITTED', N'แจ้งเจ้าหน้าที่เมื่อแนบรายชื่อผู้เรียน', 'student-roster-submitted'),
        ('STUDENT_ROSTER_UPDATED', N'แจ้งเจ้าหน้าที่เมื่อแก้ไขรายชื่อผู้เรียน', 'student-roster-updated'),
        ('STUDENT_ROSTER_REOPEN_REQUESTED', N'แจ้งเจ้าหน้าที่เมื่อขอแก้ไขรายชื่อผู้เรียน', 'student-roster-reopen-requested'),
        ('STUDENT_ROSTER_ACKNOWLEDGED', N'แจ้งผู้ยื่นเมื่อรับทราบรายชื่อผู้เรียน', 'student-roster-acknowledged'),
        ('STUDENT_ROSTER_REOPENED', N'แจ้งผู้ยื่นเมื่อเปิดให้แก้ไขรายชื่อผู้เรียน', 'student-roster-reopened'),
        ('COURSE_ID_RECORDED', N'แจ้งผู้ยื่นเมื่อบันทึก Course ID', 'course-id-recorded')
    ) AS source([code], [name], [template])
    WHERE NOT EXISTS (
        SELECT 1 FROM [service_portal].[notification_type] AS existing
        WHERE existing.[notification_type_code] = source.[code]
    );

    IF OBJECT_ID(N'course133.user_role_assignment', N'U') IS NOT NULL
    BEGIN
        INSERT INTO [service_portal].[user_role_assignment] ([pers_id], [role_id], [is_active])
        SELECT old_assignment.[pers_id], new_role.[role_id],
               CASE WHEN old_assignment.[is_active] IN ('Y', '1') THEN 'Y' ELSE 'N' END
        FROM [course133].[user_role_assignment] AS old_assignment
        INNER JOIN [course133].[app_role] AS old_role ON old_role.[role_id] = old_assignment.[role_id]
        INNER JOIN [service_portal].[app_role] AS new_role ON new_role.[role_code] = old_role.[role_code]
        WHERE NOT EXISTS (
            SELECT 1 FROM [service_portal].[user_role_assignment] AS existing
            WHERE existing.[pers_id] = old_assignment.[pers_id] AND existing.[role_id] = new_role.[role_id]
        );
    END;

    IF OBJECT_ID(N'course133.faculty_dean', N'U') IS NOT NULL
    BEGIN
        INSERT INTO [service_portal].[faculty_dean] ([target_dept_id], [dean_pers_id], [is_active])
        SELECT [target_dept_id], [dean_pers_id], CASE WHEN [is_active] IN ('Y', '1') THEN 'Y' ELSE 'N' END
        FROM [course133].[faculty_dean] AS old_dean
        WHERE NOT EXISTS (
            SELECT 1 FROM [service_portal].[faculty_dean] AS existing
            WHERE existing.[target_dept_id] = old_dean.[target_dept_id]
              AND existing.[dean_pers_id] = old_dean.[dean_pers_id]
        );
    END;

    DROP TABLE IF EXISTS [course133].[notification_outbox];
    DROP TABLE IF EXISTS [course133].[request_status_history];
    DROP TABLE IF EXISTS [course133].[dean_review];
    DROP TABLE IF EXISTS [course133].[approver_review];
    DROP TABLE IF EXISTS [course133].[course_approval];
    DROP TABLE IF EXISTS [course133].[officer_review];
    DROP TABLE IF EXISTS [course133].[course_document];
    DROP TABLE IF EXISTS [course133].[course_instructor];
    DROP TABLE IF EXISTS [course133].[course_request];
    DROP TABLE IF EXISTS [course133].[faculty_dean];
    DROP TABLE IF EXISTS [course133].[user_role_assignment];
    DROP TABLE IF EXISTS [course133].[course_category];
    DROP TABLE IF EXISTS [course133].[project_type];
    DROP TABLE IF EXISTS [course133].[app_role];

    IF SCHEMA_ID(N'course133') IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM sys.objects WHERE [schema_id] = SCHEMA_ID(N'course133')
    )
        EXEC(N'DROP SCHEMA [course133]');

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
        throw new RuntimeException('The disposable course133 test tables cannot be restored by rollback.');
    }
};
