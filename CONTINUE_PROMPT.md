Continue the gov.cabnet.app Bolt → EDXEIX bridge project.

The bridge is in safe dry-run mode. Live EDXEIX submission is OFF.

Latest driver-copy requirement:
- When Bolt sends a pre-ride email to the bridge mailbox, gov.cabnet.app should send a copy to the assigned driver.
- Recipient resolution must be driver-based, not vehicle-plate-based.
- v4.5.2 removes vehicle plate as a recipient resolver and improves Bolt driver-directory name/email extraction.

Keep all changes plain PHP/mysqli, cPanel friendly, and production safe. Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit patch.
