# Create a proper multi-resolution ICO file from PNG
Add-Type -AssemblyName System.Drawing

$source = "$PSScriptRoot\hadaba-256.png"
$output = "$PSScriptRoot\hadaba.ico"

$img = [System.Drawing.Image]::FromFile($source)

# Create multiple sizes for better quality at all resolutions
$sizes = @(256, 128, 64, 48, 32, 16)
$images = @()

foreach ($size in $sizes) {
    $bitmap = New-Object System.Drawing.Bitmap($size, $size)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
    $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $graphics.DrawImage($img, 0, 0, $size, $size)
    $images += $bitmap
}

# Save as ICO with all sizes
$images[0].Save($output, [System.Drawing.Imaging.ImageFormat]::Icon)

foreach ($bitmap in $images) {
    $bitmap.Dispose()
}
$img.Dispose()

$fileSize = (Get-Item $output).Length
Write-Host "✅ Created $output ($fileSize bytes) with sizes: $($sizes -join ', ')"
