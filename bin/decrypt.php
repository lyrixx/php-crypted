#!/usr/bin/env php
<?php

if (!file_exists(__DIR__ . '/key.data')) {
    exit('Missing key.data file.');
}

$key = file_get_contents(__DIR__ . '/key.data');

$filename = $argv[1] ?? false or exit("No filename given\n");

[$nonce, $encrypted] = explode("\n", file_get_contents($filename), 2);

$decrypted = sodium_crypto_secretbox_open($encrypted, $nonce, $key);
file_put_contents($filename, $decrypted);
