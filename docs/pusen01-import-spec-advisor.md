# Advisor List for the Student List — Specification (Final)

Reference for the Advisor List for the Student List import function. Same SFTP flow, logging pattern, and all-or-nothing rule as the Subject import (see pusen01-import-spec.md). Differences: filename, file format, target table, validation rules.

## 1. UI — Data Import Screen

Left menu → Data Import opens a table, one row per function. The **Advisor List for the Student List** row is active (Import button + latest file column); other functions are disabled.

**Latest file column (Advisor List for the Student List):**
- Show the newest un-imported file in SFTP `upload/` that matches the Advisor filename pattern (Subject/Staff/Student files are ignored).
- "Newest" = highest YYYYMMDD in filename; tie-break by upload mtime.
- If `upload/` has no advisor file → show the last imported advisor file from `tblImport_Log` (FileType='ADVISOR'), disable Import.
- After successful import the file moves to `processed/` → no longer "latest".

**Import button click flow:**
1. Load SFTP settings from `tblConfig_SFTP` (§2)
2. Connect to SFTP, read the latest advisor file
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

**Filename convention:** `sao_sen_srs_advisor_` + YYYYMMDD + `.csv`, matched case-insensitively: `^sao_sen_srs_advisor_(\d{8})\.csv$/i`

**CSV format (verified):** UTF-8, 8 columns, unquoted, no header, no BOM.

| Col | Field | Mapping | Notes |
|---|---|---|---|
| 1 | Advisor_Id | tblAdvisor_Student.Advisor_Id | = tblStaff.Staff_Id |
| 2 | UNKNOWN | — | ignored (no empty check) |
| 3 | UNKNOWN | — | ignored (no empty check) |
| 4 | UNKNOWN | — | ignored (no empty check) |
| 5 | Student Number | tblAdvisor_Student.Student_Id | = tblStudent.Student_Id |
| 6 | Advisor Type | tblAdvisor_Student.Advisor_Type | = tblAdvisor_Type.Advisor_Type |
| 7 | Start Date | tblAdvisor_Student.Start_Date | date — `YYYY-MM-DD` or `dd-MMM-yy` (e.g. 12-Mar-15 / 5-Sep-16, day 1–2 digits), normalized to YYYY-MM-DD |
| 8 | End Date | tblAdvisor_Student.End_Date | date — `YYYY-MM-DD` or `dd-MMM-yy`, normalized to YYYY-MM-DD, mandatory |

**Dedup model (no key in the CSV):** there is no key column and no composite key. Duplicate detection is a **full-row match** on all 5 stored fields `(Advisor_Id, Student_Id, Advisor_Type, Start_Date, End_Date)`. Consequences (confirmed):
- The same advisor + student pair may have **multiple rows** (e.g. PRIMARY and PROG_LEADER) — each distinct combination is a separate record.
- Changed data creates a **new row**; existing rows are never updated or removed by this import.
- No UNIQUE index is added on (Advisor_Id, Student_Id) — it would block this design.

**Staff re-enable side effect:** when a row's Advisor_Id exists in `tblStaff.Staff_Id` with `status = 1` (disabled), that staff record is re-enabled (`status = 0`). This runs inside the import transaction for all distinct Advisor_Ids in the CSV once the file passes validation.

## 3. Logging Tables

**tblImport_Log** — one row per run: File_Name, FileType='ADVISOR', CSV_Content (full raw file, MEDIUMTEXT), created_by = login user, updated_ip = request IP, Import_Status = NULL → Success/Failure. No count columns — counts come from the failed log (GROUP BY Import_Log_Id).

**tblImport_Failed_Log** — one row per CSV row: Import_Log_Id, File_Date (from filename, fallback import time), File_Name, FileType='ADVISOR', Row_Content (raw row), Import_Status (Failure/Duplicated/Update), Remarks, Import_By = login user, timestamps.

## 4. Import Pipeline

1. Load SFTP settings from `tblConfig_SFTP`
2. Connect via phpseclib3, list `Remote_Path`, pick the latest file matching the advisor pattern (by YYYYMMDD in name, then mtime)
3. Read file, INSERT `tblImport_Log` (File_Name, FileType='ADVISOR', CSV_Content, created_by, updated_ip) — status NULL
4. Parse CSV (str_getcsv per line; blank lines skipped; BOM stripped defensively)
5. Validate every row (order below, first hit wins) → write Failed_Log per row
6. Empty file / zero data rows → ABORT with "No data rows found", file stays in upload/
7. Any 'Failure' row? → ABORT: Import_Status='Failure', file STAYS in upload/, dialog = error count
8. Else → TRANSACTION (all-or-nothing): insert new rows + re-enable disabled staff
   - exception → rollback + Import_Status='Failure'
   - success → commit + Import_Status='Success', file → `processed/`

**Validation rules (per row, first failure wins):**

| # | Condition | Status | Remarks |
|---|---|---|---|
| a | Column count ≠ 8 | Failure | Incorrect number of columns |
| b | Any of the 5 used fields empty (cols 1,5,6,7,8) | Failure | One or more fields is empty |
| c | Any used field longer than its target column width | Failure | Field length exceeds column width (count characters via mb_strlen, not bytes) |
| d | Start_Date or End_Date not a valid date (`YYYY-MM-DD` or `dd-MMM-yy`) | Failure | Date must be a valid date (YYYY-MM-DD or dd-MMM-yy) |
| e | Advisor Id not in tblStaff (CI) | Failure | Advisor Id not exist in tblStaff master table |
| f | Student Id not in tblStudent (CI) | Failure | Student Id not exist in tblStudent master table |
| g | Advisor Type not in tblAdvisor_Type (CI) | Failure | Advisor Type not exist in tblAdvisor_Type master table |
| h | Identical row (all 5 fields) already seen earlier in this file | Failure | Duplicated record in the same CSV file |
| i | Identical row (all 5 fields) already exists in tblAdvisor_Student | Duplicated | Same data already exists, no update occurred |
| j | otherwise | — | eligible for INSERT |

- All fields trimmed before validation.
- Case-insensitive matching; stored values normalized: Advisor_Id → tblStaff casing, Student_Id → tblStudent casing (uppercase letter), Advisor_Type → tblAdvisor_Type casing, dates → YYYY-MM-DD (two-digit years interpreted as 2000–2099).
- i is informational — doesn't block the import.
- There is **no Update case**: existing rows are never modified.

**Column widths (for rule c):** Advisor_Id 20 · Student_Id 12 · Advisor_Type 14 (dates are fixed-length, no width check).

**Import (transaction, all-or-nothing) — runs only with zero Failure rows:**
- INSERT (j): Advisor_Id, Student_Id, Advisor_Type, Start_Date, End_Date + updated_by=login user, updated_ip=request IP (created_at/updated_at use DB defaults).
- **Staff re-enable** (inside the same transaction): for each distinct Advisor_Id among the valid rows, `UPDATE tblStaff SET status = 0 WHERE Staff_Id = ? AND status = 1` (also stamps updated_by/updated_ip). Not counted in the result dialog.
- One transaction: commit all or rollback all. Duplicated (i): no DB change.

## 5. Result Dialogs

- Success: "X inserted / Y updated / Z duplicated" (Y is always 0 for this import)
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

Fill per function: filename prefix / FileType / target table / stored columns / ignored columns / dedup model (key-based or full-row) / master lookups / side effects. Same SFTP flow (settings from tblConfig_SFTP), same log tables, same all-or-nothing + dialogs.

## 8. Assumptions (flagged, unconfirmed)

1. tblAdvisor_Student has no `created_by` → stamp `updated_by`/`updated_ip` on insert too (importer trace). Say so if inserts should stay blank.
2. Re-import of identical filename allowed; add a File_Name pre-check on `tblImport_Log` if it must be rejected.
3. `processed/` = sibling of Remote_Path at chroot root (/home/import/processed), owned import:users.
4. `tblConfig_SFTP` holds a single active row; add an Active flag column later if multiple environments are needed.
5. Full-row dedup confirmed — multiple rows per (Advisor_Id, Student_Id) pair allowed; changed data inserts new rows; no updates ever.
6. Date formats accepted: `YYYY-MM-DD` and `dd-MMM-yy` (e.g. 12-Mar-15, 5-Sep-16 — day may be 1 or 2 digits); both normalized to `YYYY-MM-DD`. Two-digit years → 2000–2099 (15 → 2015, 99 → 2099).
