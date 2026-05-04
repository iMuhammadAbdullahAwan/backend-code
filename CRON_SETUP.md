# CRON Jobs Setup

To automatically sync data from Firebase into the CI4 MySQL database, add the following CRON jobs to your server:

```bash
# Sync sensor readings every minute
* * * * * php /path/to/project/public/index.php FirebaseSync syncSensors

# Sync device list every 10 minutes
*/10 * * * * php /path/to/project/public/index.php FirebaseSync syncDevices
```

Make sure to replace `/path/to/project` with the actual path to your CodeIgniter 4 installation.
