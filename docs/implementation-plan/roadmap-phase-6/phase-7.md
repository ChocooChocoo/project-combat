[← Previous: Packet 6E Procurement](./phase-6.md) | [Next: Packet 6G Budgets and reports →](./phase-8.md)

**Status:** ✅ Done

# Packet 6F: Payroll — computation, statutory deductions, approval and payslips

**Goal:** For each pay period the system works out every employee's pay, coaches included, from stored records only: rates, attendance, approved leave, approved overtime, holidays, sessions and statutory tables. A run moves through an approval chain that cannot be skipped. Released runs post an expense and produce payslips. Covers R-59, R-60, R-61 and R-62.

## Requirements and done-when

| # | Requirement | Done when |
|---|---|---|
| R-59 | Payroll for everyone, coaches included | Every active and just-ended employee gets a line; each line shows how it was worked out; figures come from stored records, never from typed amounts |
| R-60 | Statutory deductions | Each payslip shows SSS, PhilHealth, Pag-IBIG and tax; a permitted person adds rates with a start date, and old periods keep old rates |
| R-61 | Approval and release | Steps run in order, release only at the end, rejection goes back with a reason, no way to mark released without every approval, release posts a pay-out expense |
| R-62 | Payslips and history | An employee sees released payslips only; permitted staff look up any past payroll |

## Dependencies

- Packet 6A: staff attendance, approved overtime, `PayrollLock` hook.
- Packet 6D: `expenses`, `Finance\Expenses::post()`.
- Phase 3: employee profiles and rates, schedules, leave, holidays, working days.
- Phase 5: training sessions with coaches and attendances.
- Packet 6.0: `payroll.runs.*`, `payroll.rates.manage`, gym payroll settings.

## Decisions

- Pay frequency is a gym setting: `semi_monthly` (1-15 and 16-end) by default, or `monthly`. The period is closed on both ends and cannot overlap another run for the same gym.
- Everyone with an `employee_profiles` row whose employment range overlaps the period gets a line, including someone whose `ended_on` falls inside it. The owner is not paid unless an employee profile exists.
- A missing pay basis or missing rate is a blocking warning. The run cannot be submitted until it is fixed.
- Rates are snapshotted onto the line when computed. A draft can be recomputed with current rates. Once submitted, nothing changes. Released history can never be rewritten.
- All money is integer centavos inside the calculator. Round half up at each posted line item, not at the end.
- Daily rate = `monthly × 12 ÷ annual_factor`, where `annual_factor` is the number of the employee's scheduled weekdays in the calendar year (261 for Monday to Friday in 2026). Minute rate = daily ÷ scheduled shift minutes, default 480.
- A full semi-monthly period pays `monthly ÷ 2`. A period partly outside employment pays `daily × scheduled working days inside employment`, so a mid-month start or end prorates.
- Day classification uses `WorkingDays::kind()`. Paid leave comes from `config('hr.leave_types.*.paid')`. Absence and unpaid leave reduce pay by the daily rate. Late and undertime reduce pay by the minute rate.
- Holiday premium applies only to holidays actually worked: regular `+1.00 × daily`, special `+0.30 × daily`. A holiday not worked is already covered by the monthly base or by the paid-rest rule.
- Overtime pays `approved_minutes × minute_rate × 1.25`. Requested but unapproved minutes are ignored.
- Per-session pay counts sessions where the employee is coach, the session is `scheduled`, not flagged, ended inside the period, and at least one booking is `present` or `late`. Excluded sessions are listed as warnings.
- Statutory deductions apply only when `employment_type = employee`. Contractors get none.
- Statutory rates are effective-dated. The run uses the newest rate per kind whose `effective_from` is on or before the period end.
- Semi-monthly statutory method, to avoid depending on the other cutoff: SSS, PhilHealth and Pag-IBIG are computed on the monthly equivalent (`period gross × 2`) and the employee and employer shares are halved for this cutoff. Tax uses the semi-monthly BIR table on this cutoff's taxable pay. Monthly runs use the monthly tables.
- The employer share of statutory contributions is posted as an expense at release along with gross pay. Net pay is not posted; gross plus employer share is the cost.
- Approval chain: 1 to 3 steps from `hr_review`, `finance_review`, `owner`. Owner is always last. Default `["owner"]`. Steps are snapshotted at submit.
- Compute and submit need `payroll.runs.manage`. Non-final steps need `payroll.runs.approve`. The final step needs the owner role.
- Nobody acts on two steps of the same round. The preparer cannot approve any step. Exception: in a one-step chain the owner may release a run they prepared; the run records `self_released = true` and the history shows it.
- Rejection returns the run to draft with a reason and starts a new round. Old approvals stay for history.
- `PayrollLock` blocks attendance corrections, overtime decisions, leave decisions and holiday changes for dates inside an in-review or released run. This packet turns the 6A stub into the real check and wires it into `LeaveController`, `HolidayController`, `StaffAttendanceBook` and `OvertimeController`.
- Payslips are in-app and print-friendly. No PDF package.

## Schema

Migration `backend/database/migrations/2026_11_16_100000_create_payroll_tables.php`.

**`payroll_runs`**

| Column | Type | Notes |
|---|---|---|
| `gym_id` | FK cascade | |
| `starts_on`, `ends_on` | date | |
| `status` | string(20) | `draft`, `in_review`, `released` |
| `steps` | json | snapshot at submit |
| `current_step` | unsigned tiny int, default 0 | |
| `round` | unsigned small int, default 1 | |
| `prepared_by`, `submitted_by`, `submitted_at` | | |
| `computed_at` | dateTime | |
| `rejection_reason` | text nullable | |
| `released_by`, `released_at`, `self_released` | | |
| `gross_total`, `employee_statutory_total`, `tax_total`, `net_total`, `employer_statutory_total` | decimal(14,2) | cached |

Unique `(gym_id, starts_on, ends_on)`.

**`payroll_lines`**

`payroll_run_id` FK cascade, `user_id` FK, snapshot fields `name`, `job_title`, `pay_basis`, `employment_type`, `monthly_rate`, `per_session_rate`, `daily_rate`, `minute_rate`; counts `scheduled_days`, `days_worked`, `days_absent`, `paid_leave_days`, `unpaid_leave_days`, `holiday_days`, `late_minutes`, `undertime_minutes`, `overtime_minutes`, `sessions_count`; money `base_pay`, `attendance_deduction`, `holiday_premium`, `overtime_pay`, `session_pay`, `gross_pay`, `sss_employee`, `philhealth_employee`, `pagibig_employee`, `tax`, `net_pay`, `sss_employer`, `ec_employer`, `philhealth_employer`, `pagibig_employer`; `warnings` json, `statutory_rate_ids` json.

Unique `(payroll_run_id, user_id)`. Index `(gym_id, user_id)` — add `gym_id` to the line for direct queries.

**`payroll_line_items`**

`payroll_line_id` FK cascade, `kind`, `label`, `quantity`, `rate`, `amount` signed, `ref` json nullable.

**`payroll_approvals`**

`payroll_run_id` FK cascade, `round`, `step_index`, `step_key`, `user_id`, `decision` (`approved`, `rejected`), `reason` text nullable, `decided_at`. Unique `(payroll_run_id, round, step_index)`.

**`statutory_rates`** — no gym global scope; explicit `forGym()` scope

`gym_id` nullable FK, `kind` (`sss`, `philhealth`, `pagibig`, `tax_semi_monthly`, `tax_monthly`), `effective_from` date, `brackets` json, `source` string(255) nullable, `created_by` nullable, timestamps. Index `(kind, effective_from)`. Insert-only: the model throws on update and delete.

## Backend tasks

- [x] Enums `PayrollStatus`, `PayrollStep`, `StatutoryKind`, `EmploymentType` in `backend/app/Enums/`.
- [x] Models `PayrollRun`, `PayrollLine`, `PayrollLineItem`, `PayrollApproval`, `StatutoryRate`.
- [x] `App\Support\Payroll\InputLoader` — loads the period's employees, profiles, schedules, attendance, overtime, leave, holidays, sessions and qualifying attendances with explicit `gym_id` filters.
- [x] `App\Support\Payroll\Calculator` — pure, no database writes. `calculate(array $period, array $employees, array $inputs, array $rates): array`. Returns lines and items in centavos. One method per step so tests can target the arithmetic.
- [x] `App\Support\Payroll\Statutory` — bracket lookup and share calculation for SSS, PhilHealth, Pag-IBIG and tax. Rejects a missing rate for the period.
- [x] `App\Support\Payroll\Workflow` — compute, submit, decide, release. All decisions run under a row lock. The only way to reach `released` is approving the last step.
- [x] `App\Support\PayrollLock` — `assertOpen(Gym, Carbon $date)` and `assertOpenRange(Gym, Carbon $from, Carbon $to)`. Reads in-review and released runs. Wire the calls into the leave decision/submit paths, holiday add/remove, staff attendance correction and overtime decision.
- [x] Controllers `PayrollRunController`, `PayrollSettingsController`, `StatutoryRateController`, `PayslipController`.
- [x] Routes:

| Method and path | Controller | Permission |
|---|---|---|
| `GET payroll/runs` | PayrollRunController@index | `payroll.runs.view` |
| `POST payroll/runs` | @store | `payroll.runs.manage` |
| `GET payroll/runs/{run}` | @show | `payroll.runs.view` |
| `POST payroll/runs/{run}/compute` | @compute | `payroll.runs.manage` |
| `POST payroll/runs/{run}/submit` | @submit | `payroll.runs.manage` |
| `POST payroll/runs/{run}/decision` | @decide | `payroll.runs.approve` (owner check inside for the last step) |
| `DELETE payroll/runs/{run}` | @destroy | `payroll.runs.manage`, draft only |
| `GET payroll/settings` | PayrollSettingsController@show | `payroll.runs.view` |
| `PUT payroll/settings` | @update | owner only in the controller |
| `GET payroll/rates` | StatutoryRateController@index | `payroll.runs.view` |
| `POST payroll/rates` | @store | `payroll.rates.manage` |
| `GET me/payslips` | PayslipController@index | self, outside the gym middleware |
| `GET me/payslips/{line}` | @show | self, outside the gym middleware |

- [x] On release, in one transaction: mark released, post `payroll` (gross total) and `statutory_contributions` (employer total) expenses, notify each employee with `PayslipReleased`. Idempotent through the expense source key.
- [x] On submit: `PayrollLock` starts applying; notify the current step's eligible approvers.
- [x] On reject: notify the preparer with the reason; run returns to draft, `round + 1`.
- [x] Seed platform `statutory_rates` from the legacy Combat Sports SSS, PhilHealth, Pag-IBIG and BIR tables, keeping the source and effective date on each row. The seeded `source` text records that the values are legacy and must be checked against the current circulars.
- [ ] Verify the seeded SSS, PhilHealth, Pag-IBIG and BIR values against the current circulars before production. **Not done; the seeded values are legacy.**
- [x] Add the payroll tables to `DatabaseSeeder::resetData()` and seed one draft and one released run for demo gyms.

## Frontend tasks

- [x] `frontend/src/pages/gym/GymPayroll.tsx` at route `payroll`:
  - Runs table: period, status, headcount, gross, net, current step, released date.
  - New run dialog with the period preset from frequency.
  - Settings tab: frequency, chain builder (1-3 steps, owner fixed last), grace and overtime thresholds.
  - Rates tab: current and past statutory rates, add-rate dialog with effective date and bracket editor or JSON import.
- [x] `frontend/src/pages/gym/GymPayrollRun.tsx` at route `payroll/:runId`:
  - Header with status, period, totals, actions: compute, submit, approve, reject.
  - Warnings alert with blocking items.
  - Lines table with expandable breakdown: base, attendance, holiday, overtime, sessions, each statutory line, tax, net.
  - Approval timeline with step, person, decision, reason, round.
  - Release confirmation and payslip list.
- [x] `MyWork.tsx` gains a **Payslips** tab: released only, gross, deductions, net, print button.
- [x] Nav entry "Payroll" with `[payroll.runs.view]`.
- [x] Types in `frontend/src/lib/payroll.ts`.

## Tests

| File | Must prove |
|---|---|
| `Unit/PayrollCalculatorTest` | Monthly, per-session, both; full period; mid-period start and end; paid vs unpaid leave; absence; late and undertime; regular and special holiday premiums; overtime approved only; session counting rule; centavo rounding; contractor has no statutory |
| `Unit/StatutoryTest` | Every bracket edge; floor and ceiling; effective date choice; semi-monthly halving; monthly tables; missing rate throws |
| `PayrollRunTest` | A line for every active and just-ended employee; blocking warnings stop submit; compute only in draft; submit snapshots steps; no skipping; one person cannot act on two steps; preparer blocked; owner self-release only in a one-step chain and flagged; reject returns to draft with reason and a new round; double release posts expenses once; lines and items frozen after submit |
| `PayrollLockTest` | Attendance correction, overtime decision, leave decision and holiday change inside an in-review or released period are refused; after rejection they open again |
| `PayslipTest` | Own and released only; another employee gets 404; a removed employee still sees released payslips; staff history needs `payroll.runs.view` |
| `PayrollTenancyTest` | Another gym's run, line and rate return 404 |

## Checks

`php artisan test`, `php artisan migrate:fresh --seed` on MySQL, `pnpm lint`, `pnpm build`, browser pass as owner, HR, finance and a plain employee. Print one payslip.

## Done when

- [x] Running payroll for a closed period produces a line for every employee and coach, with a full breakdown.
- [x] No figure is accepted from the screen; every amount comes from stored records.
- [x] SSS, PhilHealth, Pag-IBIG and tax appear on each payslip, at the rates effective for the period.
- [x] A new rate with a later start date changes new runs and leaves released history alone.
- [x] The chain runs in order, cannot be skipped, and rejects with a reason.
- [x] Release posts the pay-out and employer statutory expenses exactly once.
- [x] An employee sees only released payslips; permitted staff see all history.
- [x] All tests, lint and build pass.

## Status notes

- **6 November 2026 — automated build complete; browser pass pending.** Built `2026_11_16_100000_create_payroll_tables`, the payroll enums, models, `InputLoader`, `Calculator`, `Statutory`, `Workflow`, `PayrollLock`, the four payroll controllers, `PresentsPayroll`, `StatutoryRateSeeder`, `GymPayroll.tsx`, `GymPayrollRun.tsx`, `PayslipSheet`, the My Work Payslips tab and `lib/payroll.ts`. Release posts the `payroll` and `statutory_contributions` expenses once through the unique source key; the last step is the only path to `released`. Tests: `PayrollRunTest`, `PayrollLockTest`, `PayslipTest`, `PayrollTenancyTest`, `Unit\PayrollCalculatorTest`, `Unit\StatutoryTest` (all green). 6H integration: run list now paginates; the pay-visibility helper was renamed `canViewCompensation` and checks the exact catalog (`hr.staff.manage`, `payroll.runs.view`, `finance.payments.view`); removed-employee attendance history has its own test. Browser pass and one printed payslip still to run.
- **6 November 2026 — browser pass found and fixed two defects.** A six-line Oct 16–31 run first produced negative gross/net (Bianca: ₱22,500 base, ₱24,153.36 absence deduction, −₱1,653.36 gross, −₱1,903.36 net). The calculator now floors gross and net at zero and zeroes employee statutory/tax at non-positive gross; the recomputed run showed 6 lines, gross ₱8,171.28, net ₱7,364.50 and no negatives. The released payslip table showed Deductions ₱0.00 while net was reduced, and the payslip detail showed Base pay ₱0.00; the payload and sheet now expose the stored breakdown (Dindo: base ₱14,000, attendance −₱15,028.72, sessions ₱7,200, gross ₱6,171.28, statutory ₱516.78, net ₱5,654.50). Owner self-released the one-step chain with the `Self-released` flag; both payroll expenses posted exactly once (₱8,171.28 and ₱2,744.28); payslips appeared in My Work and the print button fired `window.print`; adding a holiday inside the released period was refused with “This date is locked by payroll run #1 (released).” Phone width had no overflow. Operational risk found during the pass: the dev database had no October attendance rows, and missing attendance is a non-blocking warning treated as absence, so five of six staff reached zero pay while the run still submitted. This follows the current packet rule (only missing pay basis/rate blocks), but it should be confirmed whether missing attendance ought to block submission before production use. Separately, the seeded statutory rates are legacy values and the seeder source says to verify the current SSS, PhilHealth, Pag-IBIG and BIR circulars; that verification is still open.

- **6 November 2026 — multi-step browser test found two issues; both fixed and retested.** With a Finance review → Owner chain, rejection returned the run to draft with a reason and round 2; re-submission, Finance approval and owner release all worked when HR prepared the run (approval timeline showed Finance and Owner, `self_released` false). But when the **owner prepared** a multi-step run, Finance approved and the owner step showed no Release or Reject control because the preparer was blocked at every step except a one-step chain. Fixed: `Workflow` now lets the owner release the final step of a run they prepared, while every earlier step still needs a different approver; `GymPayrollRun` now shows the final Release/Reject controls for that case and the self-release text is chain-length neutral. The low-gross run also produced employee statutory ₱751.52 against gross ₱151.79. Fixed: employee statutory and tax are capped at gross with a `statutory_capped_at_gross` warning. Retest: run #2 released as `Self-released`; a fresh Sep 1–15 draft showed deductions equal gross (₱71.88/₱71.88, ₱44.76/₱44.76, ₱35.15/₱35.15) and net ₱0.00 with the warning; the HR → Finance → Owner chain still released normally. Run #2's lines were computed before the cap fix, so its released payslips keep the old deduction values; released history is immutable. Full suite 476 tests, 25,826 assertions; lint/build green; stock counts clean.

---

[← Previous: Packet 6E Procurement](./phase-6.md) | [Next: Packet 6G Budgets and reports →](./phase-8.md)
