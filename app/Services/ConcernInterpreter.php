<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ConcernInterpreter
{
    public function interpret(string $text): array
    {
        if (!mb_check_encoding($text, 'UTF-8') || mb_strlen($text, 'UTF-8') > 500 || strlen($text) > 2000) {
            throw new \InvalidArgumentException('Keep your concern description to 500 characters.');
        }
        if (trim($text) === '') return ['version' => '1', 'status' => 'empty', 'tags' => [], 'matches' => [], 'warnings' => []];
        if (!function_exists('proc_open') || (PHP_OS_FAMILY !== 'Windows' && !function_exists('posix_kill'))) {
            throw new RuntimeException('Text interpretation is unavailable.');
        }
        $input = tmpfile();
        $output = tmpfile();
        $process = null;
        if ($input === false || $output === false) {
            if (is_resource($input)) fclose($input);
            if (is_resource($output)) fclose($output);
            throw new RuntimeException('Text interpretation is unavailable.');
        }
        try {
            // Anonymous request files are removed on close; use a private service-owned temp directory.
            $json = json_encode(['text' => $text], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (fwrite($input, $json) !== strlen($json) || !rewind($input)) throw new RuntimeException('Text interpretation is unavailable.');
            $binary = (string) env('NLP_PYTHON_BINARY', 'python');
            $deadline = hrtime(true) + 3_000_000_000;
            // No shell and no customer-controlled arguments. Windows pipe reads cannot be made nonblocking.
            $process = @proc_open([$binary, '-I', '-B', dirname(__DIR__, 2) . '/nlp/runner.py'],
                [0 => $input, 1 => $output, 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']],
                $pipes, null, null, ['bypass_shell' => true]);
            if (!is_resource($process)) throw new RuntimeException('Text interpretation is unavailable.');
            $exit = -1;
            do {
                if ((fstat($output)['size'] ?? 32769) > 32768) throw new RuntimeException('Text interpretation is unavailable.');
                if (hrtime(true) >= $deadline) {
                    throw new RuntimeException('Text interpretation timed out. Try again or leave the optional text blank.');
                }
                $status = proc_get_status($process);
                if (!$status['running']) { $exit = $status['exitcode']; break; }
                usleep(10000);
            } while (true);
            rewind($output);
            $content = stream_get_contents($output, 32769);
            if ($exit !== 0 || $content === false || strlen($content) > 32768) throw new RuntimeException('Text interpretation is unavailable.');
            $result = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($result) || !is_array($result['tags'] ?? null) || !is_array($result['matches'] ?? null)
                || !is_array($result['warnings'] ?? null) || !in_array($result['status'] ?? '', ['recognized', 'unknown'], true)) {
                throw new RuntimeException('Text interpretation is unavailable.');
            }
            return $result;
        } finally {
            try {
                if (is_resource($process)) $this->closeProcess($process);
            } finally {
                fclose($input);
                fclose($output);
            }
        }
    }

    private function closeProcess($process): void
    {
        $status = proc_get_status($process);
        if ($status['running']) {
            // The fixed runner owns a Windows kill-on-close job (or a private POSIX process group).
            // Only this invocation and its descendants are terminated; never search by executable name.
            if (PHP_OS_FAMILY !== 'Windows' && function_exists('posix_kill')) @posix_kill(-$status['pid'], 9);
            proc_terminate($process, 9);
            $cleanupDeadline = hrtime(true) + 500_000_000;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) break;
                usleep(10000);
            } while (hrtime(true) < $cleanupDeadline);
            if ($status['running']) throw new RuntimeException('Text interpretation could not stop safely.');
        }
        // Never wait in proc_close while an interpreter is still running.
        proc_close($process);
    }
}
