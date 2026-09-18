-- SQL Server / SSMS
-- Server: 10.1.5.199
-- Database: saladb
-- Read-only: inspects the two snapshot tables and returns up to 1,000 rows from each.

USE [saladb];
SET NOCOUNT ON;

-- Confirm that the query is running against the intended server/database.
SELECT
    @@SERVERNAME AS [server_name],
    DB_NAME() AS [database_name];

-- Column structure for dbo.persons and dbo.departments.
SELECT
    s.name AS [schema_name],
    t.name AS [table_name],
    c.column_id AS [column_order],
    c.name AS [column_name],
    ty.name AS [data_type],
    CASE
        WHEN ty.name IN ('nvarchar', 'nchar') AND c.max_length > 0
            THEN c.max_length / 2
        WHEN ty.name IN ('varchar', 'char', 'varbinary', 'binary')
            THEN c.max_length
        ELSE NULL
    END AS [max_length],
    c.precision AS [numeric_precision],
    c.scale AS [numeric_scale],
    c.is_nullable,
    c.is_identity,
    dc.definition AS [default_value]
FROM sys.tables AS t
JOIN sys.schemas AS s
    ON s.schema_id = t.schema_id
JOIN sys.columns AS c
    ON c.object_id = t.object_id
JOIN sys.types AS ty
    ON ty.user_type_id = c.user_type_id
LEFT JOIN sys.default_constraints AS dc
    ON dc.parent_object_id = c.object_id
   AND dc.parent_column_id = c.column_id
WHERE s.name = N'dbo'
  AND t.name IN (N'persons', N'departments')
ORDER BY t.name, c.column_id;

-- Snapshot rows from 199.
SELECT TOP (1000) *
FROM [saladb].[dbo].[persons];

SELECT TOP (1000) *
FROM [saladb].[dbo].[departments];

