# Convert hadaba-256.png to proper multi-resolution hadaba.ico using .NET
$source = "$PSScriptRoot\hadaba-256.png"
$output = "$PSScriptRoot\hadaba.ico"

# Load the PNG
Add-Type -AssemblyName System.Drawing
$bitmap = [System.Drawing.Bitmap]::FromFile($source)

# Create icon with multiple sizes
$sizes = @(16, 32, 48, 256)
$ms = New-Object System.IO.MemoryStream

# Convert bitmap to icon format
$icon = [System.Drawing.Icon]::FromHandle($bitmap.GetHicon())
$icon.Save($ms)
[System.IO.File]::WriteAllBytes($output, $ms.ToArray())

$bitmap.Dispose()
$icon.Dispose()
$ms.Dispose()

Write-Host "✅ Created $output from $source"
