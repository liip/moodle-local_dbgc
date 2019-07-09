# local/dbgc - Moodle Database Garbage Collector

[![Build Status](https://travis-ci.org/liip/moodle-local_dbgc.svg?branch=master)](https://travis-ci.org/liip/moodle-local_dbgc)

## Motivation

Some Moodle databases get brutalized over the course of their lifetimes; through Moodle bugs, arbitrary tables' truncation, or other actions on the database.
This can lead to all sorts of weird effects, sometimes much later in the Moodle instance's lifetime.

As an example, a bug was occuring in which some submitted PDF assignments were getting totally unrelated annotations, even before a teacher would have opened the submission.
This ended up being caused by the presence of old entries in the annotations table, referring to removed submissions. After some time, new submissions would get these now-available ids again.

In database language, the problem that this plugin tries to solve is the identification, backup and removal of table entries with broken foreign keys.

## How it works

Using Moodle's XMLDB structures, the `/local/dbgc/index.php` admin page will:

* loop over all registered foreign key definitions;
* count the number of entries with mispointing foreign keys;
* display a report of the affected tables-key pairs, with the number of affected entries.

From that report, an admin can either:

* trigger an ad'hoc task to backup and remove all affected entries;
* launch the backup and removal of all affected entries directly in the Moodle admin interface.

# Authors

This plugin was developed by [Liip AG](https://www.liip.ch/), the swiss Moodle Partner.

Development and open-sourcing of this plugin was made possible thanks to funding by the [École polytechnique fédérale de Lausanne (EPFL)](https://www.epfl.ch/).
