# Database Schema

Schema capture is pending.

The existing Postgres/Supabase schema should be captured before scaffolding the
Laravel models. Useful outputs:

```sql
select
  table_schema,
  table_name,
  column_name,
  data_type,
  udt_name,
  is_nullable,
  column_default
from information_schema.columns
where table_schema = 'public'
order by table_name, ordinal_position;
```

Vector/RAG-specific discovery:

```sql
select
  table_schema,
  table_name,
  column_name,
  data_type,
  udt_name
from information_schema.columns
where table_schema not in ('pg_catalog', 'information_schema')
  and (
    table_name ilike '%faq%'
    or table_name ilike '%rag%'
    or table_name ilike '%embed%'
    or table_name ilike '%document%'
    or table_name ilike '%chunk%'
    or column_name ilike '%embedding%'
    or udt_name = 'vector'
  )
order by table_schema, table_name, ordinal_position;
```

Function/RPC discovery:

```sql
select
  n.nspname as schema,
  p.proname as function_name,
  pg_get_function_arguments(p.oid) as arguments,
  pg_get_function_result(p.oid) as result_type,
  pg_get_functiondef(p.oid) as definition
from pg_proc p
join pg_namespace n on n.oid = p.pronamespace
where n.nspname = 'public'
order by p.proname;
```
