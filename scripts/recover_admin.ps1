param([Parameter(Mandatory=$true)][string]$Actor,[Parameter(Mandatory=$true)][string]$Target,[Parameter(Mandatory=$true)][string[]]$DatabaseGuards,[string]$Php='php',[switch]$SendCode)
$ErrorActionPreference='Stop'
$script=Join-Path $PSScriptRoot 'recover_admin.php'
if($SendCode){& $Php $script "--actor=$Actor" "--target=$Target" @DatabaseGuards --send-code;exit $LASTEXITCODE}
$password=Read-Host 'Administrator current password (hidden)' -AsSecureString
$code=Read-Host 'Authenticator, unused recovery code or emailed code (hidden)' -AsSecureString
$ptr1=[IntPtr]::Zero;$ptr2=[IntPtr]::Zero
try{
 $ptr1=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($password);$ptr2=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($code)
 $first=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr1);$second=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr2)
 @($first,$second)|& $Php $script "--actor=$Actor" "--target=$Target" @DatabaseGuards --apply --proof-stdin
 exit $LASTEXITCODE
}finally{
 if($ptr1 -ne [IntPtr]::Zero){[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr1)}
 if($ptr2 -ne [IntPtr]::Zero){[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr2)}
 $first=$null;$second=$null;$password.Dispose();$code.Dispose()
}
