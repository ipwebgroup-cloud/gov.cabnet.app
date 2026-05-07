# v5.2.1 Driver Receipt Wording Cleanup

This patch removes customer/driver-facing instances of the word "Estimated" from the HTML receipt copy only.

Changed receipt labels:

- Estimated total -> Total
- Estimated pick-up time -> Pick-up time
- Estimated end time -> End time
- Estimated price -> Price
- VAT / TAX included in estimated total -> VAT / TAX included in total
- Estimated total, VAT included -> Total, VAT included

The existing receipt rules remain unchanged:

- The receipt end time shown to the driver is still calculated as pick-up time + 30 minutes.
- The receipt price still uses the first value only when Bolt provides a range.
- VAT/TAX is still calculated as 13% included in the total.

No EDXEIX live-submit behavior is changed.
