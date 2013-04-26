#Cloner

Cloner is a tool to backup ftp-directories to your local machine. It´s written in PHP using the CodeIgniter framework.
Cloner is a command-line-script and you can run it as a cronjob.

Call Cloner like this: ``usr/bin/php PATH/TO/SCRIPT/Cloner/index.php Cloner clone_all``

You need the following tables in ``application/database/cloner.sqlite``

```sql
CREATE TABLE backups
(
    backupsid INTEGER PRIMARY KEY,
    folder TEXT,
    timestamp INTEGER,
    projectsid INTEGER
);
``

```sql
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
```

##Usage
1. Download a copy of the repository to local destination directory
2. Enter projects into the table ``projects`` You need to provide those fields:
 1. ``ftp_host`` url of the ftp-server
 2. ``ftp_user`` user of the ftp-account
 3. ``ftp_password`` the password of the account
 4. ``ftp_dir`` if the directory is a sub-directory
 5. ``intervall`` number of days... clone this project every n days
 6. ``destination`` name of the destination folder
3. the default download-dir is **backup** 
