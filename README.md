SongFormatter
=============

Pre-formatting script for importing multi-language Songbeamer songs into OpenLP

When importing scripts from the commercial Songbeamer lyrics projection package into the open-source OpenLP, 
one of the major problems is that OpenLP does not support multilingual songs as of yet. A common workaround is
to use OpenLP's freely definable tags to define colors for each language.

This PHP cli script takes all *.sng files in the current directory and tags up to 9 languages with configurable
tags, which can then be read by OpenLP. It also does UTF-8 conversion, some additional title formatting and 
rudimentary extraction of CCLI numbers.

Usage
-----

Place the script in a folder with your *.sng files and run it like `./SongFormat.php`.

Configuration
-------------

The script allows you to freely define the tags assigned to the various languages by editing the `$languageColors` array at the beginning of the file.

Author
------

This script was written by Christoph Fischer (christoph.fischer@volksmission.de) for Volksmission Freudenstadt (www.volksmission-freudenstadt.de).