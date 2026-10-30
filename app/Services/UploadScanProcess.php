<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

/** Bounded execution without pipes or a shell; scanner diagnostics never contain shared application logs. */
final class UploadScanProcess
{
    public function run(array $command, float $seconds): int
    {
        if ($seconds<=0 || $seconds>15 || !$command || !function_exists('proc_open')) throw new RuntimeException('Image security check unavailable.');
        $null=PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null';
        $process=null;$deadline=hrtime(true)+(int)($seconds*1_000_000_000);
        try {
            $process=@proc_open($command,[0=>['file',$null,'r'],1=>['file',$null,'w'],2=>['file',$null,'w']],$pipes,null,null,['bypass_shell'=>true]);
            if (!is_resource($process)) throw new RuntimeException('Image security check unavailable.');
            do {
                $status=proc_get_status($process);
                if (!$status['running']) return $status['exitcode'];
                if (hrtime(true)>=$deadline) throw new RuntimeException('Image security check timed out. Please try again later.');
                usleep(10000);
            } while(true);
        } finally {
            if (is_resource($process)) {
                $status=proc_get_status($process);
                if ($status['running']) {
                    // Only our CLI client. Never cancel other Defender scans or terminate service processes.
                    proc_terminate($process,9);$until=hrtime(true)+500_000_000;
                    do {$status=proc_get_status($process);if(!$status['running'])break;usleep(10000);}while(hrtime(true)<$until);
                }
                // Never wait indefinitely in proc_close. A timed-out file remains unpublished regardless.
                if (!$status['running']) proc_close($process);
                else throw new RuntimeException('Image security check could not stop safely.');
            }
        }
    }
}
