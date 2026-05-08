# gov.cabnet.app — Handoff v6.2.5

Production state:
- Bolt mail intake active.
- Bolt API sync active.
- Pickup-swipe AADE receipt worker active.
- AADE/myDATA issuing confirmed.
- Driver PDF receipt email confirmed.
- Office copy to mykonoscab@gmail.com confirmed.
- EDXEIX live submission disabled.
- submission_jobs = 0.
- submission_attempts = 0.

Receipt rule:
Issue AADE receipt when Bolt API confirms order_pickup_timestamp. Use first number from Bolt estimated price range. Skip cancelled, no-show, non-responded, zero-price, and duplicate orders.

Do not commit real config files, logs, receipt PDFs, session files, or credentials.
