<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'course133';

    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF COL_LENGTH(N'course133.course_request', N'coordinator_first_name') IS NULL
BEGIN
    ALTER TABLE [course133].[course_request] ADD
        [coordinator_first_name] nvarchar(100) NULL,
        [coordinator_last_name] nvarchar(100) NULL,
        [coordinator_position] nvarchar(200) NULL,
        [coordinator_phone] varchar(50) NULL,
        [coordinator_email] varchar(254) NULL;

    EXEC(N'UPDATE [course133].[course_request]
        SET [coordinator_first_name] = [manager_first_name],
            [coordinator_last_name] = [manager_last_name],
            [coordinator_position] = N''ไม่ระบุ'',
            [coordinator_phone] = ''-'',
            [coordinator_email] = [manager_email]');

    EXEC(N'ALTER TABLE [course133].[course_request] ALTER COLUMN [coordinator_first_name] nvarchar(100) NOT NULL');
    EXEC(N'ALTER TABLE [course133].[course_request] ALTER COLUMN [coordinator_last_name] nvarchar(100) NOT NULL');
    EXEC(N'ALTER TABLE [course133].[course_request] ALTER COLUMN [coordinator_position] nvarchar(200) NOT NULL');
    EXEC(N'ALTER TABLE [course133].[course_request] ALTER COLUMN [coordinator_phone] varchar(50) NOT NULL');
    EXEC(N'ALTER TABLE [course133].[course_request] ALTER COLUMN [coordinator_email] varchar(254) NOT NULL');
END
SQL);
    }

    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF COL_LENGTH(N'course133.course_request', N'coordinator_first_name') IS NOT NULL
BEGIN
    ALTER TABLE [course133].[course_request] DROP COLUMN
        [coordinator_first_name], [coordinator_last_name], [coordinator_position],
        [coordinator_phone], [coordinator_email];
END
SQL);
    }
};
