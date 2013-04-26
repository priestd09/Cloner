Invoke like this:
/usr/bin/php PATH/TO/SCRIPT/Cloner/index.php Cloner clone_all

```sql
CREATE TABLE backups
(
    backupsid INTEGER PRIMARY KEY,
    folder TEXT,
    timestamp INTEGER,
    projectsid INTEGER
);
```

CREATE TABLE projects
(
    projectsid INTEGER PRIMARY KEY,
    ftp_host TEXT,
    ftp_user TEXT,
    ftp_password TEXT,
    db_host TEXT,
    db_user TEXT,
    db_password TEXT,
    skip INTEGER DEFAULT 0,
    ftp_dir TEXT,
    interval INTEGER DEFAULT 10,
    last_clone TEXT,
    destination TEXT
);

