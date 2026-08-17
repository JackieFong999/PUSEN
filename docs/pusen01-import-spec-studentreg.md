# Student Registration Import — Specification (Final)

Reference for the Student Registration import function. Same SFTP flow, logging pattern, and all-or-nothing rule as the Subject import (see pusen01-import-spec.md). Differences: filename, file format, target table, validation rules.

## 1. UI — Data Import Screen

Left menu → Data Import opens a table, one row per function. The **Student Registration** row is active (Import button + latest file column); other functions are disabled.

**Latest file column (Student Registration):**
- Show the newest un-imported file in SFTP `upload/` matching the registration filename pattern (other functions' files are ignored).
- "Newest" = highest YYYYMMDD in filename; tie-break by upload mtime.
- If `upload/` has no registration file → show the last imported registration file from `tblImport_Log` (FileType='STUDENT-REG'), disable Import.
- After successful import the file moves to `processed/` → no longer "latest".

**Import button click flow:**
1. Load SFTP settings from `tblConfig_SFTP` (§2)
2. Connect to SFTP, read the latest file
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

**Filename convention:** `sao_sen_srs_subreg_` + YYYYMMDD + `.csv`, matched case-insensitively: `^sao_sen_srs_subreg_(\d{8})\.csv$/i`

**CSV format (verified):** UTF-8, **at least 2 columns**, unquoted, no header, no BOM.

| Col | Field | Mapping | Notes |
|---|---|---|---|
| 1 | Student_Id | tblStudent_Reg.Student_Id | must exist in tblStudent |
| 2+ | Subject_Code (one or more) | tblStudent_Reg.Subject_Code | each must exist in tblSubject (any Academic_Year/Semester) |

- One CSV row expands to **one pair per non-empty Subject_Code**: `25000001G,AF3111,AF3112` → pairs `(25000001G, AF3111)` and `(25000001G, AF3112)`.
- Empty subject codes are skipped after trimming (trailing commas tolerated); the row fails only if Student_Id is empty **or** all codes are empty.

**Key model:** the natural key is the pair `(Student_Id, Subject_Code)`. `UNIQUE KEY uq_tblStudent_Reg_Key (Student_Id, Subject_Code)` exists on tblStudent_Reg (added 2026-08-18) as a DB safeguard.

## 3. Logging Tables

**tblImport_Log** — one row per run: File_Name, FileType='STUDENT-REG', CSV_Content (full raw file, MEDIUMTEXT), created_by = login user, updated_ip = request IP, Import_Status = NULL → Success/Failure. No count columns — counts come from the failed log (GROUP BY Import_Log_Id).

**tblImport_Failed_Log** — one row per **failed pair** (Row_Content = the raw CSV row, repeated for each failing pair of that row): Import_Log_Id, File_Date (from filename, fallback import time), File_Name, FileType='STUDENT-REG', Row_Content, Import_Status (Failure/Duplicated/Update), Remarks, Import_By = login user, timestamps. Row-level failures (column count / empty / length / student not found) write one row only.

## 4. Import Pipeline

1. Load SFTP settings from `tblConfig_SFTP`
2. Connect via phpseclib3, list `Remote_Path`, pick the latest file matching the pattern (by YYYYMMDD in name, then mtime)
3. Read file, INSERT `tblImport_Log` (File_Name, FileType='STUDENT-REG', CSV_Content, created_by, updated_ip) — status NULL
4. Parse CSV (str_getcsv per line; blank lines skipped; BOM stripped defensively)
5. Expand rows into pairs; validate every pair (order below, first hit wins) → write Failed_Log per failed pair
6. Empty file / zero data rows → ABORT with "No data rows found", file stays in upload/
7. Any 'Failure' row? → ABORT: Import_Status='Failure', file STAYS in upload/, dialog = error count
8. Else → TRANSACTION (all-or-nothing): insert new pairs
   - exception → rollback + Import_Status='Failure'
   - success → commit + Import_Status='Success', file → `processed/`

**Validation rules (per pair, first hit wins):**

| # | Condition | Status | Remarks |
|---|---|---|---|
| a | Column count < 2 | Failure | At least 2 columns |
| b | Student_Id empty or all Subject_Codes empty | Failure | Student Id / Subject Code cannot be empty |
| c | Any used field longer than its target column width | Failure | Field length exceeds column width (count characters via mb_strlen, not bytes) |
| d | Student_Id not in tblStudent (CI) | Failure | Student Id not exist in tblStudent master table |
| e | Subject_Code not in tblSubject (CI, any AY/Sem) | Failure | Subject Code not exist in tblSubject master table |
| f | Pair (Student_Id, Subject_Code) already seen earlier in this file | Failure | Duplicated record in the same CSV file |
| g | Pair already exists in tblStudent_Reg | Duplicated | Same data already exists, no update occurred |
| h | otherwise | — | eligible for INSERT |

- All fields trimmed before validation.
- Case-insensitive matching; stored values normalized: Student_Id → tblStudent casing, Subject_Code → tblSubject casing.
- g is informational — doesn't block the import; duplicated pairs are skipped, all other pairs still insert.
- There is **no Update case**.

**Column widths (for rule c):** Student_Id 12 · Subject_Code 20.

**Import (transaction, all-or-nothing) — runs only with zero Failure rows:**
- INSERT (h): Student_Id, Subject_Code + updated_by=login user, updated_ip=request IP (Id auto-increment; created_at/updated_at use DB defaults).
- Duplicated (g): no DB change.
- One transaction: commit all or rollback all.

## 5. Result Dialogs

- Success: "X inserted / Y updated / Z duplicated" (X, Z are pair counts; Y is always 0)
- Abort: "N error record(s) found. No records imported." — file stays in upload/
- Counts: `SELECT Import_Status, COUNT(*) FROM tblImport_Failed_Log WHERE Import_Log_Id=? GROUP BY Import_Status`; inserted count from transaction result.

## 6. Non-Functional

- Auth middleware; audit fields from Auth::user() (**Staff_Id** — the login user)
- SFTP settings from `tblConfig_SFTP` (DB), not hard-coded in code/env
- SFTP via phpseclib3 (hostname sftp-server, network pusen-net)
- Import button disabled while running; re-uploading same filename allowed (→ all Duplicated)
- Missing SFTP config row → error state, Import disabled
- Source CSV encoding: UTF-8 (confirmed)

## 7. Generic Template for Other Imports

Fill per function: filename prefix / FileType / target table / stored columns / row-to-pair expansion / dedup model / master lookups / side effects. Same SFTP flow (settings from tblConfig_SFTP), same log tables, same all-or-nothing + dialogs.

## 8. Assumptions (flagged, unconfirmed)

1. tblStudent_Reg has no `created_by` → stamp `updated_by`/`updated_ip` on insert too (importer trace). Say so if inserts should stay blank.
2. Re-import of identical filename allowed; add a File_Name pre-check on `tblImport_Log` if it must be rejected.
3. `processed/` = sibling of Remote_Path at chroot root (/home/import/processed), owned import:users.
4. `tblConfig_SFTP` holds a single active row; add an Active flag column later if multiple environments are needed.
5. `UNIQUE KEY uq_tblStudent_Reg_Key (Student_Id, Subject_Code)` added to tblStudent_Reg (2026-08-18, confirmed no existing duplicates).
6. In-file pair duplicates → Failure (blocks the file); DB-existing pairs → Duplicated (informational, skipped, non-blocking).
