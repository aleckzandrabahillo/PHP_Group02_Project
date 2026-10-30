# Optional upload antivirus scanning

All product/profile image flows share actual MIME/extension, byte-size, dimension and GD-decoding validation. The current owner-approved limit is 2.5 MB. Those controls are not antivirus scanning.

`UPLOAD_SCANNER=disabled` preserves the existing deployment until a separately verified rollout. To enable the implemented Windows integration, set `UPLOAD_SCANNER=defender` and `UPLOAD_DEFENDER_BINARY` to the verified Microsoft-signed `MpCmdRun.exe` in the installed Defender Platform or Program Files directory. Do not elevate PHP, loosen Windows permissions or exempt the upload directory. Confirm that Defender is active/current and the actual service identity can scan a private clean fixture; a missing or inaccessible engine must block uploads.

Before any public file is saved, the PHP-owned temporary upload is passed to a fixed custom-file scan with `-Scan -ScanType 3 -File ... -DisableRemediation`. With remediation disabled, only a successful clean result is accepted. All nonzero results (detection **or** scanner error), timeouts and invalid configurations reject the upload. No scanner output, personal filename or detection text enters application logs. Invocation uses an argument array without a shell. It has a 15-second deadline plus at most 0.5 seconds of CLI cleanup; only that CLI process is terminated, never the Defender service or unrelated scans. A background service scan may finish separately after a timeout; the rejected upload is never published.

Use owner/service-private PHP temporary storage. Existing authorization, CSRF, randomized image names, image decoding, cleanup and business/audit transactions remain in place. Windows Defender is an OS prerequisite, not a Composer dependency. No claim of malware-free uploads is justified merely by enabling this flag.

Acceptance requires a genuine clean-file scan, an approved harmless antivirus test fixture, scanner-unavailable/timeout rejection, no public files or partial records on rejection, and genuine multipart uploads for Customer, Catalog Manager and Administrator. Simulated process exit codes validate failure handling but are not evidence of actual malware detection. No real malware is needed. Re-verify the executable path/signature after platform updates.

Reference: [Microsoft Defender command-line documentation](https://learn.microsoft.com/en-us/defender-endpoint/command-line-arguments-microsoft-defender-antivirus).
