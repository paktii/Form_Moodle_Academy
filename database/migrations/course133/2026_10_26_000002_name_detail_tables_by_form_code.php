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
    THROW 50103, 'This migration may run only on academy_db1447.', 1;

BEGIN TRANSACTION;
BEGIN TRY
    IF EXISTS (
        SELECT 1
        FROM [service_portal].[website_request_detail] AS detail
        INNER JOIN [service_portal].[service_request] AS request ON request.[request_id] = detail.[request_id]
        WHERE request.[form_type_code] = 'CC-ITED-01'
          AND detail.[domain_url] IS NULL
    )
        THROW 50104, 'CC-ITED-01 domain_url must be populated before form detail tables are split.', 1;

    CREATE TABLE [service_portal].[cc_ited_01_request_detail]
    (
        [request_id] bigint NOT NULL,
        [website_name] nvarchar(500) NOT NULL,
        [domain_url] varchar(1000) NOT NULL,
        [site_scope_type] varchar(30) NULL,
        [service_starts_on] date NULL,
        [service_ends_on] date NULL,
        [platform_code] varchar(30) NULL,
        [additional_detail] nvarchar(max) NULL,
        [allocated_domain] varchar(1000) NULL,
        [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_cc01_created] DEFAULT (SYSDATETIME()),
        [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_cc01_updated] DEFAULT (SYSDATETIME()),
        CONSTRAINT [PK_sp_cc01_detail] PRIMARY KEY ([request_id]),
        CONSTRAINT [CK_sp_cc01_scope] CHECK ([site_scope_type] IS NULL OR [site_scope_type] IN ('DEPARTMENT', 'PROJECT', 'CONFERENCE')),
        CONSTRAINT [CK_sp_cc01_period] CHECK ([service_starts_on] IS NULL OR [service_ends_on] IS NULL OR [service_ends_on] >= [service_starts_on]),
        CONSTRAINT [FK_sp_cc01_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE,
        CONSTRAINT [FK_sp_cc01_platform] FOREIGN KEY ([platform_code]) REFERENCES [service_portal].[website_platform] ([platform_code])
    );

    CREATE TABLE [service_portal].[cc_ited_02_request_detail]
    (
        [request_id] bigint NOT NULL,
        [website_name] nvarchar(500) NOT NULL,
        [domain_url] varchar(1000) NULL,
        [additional_detail] nvarchar(max) NULL,
        [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_cc02_created] DEFAULT (SYSDATETIME()),
        [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_cc02_updated] DEFAULT (SYSDATETIME()),
        CONSTRAINT [PK_sp_cc02_detail] PRIMARY KEY ([request_id]),
        CONSTRAINT [FK_sp_cc02_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE
    );

    CREATE TABLE [service_portal].[cc_ited_04_request_detail]
    (
        [request_id] bigint NOT NULL,
        [website_name] nvarchar(500) NOT NULL,
        [domain_url] varchar(1000) NULL,
        [requested_space_gb] decimal(8, 2) NULL,
        [granted_space_gb] decimal(8, 2) NULL,
        [additional_detail] nvarchar(max) NULL,
        [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_cc04_created] DEFAULT (SYSDATETIME()),
        [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_cc04_updated] DEFAULT (SYSDATETIME()),
        CONSTRAINT [PK_sp_cc04_detail] PRIMARY KEY ([request_id]),
        CONSTRAINT [CK_sp_cc04_requested_space] CHECK ([requested_space_gb] IS NULL OR [requested_space_gb] > 0),
        CONSTRAINT [CK_sp_cc04_granted_space] CHECK ([granted_space_gb] IS NULL OR [granted_space_gb] > 0),
        CONSTRAINT [FK_sp_cc04_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE
    );

    INSERT INTO [service_portal].[cc_ited_01_request_detail]
        ([request_id], [website_name], [domain_url], [site_scope_type], [service_starts_on], [service_ends_on], [platform_code], [additional_detail], [allocated_domain], [created_at], [updated_at])
    SELECT detail.[request_id], detail.[website_name], detail.[domain_url], detail.[site_scope_type], detail.[service_starts_on], detail.[service_ends_on], detail.[platform_code], detail.[additional_detail], detail.[allocated_domain], detail.[created_at], detail.[updated_at]
    FROM [service_portal].[website_request_detail] AS detail
    INNER JOIN [service_portal].[service_request] AS request ON request.[request_id] = detail.[request_id]
    WHERE request.[form_type_code] = 'CC-ITED-01';

    INSERT INTO [service_portal].[cc_ited_02_request_detail]
        ([request_id], [website_name], [domain_url], [additional_detail], [created_at], [updated_at])
    SELECT detail.[request_id], detail.[website_name], detail.[domain_url], detail.[additional_detail], detail.[created_at], detail.[updated_at]
    FROM [service_portal].[website_request_detail] AS detail
    INNER JOIN [service_portal].[service_request] AS request ON request.[request_id] = detail.[request_id]
    WHERE request.[form_type_code] = 'CC-ITED-02';

    INSERT INTO [service_portal].[cc_ited_04_request_detail]
        ([request_id], [website_name], [domain_url], [requested_space_gb], [granted_space_gb], [additional_detail], [created_at], [updated_at])
    SELECT detail.[request_id], detail.[website_name], detail.[domain_url], detail.[requested_space_gb], detail.[granted_space_gb], detail.[additional_detail], detail.[created_at], detail.[updated_at]
    FROM [service_portal].[website_request_detail] AS detail
    INNER JOIN [service_portal].[service_request] AS request ON request.[request_id] = detail.[request_id]
    WHERE request.[form_type_code] = 'CC-ITED-04';

    DROP TABLE [service_portal].[website_request_detail];
    EXEC sys.sp_rename N'service_portal.emeeting_request_detail', N'cc_ited_05_request_detail';
    EXEC sys.sp_rename N'service_portal.moodle_course_request_detail', N'cc_ited_06_request_detail';

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
CREATE TABLE [service_portal].[website_request_detail]
(
    [request_id] bigint NOT NULL PRIMARY KEY,
    [website_name] nvarchar(500) NOT NULL,
    [domain_url] varchar(1000) NULL,
    [site_scope_type] varchar(30) NULL,
    [service_starts_on] date NULL,
    [service_ends_on] date NULL,
    [platform_code] varchar(30) NULL,
    [additional_detail] nvarchar(max) NULL,
    [requested_space_gb] decimal(8, 2) NULL,
    [granted_space_gb] decimal(8, 2) NULL,
    [allocated_domain] varchar(1000) NULL,
    [created_at] datetime2 NOT NULL DEFAULT (SYSDATETIME()),
    [updated_at] datetime2 NOT NULL DEFAULT (SYSDATETIME()),
    FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE,
    FOREIGN KEY ([platform_code]) REFERENCES [service_portal].[website_platform] ([platform_code])
);

INSERT INTO [service_portal].[website_request_detail]
    ([request_id], [website_name], [domain_url], [site_scope_type], [service_starts_on], [service_ends_on], [platform_code], [additional_detail], [requested_space_gb], [granted_space_gb], [allocated_domain], [created_at], [updated_at])
SELECT [request_id], [website_name], [domain_url], [site_scope_type], [service_starts_on], [service_ends_on], [platform_code], [additional_detail], NULL, NULL, [allocated_domain], [created_at], [updated_at]
FROM [service_portal].[cc_ited_01_request_detail]
UNION ALL
SELECT [request_id], [website_name], [domain_url], NULL, NULL, NULL, NULL, [additional_detail], NULL, NULL, NULL, [created_at], [updated_at]
FROM [service_portal].[cc_ited_02_request_detail]
UNION ALL
SELECT [request_id], [website_name], [domain_url], NULL, NULL, NULL, NULL, [additional_detail], [requested_space_gb], [granted_space_gb], NULL, [created_at], [updated_at]
FROM [service_portal].[cc_ited_04_request_detail];

DROP TABLE [service_portal].[cc_ited_01_request_detail];
DROP TABLE [service_portal].[cc_ited_02_request_detail];
DROP TABLE [service_portal].[cc_ited_04_request_detail];
EXEC sys.sp_rename N'service_portal.cc_ited_05_request_detail', N'emeeting_request_detail';
EXEC sys.sp_rename N'service_portal.cc_ited_06_request_detail', N'moodle_course_request_detail';
SQL);
    }
};
