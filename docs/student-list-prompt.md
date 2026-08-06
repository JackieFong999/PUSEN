# Student List Page — Build Prompt (v3, for future reference)

## Goal
Create the **Student List** web page, following the same design and behavior as the existing
**Staff List** page (`/admin/staff-list`): a criteria bar with a **Search** button, results shown
in an **AG Grid**. The grid is **read-only — no editing**.

## Menu
- Add "Student List" under the **Admin** group, just below "Staff List" (already in `config/nav.php`;
  link `#` to be wired to the real page once the route exists).

## Search Criteria (6 fields)
| Field            | Input type   | Source / behavior                                |
|------------------|--------------|--------------------------------------------------|
| Student_Name_Eng | Text input   | **Partial match** (LIKE %term%)                  |
| Student_Name_Chn | Text input   | **Partial match** (LIKE %term%)                  |
| Faculty          | Selection box| **Distinct** values from `tblStudent.Faculty`    |
| Department       | Selection box| **Distinct** values from `tblStudent.Department` |
| Fund_Type_Code   | Selection box| From `tblFund_Type`, **show code + description** (`S — Self-Financed`) |
| Student_Status   | Selection box| From `tblStudent_Status` (e.g. `ACTIVE`, `COMPLETED`) |

Search rules (same as Staff List): AND logic across all filled criteria; empty criteria returns
all students.

## Grid Columns (read-only, no Actions column)
Student_Id, Student_Name_Eng, Student_Name_Chn, Faculty (code only), Department, Prog_Sub_Code,
Prog_Title, **Fund_Type_Code (show code + description)**, Student_Status

## Pagination
8 rows per page (same as Staff List).

---

## Resolved decisions (v3)
- **Fund_Type_Code** (dropdown + grid): show `S — Self-Financed` style (code + `tblFund_Type.Fund_Status`).
- **Faculty**: code only — **no** description lookup table needed.

## DB facts (verified 2026-08-06)
- `tblStudent`: Id, Student_Id(v12), Student_Name_Eng(v30), Student_Name_Chn(v5), Faculty(v10),
  Department(v10), Prog_Sub_Code(v10), Prog_Title(v60), Fund_Type_Code(char1), Student_Status(v15),
  created_at, updated_at, updated_by, updated_ip. 3 sample rows.
- `tblFund_Type`: S = Self-Financed, U = UGC-Funded.
- `tblStudent_Status`: ACTIVE, COMPLETED.
- Distinct Faculty: FBI, KENN · Distinct Department: AAF, IIS, MME.
- DB connection name: `pusen` (MySQL via Docker, port 3307).
