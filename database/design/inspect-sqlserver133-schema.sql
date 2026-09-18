-- SQL Server / SSMS: read-only inspection of the real Server 133 database.
-- This script does not read business rows and does not modify any object.
USE [academy_db1447];
SET NOCOUNT ON;

-- 1. Confirm the server and database selected in SSMS.
SELECT
    @@SERVERNAME AS server_name,
    DB_NAME() AS database_name,
    CAST(SERVERPROPERTY('ProductVersion') AS nvarchar(128)) AS product_version;

-- 2. User tables.
SELECT
    s.name AS schema_name,
    t.name AS table_name
FROM sys.tables AS t
JOIN sys.schemas AS s ON s.schema_id = t.schema_id
WHERE t.is_ms_shipped = 0
ORDER BY s.name, t.name;

-- 3. Columns and SQL Server types.
SELECT
    s.name AS schema_name,
    t.name AS table_name,
    c.column_id AS column_order,
    c.name AS column_name,
    ty.name AS data_type,
    CASE
        WHEN ty.name IN ('nvarchar', 'nchar') AND c.max_length > 0 THEN c.max_length / 2
        WHEN ty.name IN ('varchar', 'char', 'varbinary', 'binary') THEN c.max_length
        ELSE NULL
    END AS max_length,
    c.precision AS numeric_precision,
    c.scale AS numeric_scale,
    c.is_nullable,
    c.is_identity,
    dc.definition AS default_value
FROM sys.tables AS t
JOIN sys.schemas AS s ON s.schema_id = t.schema_id
JOIN sys.columns AS c ON c.object_id = t.object_id
JOIN sys.types AS ty ON ty.user_type_id = c.user_type_id
LEFT JOIN sys.default_constraints AS dc
    ON dc.parent_object_id = c.object_id
   AND dc.parent_column_id = c.column_id
WHERE t.is_ms_shipped = 0
ORDER BY s.name, t.name, c.column_id;

-- 4. Primary keys and unique constraints.
SELECT
    s.name AS schema_name,
    t.name AS table_name,
    kc.name AS constraint_name,
    kc.type_desc AS constraint_type,
    ic.key_ordinal,
    c.name AS column_name
FROM sys.key_constraints AS kc
JOIN sys.tables AS t ON t.object_id = kc.parent_object_id
JOIN sys.schemas AS s ON s.schema_id = t.schema_id
JOIN sys.index_columns AS ic
    ON ic.object_id = kc.parent_object_id
   AND ic.index_id = kc.unique_index_id
JOIN sys.columns AS c
    ON c.object_id = ic.object_id
   AND c.column_id = ic.column_id
ORDER BY s.name, t.name, kc.name, ic.key_ordinal;

-- 5. Foreign keys that physically exist inside academy_db1447.
SELECT
    ps.name AS parent_schema,
    pt.name AS parent_table,
    pc.name AS parent_column,
    fk.name AS foreign_key_name,
    rs.name AS referenced_schema,
    rt.name AS referenced_table,
    rc.name AS referenced_column,
    fk.delete_referential_action_desc AS on_delete,
    fk.update_referential_action_desc AS on_update
FROM sys.foreign_keys AS fk
JOIN sys.foreign_key_columns AS fkc
    ON fkc.constraint_object_id = fk.object_id
JOIN sys.tables AS pt ON pt.object_id = fk.parent_object_id
JOIN sys.schemas AS ps ON ps.schema_id = pt.schema_id
JOIN sys.columns AS pc
    ON pc.object_id = fkc.parent_object_id
   AND pc.column_id = fkc.parent_column_id
JOIN sys.tables AS rt ON rt.object_id = fk.referenced_object_id
JOIN sys.schemas AS rs ON rs.schema_id = rt.schema_id
JOIN sys.columns AS rc
    ON rc.object_id = fkc.referenced_object_id
   AND rc.column_id = fkc.referenced_column_id
ORDER BY ps.name, pt.name, fk.name, fkc.constraint_column_id;

-- 6. Check constraints used by statuses and business rules.
SELECT
    s.name AS schema_name,
    t.name AS table_name,
    cc.name AS constraint_name,
    cc.definition
FROM sys.check_constraints AS cc
JOIN sys.tables AS t ON t.object_id = cc.parent_object_id
JOIN sys.schemas AS s ON s.schema_id = t.schema_id
ORDER BY s.name, t.name, cc.name;
