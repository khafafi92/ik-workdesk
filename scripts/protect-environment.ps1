param([Parameter(Mandatory = $true)][string]$Path)

$ErrorActionPreference = 'Stop'
$workspace = [System.IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$target = Get-Item -LiteralPath $Path -Force
if ($target.PSIsContainer -or ($target.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -or
    $target.DirectoryName -ne $workspace -or
    $target.Name -notmatch '^\.env(?:\.database-admin)?(?:\.security-tmp-[a-f0-9]{16})?$') {
    throw 'Hanya file environment workspace yang dapat dibatasi.'
}

$currentUser = [System.Security.Principal.WindowsIdentity]::GetCurrent().User
$acl = [System.Security.AccessControl.FileSecurity]::new()
$acl.SetAccessRuleProtection($true, $false)
foreach ($identity in @($currentUser, [System.Security.Principal.SecurityIdentifier]::new('S-1-5-18'), [System.Security.Principal.SecurityIdentifier]::new('S-1-5-32-544'))) {
    $rule = [System.Security.AccessControl.FileSystemAccessRule]::new($identity, 'FullControl', 'Allow')
    $acl.AddAccessRule($rule)
}
Set-Acl -LiteralPath $target.FullName -AclObject $acl
Write-Output ('PASS: izin file dibatasi untuk ' + $target.Name)
