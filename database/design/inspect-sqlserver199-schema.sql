-- SQL Server 2016+ / SSMS
-- Read-only schema inspection. This script does not select business rows
-- and does not INSERT, UPDATE, DELETE, ALTER, DROP or CREATE anything.
-- In SSMS, select the real database used by server 199 before running.

SET NOCOUNT ON;

-- 1. Confirm the selected server and database.
SELECT
    @@SERVERNAME AS server_name,
    DB_NAME() AS database_name,
    CAST(SERVERPROPERTY('ProductVersion') AS nvarchar(128)) AS product_version,
    CAST(SERVERPROPERTY('Edition') AS nvarchar(128)) AS edition;

-- 2. User tables.
SELECT
    s.name AS schema_name,
    t.name AS table_name
FROM sys.tables AS t
JOIN sys.schemas AS s ON s.schema_id = t.schema_id
WHERE t.is_ms_shipped = 0
ORDER BY s.name, t.name;

-- 3. Columns, SQL types, nullability, identity, computed columns and defaults.
SELECT
    s.name AS schema_name,
    tb.name AS table_name,
    c.column_id AS ordinal_position,
    c.name AS column_name,
    ty.name AS data_type,
    CASE
        WHEN ty.name IN ('nvarchar', 'nchar') AND c.max_length > 0 THEN c.max_length / 2
        WHEN ty.name IN ('varchar', 'char', 'varbinary', 'binary') THEN c.max_length
        ELSE NULL
    END AS character_maximum_length,
    c.precision AS numeric_precision,
    c.scale AS numeric_scale,
    c.is_nullable,
    c.is_identity,
    c.is_computed,
    dc.definition AS default_definition,
    c.collation_name
FROM sys.tables AS tb
JOIN sys.schemas AS s ON s.schema_id = tb.schema_id
JOIN sys.columns AS c ON c.object_id = tb.object_id
JOIN sys.types AS ty
    ON ty.user_type_id = c.user_type_id
LEFT JOIN sys.default_constraints AS dc
    ON dc.parent_object_id = c.object_id
   AND dc.parent_column_id = c.column_id
WHERE tb.is_ms_shipped = 0
ORDER BY s.name, tb.name, c.column_id;

-- 4. Primary keys and unique constraints.
SELECT
    s.name AS schema_name,
    tb.name AS table_name,
    kc.name AS constraint_name,
    kc.type_desc AS constraint_type,
    ic.key_ordinal,
    c.name AS column_name,
    ic.is_descending_key
FROM sys.key_constraints AS kc
JOIN sys.tables AS tb ON tb.object_id = kc.parent_object_id
JOIN sys.schemas AS s ON s.schema_id = tb.schema_id
JOIN sys.index_columns AS ic
    ON ic.object_id = kc.parent_object_id
   AND ic.index_id = kc.unique_index_id
JOIN sys.columns AS c
    ON c.object_id = ic.object_id
   AND c.column_id = ic.column_id
ORDER BY s.name, tb.name, kc.name, ic.key_ordinal;

-- 5. Foreign keys.
SELECT
    ps.name AS parent_schema,
    pt.name AS parent_table,
    fk.name AS foreign_key_name,
    pc.name AS parent_column,
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

-- 6. Check constraints.
SELECT
    s.name AS schema_name,
    tb.name AS table_name,
    cc.name AS constraint_name,
    cc.definition
FROM sys.check_constraints AS cc
JOIN sys.tables AS tb ON tb.object_id = cc.parent_object_id
JOIN sys.schemas AS s ON s.schema_id = tb.schema_id
ORDER BY s.name, tb.name, cc.name;

-- 7. Indexes not created by a primary-key or unique constraint.
SELECT
    s.name AS schema_name,
    tb.name AS table_name,
    i.name AS index_name,
    i.is_unique,
    i.type_desc,
    ic.key_ordinal,
    c.name AS column_name,
    ic.is_included_column,
    ic.is_descending_key,
    i.filter_definition
FROM sys.indexes AS i
JOIN sys.tables AS tb ON tb.object_id = i.object_id
JOIN sys.schemas AS s ON s.schema_id = tb.schema_id
JOIN sys.index_columns AS ic
    ON ic.object_id = i.object_id
   AND ic.index_id = i.index_id
JOIN sys.columns AS c
    ON c.object_id = ic.object_id
   AND c.column_id = ic.column_id
WHERE tb.is_ms_shipped = 0
  AND i.name IS NOT NULL
  AND i.is_primary_key = 0
  AND i.is_unique_constraint = 0
ORDER BY s.name, tb.name, i.name, ic.key_ordinal, ic.index_column_id;

-- 8. Views and their columns. Definition is included only when the account
-- has VIEW DEFINITION permission.
SELECT
    s.name AS schema_name,
    v.name AS view_name,
    c.column_id AS ordinal_position,
    c.name AS column_name,
    ty.name AS data_type,
    c.max_length,
    c.precision,
    c.scale,
    c.is_nullable,
    OBJECT_DEFINITION(v.object_id) AS view_definition
FROM sys.views AS v
JOIN sys.schemas AS s ON s.schema_id = v.schema_id
JOIN sys.columns AS c ON c.object_id = v.object_id
JOIN sys.types AS ty ON ty.user_type_id = c.user_type_id
WHERE v.is_ms_shipped = 0
ORDER BY s.name, v.name, c.column_id;

