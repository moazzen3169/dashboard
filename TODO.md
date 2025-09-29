# TODO: Fix Total Payments Filter in Sales Report

## Completed Tasks
- [x] Add new method `getFilteredTotalPayments()` to `SalesReport` class in `reports-update.php`
  - Builds WHERE clause for payments table based on date (`payment_date BETWEEN ? AND ?`) and buyer (`buyer_id = ?`) filters
  - Ignores product filter (not applicable to payments)
  - Uses prepared statement for security
- [x] Update execution block to call `$totalPayments = $report->getFilteredTotalPayments();` instead of `getTotalPayments()`
- [x] Add print CSS to hide sidebar when printing
  - Added `@media print` styles to hide `.sidebar` and adjust `.main-content` width

## Followup Steps
- [ ] Test the changes:
  - Reload the page without filters: Verify total payments shows 755,000,000 (global sum).
  - Apply date filter (e.g., 1403/01/01 to 1403/12/29): Check if total payments sums only payments in that Gregorian date range.
  - Apply buyer filter: Verify total payments sums only for the selected buyer.
  - Apply product filter: Confirm total payments remains unchanged (ignores product filter).
  - Combine filters (date + buyer): Ensure both filters apply together correctly.
  - Test print functionality: Click "چاپ گزارش" and verify sidebar is hidden in print preview.
  - Check error logs for any issues with date conversion or queries
- [ ] Verify no impact on other stats (revenue, purchases, etc. still filter correctly)

## Notes
- Changes are isolated to `reports-update.php`
- No database or other file modifications needed
- Product filter intentionally ignored for payments as payments aren't product-specific
- Print styles added for better print layout
