# Student Import — Specification (Final)

Reference for the Student import function. Same SFTP flow, logging pattern, and all-or-nothing rule as the Subject import (see pusen01-import-spec.md). Differences: filename, file format, target table, validation rules.

## 1. UI — Data Import Screen

Left menu → Data Import opens a table, one row per function. The **Student List** row is active (Import button + latest file column); other functions are disabled.

**Latest file column (Student):**
- Show the newest un-imported file in SFTP `upload/` that matches the Student filename pattern (Subject/Staff files are ignored).
- "Newest" = highest YYYYMMDD in filename; tie-break by upload mtime.
- If `upload/` has no student file → show the last imported student file from `tblImport_Log` (FileType='STUDENT'), disable Import.
- After successful import the file moves to `processed/` → no longer "latest".

**Import button click flow:**
1. Load SFTP settings from `tblConfig_SFTP` (§2)
2. Connect to SFTP, read the latest student file
3. Run the pipeline (§4)
4. Show result dialog (§5)

## 2. SFTP Source (DB-driven)

Connection settings are not hard-coded — read from `tblConfig_SFTP` (single active row, `ORDER BY Id LIMIT 1`; if no row exists, the import page shows an error state and disables Import).

| Column | Purpose | Current value |
|---|---|---|
| Host | SFTP host name | `sftp-server` (Docker container, network pusen-net, host port 2222) |
| Port | SFTP port | 22 |
| Username | login user | `import` |
| Password | login password | `Import123` |
| Remote_Path | folder containing files to import | `upload` (chroot home /home/import) |
| Remarks | description | Pusen SFTP server (Docker container sftp-server) |

**Directories (derived from Remote_Path):**
- Import dir = `Remote_Path` (`upload`) — files picked from here.
- Archive dir = sibling of `Remote_Path` at the chroot root → `processed` (/home/import/processed, owned import:users). Files move here after successful import.

**Filename convention:** `sao_sen_srs_student_` + YYYYMMDD + `.csv`, matched case-insensitively: `^sao_sen_srs_student_(\d{8})\.csv$/i`

**CSV format (verified):** UTF-8, 9 columns, unquoted, no header, no BOM.

| Col | Field | Mapping | Notes |
|---|---|---|---|
| 1 | Student Id | tblStudent.Student_Id | 8 digits + one letter (A-Z), e.g. `25000001G` |
| 2 | English Name | tblStudent.Student_Name_Eng | varchar(30) |
| 3 | Chinese name | tblStudent.Student_Name_Chn | varchar(5) |
| 4 | Faculty | tblStudent.Faculty | varchar(10) |
| 5 | Department | tblStudent.Department | varchar(10) |
| 6 | Programme Code-Subcode | tblStudent.Prog_Sub_Code | varchar(10) |
| 7 | UNKNOWN | — | ignored (no empty check) |
| 8 | Programme Title | tblStudent.Prog_Title | varchar(60) |
| 9 | Fund Type | tblStudent.Fund_Type_Code | = tblFund_Type.Fund_Type_Code |

Primary key: `Student_Id` (Primary Key on tblStudent).

## 3. Logging Tables

**tblImport_Log** — one row per run: File_Name, FileType='STUDENT', CSV_Content (full raw file, MEDIUMTEXT), created_by = login user, updated_ip = request IP, Import_Status = NULL → Success/Failure. No count columns — counts come from the failed log (GROUP BY Import_Log_Id).

**tblImport_Failed_Log** — one row per CSV row: Import_Log_Id, File_Date (from filename, fallback import time), File_Name, FileType='STUDENT', Row_Content (raw row), Import_Status (Failure/Duplicated/Update), Remarks, Import_By = login user, timestamps.

## 4. Import Pipeline

1. Load SFTP settings from `tblConfig_SFTP`
2. Connect via phpseclib3, list `Remote_Path`, pick the latest file matching the student pattern (by YYYYMMDD in name, then mtime)
3. Read file, INSERT `tblImport_Log` (File_Name, FileType='STUDENT', CSV_Content, created_by, updated_ip) — status NULL
4. Parse CSV (str_getcsv per line; blank lines skipped; BOM stripped defensively)
5. Validate every row (order below, first hit wins) → write Failed_Log per row
6. Empty file / zero data rows → ABORT with "No data rows found", file stays in upload/
7. Any 'Failure' row? → ABORT: Import_Status='Failure', file STAYS in upload/, dialog = error count
8. Else → TRANSACTION (all-or-nothing): insert new + update changed
   - exception → rollback + Import_Status='Failure'
   - success → commit + Import_Status='Success', file → `processed/`

**Validation rules (per row, first failure wins):**

| # | Condition | Status | Remarks |
|---|---|---|---|
| a | Column count ≠ 9 | Failure | Incorrect number of columns |
| b | Any of the 8 used fields empty (cols 1,2,3,4,5,6,8,9) | Failure | One or more fields is empty |
| c | Any used field longer than its target column width | Failure | Field length exceeds column width (count characters via mb_strlen, not bytes) |
| d | Student Id not `^\d{8}[A-Z]$` (case-insensitive) | Failure | Student Id must be 8 digits + one letter (A-Z); legacy 7-digit IDs are excluded by design |
| e | Key already seen earlier in this file | Failure | Duplicated record in the same CSV file |
| f | Fund Type code not in tblFund_Type (CI) | Failure | Fund Type code not exist in tblFund_Type master table |
| g | Key exists + other fields identical | Duplicated | Same data already exists, no update occurred |
| h | Key exists + any field differs | Update | Information: key already exists, record will be updated |
| i | otherwise (new key) | — | eligible for INSERT |

- All fields trimmed before validation.
- Case-insensitive matching everywhere; stored values normalized: Student_Id letter → uppercase, Fund_Type → master casing.
- g/h are informational — don't block the import.

**Column widths (for rule c):** Student_Id 12 · Student_Name_Eng 30 · Student_Name_Chn 5 · Faculty 10 · Department 10 · Prog_Sub_Code 10 · Prog_Title 60 · Fund_Type_Code 1.

**Import (transaction, all-or-nothing) — runs only with zero Failure rows:**
- INSERT (i): Student_Id, Student_Name_Eng, Student_Name_Chn, Faculty, Department, Prog_Sub_Code, Prog_Title, Fund_Type_Code + updated_by=login user, updated_ip=request IP. **Student_Status is NOT in the CSV — omit the column so the table default 'ACTIVE' applies; never insert NULL.**
- UPDATE (g/h) by Primary Key: Student_Id, Student_Name_Eng, Student_Name_Chn, Faculty, Department, Prog_Sub_Code, Prog_Title, Fund_Type_Code, updated_by, updated_ip (updated_at auto). **Student_Status is never touched on update** (a withdrawn student stays withdrawn).
- One transaction: commit all or rollback all. Duplicated (g): no DB change.

## 5. Result Dialogs

- Success: "X inserted / Y updated / Z duplicated"
- Abort: "N error record(s) found. No records imported." — file stays in upload/
- Counts: `SELECT Import_Status, COUNT(*) FROM tblImport_Failed_Log WHERE Import_Log_Id=? GROUP BY Import_Status`; inserted count from transaction result.

## 6. Non-Functional

- Auth middleware; audit fields from Auth::user() (Staff_Id)
- SFTP settings from `tblConfig_SFTP` (DB), not hard-coded in code/env
- SFTP via phpseclib3 (hostname sftp-server, network pusen-net)
- Import button disabled while running; re-uploading same filename allowed (→ all Duplicated)
- Missing SFTP config row → error state, Import disabled
- Source CSV encoding: UTF-8 (confirmed)

## 7. Generic Template for Other Imports

Fill per function: filename prefix / FileType / target table / primary key / master lookups / stored columns / ignored columns. Same SFTP flow (settings from tblConfig_SFTP), same log tables, same all-or-nothing + dialogs.

## 8. Assumptions (flagged, unconfirmed)

1. tblStudent has no `created_by` → stamp `updated_by`/`updated_ip` on insert too (importer trace). Say so if inserts should stay blank.
2. Re-import of identical filename allowed; add a File_Name pre-check on `tblImport_Log` if it must be rejected.
3. `processed/` = sibling of Remote_Path at chroot root (/home/import/processed), owned import:users.
4. `tblConfig_SFTP` holds a single active row; add an Active flag column later if multiple environments are needed.
5. Legacy 7-digit Student IDs (e.g. `2000056D`) are excluded by the strict 8-digit rule — they cannot be updated via import (confirmed decision A).
