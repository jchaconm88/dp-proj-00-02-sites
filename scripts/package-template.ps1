<#
.SYNOPSIS
    Packages the contents of a client's components/ directory into a template.zip file.

.DESCRIPTION
    Creates a ZIP archive from the components/ directory of a client template folder.
    Includes HTML, manifest JSON, partials, and styles. Excludes example files,
    README.md, and other documentation files (*.md).

.PARAMETER ClientPath
    Path to the client folder in templates/ (e.g., templates/ecommerce/mi-cliente.local).

.PARAMETER OutputPath
    Optional path for the output ZIP file. Defaults to $ClientPath/template.zip.

.EXAMPLE
    .\package-template.ps1 -ClientPath "templates/ecommerce/mi-cliente.local"
    .\package-template.ps1 -ClientPath "templates/ecommerce/mi-cliente.local" -OutputPath "output/my-template.zip"
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$ClientPath,

    [Parameter(Mandatory = $false)]
    [string]$OutputPath = ""
)

# --- Resolve paths ---

$resolvedClientPath = $null
if ([System.IO.Path]::IsPathRooted($ClientPath)) {
    $resolvedClientPath = $ClientPath
}
else {
    $resolvedClientPath = Join-Path (Get-Location).Path $ClientPath
}

if (-not (Test-Path $resolvedClientPath -PathType Container)) {
    Write-Error "Client path does not exist: $resolvedClientPath"
    exit 1
}

$componentsPath = Join-Path $resolvedClientPath 'components'

# --- Validate components/ directory ---

if (-not (Test-Path $componentsPath -PathType Container)) {
    Write-Error "components/ directory does not exist in: $resolvedClientPath"
    exit 1
}

# Check that components/ is not empty (has at least one file recursively)
$allFiles = Get-ChildItem -Path $componentsPath -File -Recurse -ErrorAction SilentlyContinue
if ($null -eq $allFiles -or $allFiles.Count -eq 0) {
    Write-Error "components/ directory is empty - no files to package in: $componentsPath"
    exit 1
}

# --- Determine output path ---

$zipOutputPath = $OutputPath
if ([string]::IsNullOrWhiteSpace($zipOutputPath)) {
    $zipOutputPath = Join-Path $resolvedClientPath 'template.zip'
}
elseif (-not [System.IO.Path]::IsPathRooted($zipOutputPath)) {
    $zipOutputPath = Join-Path (Get-Location).Path $zipOutputPath
}

# --- Filter files: include/exclude rules ---
# Include: .html, .json (manifest), files in partials/, files in styles/
# Exclude: *.example.json, README.md, *.md (documentation files)

$includedFiles = @()

foreach ($file in $allFiles) {
    $relativePath = $file.FullName.Substring($componentsPath.Length + 1)
    $fileName = $file.Name
    $extension = $file.Extension.ToLower()

    # Exclusion rules (checked first)
    # Exclude *.example.json
    if ($fileName -like '*.example.json') {
        continue
    }

    # Exclude README.md and all *.md documentation files
    if ($extension -eq '.md') {
        continue
    }

    # Inclusion rules
    $include = $false

    # Include .html files
    if ($extension -eq '.html') {
        $include = $true
    }

    # Include .json files (manifest and other config)
    if ($extension -eq '.json') {
        $include = $true
    }

    # Include files in partials/ directory
    if ($relativePath -like 'partials\*' -or $relativePath -like 'partials/*') {
        $include = $true
    }

    # Include files in styles/ directory
    if ($relativePath -like 'styles\*' -or $relativePath -like 'styles/*') {
        $include = $true
    }

    if ($include) {
        $includedFiles += $file
    }
}

if ($includedFiles.Count -eq 0) {
    Write-Error "No files matched inclusion criteria in components/ - nothing to package."
    exit 1
}

# --- Create ZIP using .NET System.IO.Compression ---

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

# Ensure output directory exists
$outputDir = [System.IO.Path]::GetDirectoryName($zipOutputPath)
if (-not [string]::IsNullOrWhiteSpace($outputDir) -and -not (Test-Path $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
}

# Remove existing ZIP if present
if (Test-Path $zipOutputPath) {
    Remove-Item $zipOutputPath -Force
}

try {
    $zipStream = [System.IO.File]::Create($zipOutputPath)
    $archive = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)

    foreach ($file in $includedFiles) {
        $relativePath = $file.FullName.Substring($componentsPath.Length + 1)
        # Normalize path separators to forward slashes for ZIP compatibility
        $entryName = $relativePath -replace '\\', '/'

        $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
        $entryStream = $entry.Open()
        $fileStream = [System.IO.File]::OpenRead($file.FullName)
        $fileStream.CopyTo($entryStream)
        $fileStream.Close()
        $entryStream.Close()
    }

    $archive.Dispose()
    $zipStream.Close()

    # Report success
    $zipInfo = Get-Item $zipOutputPath
    $zipSizeKB = [math]::Round($zipInfo.Length / 1024, 2)
    $fileCount = $includedFiles.Count

    Write-Host "SUCCESS: Template packaged successfully." -ForegroundColor Green
    Write-Host "  Files included: $fileCount" -ForegroundColor Green
    Write-Host "  Output: $zipOutputPath" -ForegroundColor Green
    Write-Host "  Size: $zipSizeKB KB" -ForegroundColor Green

    exit 0
}
catch {
    Write-Error "Failed to create ZIP archive: $_"
    # Clean up partial ZIP if it exists
    if (Test-Path $zipOutputPath) {
        Remove-Item $zipOutputPath -Force -ErrorAction SilentlyContinue
    }
    exit 1
}
