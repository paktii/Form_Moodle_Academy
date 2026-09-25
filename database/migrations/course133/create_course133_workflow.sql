IF DB_NAME() <> N'academy_db1447' THROW 50001       ,
    'This migration may run only on academy_db1447.',
    1;
    IF SCHEMA_ID(N'course133') IS NULL EXEC(N'CREATE SCHEMA [course133] AUTHORIZATION [dbo]');
        IF OBJECT_ID(N'[course133].[app_role]', N'U') IS NOT NULL THROW 50002          ,
            'course133 tables already exist. Migration stopped without replacing them.',
            1;
            CREATE TABLE [course133].[app_role]
                (
                    [role_id]      smallint IDENTITY PRIMARY KEY,
                    [role_code]    varchar(30) NOT NULL UNIQUE  ,
                    [role_name_th] nvarchar(100) NOT NULL
                )
            ;
            CREATE TABLE [course133].[project_type]
                (
                    [project_type_code]    varchar(30) PRIMARY KEY      ,
                    [project_type_name_th] nvarchar(150) NOT NULL UNIQUE,
                    [requires_detail]      bit NOT NULL DEFAULT (0)     ,
                    [is_active]            bit NOT NULL DEFAULT (1)
                )
            ;
            CREATE TABLE [course133].[course_category]
                (
                    [category_code]    varchar(30) PRIMARY KEY      ,
                    [category_name_th] nvarchar(150) NOT NULL UNIQUE,
                    [is_active]        bit NOT NULL DEFAULT (1)
                )
            ;
            CREATE TABLE [course133].[user_role_assignment]
                (
                    [role_assignment_id] bigint IDENTITY PRIMARY KEY                                                         ,
                    [pers_id]            int NOT NULL                                                                        ,
                    [role_id]            smallint NOT NULL                                                                   ,
                    [is_active]          bit NOT NULL DEFAULT (1)                                                            ,
                    CONSTRAINT [FK_role_assignment_role] FOREIGN KEY ([role_id]) REFERENCES [course133].[app_role]([role_id]),
                    CONSTRAINT [UQ_role_assignment] UNIQUE ([pers_id], [role_id])
                )
            ;
            CREATE TABLE [course133].[course_request]
                (
                    [request_id]             bigint IDENTITY PRIMARY KEY                                                                                                                                                                                                                                    ,
                    [request_no]             varchar(30) NOT NULL UNIQUE                                                                                                                                                                                                                                    ,
                    [requester_pers_id]      int NOT NULL                                                                                                                                                                                                                                                   ,
                    [target_dept_id]         int NOT NULL                                                                                                                                                                                                                                                   ,
                    [course_name_th]         nvarchar(500) NOT NULL                                                                                                                                                                                                                                         ,
                    [course_name_en]         nvarchar(500) NOT NULL                                                                                                                                                                                                                                         ,
                    [status]                 varchar(40) NOT NULL DEFAULT ('PENDING_SIGNED_DOCUMENT')                                                                                                                                                                                                                         ,
                    [submitted_at]           datetime2 NULL                                                                                                                                                                                                                                                 ,
                    [unsigned_pdf_downloaded_at] datetime2 NULL                                                                                                                                                                                                                                             ,
                    [created_at]             datetime2 NOT NULL DEFAULT (SYSDATETIME())                                                                                                                                                                                                                     ,
                    [updated_at]             datetime2 NOT NULL DEFAULT (SYSDATETIME())                                                                                                                                                                                                                     ,
                    [project_name]           nvarchar(500) NOT NULL                                                                                                                                                                                                                                         ,
                    [project_type_code]      varchar(30) NOT NULL                                                                                                                                                                                                                                           ,
                    [project_other]          nvarchar(500) NULL                                                                                                                                                                                                                                             ,
                    [coordinator_first_name] nvarchar(100) NOT NULL                                                                                                                                                                                                                                         ,
                    [coordinator_last_name]  nvarchar(100) NOT NULL                                                                                                                                                                                                                                         ,
                    [coordinator_position]   nvarchar(200) NOT NULL                                                                                                                                                                                                                                         ,
                    [coordinator_phone]      varchar(50) NOT NULL                                                                                                                                                                                                                                           ,
                    [coordinator_email]      varchar(254) NOT NULL                                                                                                                                                                                                                                          ,
                    [category_code]          varchar(30) NOT NULL                                                                                                                                                                                                                                           ,
                    [category_other]         nvarchar(500) NULL                                                                                                                                                                                                                                             ,
                    [course_description]     nvarchar(max) NOT NULL                                                                                                                                                                                                                                         ,
                    [learning_mode]          nvarchar(100) NOT NULL                                                                                                                                                                                                                                         ,
                    [activity_round]         nvarchar(500) NULL                                                                                                                                                                                                                                             ,
                    [activity_phase]         nvarchar(250) NULL                                                                                                                                                                                                                                             ,
                    [starts_on]              date NULL                                                                                                                                                                                                                                                      ,
                    [ends_on]                date NULL                                                                                                                                                                                                                                                      ,
                    [enrollment_method]      nvarchar(100) NOT NULL                                                                                                                                                                                                                                         ,
                    [enrollment_other]       nvarchar(500) NULL                                                                                                                                                                                                                                             ,
                    [expected_students]      int NOT NULL                                                                                                                                                                                                                                                   ,
                    [course_id]              varchar(100) NULL                                                                                                                                                                                                                                              ,
                    [recorded_by_pers_id]    int NULL                                                                                                                                                                                                                                                       ,
                    CONSTRAINT [FK_request_project_type] FOREIGN KEY ([project_type_code]) REFERENCES [course133].[project_type]([project_type_code])                                                                                                                                                       ,
                    CONSTRAINT [FK_request_category] FOREIGN KEY ([category_code]) REFERENCES [course133].[course_category]([category_code])                                                                                                                                                                ,
                    CONSTRAINT [CK_request_status] CHECK ([status] IN ('PENDING_SIGNED_DOCUMENT','UNDER_OFFICER_REVIEW','RETURNED_FOR_REVISION','PENDING_APPROVAL','REJECTED','PENDING_COURSE_ID','COURSE_ID_RECORDED'))                                                                            ,
                    CONSTRAINT [CK_request_students] CHECK ([expected_students] > 0)                                                                                                                                                                                                                        ,
                    CONSTRAINT [CK_request_learning_period] CHECK ([learning_mode] IN (N'เปิดแบบตามวงรอบ (Phase/Batch-based)', N'แบบเปิดตามกรอบระยะเวลาของโครงการ (Event / Project-based)') AND [starts_on] IS NOT NULL AND [ends_on] IS NOT NULL AND [ends_on] >= [starts_on]),
                    CONSTRAINT [CK_request_activity_details] CHECK ([learning_mode] <> N'เปิดแบบตามวงรอบ (Phase/Batch-based)' OR (NULLIF(LTRIM(RTRIM([activity_round])), N'') IS NOT NULL AND NULLIF(LTRIM(RTRIM([activity_phase])), N'') IS NOT NULL)),
                    CONSTRAINT [CK_request_project_other] CHECK ([project_type_code]                                                                                         <> 'OTHER' OR NULLIF(LTRIM(RTRIM([project_other])), N'') IS NOT NULL)                                                          ,
                    CONSTRAINT [CK_request_category_other] CHECK ([category_code]                                                                                            <> 'OTHER' OR NULLIF(LTRIM(RTRIM([category_other])), N'') IS NOT NULL)                                                         ,
                    CONSTRAINT [CK_request_enrollment_other] CHECK ([enrollment_method]                                                                                      <> N'อื่น ๆ (ระบุ)' OR NULLIF(LTRIM(RTRIM([enrollment_other])), N'') IS NOT NULL)                                              ,
                    CONSTRAINT [CK_request_course_id] CHECK (([status] = 'COURSE_ID_RECORDED' AND [course_id] IS NOT NULL AND [recorded_by_pers_id] IS NOT NULL) OR [status] <> 'COURSE_ID_RECORDED')
                )
            ;
            CREATE TABLE [course133].[course_instructor]
                (
                    [instructor_id]    bigint IDENTITY PRIMARY KEY,
                    [request_id]       bigint NOT NULL            ,
                    [pers_id]          int NULL                   ,
                    [instructor_name]  nvarchar(300) NOT NULL     ,
                    [instructor_email] varchar(254) NOT NULL      ,
                    CONSTRAINT [FK_instructor_request] FOREIGN KEY ([request_id]) REFERENCES [course133].[course_request]([request_id]) ON
                    DELETE
                        CASCADE );
            CREATE TABLE [course133].[course_document]
                (
                    [document_id]         bigint IDENTITY PRIMARY KEY                                                              ,
                    [request_id]          bigint NOT NULL                                                                          ,
                    [document_type]       varchar(40) NOT NULL                                                                     ,
                    [storage_key]         varchar(1000) NOT NULL UNIQUE                                                            ,
                    [original_filename]   nvarchar(500) NOT NULL                                                                   ,
                    [mime_type]           varchar(100) NOT NULL                                                                    ,
                    [file_size_bytes]     bigint NOT NULL                                                                          ,
                    [uploaded_by_pers_id] int NOT NULL                                                                             ,
                    [uploaded_at]         datetime2 NOT NULL DEFAULT (SYSDATETIME())                                               ,
                    [roster_acknowledged_at] datetime2 NULL                                                                        ,
                    [roster_acknowledged_by_pers_id] int NULL                                                                      ,
                    CONSTRAINT [CK_document_type] CHECK ([document_type] IN ('GENERATED_FORM','SIGNED_FORM','ADDITIONAL_DOCUMENT','STUDENT_ROSTER')),
                    CONSTRAINT [CK_signed_pdf] CHECK ([document_type]      <> 'SIGNED_FORM' OR [mime_type] = 'application/pdf')    ,
                    CONSTRAINT [CK_document_size] CHECK ([file_size_bytes] > 0)                                                    ,
                    CONSTRAINT [FK_document_request] FOREIGN KEY ([request_id]) REFERENCES [course133].[course_request]([request_id]) ON
                    DELETE
                        CASCADE );
            CREATE TABLE [course133].[officer_review]
                (
                    [officer_review_id] bigint IDENTITY PRIMARY KEY                                                                                                                                          ,
                    [request_id]        bigint NOT NULL                                                                                                                                                      ,
                    [officer_pers_id]   int NOT NULL                                                                                                                                                         ,
                    [decision]          varchar(20) NOT NULL                                                                                                                                                 ,
                    [return_reason]     nvarchar(max) NULL                                                                                                                                                   ,
                    [reviewed_at]       datetime2 NOT NULL DEFAULT (SYSDATETIME())                                                                                                                           ,
                    CONSTRAINT [CK_officer_decision] CHECK ([decision] IN ('PASSED','RETURNED'))                                                                                                             ,
                    CONSTRAINT [CK_officer_return_reason] CHECK (([decision] = 'PASSED' AND [return_reason] IS NULL) OR ([decision] = 'RETURNED' AND NULLIF(LTRIM(RTRIM([return_reason])), N'') IS NOT NULL)),
                    CONSTRAINT [FK_officer_review_request] FOREIGN KEY ([request_id]) REFERENCES [course133].[course_request]([request_id])
                )
            ;
            CREATE TABLE [course133].[course_approval]
                (
                    [approval_id]      bigint IDENTITY PRIMARY KEY                                                                     ,
                    [request_id]       bigint NOT NULL                                                                                 ,
                    [approver_pers_id] int NOT NULL                                                                                    ,
                    [decision]         varchar(20) NOT NULL                                                                            ,
                    [comment]          nvarchar(max) NULL                                                                              ,
                    [decided_at]       datetime2 NOT NULL DEFAULT (SYSDATETIME())                                                      ,
                    CONSTRAINT [CK_approval_decision] CHECK ([decision] IN ('APPROVED','REJECTED','RETURNED'))                         ,
                    CONSTRAINT [CK_approval_reason] CHECK ([decision] = 'APPROVED' OR NULLIF(LTRIM(RTRIM([comment])), N'') IS NOT NULL),
                    CONSTRAINT [FK_approval_request] FOREIGN KEY ([request_id]) REFERENCES [course133].[course_request]([request_id])
                )
            ;
            CREATE TABLE [course133].[request_status_history]
                (
                    [history_id]         bigint IDENTITY PRIMARY KEY                                                     ,
                    [request_id]         bigint NOT NULL                                                                 ,
                    [status]             varchar(40) NOT NULL                                                            ,
                    [changed_by_pers_id] int NULL                                                                        ,
                    [change_source]      varchar(30) NOT NULL                                                            ,
                    [changed_at]         datetime2 NOT NULL DEFAULT (SYSDATETIME())                                      ,
                    CONSTRAINT [CK_history_source] CHECK ([change_source] IN ('REQUESTER','OFFICER','APPROVER','SYSTEM')),
                    CONSTRAINT [FK_history_request] FOREIGN KEY ([request_id]) REFERENCES [course133].[course_request]([request_id]) ON
                    DELETE
                        CASCADE );
            CREATE TABLE [course133].[notification_outbox]
                (
                    [notification_id]   bigint IDENTITY PRIMARY KEY                                                       ,
                    [request_id]        bigint NOT NULL                                                                   ,
                    [notification_type] varchar(50) NOT NULL                                                              ,
                    [recipient_pers_id] int NULL                                                                          ,
                    [recipient_email]   varchar(254) NOT NULL                                                             ,
                    [delivery_status]   varchar(20) NOT NULL DEFAULT ('PENDING')                                          ,
                    [attempt_count]     int NOT NULL DEFAULT (0)                                                          ,
                    [sent_at]           datetime2 NULL                                                                    ,
                    [last_error]        nvarchar(max) NULL                                                                ,
                    [created_at]        datetime2 NOT NULL DEFAULT (SYSDATETIME())                                        ,
                    CONSTRAINT [CK_notification_status] CHECK ([delivery_status] IN ('PENDING','SENDING','SENT','FAILED')),
                    CONSTRAINT [CK_notification_attempt] CHECK ([attempt_count] >= 0)                                     ,
                    CONSTRAINT [FK_notification_request] FOREIGN KEY ([request_id]) REFERENCES [course133].[course_request]([request_id]) ON
                    DELETE
                        CASCADE );
            CREATE INDEX [IX_request_target_status]
            ON [course133].[course_request]
                (
                    [target_dept_id],
                    [status]        ,
                    [created_at] DESC
                )
            ;
            CREATE INDEX [IX_document_request]
            ON [course133].[course_document]
                (
                    [request_id]   ,
                    [document_type],
                    [uploaded_at] DESC
                )
            ;
            CREATE INDEX [IX_history_request]
            ON [course133].[request_status_history]
                (
                    [request_id],
                    [changed_at]
                )
            ;
            CREATE INDEX [IX_notification_pending]
            ON [course133].[notification_outbox]
                (
                    [delivery_status],
                    [created_at]
                )
            WHERE [delivery_status] IN
                (
                    'PENDING',
                    'FAILED'
                )
            ;
            INSERT INTO [course133].[app_role]
                (
                    [role_code],
                    [role_name_th]
                )
            VALUES
                (
                    'REQUESTER',
                    N'ผู้ยื่นคำร้อง'
                )
                ,
                (
                    'OFFICER',
                    N'เจ้าหน้าที่ตรวจสอบ'
                )
                ,
                (
                    'APPROVER',
                    N'ผู้มีอำนาจอนุมัติ'
                )
            ;
            INSERT INTO [course133].[project_type]
                (
                    [project_type_code]   ,
                    [project_type_name_th],
                    [requires_detail]
                )
            VALUES
                (
                    'INCOME_SERVICE'                  ,
                    N'โครงการบริการวิชาการแบบมีรายได้',
                    0
                )
                ,
                (
                    'NO_INCOME_SERVICE'                  ,
                    N'โครงการบริการวิชาการแบบไม่มีรายได้',
                    0
                )
                ,
                (
                    'RESEARCH'     ,
                    N'โครงการวิจัย',
                    0
                )
                ,
                (
                    'OTHER'         ,
                    N'อื่น ๆ (ระบุ)',
                    1
                )
            ;
            INSERT INTO [course133].[course_category]
                (
                    [category_code],
                    [category_name_th]
                )
            VALUES
                (
                    'HEALTH',
                    N'การแพทย์และสุขภาพ'
                )
                ,
                (
                    'ADMIN_FINANCE',
                    N'การบริหาร/การเงิน'
                )
                ,
                (
                    'DIGITAL',
                    N'เทคโนโลยีดิจิทัล'
                )
                ,
                (
                    'EDUCATION',
                    N'การศึกษา'
                )
                ,
                (
                    'LANGUAGE',
                    N'ภาษาและการสื่อสาร'
                )
                ,
                (
                    'OTHER',
                    N'อื่น ๆ (ระบุ)'
                )
            ;
            EXEC(N'CREATE VIEW [course133].[officer_work_queue] AS
SELECT r.* FROM [course133].[course_request] r
WHERE r.[status] = ''UNDER_OFFICER_REVIEW'' AND EXISTS (
 SELECT 1 FROM [course133].[course_document] d WHERE d.[request_id] = r.[request_id]
 AND d.[document_type] = ''SIGNED_FORM'' AND d.[mime_type] = ''application/pdf'')');
