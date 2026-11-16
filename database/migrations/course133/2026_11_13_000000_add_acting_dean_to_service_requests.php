<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('course133')->unprepared(<<<'SQL'
IF COL_LENGTH(N'service_portal.service_request', N'dean_principal_pers_id') IS NULL
BEGIN
    ALTER TABLE [service_portal].[service_request]
        ADD [dean_principal_pers_id] int NULL;
END;

EXEC(N'UPDATE [service_portal].[service_request]
SET [dean_principal_pers_id] = [dean_pers_id]
WHERE [dean_principal_pers_id] IS NULL;');

IF COL_LENGTH(N'service_portal.service_request', N'dean_is_acting') IS NULL
BEGIN
    ALTER TABLE [service_portal].[service_request]
        ADD [dean_is_acting] bit NOT NULL
            CONSTRAINT [DF_sp_service_request_dean_is_acting] DEFAULT (0);
END;
SQL);
    }

    public function down(): void
    {
        DB::connection('course133')->unprepared(<<<'SQL'
IF COL_LENGTH(N'service_portal.service_request', N'dean_is_acting') IS NOT NULL
BEGIN
    IF OBJECT_ID(N'service_portal.DF_sp_service_request_dean_is_acting', N'D') IS NOT NULL
        ALTER TABLE [service_portal].[service_request]
            DROP CONSTRAINT [DF_sp_service_request_dean_is_acting];

    ALTER TABLE [service_portal].[service_request]
        DROP COLUMN [dean_is_acting];
END;

IF COL_LENGTH(N'service_portal.service_request', N'dean_principal_pers_id') IS NOT NULL
    ALTER TABLE [service_portal].[service_request]
        DROP COLUMN [dean_principal_pers_id];
SQL);
    }
};
