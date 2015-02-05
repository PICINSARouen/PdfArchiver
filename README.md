PdfArchiver
===============

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE.md)
[![Latest Version](https://img.shields.io/github/release/AntoineAugusti/PdfArchiver.svg?style=flat)](https://github.com/AntoineAugusti/PdfArchiver/releases)

## What's the goal?
The goal is to easily archive generated PDF documents from one location (the "local" location) to another location (the "remote" one).

Let's say we have a local architecture like this:
```
company/
├── first-folder
│   ├── makefile
│   ├── document.tex
│   ├── pdf
│   │   └── document.pdf
│   └── report
│       ├── makefile
│       ├── report.tex
│       └── pdf
│           └── report.pdf
├── second-folder
│   ├── makefile
│   ├── reporting.tex
│   └── pdf
│       └── reporting.pdf
└── dummy
```

We will transfer PDF files to the remote filesystem with this architecture:
```
company/
├── first-folder
│   ├── document.pdf
│   └── report
│       ├── report.pdf
├── second-folder
│   ├── reporting.pdf
```

You **don't need** to create sub directories on the remote filesytem, they will automatically be created when copying PDF files to the remote filesystem.

## Installation

[PHP](https://php.net) 5.4+ or [HHVM](http://hhvm.com) 3.2+, and [Composer](https://getcomposer.org) are required.

To get the latest version of PdfArchiver, just run the following command from your Terminal:
```bash
$ composer require antoineaugusti/pdfarchiver
```
And then pull the dependencies with the following command:
```bash
$ composer install
```

## Filesystem adapters
PdfArchiver relies on the awesome [Flysystem package from The PHP League](http://flysystem.thephpleague.com). A lot of adapters are available in the documentation. Determine which adapters you'll need for your local and remote filesystems and then let the `Antoineaugusti\PdfArchiver\Console\MoverCommand` class do the work for you.

### Example: local filesystem to SFTP server
For example, let's say you want to move generated PDFs from your local machine to a SFTP server. An example is given [here](examples/local-sftp.php).

Steal the example file and place it at the root of this directory. Replace configuration values with your needs and you're good to go.

**Don't forget to add dependencies for your adapters** in your `composer.json` file and then run `composer update`.

## How to run
*`commands` is the file placed at the root of this directory where you have previously wired your adapters to the MoverCommand class*.

Once you've chosen the right adapters (don't forget to pull dependencies with `composer update`) and you've set your configuration values, it will be very easy. The `Antoineaugusti\PdfArchiver\Console\MoverCommand` class will search recursively from the root folder you have defined in your local adapter with the following command:

```bash
$ php commands archive
```

### Starting from a subfolder
If you don't want to start at the defined root folder, but somewhere else, just give the relative path as the first argument:
```bash
$ php commands archive example/subfolder
```

### Generating PDF files
Since we are relying on the existence of a `makefile` and `pdf` folder, you may want to generate your PDF files before moving them to a remote location. Just pass the option `--make` when calling the script:

```bash
$ php commands archive example/subfolder --make
```

## Contributing
Contributions are very welcome. This package is pretty simple right now and it only suits my needs. Feel free to open a PR to add some options or additional behavior!