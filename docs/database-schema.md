# Database Schema

Captured from the shared self-hosted Supabase/Postgres instance on 2026-08-12
using Ninja Mode direct `psql`.

The shared database contains multiple projects. The FAQ autoresponder currently
appears to use only:

- `public.faqs`
- `public.faq_approved_responses`
- `public.match_faqs_json(...)`
- `public.upsert_faq_approved_response(...)`

Other vector tables such as `chapter_chunks`, `chapters`, and
`chapters_for_vector` belong to the book summaries app and should not be treated
as part of the FAQ autoresponder.

## Existing Tables

### `public.faqs`

| Column | Type | Nullable | Default |
| --- | --- | --- | --- |
| `id` | `uuid` | no | `gen_random_uuid()` |
| `question` | `text` | yes | |
| `answer` | `text` | yes | |
| `created_at` | `timestamp with time zone` | yes | `now()` |
| `embedding` | `vector` | yes | |

Current row count: `79`.

All populated FAQ embeddings are 1536 dimensions.

Indexes/constraints:

- `faqs_pkey1`: primary key on `id`

### `public.faq_approved_responses`

| Column | Type | Nullable | Default |
| --- | --- | --- | --- |
| `id` | `uuid` | no | `gen_random_uuid()` |
| `faq_id` | `uuid` | no | |
| `approved_response` | `text` | no | |
| `match_similarity` | `double precision` | yes | |
| `created_at` | `timestamp with time zone` | yes | `now()` |
| `updated_at` | `timestamp with time zone` | yes | `now()` |

Current row count: `15`.

Indexes/constraints:

- `faq_approved_responses_pkey`: primary key on `id`
- `faq_approved_responses_faq_id_key`: unique on `faq_id`
- `faq_approved_responses_faq_id_idx`: btree index on `faq_id`
- `faq_approved_responses_faq_id_fkey`: `faq_id` references `faqs(id)` on delete cascade

## Existing Functions

### `public.match_faqs_json(match_count integer, query_embedding vector)`

Returns:

```sql
table(
  id uuid,
  question text,
  answer text,
  similarity double precision,
  approved_response text
)
```

Definition:

```sql
select
  f.id,
  f.question,
  f.answer,
  1 - (f.embedding <=> query_embedding) as similarity,
  far.approved_response
from public.faqs f
left join public.faq_approved_responses far
  on far.faq_id = f.id
order by f.embedding <=> query_embedding
limit match_count;
```

### `public.upsert_faq_approved_response(...)`

Arguments:

```sql
p_faq_id uuid,
p_approved_response text,
p_match_similarity double precision default null
```

Behavior:

- inserts or updates one approved response per `faq_id`
- updates `approved_response`, `match_similarity`, and `updated_at`
- returns the `faq_approved_responses` row

## Discovery Queries

Useful outputs:

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
