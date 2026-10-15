<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'course133';

    public function up(): void
    {
        $this->database()->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF OBJECT_ID(N'course133.approver_review', N'U') IS NULL
   AND OBJECT_ID(N'course133.course_approval', N'U') IS NOT NULL
    EXEC sys.sp_rename N'course133.course_approval', N'approver_review';

IF COL_LENGTH(N'course133.approver_review', N'approver_review_id') IS NULL
   AND COL_LENGTH(N'course133.approver_review', N'approval_id') IS NOT NULL
    EXEC sys.sp_rename N'course133.approver_review.approval_id', N'approver_review_id', N'COLUMN';
SQL);
    }

    public function down(): void
    {
        $this->database()->unprepared(<<<'SQL'
IF DB_NAME() <> N'academy_db1447'
    THROW 50001, 'This migration may run only on academy_db1447.', 1;

IF COL_LENGTH(N'course133.approver_review', N'approval_id') IS NULL
   AND COL_LENGTH(N'course133.approver_review', N'approver_review_id') IS NOT NULL
    EXEC sys.sp_rename N'course133.approver_review.approver_review_id', N'approval_id', N'COLUMN';

IF OBJECT_ID(N'course133.course_approval', N'U') IS NULL
   AND OBJECT_ID(N'course133.approver_review', N'U') IS NOT NULL
    EXEC sys.sp_rename N'course133.approver_review', N'course_approval';
SQL);
    }

    private function database(): Connection
    {
        return DB::connection($this->connection);
    }
};
