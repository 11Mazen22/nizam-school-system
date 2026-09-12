# Simple single-size ICO that actually works for embedding
Add-Type -AssemblyName System.Drawing

$source = "$PSScriptRoot\hadaba-256.png"
$output = "$PSScriptRoot\hadaba.ico"

$img = [System.Drawing.Bitmap]::new($source)
$icon = [System.Drawing.Icon]::FromHandle($img.GetHicon())

$fileStream = [System.IO.File]::Create($output)
$icon.Save($fileStream)
$fileStream.Close()

$img.Dispose()
$icon.Dispose()

$fileSize = (Get-Item $output).Length
Write-Host "✅ Created $output ($fileSize bytes)"
