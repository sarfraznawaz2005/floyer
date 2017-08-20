# Floyer

## DISCLAIMER ##

This tool is not fully tested and might be buggy. **Use it at your own risk!**

Currently works for my needs but if you find any bugs or something missing, please do send PR. Thanks

## Introduction ##

Floyer is simple and fast deployment tool using git and FTP - especially useful for shared hosting.

To run deployment, just type this on terminal: `php floyer deploy`. See below for command options. 

## Screenshot ##

![Main Window](https://raw.github.com/sarfraznawaz2005/floyer/master/screenshot.png)

## Requirements ##

 - PHP >= 5.6
 - `git` added to PATH env
 
## Command Options ##

- `php floyer deploy --sync` : Synchronize last local commit id with remote revision file.
- `php floyer deploy --history` : List files deployed in previous deployment.
- `php floyer deploy --rollback` : Rollback previous deployment.

## How it works ##

 - It stores revision/commit hash on the server in a file when deployment is started.
 - On next deployment, it compares local revision with remote one thereby able to deploy only the files changed between these two revisions.
 - Once it knows what files to upload, it creates zip archive of these files to be deployed on the server.
 - Rather than uploading each file individually (which is very **slow** process), it creates and uploads zip archive file to server where there is corresponding extract zip PHP script which extracts these files **very fast**. This script is also created and uploaded by Floyer.
 - After deployment is finished, zip archive and extract script is deleted automatically.

## Current Limitations ##

- Does not have multiple server suppport eg staging and production
- Works with current main git branch only
- Currenly only supports `FTP` connector
- Currenly only supports `git` driver
- Not fully tested especially `rollback` feature

## Extending ##

- You can extend it by adding more connectors like `SFTP`, etc. Checkout existing connector in `src/Connectors/FTP.php`
- You can extend it by adding more drivers like `svn`, etc Checkout existing driver in `src/Drivers/Git.php`

## License ##

This code is published under the [MIT License](http://opensource.org/licenses/MIT).
This means you can do almost anything with it, as long as the copyright notice and the accompanying license file is left intact.
