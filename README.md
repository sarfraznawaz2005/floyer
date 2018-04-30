# Floyer

## DISCLAIMER ##

This tool is not fully tested, **use it at your own risk!**

Currently works for my needs but if you find any bugs or something missing, please do send PR. Thanks

## Introduction ##

Floyer is simple and fast deployment tool using git/svn and (S)FTP - especially useful for shared hosting.

To run deployment, just type this on terminal: `php floyer deploy`. See below for command options. 

## Screenshot ##

![Main Window](https://raw.githubusercontent.com/sarfraznawaz2005/floyer/master/screenshot.png)

## Requirements ##

 - PHP >= 5.6
 - `git` added to PATH env (If using Git driver)
 - `svn` added to PATH env (If using Svn driver)
 - `FTP` and `Zip` PHP extensions (both ship with PHP and usually turned on)
 
## Command Options ##

- `php floyer deploy --sync` : Synchronize last local revision id with remote revision file.
- `php floyer deploy --history` : List files deployed in previous deployment.
- `php floyer deploy --rollback` : Rollback previous deployment.

## How it works ##

 - It stores revision/commit hash on the server in a file when deployment is started.
 - On next deployment, it compares local revision with remote one thereby able to deploy only the files changed between these two revisions.
 - Once it knows what files to upload, it creates zip archive of these files to be deployed on the server.
 - Rather than uploading each file individually (which is very **slow** process), it creates and uploads zip archive file to server where there is corresponding extract zip PHP script which extracts these files **very fast**. This script is also created and uploaded by Floyer.
 - After deployment is finished, zip archive and extract script is deleted automatically.

Deploying by uploading and extracting archive file not only makes deployment fast but also we don't have to worry about some permission issues or creating new directories and so on because extract script runs from server itself thereby avoiding these issues.

## Current Limitations ##

- Does not have multiple server support eg staging and production
- Works with current main git branch only
- Not fully tested especially `rollback` feature

## Download ##

The phar version is present at `dist/floyer.phar`. Once you download it, copy it to your project and issue this command at terminal:

`php floyer.phar deploy` or if you rename `floyer.phar` to `floyer`:

`php floyer deploy`

## Extending ##

- You can extend it by adding more connectors. Checkout existing connectors at `src/Connectors`
- You can extend it by improving existing `Svn` and `Git` drivers. Checkout existing drivers at `src/Drivers`

## Similar Project ##
 - [gitup](https://github.com/sarfraznawaz2005/gitup)

## License ##

This code is published under the [MIT License](http://opensource.org/licenses/MIT).
This means you can do almost anything with it, as long as the copyright notice and the accompanying license file is left intact.
