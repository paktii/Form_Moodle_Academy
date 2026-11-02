IF DB_NAME() <> N'academy_db1447'
    THROW 50101, 'This migration may run only on academy_db1447.', 1;

IF EXISTS (
    SELECT 1
    FROM sys.tables AS t
    INNER JOIN sys.schemas AS s ON s.[schema_id] = t.[schema_id]
    WHERE s.[name] = N'service_portal'
)
    THROW 50102, 'service_portal tables already exist. Migration stopped without replacing them.', 1;

SET XACT_ABORT ON;
BEGIN TRANSACTION;

BEGIN TRY
    IF SCHEMA_ID(N'service_portal') IS NULL
        EXEC(N'CREATE SCHEMA [service_portal] AUTHORIZATION [dbo]');

CREATE TABLE [service_portal].[form_type]
(
    [form_type_code] varchar(20) NOT NULL,
    [form_name_th] nvarchar(500) NOT NULL,
    [short_name_th] nvarchar(200) NOT NULL,
    [route_name] varchar(150) NOT NULL,
    [current_version] smallint NOT NULL CONSTRAINT [DF_sp_form_type_version] DEFAULT (1),
    [sort_order] smallint NOT NULL CONSTRAINT [DF_sp_form_type_sort] DEFAULT (0),
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_form_type_active] DEFAULT ('Y'),
    [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_form_type_created] DEFAULT (SYSDATETIME()),
    [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_form_type_updated] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_form_type] PRIMARY KEY ([form_type_code]),
    CONSTRAINT [UQ_sp_form_type_name] UNIQUE ([form_name_th]),
    CONSTRAINT [UQ_sp_form_type_route] UNIQUE ([route_name]),
    CONSTRAINT [CK_sp_form_type_version] CHECK ([current_version] > 0),
    CONSTRAINT [CK_sp_form_type_active] CHECK ([is_active] IN ('Y', 'N'))
);

CREATE TABLE [service_portal].[request_status]
(
    [status_code] varchar(40) NOT NULL,
    [status_name_th] nvarchar(100) NOT NULL,
    [badge_style] varchar(30) NOT NULL CONSTRAINT [DF_sp_status_badge] DEFAULT ('NEUTRAL'),
    [is_terminal] bit NOT NULL CONSTRAINT [DF_sp_status_terminal] DEFAULT (0),
    [sort_order] smallint NOT NULL CONSTRAINT [DF_sp_status_sort] DEFAULT (0),
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_status_active] DEFAULT ('Y'),
    CONSTRAINT [PK_sp_request_status] PRIMARY KEY ([status_code]),
    CONSTRAINT [UQ_sp_request_status_name] UNIQUE ([status_name_th]),
    CONSTRAINT [CK_sp_status_badge] CHECK ([badge_style] IN ('NEUTRAL', 'INFO', 'WARNING', 'SUCCESS', 'DANGER')),
    CONSTRAINT [CK_sp_status_active] CHECK ([is_active] IN ('Y', 'N'))
);

CREATE TABLE [service_portal].[contact_role]
(
    [contact_role_code] varchar(50) NOT NULL,
    [contact_role_name_th] nvarchar(150) NOT NULL,
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_contact_role_active] DEFAULT ('Y'),
    CONSTRAINT [PK_sp_contact_role] PRIMARY KEY ([contact_role_code]),
    CONSTRAINT [UQ_sp_contact_role_name] UNIQUE ([contact_role_name_th]),
    CONSTRAINT [CK_sp_contact_role_active] CHECK ([is_active] IN ('Y', 'N'))
);

CREATE TABLE [service_portal].[document_type]
(
    [document_type_code] varchar(50) NOT NULL,
    [document_type_name_th] nvarchar(150) NOT NULL,
    [allowed_mime_types] nvarchar(1000) NULL,
    [max_file_size_bytes] bigint NULL,
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_document_type_active] DEFAULT ('Y'),
    CONSTRAINT [PK_sp_document_type] PRIMARY KEY ([document_type_code]),
    CONSTRAINT [UQ_sp_document_type_name] UNIQUE ([document_type_name_th]),
    CONSTRAINT [CK_sp_document_type_size] CHECK ([max_file_size_bytes] IS NULL OR [max_file_size_bytes] > 0),
    CONSTRAINT [CK_sp_document_type_active] CHECK ([is_active] IN ('Y', 'N'))
);

CREATE TABLE [service_portal].[notification_type]
(
    [notification_type_code] varchar(60) NOT NULL,
    [notification_type_name_th] nvarchar(200) NOT NULL,
    [template_key] varchar(150) NOT NULL,
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_notification_type_active] DEFAULT ('Y'),
    CONSTRAINT [PK_sp_notification_type] PRIMARY KEY ([notification_type_code]),
    CONSTRAINT [UQ_sp_notification_type_name] UNIQUE ([notification_type_name_th]),
    CONSTRAINT [UQ_sp_notification_template] UNIQUE ([template_key]),
    CONSTRAINT [CK_sp_notification_type_active] CHECK ([is_active] IN ('Y', 'N'))
);

CREATE TABLE [service_portal].[website_platform]
(
    [platform_code] varchar(30) NOT NULL,
    [platform_name_th] nvarchar(150) NOT NULL,
    [requires_detail] bit NOT NULL CONSTRAINT [DF_sp_platform_detail] DEFAULT (0),
    [sort_order] smallint NOT NULL CONSTRAINT [DF_sp_platform_sort] DEFAULT (0),
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_platform_active] DEFAULT ('Y'),
    CONSTRAINT [PK_sp_website_platform] PRIMARY KEY ([platform_code]),
    CONSTRAINT [UQ_sp_website_platform_name] UNIQUE ([platform_name_th]),
    CONSTRAINT [CK_sp_platform_active] CHECK ([is_active] IN ('Y', 'N'))
);

CREATE TABLE [service_portal].[app_role]
(
    [role_id] smallint IDENTITY NOT NULL,
    [role_code] varchar(30) NOT NULL,
    [role_name_th] nvarchar(100) NOT NULL,
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_role_active] DEFAULT ('Y'),
    CONSTRAINT [PK_sp_app_role] PRIMARY KEY ([role_id]),
    CONSTRAINT [UQ_sp_app_role_code] UNIQUE ([role_code]),
    CONSTRAINT [CK_sp_role_active] CHECK ([is_active] IN ('Y', 'N'))
);

CREATE TABLE [service_portal].[project_type]
(
    [project_type_code] varchar(30) NOT NULL,
    [project_type_name_th] nvarchar(200) NOT NULL,
    [requires_detail] bit NOT NULL CONSTRAINT [DF_sp_project_type_detail] DEFAULT (0),
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_project_type_active] DEFAULT ('Y'),
    CONSTRAINT [PK_sp_project_type] PRIMARY KEY ([project_type_code]),
    CONSTRAINT [UQ_sp_project_type_name] UNIQUE ([project_type_name_th]),
    CONSTRAINT [CK_sp_project_type_active] CHECK ([is_active] IN ('Y', 'N'))
);

CREATE TABLE [service_portal].[course_category]
(
    [category_code] varchar(30) NOT NULL,
    [category_name_th] nvarchar(200) NOT NULL,
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_course_category_active] DEFAULT ('Y'),
    CONSTRAINT [PK_sp_course_category] PRIMARY KEY ([category_code]),
    CONSTRAINT [UQ_sp_course_category_name] UNIQUE ([category_name_th]),
    CONSTRAINT [CK_sp_course_category_active] CHECK ([is_active] IN ('Y', 'N'))
);

CREATE TABLE [service_portal].[user_role_assignment]
(
    [role_assignment_id] bigint IDENTITY NOT NULL,
    [pers_id] int NOT NULL,
    [role_id] smallint NOT NULL,
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_role_assignment_active] DEFAULT ('Y'),
    [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_role_assignment_created] DEFAULT (SYSDATETIME()),
    [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_role_assignment_updated] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_role_assignment] PRIMARY KEY ([role_assignment_id]),
    CONSTRAINT [UQ_sp_role_assignment] UNIQUE ([pers_id], [role_id]),
    CONSTRAINT [CK_sp_role_assignment_active] CHECK ([is_active] IN ('Y', 'N')),
    CONSTRAINT [FK_sp_role_assignment_role] FOREIGN KEY ([role_id]) REFERENCES [service_portal].[app_role] ([role_id])
);

CREATE TABLE [service_portal].[faculty_dean]
(
    [faculty_dean_id] bigint IDENTITY NOT NULL,
    [target_dept_id] int NOT NULL,
    [dean_pers_id] int NOT NULL,
    [position_name] nvarchar(300) NULL,
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_faculty_dean_active] DEFAULT ('Y'),
    [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_faculty_dean_created] DEFAULT (SYSDATETIME()),
    [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_faculty_dean_updated] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_faculty_dean] PRIMARY KEY ([faculty_dean_id]),
    CONSTRAINT [UQ_sp_faculty_dean] UNIQUE ([target_dept_id], [dean_pers_id]),
    CONSTRAINT [CK_sp_faculty_dean_active] CHECK ([is_active] IN ('Y', 'N'))
);

CREATE TABLE [service_portal].[form_notification_recipient]
(
    [recipient_rule_id] bigint IDENTITY NOT NULL,
    [form_type_code] varchar(20) NOT NULL,
    [notification_type_code] varchar(60) NOT NULL,
    [recipient_source] varchar(20) NOT NULL,
    [role_id] smallint NULL,
    [recipient_pers_id] int NULL,
    [recipient_email] varchar(254) NULL,
    [recipient_name] nvarchar(300) NULL,
    [is_active] char(1) NOT NULL CONSTRAINT [DF_sp_recipient_rule_active] DEFAULT ('Y'),
    CONSTRAINT [PK_sp_recipient_rule] PRIMARY KEY ([recipient_rule_id]),
    CONSTRAINT [CK_sp_recipient_source] CHECK ([recipient_source] IN ('APP_ROLE', 'PERSON', 'STATIC_EMAIL')),
    CONSTRAINT [CK_sp_recipient_target] CHECK (
        ([recipient_source] = 'APP_ROLE' AND [role_id] IS NOT NULL AND [recipient_pers_id] IS NULL AND [recipient_email] IS NULL)
        OR ([recipient_source] = 'PERSON' AND [role_id] IS NULL AND [recipient_pers_id] IS NOT NULL AND [recipient_email] IS NULL)
        OR ([recipient_source] = 'STATIC_EMAIL' AND [role_id] IS NULL AND [recipient_pers_id] IS NULL AND [recipient_email] IS NOT NULL)
    ),
    CONSTRAINT [CK_sp_recipient_rule_active] CHECK ([is_active] IN ('Y', 'N')),
    CONSTRAINT [FK_sp_recipient_form] FOREIGN KEY ([form_type_code]) REFERENCES [service_portal].[form_type] ([form_type_code]),
    CONSTRAINT [FK_sp_recipient_notification] FOREIGN KEY ([notification_type_code]) REFERENCES [service_portal].[notification_type] ([notification_type_code]),
    CONSTRAINT [FK_sp_recipient_role] FOREIGN KEY ([role_id]) REFERENCES [service_portal].[app_role] ([role_id])
);

CREATE TABLE [service_portal].[service_request]
(
    [request_id] bigint IDENTITY NOT NULL,
    [request_no] varchar(40) NOT NULL,
    [form_type_code] varchar(20) NOT NULL,
    [requester_pers_id] int NOT NULL,
    [target_dept_id] int NOT NULL,
    [dean_pers_id] int NOT NULL,
    [dean_position_name] nvarchar(300) NULL,
    [request_title] nvarchar(500) NOT NULL,
    [status_code] varchar(40) NOT NULL CONSTRAINT [DF_sp_request_status] DEFAULT ('DRAFT'),
    [submitted_at] datetime2 NULL,
    [completed_at] datetime2 NULL,
    [completed_by_pers_id] int NULL,
    [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_request_created] DEFAULT (SYSDATETIME()),
    [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_request_updated] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_service_request] PRIMARY KEY ([request_id]),
    CONSTRAINT [UQ_sp_service_request_no] UNIQUE ([request_no]),
    CONSTRAINT [CK_sp_request_completion] CHECK (
        ([status_code] = 'COMPLETED' AND [completed_at] IS NOT NULL AND [completed_by_pers_id] IS NOT NULL)
        OR ([status_code] <> 'COMPLETED' AND [completed_at] IS NULL AND [completed_by_pers_id] IS NULL)
    ),
    CONSTRAINT [FK_sp_request_form] FOREIGN KEY ([form_type_code]) REFERENCES [service_portal].[form_type] ([form_type_code]),
    CONSTRAINT [FK_sp_request_status] FOREIGN KEY ([status_code]) REFERENCES [service_portal].[request_status] ([status_code])
);

CREATE TABLE [service_portal].[request_contact]
(
    [contact_id] bigint IDENTITY NOT NULL,
    [request_id] bigint NOT NULL,
    [contact_role_code] varchar(50) NOT NULL,
    [pers_id] int NULL,
    [full_name] nvarchar(300) NOT NULL,
    [position_name] nvarchar(200) NULL,
    [email] varchar(254) NULL,
    [phone] varchar(50) NULL,
    [is_primary] bit NOT NULL CONSTRAINT [DF_sp_contact_primary] DEFAULT (1),
    [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_contact_created] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_request_contact] PRIMARY KEY ([contact_id]),
    CONSTRAINT [FK_sp_contact_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE,
    CONSTRAINT [FK_sp_contact_role] FOREIGN KEY ([contact_role_code]) REFERENCES [service_portal].[contact_role] ([contact_role_code])
);

CREATE TABLE [service_portal].[request_document]
(
    [document_id] bigint IDENTITY NOT NULL,
    [request_id] bigint NOT NULL,
    [document_type_code] varchar(50) NOT NULL,
    [storage_key] varchar(1000) NOT NULL,
    [original_filename] nvarchar(500) NOT NULL,
    [mime_type] varchar(150) NOT NULL,
    [file_size_bytes] bigint NOT NULL,
    [version_no] int NOT NULL CONSTRAINT [DF_sp_document_version] DEFAULT (1),
    [uploaded_by_pers_id] int NOT NULL,
    [uploaded_at] datetime2 NOT NULL CONSTRAINT [DF_sp_document_uploaded] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_request_document] PRIMARY KEY ([document_id]),
    CONSTRAINT [UQ_sp_document_storage] UNIQUE ([storage_key]),
    CONSTRAINT [UQ_sp_document_version] UNIQUE ([request_id], [document_type_code], [version_no]),
    CONSTRAINT [CK_sp_document_size] CHECK ([file_size_bytes] > 0),
    CONSTRAINT [CK_sp_document_version] CHECK ([version_no] > 0),
    CONSTRAINT [FK_sp_document_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE,
    CONSTRAINT [FK_sp_document_type] FOREIGN KEY ([document_type_code]) REFERENCES [service_portal].[document_type] ([document_type_code])
);

CREATE TABLE [service_portal].[dean_review]
(
    [dean_review_id] bigint IDENTITY NOT NULL,
    [request_id] bigint NOT NULL,
    [dean_pers_id] int NOT NULL,
    [decision] varchar(20) NOT NULL,
    [comment] nvarchar(max) NULL,
    [decided_at] datetime2 NOT NULL CONSTRAINT [DF_sp_dean_review_decided] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_dean_review] PRIMARY KEY ([dean_review_id]),
    CONSTRAINT [CK_sp_dean_decision] CHECK ([decision] IN ('APPROVED', 'REJECTED')),
    CONSTRAINT [CK_sp_dean_comment] CHECK ([decision] = 'APPROVED' OR NULLIF(LTRIM(RTRIM([comment])), N'') IS NOT NULL),
    CONSTRAINT [FK_sp_dean_review_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE
);

CREATE TABLE [service_portal].[officer_review]
(
    [officer_review_id] bigint IDENTITY NOT NULL,
    [request_id] bigint NOT NULL,
    [officer_pers_id] int NOT NULL,
    [decision] varchar(20) NOT NULL,
    [rejection_reason] nvarchar(max) NULL,
    [reviewed_at] datetime2 NOT NULL CONSTRAINT [DF_sp_officer_reviewed] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_officer_review] PRIMARY KEY ([officer_review_id]),
    CONSTRAINT [CK_sp_officer_decision] CHECK ([decision] IN ('APPROVED', 'REJECTED')),
    CONSTRAINT [CK_sp_officer_reason] CHECK (
        ([decision] = 'APPROVED' AND [rejection_reason] IS NULL)
        OR ([decision] = 'REJECTED' AND NULLIF(LTRIM(RTRIM([rejection_reason])), N'') IS NOT NULL)
    ),
    CONSTRAINT [FK_sp_officer_review_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE
);

CREATE TABLE [service_portal].[request_status_history]
(
    [history_id] bigint IDENTITY NOT NULL,
    [request_id] bigint NOT NULL,
    [from_status_code] varchar(40) NULL,
    [to_status_code] varchar(40) NOT NULL,
    [changed_by_pers_id] int NULL,
    [change_source] varchar(30) NOT NULL,
    [comment] nvarchar(max) NULL,
    [changed_at] datetime2 NOT NULL CONSTRAINT [DF_sp_history_changed] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_request_history] PRIMARY KEY ([history_id]),
    CONSTRAINT [CK_sp_history_source] CHECK ([change_source] IN ('REQUESTER', 'DEAN', 'OFFICER', 'SYSTEM')),
    CONSTRAINT [FK_sp_history_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE,
    CONSTRAINT [FK_sp_history_from_status] FOREIGN KEY ([from_status_code]) REFERENCES [service_portal].[request_status] ([status_code]),
    CONSTRAINT [FK_sp_history_to_status] FOREIGN KEY ([to_status_code]) REFERENCES [service_portal].[request_status] ([status_code])
);

CREATE TABLE [service_portal].[notification_outbox]
(
    [notification_id] bigint IDENTITY NOT NULL,
    [request_id] bigint NOT NULL,
    [notification_type_code] varchar(60) NOT NULL,
    [recipient_pers_id] int NULL,
    [recipient_email] varchar(254) NOT NULL,
    [recipient_name] nvarchar(300) NULL,
    [reply_to_email] varchar(254) NULL,
    [reply_to_name] nvarchar(300) NULL,
    [delivery_status] varchar(20) NOT NULL CONSTRAINT [DF_sp_outbox_status] DEFAULT ('PENDING'),
    [attempt_count] int NOT NULL CONSTRAINT [DF_sp_outbox_attempt] DEFAULT (0),
    [next_attempt_at] datetime2 NULL,
    [claimed_at] datetime2 NULL,
    [sent_at] datetime2 NULL,
    [last_error] nvarchar(max) NULL,
    [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_outbox_created] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_notification_outbox] PRIMARY KEY ([notification_id]),
    CONSTRAINT [CK_sp_outbox_status] CHECK ([delivery_status] IN ('PENDING', 'SENDING', 'SENT', 'FAILED')),
    CONSTRAINT [CK_sp_outbox_attempt] CHECK ([attempt_count] >= 0),
    CONSTRAINT [FK_sp_outbox_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE,
    CONSTRAINT [FK_sp_outbox_type] FOREIGN KEY ([notification_type_code]) REFERENCES [service_portal].[notification_type] ([notification_type_code])
);

CREATE TABLE [service_portal].[website_request_detail]
(
    [request_id] bigint NOT NULL,
    [website_name] nvarchar(500) NOT NULL,
    [domain_url] varchar(1000) NULL,
    [site_scope_type] varchar(30) NULL,
    [service_starts_on] date NULL,
    [service_ends_on] date NULL,
    [platform_code] varchar(30) NULL,
    [additional_detail] nvarchar(max) NULL,
    [requested_space_gb] decimal(8, 2) NULL,
    [granted_space_gb] decimal(8, 2) NULL,
    [allocated_domain] nvarchar(max) NULL,
    [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_website_detail_created] DEFAULT (SYSDATETIME()),
    [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_website_detail_updated] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_website_detail] PRIMARY KEY ([request_id]),
    CONSTRAINT [CK_sp_site_scope] CHECK ([site_scope_type] IS NULL OR [site_scope_type] IN ('DEPARTMENT', 'PROJECT', 'CONFERENCE')),
    CONSTRAINT [CK_sp_website_period] CHECK ([service_starts_on] IS NULL OR [service_ends_on] IS NULL OR [service_ends_on] >= [service_starts_on]),
    CONSTRAINT [CK_sp_requested_space] CHECK ([requested_space_gb] IS NULL OR [requested_space_gb] > 0),
    CONSTRAINT [CK_sp_granted_space] CHECK ([granted_space_gb] IS NULL OR [granted_space_gb] > 0),
    CONSTRAINT [FK_sp_website_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE,
    CONSTRAINT [FK_sp_website_platform] FOREIGN KEY ([platform_code]) REFERENCES [service_portal].[website_platform] ([platform_code])
);

CREATE TABLE [service_portal].[emeeting_request_detail]
(
    [request_id] bigint NOT NULL,
    [action_type] varchar(20) NOT NULL,
    [execution_detail] nvarchar(max) NULL,
    [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_emeeting_detail_created] DEFAULT (SYSDATETIME()),
    [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_emeeting_detail_updated] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_emeeting_detail] PRIMARY KEY ([request_id]),
    CONSTRAINT [CK_sp_emeeting_action] CHECK ([action_type] IN ('NEW_ACCESS', 'CHANGE_CONTACT')),
    CONSTRAINT [FK_sp_emeeting_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE
);

CREATE TABLE [service_portal].[moodle_course_request_detail]
(
    [request_id] bigint NOT NULL,
    [project_name] nvarchar(500) NOT NULL,
    [project_type_code] varchar(30) NOT NULL,
    [project_other] nvarchar(500) NULL,
    [course_name_th] nvarchar(500) NOT NULL,
    [course_name_en] nvarchar(500) NOT NULL,
    [category_code] varchar(30) NOT NULL,
    [category_other] nvarchar(500) NULL,
    [course_description] nvarchar(max) NOT NULL,
    [learning_mode] varchar(100) NOT NULL,
    [activity_round] nvarchar(500) NULL,
    [activity_phase] nvarchar(250) NULL,
    [starts_on] date NOT NULL,
    [ends_on] date NOT NULL,
    [enrollment_method] varchar(100) NOT NULL,
    [enrollment_other] nvarchar(500) NULL,
    [expected_students] int NOT NULL,
    [course_id] varchar(100) NULL,
    [created_at] datetime2 NOT NULL CONSTRAINT [DF_sp_moodle_detail_created] DEFAULT (SYSDATETIME()),
    [updated_at] datetime2 NOT NULL CONSTRAINT [DF_sp_moodle_detail_updated] DEFAULT (SYSDATETIME()),
    CONSTRAINT [PK_sp_moodle_detail] PRIMARY KEY ([request_id]),
    CONSTRAINT [CK_sp_moodle_period] CHECK ([ends_on] >= [starts_on]),
    CONSTRAINT [CK_sp_moodle_students] CHECK ([expected_students] > 0),
    CONSTRAINT [FK_sp_moodle_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[service_request] ([request_id]) ON DELETE CASCADE,
    CONSTRAINT [FK_sp_moodle_project_type] FOREIGN KEY ([project_type_code]) REFERENCES [service_portal].[project_type] ([project_type_code]),
    CONSTRAINT [FK_sp_moodle_category] FOREIGN KEY ([category_code]) REFERENCES [service_portal].[course_category] ([category_code])
);

CREATE TABLE [service_portal].[course_instructor]
(
    [instructor_id] bigint IDENTITY NOT NULL,
    [request_id] bigint NOT NULL,
    [pers_id] int NULL,
    [instructor_name] nvarchar(300) NOT NULL,
    [instructor_email] varchar(254) NOT NULL,
    [sort_order] smallint NOT NULL CONSTRAINT [DF_sp_instructor_sort] DEFAULT (0),
    CONSTRAINT [PK_sp_course_instructor] PRIMARY KEY ([instructor_id]),
    CONSTRAINT [FK_sp_instructor_request] FOREIGN KEY ([request_id]) REFERENCES [service_portal].[moodle_course_request_detail] ([request_id]) ON DELETE CASCADE
);

CREATE UNIQUE INDEX [UX_sp_contact_primary_role]
    ON [service_portal].[request_contact] ([request_id], [contact_role_code])
    WHERE [is_primary] = 1;

CREATE INDEX [IX_sp_request_requester_created]
    ON [service_portal].[service_request] ([requester_pers_id], [created_at] DESC);

CREATE INDEX [IX_sp_request_status_created]
    ON [service_portal].[service_request] ([status_code], [created_at]);

CREATE INDEX [IX_sp_request_form_submitted]
    ON [service_portal].[service_request] ([form_type_code], [submitted_at] DESC);

CREATE INDEX [IX_sp_faculty_dean_active]
    ON [service_portal].[faculty_dean] ([target_dept_id], [is_active]);

CREATE INDEX [IX_sp_dean_review_latest]
    ON [service_portal].[dean_review] ([request_id], [decided_at] DESC);

CREATE INDEX [IX_sp_officer_review_latest]
    ON [service_portal].[officer_review] ([request_id], [reviewed_at] DESC);

CREATE INDEX [IX_sp_history_request]
    ON [service_portal].[request_status_history] ([request_id], [changed_at], [history_id]);

CREATE INDEX [IX_sp_recipient_rule_lookup]
    ON [service_portal].[form_notification_recipient] ([form_type_code], [notification_type_code], [is_active]);

CREATE INDEX [IX_sp_outbox_pending]
    ON [service_portal].[notification_outbox] ([delivery_status], [next_attempt_at], [notification_id])
    WHERE [delivery_status] IN ('PENDING', 'FAILED', 'SENDING');

INSERT INTO [service_portal].[form_type]
    ([form_type_code], [form_name_th], [short_name_th], [route_name], [current_version], [sort_order])
VALUES
    ('CC-ITED-01', N'แบบฟอร์มขอใช้พื้นที่สำหรับจัดทำเว็บไซต์', N'ขอใช้พื้นที่จัดทำเว็บไซต์', 'website-space.create', 1, 10),
    ('CC-ITED-02', N'แบบฟอร์มขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์', N'ขอเปลี่ยนผู้ดูแลเว็บไซต์', 'website-contact-change.create', 1, 20),
    ('CC-ITED-04', N'แบบฟอร์มขอเพิ่มพื้นที่บนเว็บไซต์', N'ขอเพิ่มพื้นที่เว็บไซต์', 'website-storage.create', 1, 30),
    ('CC-ITED-05', N'แบบฟอร์มขอสิทธิ์ผู้ดูแลกรอบการประชุมของส่วนงานบนระบบ e-Meeting', N'ขอสิทธิ์ผู้ดูแล e-Meeting', 'emeeting-contact.create', 1, 40),
    ('CC-ITED-06', N'แบบฟอร์มขอสร้างรายวิชาในระบบ SWU Moodle Academy', N'ขอสร้างรายวิชา Moodle Academy', 'moodle-course.create', 1, 50);

INSERT INTO [service_portal].[request_status]
    ([status_code], [status_name_th], [badge_style], [is_terminal], [sort_order])
VALUES
    ('DRAFT', N'แบบร่าง', 'NEUTRAL', 0, 10),
    ('PENDING_DEAN_REVIEW', N'รอผู้รับรอง', 'WARNING', 0, 20),
    ('UNDER_OFFICER_REVIEW', N'รอเจ้าหน้าที่ดำเนินการ', 'INFO', 0, 40),
    ('REJECTED', N'ไม่อนุมัติ', 'DANGER', 1, 60),
    ('COMPLETED', N'ดำเนินการเสร็จสิ้น', 'SUCCESS', 1, 70);

INSERT INTO [service_portal].[contact_role]
    ([contact_role_code], [contact_role_name_th])
VALUES
    ('WEBSITE_CONTACT', N'ผู้ดูแลเว็บไซต์'),
    ('CURRENT_WEBSITE_CONTACT', N'ผู้ดูแลเว็บไซต์เดิม'),
    ('NEW_WEBSITE_CONTACT', N'ผู้ดูแลเว็บไซต์คนใหม่'),
    ('EMEETING_CONTACT', N'ผู้ดูแลกรอบการประชุม'),
    ('CURRENT_EMEETING_CONTACT', N'ผู้ดูแลกรอบการประชุมเดิม'),
    ('COORDINATOR', N'ผู้ประสานงานโครงการ Moodle'),
    ('INSTRUCTOR', N'อาจารย์ผู้สอน');

INSERT INTO [service_portal].[document_type]
    ([document_type_code], [document_type_name_th], [allowed_mime_types], [max_file_size_bytes])
VALUES
    ('GENERATED_FORM', N'แบบฟอร์มที่ระบบสร้าง', N'application/pdf', 10485760),
    ('SIGNED_FORM', N'แบบฟอร์มที่ลงนามแล้ว', N'application/pdf', 10485760),
    ('ADDITIONAL_DOCUMENT', N'เอกสารประกอบเพิ่มเติม', N'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv', 10485760),
    ('STUDENT_ROSTER', N'รายชื่อผู้เรียน', N'application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv', 10485760);

INSERT INTO [service_portal].[notification_type]
    ([notification_type_code], [notification_type_name_th], [template_key])
VALUES
    ('DEAN_REVIEW_REQUIRED', N'แจ้งผู้รับรองให้ตรวจคำร้อง', 'dean-review-required'),
    ('DEAN_REJECTED', N'แจ้งผู้ยื่นเมื่อผู้รับรองไม่รับรอง', 'dean-rejected'),
    ('OFFICER_REVIEW_REQUIRED', N'แจ้งเจ้าหน้าที่ให้ดำเนินการ', 'officer-review-required'),
    ('REQUEST_REJECTED', N'แจ้งผู้ยื่นเมื่อเจ้าหน้าที่ไม่อนุมัติ', 'request-rejected'),
    ('REQUEST_COMPLETED_REQUESTER', N'แจ้งผู้ยื่นเมื่อดำเนินการเสร็จ', 'request-completed-requester'),
    ('REQUEST_COMPLETED_APPROVER', N'แจ้ง Approver เพื่อรับทราบเมื่อดำเนินการเสร็จ', 'request-completed-approver');

INSERT INTO [service_portal].[website_platform]
    ([platform_code], [platform_name_th], [requires_detail], [sort_order])
VALUES
    ('PHP_MYSQL', N'PHP + MySQL', 0, 10),
    ('DNN', N'DNN (DotNetNuke)', 0, 20),
    ('OTHER', N'อื่น ๆ (ระบุ)', 1, 30);

INSERT INTO [service_portal].[app_role]
    ([role_code], [role_name_th])
VALUES
    ('USER', N'ผู้ยื่นคำร้อง'),
    ('OFFICER', N'เจ้าหน้าที่ดำเนินการ');

INSERT INTO [service_portal].[project_type]
    ([project_type_code], [project_type_name_th], [requires_detail])
VALUES
    ('INCOME_SERVICE', N'โครงการบริการวิชาการแบบมีรายได้', 0),
    ('NO_INCOME_SERVICE', N'โครงการบริการวิชาการแบบไม่มีรายได้', 0),
    ('RESEARCH', N'โครงการวิจัย', 0),
    ('OTHER', N'อื่น ๆ (ระบุ)', 1);

INSERT INTO [service_portal].[course_category]
    ([category_code], [category_name_th])
VALUES
    ('HEALTH', N'การแพทย์และสุขภาพ'),
    ('ADMIN_FINANCE', N'การบริหาร/การเงิน'),
    ('DIGITAL', N'เทคโนโลยีดิจิทัล'),
    ('EDUCATION', N'การศึกษา'),
    ('LANGUAGE', N'ภาษาและการสื่อสาร'),
    ('OTHER', N'อื่น ๆ (ระบุ)');

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0
        ROLLBACK TRANSACTION;

    THROW;
END CATCH;
