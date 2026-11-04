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
    THROW 50119, 'This migration may run only on academy_db1447.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;
BEGIN TRY
    IF OBJECT_ID(N'service_portal.form_head_assignment', N'U') IS NULL
    BEGIN
        CREATE TABLE [service_portal].[form_head_assignment]
        (
            [head_assignment_id] bigint IDENTITY NOT NULL,
            [form_type_code] varchar(20) NOT NULL,
            [head_pers_id] int NOT NULL,
            [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_form_head_active] DEFAULT ('Y'),
            [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_form_head_created] DEFAULT (SYSDATETIME()),
            [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_form_head_updated] DEFAULT (SYSDATETIME()),
            CONSTRAINT [PK_sp_form_head_assignment] PRIMARY KEY ([head_assignment_id]),
            CONSTRAINT [UQ_sp_form_head_assignment] UNIQUE ([form_type_code], [head_pers_id]),
            CONSTRAINT [CK_sp_form_head_active] CHECK ([is_active] IN ('Y', 'N')),
            CONSTRAINT [FK_sp_form_head_form] FOREIGN KEY ([form_type_code])
                REFERENCES [service_portal].[form_type] ([form_type_code])
        );

        CREATE UNIQUE INDEX [UX_sp_form_head_one_active]
            ON [service_portal].[form_head_assignment] ([form_type_code])
            WHERE [is_active] = 'Y';

        CREATE INDEX [IX_sp_form_head_person]
            ON [service_portal].[form_head_assignment] ([head_pers_id], [is_active]);
    END;

    IF OBJECT_ID(N'service_portal.form_officer_assignment', N'U') IS NULL
    BEGIN
        CREATE TABLE [service_portal].[form_officer_assignment]
        (
            [officer_assignment_id] bigint IDENTITY NOT NULL,
            [form_type_code] varchar(20) NOT NULL,
            [officer_pers_id] int NOT NULL,
            [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_form_officer_active] DEFAULT ('Y'),
            [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_form_officer_created] DEFAULT (SYSDATETIME()),
            [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_form_officer_updated] DEFAULT (SYSDATETIME()),
            CONSTRAINT [PK_sp_form_officer_assignment] PRIMARY KEY ([officer_assignment_id]),
            CONSTRAINT [UQ_sp_form_officer_assignment] UNIQUE ([form_type_code], [officer_pers_id]),
            CONSTRAINT [CK_sp_form_officer_active] CHECK ([is_active] IN ('Y', 'N')),
            CONSTRAINT [FK_sp_form_officer_form] FOREIGN KEY ([form_type_code])
                REFERENCES [service_portal].[form_type] ([form_type_code])
        );

        CREATE INDEX [IX_sp_form_officer_active]
            ON [service_portal].[form_officer_assignment] ([form_type_code], [is_active], [officer_pers_id]);

        CREATE INDEX [IX_sp_form_officer_person]
            ON [service_portal].[form_officer_assignment] ([officer_pers_id], [is_active]);
    END;

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
    THROW 50119, 'This migration may run only on academy_db1447.', 1;

DROP TABLE IF EXISTS [service_portal].[form_officer_assignment];
DROP TABLE IF EXISTS [service_portal].[form_head_assignment];
SQL);
    }
};

