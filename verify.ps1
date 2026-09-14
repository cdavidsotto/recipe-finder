$ErrorActionPreference = "Stop"

# Run from the folder containing this script and the Vagrantfile.
Set-Location $PSScriptRoot

# Check that every required VM is running.
$status = & vagrant status --machine-readable
if ($LASTEXITCODE -ne 0) {
    throw "Could not read Vagrant status."
}

foreach ($vm in @("webserver", "appserver", "dbserver")) {
    $pattern = ",$vm,state,running$"

    if (-not ($status | Select-String -Pattern $pattern)) {
        throw "$vm is not running."
    }

    Write-Host "PASS: $vm is running."
}

function Check-Page {
    param(
        [string]$Path,
        [string]$ExpectedText,
        [bool]$ShouldContain = $true
    )

    $response = Invoke-WebRequest `
        -Uri ("http://127.0.0.1:8080/" + $Path) `
        -UseBasicParsing `
        -TimeoutSec 20

    if ($response.StatusCode -ne 200) {
        throw "Unexpected HTTP status for $Path"
    }

    $contains = $response.Content.Contains($ExpectedText)

    if ($contains -ne $ShouldContain) {
        throw "Unexpected result for $Path"
    }

    Write-Host "PASS: $Path"
}

# Test through the front web server, including database-backed results.
Check-Page -Path "" -ExpectedText "Recipe Finder"

Check-Page `
    -Path "?search=1&ingredients%5B%5D=eggs" `
    -ExpectedText "<h3>Scrambled eggs</h3>"

Check-Page `
    -Path "?search=1&mode=and&ingredients%5B%5D=eggs" `
    -ExpectedText "<h3>Scrambled eggs</h3>" `
    -ShouldContain $false

Check-Page `
    -Path "?search=1&mode=and&ingredients%5B%5D=eggs&ingredients%5B%5D=butter" `
    -ExpectedText "<h3>Scrambled eggs</h3>"

Check-Page `
    -Path "?search=1" `
    -ExpectedText "Please select at least one ingredient."

Write-Host "All deployment checks passed."
