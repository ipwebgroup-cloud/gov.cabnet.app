-- gov.cabnet.app — v4.5.1 Bolt driver directory email columns
-- Additive migration only. No destructive changes.
-- Enables driver notification lookup from the Bolt driver API directory instead of manual config mappings.

SET NAMES utf8mb4;

ALTER TABLE mapping_drivers
  ADD COLUMN IF NOT EXISTS driver_identifier VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS individual_identifier VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS driver_email VARCHAR(190) NULL;
