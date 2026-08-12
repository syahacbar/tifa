# Teacher Data Tool v1

`TeacherDataTool` is the only bounded adapter from conversational teacher analytics to `TeacherAnalyticsService`. It accepts `count`, `breakdown`, or `ranking`; entities `teacher_assignment`/`teacher_identity`; metrics `assignment_count`/`unique_teacher_count`; and only these filters: education level, district, school ID, employment status, PTK type/position, and education.

`group_by` is limited to district, school, education level, employment status, PTK type/position, or education. Ranking requires `group_by` and `top_n` 1–20. The tool returns v1 status, context, authoritative provenance, and machine-readable school-resolution quality metadata.

It never accepts SQL, table names, arbitrary fields, row listing, personal search, or NIK/NIP/NUPTK/phone/birth data. Database facts are computed only by `TeacherAnalyticsService` using the latest authoritative batch.

```json
{"version":"v1","operation":"count","entity":"teacher_identity","metric":"unique_teacher_count","filters":{"education_level":"SD","district":null,"school_id":null,"employment_status":"PPPK","ptk_type":null,"ptk_position":null,"education":null},"group_by":null,"top_n":null}
```
