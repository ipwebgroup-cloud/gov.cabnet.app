#!/usr/bin/env bash
set -euo pipefail

LOG="/home/cabnet/gov.cabnet.app_app/storage/logs/edxeix_candidate_watch.log"

{
  echo "[$(date '+%F %T %Z')] EDXEIX candidate watch tick"

  /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/sync_bolt.php --hours=6 >/dev/null 2>&1 || true

  /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_prelive_audit.php --future-hours=24 --limit=80 --only-candidates --json \
    | grep -E '"prelive_candidates"|"live_submission_allowed_now"|"edxeix_queues_unchanged"|"edxeix_session_connected"|"require_one_shot_lock"' || true

  mysql cabnet_gov -e "SELECT COUNT(*) AS submission_jobs FROM submission_jobs; SELECT COUNT(*) AS submission_attempts FROM submission_attempts;"
  echo "----"
} >> "$LOG" 2>&1
