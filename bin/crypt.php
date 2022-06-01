#!/usr/bin/env php
<?php

$filename = $argv[1] ?? false or exit("No filename given\n");

if (file_exists(__DIR__ . '/key.data')) {
    $key = file_get_contents(__DIR__ . '/key.data');
} else {
    $key = sodium_crypto_secretbox_keygen();
    file_put_contents(__DIR__ . '/key.data', $key);
}

$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

$encrypted = sodium_crypto_secretbox(file_get_contents($filename), $nonce, $key);

file_put_contents($filename, sprintf("%s\n%s", $nonce, $encrypted));
