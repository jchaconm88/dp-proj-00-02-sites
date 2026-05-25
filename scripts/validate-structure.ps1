<#
.SYNOPSIS
    Validates the monorepo structure for the WordPress Web Agency project.

.DESCRIPTION
    Checks that client folders follow hostname naming conventions, contain required
    design/ files, and maintain 1:1 correspondence between templates/ and front/.

.PARAMETER MonorepoRoot
    Path to the monorepo root directory.

.PARAMETER Hostname
    Optional specific hostname to validate.

.PARAMETER ValidateAll
    Switch to validate all clients in the monorepo.

.EXAMPLE
    .\validate-structure.ps1 -MonorepoRoot . -ValidateAll
    .\validate-structure.ps1 -MonorepoRoot . -Hostname "mi-cliente.local"
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$MonorepoRoot,

    [Parameter(Mandatory = $false)]
    [string]$Hostname,

    [switch]$ValidateAll
)

# --- Helper Functions ---

function Test-ValidHostname {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Name
    )

    # Max 253 characters total
    if ($Name.Length -gt 253) {
        return @{ Valid = $false; Error = "Hostname exceeds 253 characters total (got $($Name.Length))" }
    }

    # Must be lowercase
    if ($Name -cne $Name.ToLower()) {
        return @{ Valid = $false; Error = "Hostname must be lowercase" }
    }

    # Must not be empty
    if ([string]::IsNullOrWhiteSpace($Name)) {
        return @{ Valid = $false; Error = "Hostname cannot be empty" }
    }

    # Split into segments by dots
    $segments = $Name.Split('.')

    # Must have at least 2 segments (name + TLD)
    if ($segments.Count -lt 2) {
        return @{ Valid = $false; Error = "Hostname must have at least two segments (name.tld)" }
    }

    # Validate each segment
    foreach ($segment in $segments) {
        if ([string]::IsNullOrEmpty($segment)) {
            return @{ Valid = $false; Error = "Hostname contains empty segment" }
        }

        if ($segment.Length -gt 63) {
            return @{ Valid = $false; Error = "Segment exceeds 63 characters" }
        }

        if ($segment -notmatch '^[a-z0-9]([a-z0-9-]*[a-z0-9])?$') {
            return @{ Valid = $false; Error = "Segment contains invalid characters or starts/ends with hyphen" }
        }
    }

    # TLD must be at least 2 alphabetic characters
    $tld = $segments[-1]
    if ($tld -notmatch '^[a-z]{2,}$') {
        return @{ Valid = $false; Error = "TLD must be at least 2 alphabetic characters" }
    }

    return @{ Valid = $true; Error = $null }
}

function Get-ClientFolders {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ModulePath
    )

    $clients = @()

    if (-not (Test-Path $ModulePath)) {
        return $clients
    }

    $subdirs = Get-ChildItem -Path $ModulePath -Directory -ErrorAction SilentlyContinue

    foreach ($dir in $subdirs) {
        $hostnameCheck = Test-ValidHostname -Name $dir.Name
        if ($hostnameCheck.Valid) {
            $clients += @{
                Name     = $dir.Name
                Path     = $dir.FullName
                Category = $null
            }
        }
        else {
            # Might be a category folder - check subdirectories
            $categoryDirs = Get-ChildItem -Path $dir.FullName -Directory -ErrorAction SilentlyContinue
            foreach ($catDir in $categoryDirs) {
                $catHostnameCheck = Test-ValidHostname -Name $catDir.Name
                if ($catHostnameCheck.Valid) {
                    $clients += @{
                        Name     = $catDir.Name
                        Path     = $catDir.FullName
                        Category = $dir.Name
                    }
                }
            }
        }
    }

    return $clients
}

function Test-DesignDirectory {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ClientPath
    )

    $requiredFiles = @('code.html', 'DESIGN.md', 'screen.png')
    $designPath = Join-Path $ClientPath 'design'
    $missingFiles = @()

    if (-not (Test-Path $designPath)) {
        return @{
            Valid        = $false
            MissingFiles = @('design/ directory does not exist')
        }
    }

    foreach ($file in $requiredFiles) {
        $filePath = Join-Path $designPath $file
        if (-not (Test-Path $filePath)) {
            $missingFiles += $file
        }
    }

    return @{
        Valid        = ($missingFiles.Count -eq 0)
        MissingFiles = $missingFiles
    }
}

# --- Main Validation Logic ---

$errors = @()
$warnings = @()
$passCount = 0
$failCount = 0

$resolvedRoot = Resolve-Path $MonorepoRoot -ErrorAction SilentlyContinue
if (-not $resolvedRoot) {
    Write-Error "Monorepo root not found: $MonorepoRoot"
    exit 1
}

$rootPath = $resolvedRoot.Path
$templatesPath = Join-Path $rootPath 'templates'
$frontPath = Join-Path $rootPath 'front'

# Determine which clients to validate
$clientsToValidate = @()

if ($Hostname) {
    # Validate specific hostname format first
    $hostnameResult = Test-ValidHostname -Name $Hostname
    if (-not $hostnameResult.Valid) {
        $errMsg = $hostnameResult.Error
        Write-Host "[FAIL] Hostname format invalid: $errMsg" -ForegroundColor Red
        $failCount++
        $errors += "Hostname is not valid: $errMsg"
    }
    else {
        Write-Host "[PASS] Hostname format valid: $Hostname" -ForegroundColor Green
        $passCount++

        # Find this client in templates/
        $found = $false
        $templateClients = Get-ClientFolders -ModulePath $templatesPath
        foreach ($client in $templateClients) {
            if ($client.Name -eq $Hostname) {
                $clientsToValidate += $client
                $found = $true
                break
            }
        }

        if (-not $found) {
            Write-Host "[FAIL] Client folder not found in templates/: $Hostname" -ForegroundColor Red
            $failCount++
            $errors += "Client not found in templates/: $Hostname"
        }
    }
}
elseif ($ValidateAll) {
    # Discover all clients in templates/
    $clientsToValidate = Get-ClientFolders -ModulePath $templatesPath
    if ($clientsToValidate.Count -eq 0) {
        Write-Host "[INFO] No client folders found in templates/" -ForegroundColor Yellow
    }
}
else {
    Write-Host "Usage: Specify -Hostname <name> or -ValidateAll" -ForegroundColor Yellow
    Write-Host "  -Hostname <name>  : Validate a specific client"
    Write-Host "  -ValidateAll      : Validate all clients in the monorepo"
    exit 1
}

# --- Validate each client ---

foreach ($client in $clientsToValidate) {
    $clientName = $client.Name
    $clientPath = $client.Path

    Write-Host ""
    Write-Host "--- Validating client: $clientName ---" -ForegroundColor Cyan

    # 1. Validate hostname format
    $hostnameResult = Test-ValidHostname -Name $clientName
    if (-not $hostnameResult.Valid) {
        $errMsg = $hostnameResult.Error
        Write-Host "  [FAIL] Hostname format: $errMsg" -ForegroundColor Red
        $failCount++
        $errors += "Client $clientName - Invalid hostname: $errMsg"
    }
    else {
        Write-Host "  [PASS] Hostname format valid" -ForegroundColor Green
        $passCount++
    }

    # 2. Validate design/ directory and required files
    $designResult = Test-DesignDirectory -ClientPath $clientPath
    if (-not $designResult.Valid) {
        $missingList = $designResult.MissingFiles -join ', '
        Write-Host "  [FAIL] Design directory - Missing files: $missingList" -ForegroundColor Red
        $failCount++
        $errors += "Client $clientName - Missing design files: $missingList"
    }
    else {
        Write-Host "  [PASS] Design directory contains all required files" -ForegroundColor Green
        $passCount++
    }
}

# --- Validate 1:1 correspondence between templates/ and front/ ---

Write-Host ""
Write-Host "--- Validating templates/ <-> front/ correspondence ---" -ForegroundColor Cyan

$frontClients = Get-ClientFolders -ModulePath $frontPath
$templateClients = Get-ClientFolders -ModulePath $templatesPath

# Build hostname lists
$templateHostnames = @()
foreach ($tc in $templateClients) {
    $templateHostnames += $tc.Name
}

$frontHostnames = @()
foreach ($fc in $frontClients) {
    $frontHostnames += $fc.Name
}

# Check: every client in front/ must exist in templates/
foreach ($frontClient in $frontClients) {
    $fcName = $frontClient.Name
    if ($templateHostnames -notcontains $fcName) {
        Write-Host "  [FAIL] Client '$fcName' exists in front/ but not in templates/" -ForegroundColor Red
        $failCount++
        $errors += "Correspondence mismatch: '$fcName' exists in front/ but not in templates/"
    }
    else {
        Write-Host "  [PASS] Client '$fcName' has corresponding template" -ForegroundColor Green
        $passCount++
    }
}

if ($frontClients.Count -eq 0 -and $templateClients.Count -gt 0) {
    Write-Host "  [INFO] No clients in front/ - correspondence check skipped" -ForegroundColor Yellow
}
elseif ($frontClients.Count -eq 0 -and $templateClients.Count -eq 0) {
    Write-Host "  [INFO] No clients found in either templates/ or front/" -ForegroundColor Yellow
}

# --- Summary ---

Write-Host ""
Write-Host "========================================" -ForegroundColor White
Write-Host "Validation Summary" -ForegroundColor White
Write-Host "========================================" -ForegroundColor White
Write-Host "  Passed: $passCount" -ForegroundColor Green
if ($failCount -gt 0) {
    Write-Host "  Failed: $failCount" -ForegroundColor Red
}
else {
    Write-Host "  Failed: $failCount" -ForegroundColor Green
}

if ($errors.Count -gt 0) {
    Write-Host ""
    Write-Host "Errors:" -ForegroundColor Red
    foreach ($errItem in $errors) {
        Write-Host "  - $errItem" -ForegroundColor Red
    }
}

if ($failCount -gt 0) {
    exit 1
}
else {
    exit 0
}
