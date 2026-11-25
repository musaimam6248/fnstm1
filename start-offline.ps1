param(
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $projectRoot

function Resolve-PhpExe {
    function CanRunPhp([string]$ExePath) {
        try {
            if (-not (Test-Path -LiteralPath $ExePath)) { return $false }
            & $ExePath -v *> $null
            return $LASTEXITCODE -eq 0
        } catch {
            return $false
        }
    }

    if ($env:PHP_EXE -and (CanRunPhp $env:PHP_EXE)) {
        return (Resolve-Path -LiteralPath $env:PHP_EXE).Path
    }

    $candidates = @(
        (Join-Path $projectRoot "php\php.exe"),
        (Join-Path $projectRoot "xampp\php\php.exe"),
        (Join-Path $projectRoot "xampp\php\windowsXamppPhp\php.exe"),
        (Join-Path $env:USERPROFILE "OneDrive\Desktop\school1\xampp\php\windowsXamppPhp\php.exe"),
        (Join-Path $env:USERPROFILE "Desktop\school1\xampp\php\windowsXamppPhp\php.exe"),
        "C:\xampp\php\php.exe",
        "C:\xamppp\php\php.exe",
        "C:\php\php.exe"
    )

    foreach ($candidate in $candidates) {
        if (CanRunPhp $candidate) {
            return (Resolve-Path -LiteralPath $candidate).Path
        }
    }

    $phpCmd = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCmd -and $phpCmd.Source -and (CanRunPhp $phpCmd.Source)) {
        return $phpCmd.Source
    }

    throw "PHP executable not found. Install PHP 8+ or set PHP_EXE to your php.exe path."
}

function Get-ListeningPids {
    param([int]$CheckPort)

    $pids = @()
    if (Get-Command Get-NetTCPConnection -ErrorAction SilentlyContinue) {
        $pids = @(Get-NetTCPConnection -LocalPort $CheckPort -State Listen -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess -Unique)
    }

    if (-not $pids -or $pids.Count -eq 0) {
        $matches = @(netstat -ano | Select-String (":$CheckPort\s+.*LISTENING"))
        foreach ($line in $matches) {
            $parts = ($line.Line -split "\s+") | Where-Object { $_ -ne "" }
            if ($parts.Count -gt 0) {
                $last = $parts[$parts.Count - 1]
                if ($last -match "^\d+$") {
                    $pids += [int]$last
                }
            }
        }
        $pids = @($pids | Select-Object -Unique)
    }

    return $pids
}

$phpExe = Resolve-PhpExe
$phpDir = Split-Path -Parent $phpExe
$extDir = Join-Path $phpDir "ext"

$phpArgs = @()
if (Test-Path -LiteralPath $extDir) {
    $phpArgs += "-n"
    $phpArgs += "-d"
    $phpArgs += "extension_dir=$extDir"

    $neededExtensions = @("pdo_sqlite", "sqlite3", "gd", "mbstring", "fileinfo")
    foreach ($ext in $neededExtensions) {
        $dllPath = Join-Path $extDir ("php_{0}.dll" -f $ext)
        if (Test-Path -LiteralPath $dllPath) {
            $phpArgs += "-d"
            $phpArgs += "extension=$ext"
        }
    }
}

$phpArgs += @(
    '-d', 'upload_max_filesize=50M',
    '-d', 'post_max_size=128M',
    '-d', 'max_file_uploads=100',
    '-d', 'max_execution_time=180',
    '-d', 'max_input_time=180'
)

$moduleList = & $phpExe @phpArgs -m
if ($LASTEXITCODE -ne 0) {
    throw "Failed to run PHP CLI using: $phpExe"
}

if (-not ($moduleList -match "^pdo_sqlite$")) {
    throw "Missing required extension: pdo_sqlite. Update PHP config or use a PHP build with SQLite enabled."
}
if (-not ($moduleList -match "^mbstring$")) {
    throw "Missing required extension: mbstring. Enable it in PHP before starting the portal."
}
if (-not ($moduleList -match "^fileinfo$")) {
    throw "Missing required extension: fileinfo. Enable it in PHP for secure report and image uploads."
}

$existingPids = @(Get-ListeningPids -CheckPort $Port)
foreach ($procId in $existingPids) {
    $proc = Get-Process -Id $procId -ErrorAction SilentlyContinue
    $owner = if ($proc) { $proc.ProcessName } else { "PID $procId" }
    throw ("Port {0} is already used by {1} (PID {2}). Stop that server yourself or start with -Port <another-port>." -f $Port, $owner, $procId)
}

Write-Host ("Using PHP: {0}" -f $phpExe)
Write-Host "Running database migration..."
$databasePath = Join-Path $projectRoot "database\app.db"
$newDatabase = -not (Test-Path -LiteralPath $databasePath) -or (Get-Item -LiteralPath $databasePath -ErrorAction SilentlyContinue).Length -eq 0
$localConfigPath = Join-Path $projectRoot "config\local.php"
$localAdminPasswordConfigured = $false
if (Test-Path -LiteralPath $localConfigPath) {
    $localAdminPasswordStatus = & $phpExe @phpArgs -r '$settings = require "config/local.php"; echo is_array($settings) && !empty($settings["ADMIN_DEFAULT_PASSWORD"]) ? "configured" : "missing";'
    if ($LASTEXITCODE -ne 0) {
        throw "Could not read ADMIN_DEFAULT_PASSWORD from config/local.php."
    }
    $localAdminPasswordConfigured = ($localAdminPasswordStatus.Trim() -eq "configured")
}
$promptedForAdminPassword = $false
if ($newDatabase -and [string]::IsNullOrWhiteSpace($env:ADMIN_DEFAULT_PASSWORD) -and -not $localAdminPasswordConfigured) {
    function Convert-SecureStringToPlainText([Security.SecureString]$Value) {
        $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Value)
        try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
        finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
    }

    do {
        $firstPassword = Read-Host "Create the initial admin password (12+ characters)" -AsSecureString
        $confirmPassword = Read-Host "Confirm the initial admin password" -AsSecureString
        $passwordText = Convert-SecureStringToPlainText $firstPassword
        $confirmationText = Convert-SecureStringToPlainText $confirmPassword
        if ($passwordText.Length -lt 12 -or $passwordText -cne $confirmationText) {
            Write-Warning "Use at least 12 characters and enter the same password twice."
            $passwordText = $null
            $confirmationText = $null
        }
    } while ($null -eq $passwordText)

    $env:ADMIN_DEFAULT_PASSWORD = $passwordText
    $passwordText = $null
    $confirmationText = $null
    $promptedForAdminPassword = $true
}

try {
    & $phpExe @phpArgs "database/migrate.php"
    if ($LASTEXITCODE -ne 0) {
        throw "Database migration failed."
    }
} finally {
    if ($promptedForAdminPassword) {
        Remove-Item Env:\ADMIN_DEFAULT_PASSWORD -ErrorAction SilentlyContinue
    }
}

Write-Host ("Starting offline server at http://127.0.0.1:{0}" -f $Port)
Write-Host "Press Ctrl+C to stop."
& $phpExe @phpArgs "-S" ("127.0.0.1:{0}" -f $Port)
