#! /usr/bin/php -q
<?php

// Code refactored / debugged by KB4FXC, 11/06/2018.
// Fixed issue where error conditions were always generated by private nodes.

// Updated to check for strictly private nodes
// and change load order - private first
// WA3DSP 2/2016
// KK9ROB 4/2021
// $Id $

// Original code by WD6AWP
// Modified to run immediately when called directly
// and load randomly within  30 minute window when
// called by cron

// Refactor to use CURL --- KB4FXC 11/19/2018
// Refactor to use ASL distro --- KK9ROB 04/19/2021

/* Refactored 2020-02-29 WD6AWP
   - Use file_get_contents for $url
   - Comment HamVoIP url
   - Add $dir for storage location
   - Reformat indentation
   - Minor edits to messages
 */

$dir = "/var/www/html/supermon/";
$db = "/var/log/asterisk/astdb.txt";
$privatefile = $dir."privatenodes.txt";

$retries = 0;
$Pcontents = '';
$private = getenv('PRIVATE_NODE');
$contents = '';
$contents2 = '';

$url = "http://allmondb.allstarlink.org/";

// Load private nodes if any
// Private nodes are less than 2000 and loaded first
// to ensure proper order of file
if (file_exists($privatefile)) {
    $Pcontents .= file_get_contents($privatefile);
}

// Check if ONLY private nodes
// In that case do not read Allstar database
// Maintains compatibility with pre 1.3 versions
// assumes empty environment variable
if (is_null($private) || !$private) {  // If not a private node...
    // If called by cron wait between 0 and 30 min
    if (isset($argv[1]) && $argv[1] == 'cron') {
        $seconds = mt_rand(0, 1800);
        print "Waiting for $seconds seconds...\n";
        while ($seconds > 0) {
            if ($seconds > 60) {
                print "Sleeping for $seconds seconds...\n";
                sleep(60);
                $seconds = $seconds - 60;
            } else {
                print "Sleeping for $seconds seconds...\n";
                sleep($seconds);
                $seconds = 0;
            }
        }
    }

    while (true) {
        // Open AllStar db URL and retrieve file.
        $contents2 = @file_get_contents($url);

        // Test size.
        $size = strlen($contents2);
        if ($size < 300000) {
            if ($retries >= 5) {
                die ("astdb.txt: Retries exceeded!!  $size bytes - Invalid: file too small, bailing out.\n");
            }

            $retries++;
            print "Retry $retries of 5. Will retry $url\n";
            sleep(5);
        } else {
            break;
        }
    }
} // End private node check

$contents = $Pcontents;
$contents .= $contents2;

// Added to strip non printing characters.
$contents = preg_replace('/[\x00-\x09\x0B-\x0C\x0E-\x1F\x7F-\xFF]/', '', $contents);

// Save the data
if (!($fh = fopen($db, 'w'))) {
    die("Cannot open $db.");
}
if (!flock($fh, LOCK_EX)) {
    echo 'Unable to obtain lock.';
    exit(-1);
}
if (fwrite($fh, $contents) === false) {
    die ("Cannot write $db.");
}
fclose($fh);

$size = strlen($contents);
print "Success: astdb.txt $size bytes\n";
?>