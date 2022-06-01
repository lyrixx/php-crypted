# Run PHP encrypted code

## Usage

```
docker pull ghcr.io/lyrixx/php-crypted:latest
docker run -it --rm ghcr.io/lyrixx/php-crypted php index.php
docker run -it --rm ghcr.io/lyrixx/php-crypted cat index.php
docker run -it --rm ghcr.io/lyrixx/php-crypted cat src.php
```

## How it works

It uses a stream wrapper and a stream filter to be able to decrypt the code. It
also uses the auto_prepend_file configuration option to register the stream
wrapper.
