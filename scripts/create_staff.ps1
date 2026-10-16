# PowerShell 7+. Passwords travel only through the child process's redirected input.
[CmdletBinding()]
param(
    [ValidateSet('admin','catalog_manager')][string]$Role,
    [string]$Email,
    [string]$Username,
    [string]$FullName,
    [string]$PhpBinary = 'php'
)
$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSVersion.Major -lt 7) { throw 'Run this helper with PowerShell 7 (pwsh).' }
if (!$Role) { $Role = Read-Host 'Role (admin or catalog_manager)' }
if ($Role -notin 'admin','catalog_manager') { throw 'Invalid staff role.' }
if (!$Email) { $Email = Read-Host 'Email' }
if (!$Username) { $Username = Read-Host 'Username' }
if (!$FullName) { $FullName = Read-Host 'Full name' }
$phpPath = (Get-Command $PhpBinary -CommandType Application -ErrorAction Stop).Source
$secret = Read-Host 'Password (at least 12 characters, upper/lowercase, number and special character)' -AsSecureString
$confirmation = Read-Host 'Confirm password' -AsSecureString
$firstPtr = [IntPtr]::Zero
$secondPtr = [IntPtr]::Zero
$child = $null
try {
    $firstPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
    $secondPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($confirmation)
    $passwordText = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($firstPtr)
    $confirmationText = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($secondPtr)
    if ($passwordText -cne $confirmationText) { throw 'Passwords do not match.' }
    if ($passwordText.Contains("`r") -or $passwordText.Contains("`n")) { throw 'Password cannot contain line breaks.' }
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = $phpPath
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardInput = $true
    $start.StandardInputEncoding = [Text.UTF8Encoding]::new($false)
    foreach ($argument in @((Join-Path $PSScriptRoot 'create_staff.php'),$Role,$Email,$Username,$FullName,'--password-stdin')) {
        $start.ArgumentList.Add($argument)
    }
    $child = [Diagnostics.Process]::Start($start)
    $child.StandardInput.WriteLine($passwordText)
    $child.StandardInput.Close()
    $child.WaitForExit()
    if ($child.ExitCode -ne 0) { throw 'Staff creation did not complete.' }
} finally {
    if ($firstPtr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($firstPtr) }
    if ($secondPtr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secondPtr) }
    $passwordText = $confirmationText = $null
    $secret.Dispose()
    $confirmation.Dispose()
    if ($child) { $child.Dispose() }
}
