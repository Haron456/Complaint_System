#!/bin/bash
# Backs up the complaint system database (SRS NFR-13).
# Each run makes a compressed copy in ~/complaint_system_backups and
# deletes copies older than 14 days.
BACKUP_DIR="$HOME/complaint_system_backups"
mkdir -p "$BACKUP_DIR"
/opt/lampp/bin/mysqldump -u root complaint_system | gzip > "$BACKUP_DIR/complaint_system_$(date +%F_%H%M).sql.gz"
find "$BACKUP_DIR" -name 'complaint_system_*.sql.gz' -mtime +14 -delete
echo "$(date '+%F %T') backup finished"
