# Pusen01 — Housekeeping for Student (Requirement Spec)

Version: 1.0 (final, 2026-08-23)

## 1. Purpose

In the Housekeeping page, add a **"Housekeeping for Student"** button.

It finds students who have left the university and whose records have been untouched for over 3 years, then **permanently deletes** their SEN case data, attached documents, and related records — after writing a full audit log (the log is the backup; **no archiving, no recovery**).

## 2. Selection criteria

A student qualifies when **both** conditions are true:

- `tblStudent.Student_Status` IN (`'COMPLETED'`, `'LEFT'`, `'PASSED AWAY'`)
- `tblStudent.updated_at` is **strictly older than 3 years**: `updated_at < now() - 3 years` (compared in UTC)

## 3. Scope of deletion (per qualifying student)

- **All SEN cases**: `tblSEN` WHERE `Student_Id` = student (a student may have multiple cases — delete all)
- **All physical documents** stored on the server (see §5.3)
- **All SEN attachments**: `tblSEN_Doc` (all rows belonging to the student's SEN cases)
- **Advisor assignments**: `tblAdvisor_Student` WHERE `Student_Id` = student
- **Subject registrations**: `tblStudent_Reg` WHERE `Student_Id` = student
- **The student record**: `tblStudent` WHERE `Student_Id` = student

## 4. NOT deleted

- **Log / audit tables** — `tblSEN_Log`, `tblLogin_Log`, `tblImport_Log`, `tblImport_Failed_Log`, and the new `tblHK_*` log tables.
- **`tblEmail_List` / `tblEmail_SEN`** — kept as historical email records (decision deferred). Note: any UI joining these to `tblSEN` must tolerate missing SENs after housekeeping runs.

## 5. Procedure — per student, one student at a time

### 5.1 Read phase
Load into memory: the student row, all their SEN cases, all their docs, advisor rows, registration rows. Build the log rows.

### 5.2 Log phase (backup) — commit first
Insert into:
- `tblHK_Student_Log` — 1 row (its `Id` becomes the `HK_Run_Id` for this student)
- `tblHK_SEN_Log` — 1 row per SEN case
- `tblHK_SEN_Doc_Log` — 1 row per document

**Commit.** The log is written before anything is deleted, and survives regardless of what happens next.

### 5.3 Delete physical files
For each doc, delete the file at its resolved path (`Doc_Path`).

Rules:
- File **already missing** → non-fatal; record "file not found" in `Remarks`; continue.
- **`unlink` error** (permissions, etc.) → **abort this student entirely**: no records are deleted, remaining files stay, the log rows remain with a "FILE DELETE FAILED" remark.
- `Doc_Filename` NULL/empty → skip; remark "no file".
- Also purge staging leftovers for the deleted cases: `sen_docs/staging/{SEN_Id}*` and `sen_docs/staging/{SEN_Id}.meta.json`.

### 5.4 Delete records — one DB transaction per student, in this order
1. `tblSEN_Doc`
2. `tblSEN`
3. `tblStudent_Reg`
4. `tblAdvisor_Student`
5. `tblStudent`

Commit.

### 5.5 Failure handling
- If the record-delete transaction fails → rollback. Files may already be gone and log rows exist; the student still qualifies, so the run can be retried. **Skip students already present in `tblHK_Student_Log`** to avoid double processing (also protects against two concurrent runs).
- One student's failure must not stop the batch — process the remaining students and report failures.

### 5.6 Summary
Report: students processed, SEN cases deleted, docs deleted, files deleted / missing / failed, errors.

## 6. Log tables (final definitions)

### tblHK_Student_Log
| Column | Type | Notes |
|---|---|---|
| Id | int AUTO_INCREMENT | PK |
| Student_Id | varchar(12) | |
| Student_Name_Eng | varchar(30) | copied from tblStudent |
| Student_Name_Chn | varchar(5) | copied from tblStudent |
| Student_Status | varchar(15) | copied from tblStudent |
| Student_created_at | datetime | copied from tblStudent |
| Student_updated_at | datetime | the value that qualified the row |
| Remarks | varchar(255) | reason + file outcomes |
| Delete_At | datetime | UTC |
| Delete_By | varchar(20) | logged-in Staff_Id |

Index: `Student_Id`

### tblHK_SEN_Log
| Column | Type | Notes |
|---|---|---|
| Id | int AUTO_INCREMENT | PK |
| HK_Run_Id | int | → tblHK_Student_Log.Id |
| SEN_Id | varchar(10) | |
| Student_Id | varchar(12) | |
| Remarks | varchar(255) | |
| Delete_At | datetime | UTC |
| Delete_By | varchar(20) | logged-in Staff_Id |

Indexes: `HK_Run_Id`, `Student_Id`

### tblHK_SEN_Doc_Log
| Column | Type | Notes |
|---|---|---|
| Id | int AUTO_INCREMENT | PK |
| HK_Run_Id | int | → tblHK_Student_Log.Id |
| SEN_Id | varchar(10) | |
| Doc_Seq | int | |
| Doc_Path | varchar(255) | resolved server path at delete time |
| Doc_Filename | varchar(255) | |
| Doc_Filename_Original | varchar(255) | |
| Remarks | varchar(255) | |
| Delete_At | datetime | UTC |
| Delete_By | varchar(20) | logged-in Staff_Id |

Index: `HK_Run_Id`

## 7. Technical conventions

- **Collation**: `utf8mb4_0900_ai_ci` on all three tables (matches `tblStudent`; `.env` already has `PUSEN_DB_COLLATION=utf8mb4_0900_ai_ci` so Laravel-created tables inherit it).
- **Timestamps**: `Delete_At` stored UTC (Carbon `now()`), converted for display — same convention as `tblLogin_Log`.
- **No FKs** on the log tables; logical link via `HK_Run_Id`.
- **Access**: SA only (Housekeeping is already SA-gated). Disable the button while a run is in progress (double-click protection).
- **Remarks content**: standardized, e.g. `HK delete: N SEN case(s), M doc(s); file missing: <name>`.

## 8. Testing notes

- As of 2026-08-23 there are **0 qualifying students** (13 COMPLETED, none stale 3y; no LEFT/PASSED AWAY). Verify the flow with temporarily backdated `updated_at` on test rows and/or factory-based unit tests.
- Test cases: happy path; multiple SEN cases per student (up to 6); student with no SEN/docs; file already missing; unlink failure; DB transaction failure; already-logged skip; status not in list; updated_at fresh.
