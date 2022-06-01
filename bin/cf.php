<?php

const STREAM_OPEN_FOR_INCLUDE = 128;

final class StreamContext
{
    public string $path;
    public string $ext;
    public string $content;

    public function __construct(string $path) {
        $this->path = $path;
        $this->ext = pathinfo($path, PATHINFO_EXTENSION);
    }
}

final class CryptoFixer
{
    public static string $key;

    public static function register()
    {
        self::$key = file_get_contents(__DIR__ . '/key.data');

        StreamFilter::register();
        StreamWrapper::register();
    }
}

final class StreamWrapper
{
    const PROTOCOLS = ['file', 'phar'];

    public static function register()
    {
        foreach (self::PROTOCOLS as $protocol) {
            stream_wrapper_unregister($protocol);
            stream_wrapper_register($protocol, self::class);
        }
    }

    public static function unregister()
    {
        foreach (self::PROTOCOLS as $protocol) {
            set_error_handler(function () {
            });
            stream_wrapper_restore($protocol);
            restore_error_handler();
        }
    }

    public function dir_closedir(): bool
    {
        closedir($this->resource);

        return true;
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $this->resource = $this->wrapCall('opendir', $path);

        return false !== $this->resource;
    }

    public function dir_readdir()
    {
        return readdir($this->resource);
    }

    public function dir_rewinddir(): bool
    {
        rewinddir($this->resource);

        return true;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);

        return $this->wrapCall('mkdir', $path, $mode, $recursive);
    }

    public function rename(string $path_from, string $path_to): bool
    {
        return $this->wrapCall('rename', $path_from, $path_to);
    }

    public function rmdir(string $path, int $options): bool
    {
        return $this->wrapCall('rmdir', $path);
    }

    public function stream_cast(int $cast_as)
    {
        return $this->resource;
    }

    public function stream_close()
    {
        fclose($this->resource);
    }

    public function stream_eof(): bool
    {
        return feof($this->resource);
    }

    public function stream_flush(): bool
    {
        return fflush($this->resource);
    }

    public function stream_lock(int $operation): bool
    {
        return flock($this->resource, $operation);
    }

    public function stream_metadata(string $path, int $option, $value): bool
    {
        return (bool) $this->wrapCall(function (string $path, int $option, $value) {
            switch ($option) {
                case STREAM_META_TOUCH:
                    if (empty($value)) {
                        $result = touch($path);
                    } else {
                        $result = touch($path, $value[0], $value[1]);
                    }
                    break;
                case STREAM_META_OWNER_NAME:
                case STREAM_META_OWNER:
                    $result = chown($path, $value);
                    break;
                case STREAM_META_GROUP_NAME:
                case STREAM_META_GROUP:
                    $result = chgrp($path, $value);
                    break;
                case STREAM_META_ACCESS:
                    $result = chmod($path, $value);
                    break;
            }
        }, $path, $option, $value);
    }

    public function stream_open(string $path, string $mode, int $options, string &$opened_path = null): bool
    {
        $useIncludePath = (bool) ($options & STREAM_USE_PATH);

        $this->resource = $this->wrapCall('fopen', $path, $mode, $useIncludePath);

        $including = (bool) ($options & STREAM_OPEN_FOR_INCLUDE);

        if ($including && false !== $this->resource) {
            $this->context = new StreamContext($path);
            StreamFilter::append($this->resource, $this->context);
        }

        return false !== $this->resource;
    }

    public function stream_read(int $count): string
    {
        return fread($this->resource, $count);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        return fseek($this->resource, $offset, $whence);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                return stream_set_blocking($this->resource, $arg1);
            case STREAM_OPTION_READ_TIMEOUT:
                return stream_set_timeout($this->resource, $arg1, $arg2);
            case STREAM_OPTION_WRITE_BUFFER:
                return stream_set_write_buffer($this->resource, $arg1);
            case STREAM_OPTION_READ_BUFFER:
                return stream_set_read_buffer($this->resource, $arg1);
        }
    }

    public function stream_stat(): array
    {
        $stat = fstat($this->resource);

        if (!$this->context instanceof StreamContext) {
            return $stat;
        }

        // This method is called before the filter is applied.
        // So need to apply the filter manually before to get the new size.

        unset($stat['size']);

        return $stat;
    }

    public function stream_tell(): int
    {
        return ftell($this->resource);
    }

    public function stream_truncate(int $new_size): bool
    {
        return ftruncate($this->resource, $new_size);
    }

    public function stream_write(string $data): int
    {
        return fwrite($this->resource, $data);
    }

    public function unlink(string $path): bool
    {
        return $this->wrapCall('unlink', $path);
    }

    public function url_stat(string $path, int $flags)
    {
        $result = @$this->wrapCall('stat', $path);
        if (false === $result) {
            $result = null;
        }

        return $result;
    }

    private function wrapCall(callable $function, ...$args)
    {
        try {
            foreach (self::PROTOCOLS as $protocol) {
                set_error_handler(function () {
                });
                stream_wrapper_restore($protocol);
                restore_error_handler();
            }

            return $function(...$args);
        } catch (\Throwable $e) {
            return false;
        } finally {
            foreach (self::PROTOCOLS as $protocol) {
                stream_wrapper_unregister($protocol);
                stream_wrapper_register($protocol, self::class);
            }
        }
    }
}

class StreamFilter extends php_user_filter
{
    const NAME = 'test';

    protected $buffer = '';

    public static function append($resource, StreamContext $context)
    {
        stream_filter_append(
            $resource,
            self::NAME,
            STREAM_FILTER_READ,
            [
                'context' => $context,
            ]
        );
    }

    public static function register()
    {
        stream_filter_register(self::NAME, static::class);
    }

    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $this->buffer .= $bucket->data;
            $consumed += $bucket->datalen;
        }
        if ($closing) {
            $buffer = $this->doFilter($this->buffer, $this->params['context']);
            $bucket = stream_bucket_new($this->stream, $buffer);
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }

    public static function doFilter(string $buffer, StreamContext $context): string
    {
        if (isset($context->content)) {
            return $context->content;
        }

        if ('php' !== $context->ext) {
            return $buffer;
        }

        [$nonce, $encrypted] = explode("\n", $buffer, 2);

        $decrypted = sodium_crypto_secretbox_open($encrypted, $nonce, CryptoFixer::$key);

        return $decrypted;
    }
}

CryptoFixer::register();
