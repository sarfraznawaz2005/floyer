# Floyer

## DISCLAIMER ##

This tool is not fully tested, **use it at your own risk!**

Currently works for my needs but if you find any bugs or something missing, please do send PR. Thanks

## Introduction ##

Floyer is simple and fast deployment tool using git/svn and (S)FTP - especially useful for shared hosting.

To run deployment, just type this on terminal: `php floyer deploy ini_file_to_use`. See below for commands.

## Screenshot ##

![Main Window](https://raw.githubusercontent.com/sarfraznawaz2005/floyer/master/screenshot.png)

## Requirements ##

 - PHP >= 5.6
 - `git` added to PATH env (If using Git driver)
 - `svn` added to PATH env (If using Svn driver)
 - `FTP` and `Zip` PHP extensions (both ship with PHP and usually turned on)
 
## Command Options ##

- `php floyer deploy ini_file_to_use --sync` : Synchronize last local revision id with remote revision file.
- `php floyer deploy ini_file_to_use --history` : List files deployed in previous deployment.
- `php floyer deploy ini_file_to_use --rollback` : Rollback previous deployment.

The `ini_file_to_use` is ini server config file that you can create copying from given sample file `floyer-sample.ini`. For different servers, you need to create different ini config files. 

Because these ini config file contain server connection details, make sure to gitignore them in your project.

## How it works ##

 - It stores revision/commit hash on the server in a file when deployment is started.
 - On next deployment, it compares local revision with remote one thereby able to deploy only the files changed between these two revisions.
 - Once it knows what files to upload, it creates zip archive of these files to be deployed on the server.
 - Rather than uploading each file individually (which is very **slow** process), it creates and uploads zip archive file to server where there is corresponding extract zip PHP script which extracts these files **very fast**. This script is also created and uploaded by Floyer.
 - After deployment is finished, zip archive and extract script is deleted automatically.

Deploying by uploading and extracting archive file not only makes deployment fast but also we don't have to worry about some permission issues or creating new directories and so on because extract script runs from server itself thereby avoiding these issues.

## Current Limitations ##

- Works with current main git branch only
- Not fully tested especially `rollback` feature

## Download ##

The phar version is present at `dist/floyer.phar`. Once you download it, copy it to your project and issue this command at terminal:

`php floyer.phar deploy ini_file_to_use` or if you rename `floyer.phar` to `floyer`:

`php floyer deploy ini_file_to_use`

## Tip ##

If you only upload to single server or don't want to type `ini_file_to_use` again and again, you can create a file called `floyer_default_server.txt` in project root directory and in that file type ini file name you want to use as default then you can skip typing `ini_file_to_use` in commands like:

 `php floyer deploy`
 
 `php floyer deploy --history`
 
However, you can still use `ini_file_to_use` argument if you wish to upload to differnt server.

## Extending ##

- You can extend it by adding more connectors. Checkout existing connectors at `src/Connectors`
- You can extend it by improving existing `Svn` and `Git` drivers. Checkout existing drivers at `src/Drivers`

## Similar Project ##
 - [gitup](https://github.com/sarfraznawaz2005/gitup)

## License ##

This code is published under the [MIT License](http://opensource.org/licenses/MIT).
This means you can do almost anything with it, as long as the copyright notice and the accompanying license file is left intact.
