<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'course133';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF COL_LENGTH(N'course133.course_request', N'manager_first_name') IS NOT NULL
BEGIN
    ALTER TABLE [course133].[course_request] DROP COLUMN [manager_first_name], [manager_last_name], [manager_email];
END
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection($this->connection)->unprepared(<<<'SQL'
IF COL_LENGTH(N'course133.course_request', N'manager_first_name') IS NULL
BEGIN
    ALTER TABLE [course133].[course_request] ADD
        [manager_first_name] nvarchar(100) NULL,
        [manager_last_name] nvarchar(100) NULL,
        [manager_email] varchar(254) NULL;
        
    EXEC(N'UPDATE [course133].[course_request] SET [manager_first_name] = [coordinator_first_name], [manager_last_name] = [coordinator_last_name], [manager_email] = [coordinator_email]');
    
    EXEC(N'ALTER TABLE [course133].[course_request] ALTER COLUMN [manager_first_name] nvarchar(100) NOT NULL');
    EXEC(N'ALTER TABLE [course133].[course_request] ALTER COLUMN [manager_last_name] nvarchar(100) NOT NULL');
    EXEC(N'ALTER TABLE [course133].[course_request] ALTER COLUMN [manager_email] varchar(254) NOT NULL');
END
SQL);
    }
};
