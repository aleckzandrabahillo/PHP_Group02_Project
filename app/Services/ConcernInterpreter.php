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
        if (!function_exists('proc_open')) throw new RuntimeException('Text interpretation is unavailable.');
        $binary = (string) env('NLP_PYTHON_BINARY', 'python');
        // Configuration is server-owned. Neither customer text nor command arguments reach a shell.
        $process = @proc_open([$binary, '-I', '-B', dirname(__DIR__, 2) . '/nlp/interpret.py'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) throw new RuntimeException('Text interpretation is unavailable.');
        try {
            fwrite($pipes[0], json_encode(['text' => $text], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $output = '';
            $deadline = microtime(true) + 3;
            $exit = -1;
            do {
                $output .= stream_get_contents($pipes[1]);
                stream_get_contents($pipes[2]); // Never log submitted concerns or interpreter diagnostics.
                if (strlen($output) > 32768 || microtime(true) > $deadline) {
                    proc_terminate($process);
                    throw new RuntimeException('Text interpretation timed out. Try again or leave the optional text blank.');
                }
                $status = proc_get_status($process);
                if (!$status['running']) { $exit = $status['exitcode']; break; }
                usleep(10000);
            } while (true);
            $output .= stream_get_contents($pipes[1]);
            if ($exit !== 0 || strlen($output) > 32768) throw new RuntimeException('Text interpretation is unavailable.');
            $result = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($result) || !is_array($result['tags'] ?? null) || !is_array($result['matches'] ?? null)
                || !is_array($result['warnings'] ?? null) || !in_array($result['status'] ?? '', ['recognized', 'unknown'], true)) {
                throw new RuntimeException('Text interpretation is unavailable.');
            }
            return $result;
        } finally {
            foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close($process);
        }
    }
}
