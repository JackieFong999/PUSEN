# SEN Search Web Page — Build Prompt (v2, FINAL-ish, for future reference)

## Goal
SEN Search page querying `tblSEN`, same design language as Student/Staff List pages (stat-card
criteria bar, AG Grid, dark theme, loading overlay, toasts).

## 1. Search criteria bar (top)
| Field | Input | Source / behavior |
|---|---|---|
| Student_Id | Text | **Exact match** on `tblSEN.Student_Id` |
| Student_Name_Eng | Text | Partial match (via tblStudent) |
| Student_Name_Chn | Text | Partial match (via tblStudent) |
| Programme_Leader | Select | `tblStaff` WHERE `Target_User_Id='PL'`, display `Staff_Id — Staff_Name` |
| Department_Admin_Staff | Select | `tblStaff` WHERE `Target_User_Id='DA'`, `Staff_Id — Staff_Name` |
| Counsellor | Select | `tblStaff` WHERE `Target_User_Id='C'`, `Staff_Id — Staff_Name` |
| SEN_Officer | Select | `tblStaff` WHERE `Target_User_Id='SO'`, `Staff_Id — Staff_Name` |
| Undergraduate_Studies_Support_Officer | Select | `tblStaff` WHERE `Target_User_Id='USSO'`, `Staff_Id — Staff_Name` |
| SEN_Type | Select | `tblSEN_Type` (column `SEN_Type`) |
| SEN_Detail | Text | Partial match |

All selects have an empty first option (default).

## 2. Buttons
- **Search** — query tblSEN, AND logic across filled criteria; results in AG Grid
- **Reset** — clear all criteria AND clear the grid (no rows)

## 3. Results grid (AG Grid, pagination 8/page)
Columns: Student_Id · Student_Name_Eng (from tblStudent) · Student_Name_Chn (from tblStudent) ·
Programme_Leader (Staff_Display_Name) · Department_Admin_Staff (Staff_Display_Name) · Counsellor
(Staff_Display_Name) · SEN_Officer (Staff_Display_Name) ·
Undergraduate_Studies_Support_Officer (Staff_Display_Name) · SEN_Type · SEN_Detail ·
Special_Support_Required · Special_Examination_Arrangement · Temporary_Special_Support ·
**Actions (Edit button)**

Staff display fallback: `Staff_Display_Name` → `Staff_Name` → `Staff_Id` if null.
Student names: fetch student map in PHP (collation-safe, no SQL JOIN) — missing/orphaned students
show `—`.

## 4. Edit flow
- Click **Edit** → Create SEN page opens in **edit mode**: title "Edit SEN", **[+ Create SEN Case] hidden**
- SEN record fields pre-filled; **Student_Id disabled** (not changeable); student display-only block
  populated from the record's Student_Id
- **Documents**: existing `tblSEN_Doc` files load into the Documents List Box; clicking a file opens
  the PDF **in a new tab** (`/storage/sen_docs/...`)
- User **can add new documents** (upload flow same as create: PDF only, ≤1MB, max 20 total)
- User **can Remove an already-saved doc** (file + tblSEN_Doc row)
- **Save** → UPDATE `tblSEN` row + reconcile `tblSEN_Doc` (new uploads inserted, removed docs deleted,
  staged files moved to final) → **go back to SEN Search page**
- **Cancel** → discard changes, **delete any newly-staged files**, go back to SEN Search page

## Pending confirmations (Jackie, 2026-08-06)
1. Staff dropdowns: enabled staff only (`status = 0`) like Create SEN? (assumed yes)
2. Removing a saved doc then pressing **Cancel** — should the doc stay (discard = revert)? (assumed
   yes: removals are applied only on Save)
3. Orphaned SEN.Student_Id (2000056D…2000067D not in tblStudent) → names show `—` for now (assumed)

## DB facts (verified 2026-08-06)
- `tblSEN` (PK SEN_Id varchar10): Student_Id, Programme_Leader, Department_Admin_Staff, Counsellor,
  SEN_Officer, Undergraduate_Studies_Support_Officer, SEN_Type, SEN_Detail, Special_Support_Required,
  Special_Examination_Arrangement, Temporary_Special_Support + audit. 16 rows (13-16 = demo).
- `tblStudent`: utf8mb4_0900_ai_ci; `tblStaff`: utf8mb4_0900_ai_ci; `tblSEN_Type`: utf8mb4_unicode_ci
  → **no cross-table SQL JOINs** (collation 1267); fetch lookup maps in PHP and merge.
- `tblSEN_Doc`: PK (SEN_Id, Doc_Seq), Doc_Filename (SEN_Id_Seq_original.pdf), Doc_Password (unused).
  Files in `storage/app/public/sen_docs/` (served at `/storage/sen_docs/` via storage:link).
- DB connection: `pusen`.
