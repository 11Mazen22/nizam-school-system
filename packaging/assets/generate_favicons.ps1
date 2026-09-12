# Generate all favicon sizes from hadaba-256.png
Add-Type -AssemblyName System.Drawing

$sourcePath = "I:\Nizam-School-System\packaging\assets\hadaba-256.png"
$outputDir = "I:\Nizam-School-System\public"

# Load source image
$sourceImage = [System.Drawing.Image]::FromFile($sourcePath)

# Generate 32x32 PNG
Write-Host "Generating 32x32 favicon..."
$bitmap32 = New-Object System.Drawing.Bitmap 32, 32
$graphics32 = [System.Drawing.Graphics]::FromImage($bitmap32)
$graphics32.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$graphics32.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
$graphics32.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
$graphics32.DrawImage($sourceImage, 0, 0, 32, 32)
$bitmap32.Save("$outputDir\assets\img\favicon-32.png", [System.Drawing.Imaging.ImageFormat]::Png)
$graphics32.Dispose()
$bitmap32.Dispose()
Write-Host "✓ Created favicon-32.png"

# Generate 180x180 PNG
Write-Host "Generating 180x180 favicon..."
$bitmap180 = New-Object System.Drawing.Bitmap 180, 180
$graphics180 = [System.Drawing.Graphics]::FromImage($bitmap180)
$graphics180.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$graphics180.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
$graphics180.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
$graphics180.DrawImage($sourceImage, 0, 0, 180, 180)
$bitmap180.Save("$outputDir\assets\img\favicon-180.png", [System.Drawing.Imaging.ImageFormat]::Png)
$graphics180.Dispose()
$bitmap180.Dispose()
Write-Host "✓ Created favicon-180.png"

# Generate multi-size ICO (16x16, 32x32, 48x48, 256x256)
Write-Host "Generating favicon.ico..."

$sizes = @(16, 32, 48, 256)
$ms = New-Object System.IO.MemoryStream

# ICO header
$writer = New-Object System.IO.BinaryWriter($ms)
$writer.Write([uint16]0)     # Reserved
$writer.Write([uint16]1)     # Type: ICO
$writer.Write([uint16]$sizes.Count)  # Number of images

$imageDataList = @()
$offset = 6 + ($sizes.Count * 16)  # Header + directory entries

foreach ($size in $sizes) {
    $bitmap = New-Object System.Drawing.Bitmap $size, $size
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
    $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $graphics.DrawImage($sourceImage, 0, 0, $size, $size)
    
    $pngStream = New-Object System.IO.MemoryStream
    $bitmap.Save($pngStream, [System.Drawing.Imaging.ImageFormat]::Png)
    $pngData = $pngStream.ToArray()
    
    # Write directory entry
    $width = if ($size -eq 256) { 0 } else { $size }
    $height = if ($size -eq 256) { 0 } else { $size }
    $writer.Write([byte]$width)   # Width (0 means 256)
    $writer.Write([byte]$height)  # Height (0 means 256)
    $writer.Write([byte]0)                             # Color palette
    $writer.Write([byte]0)                             # Reserved
    $writer.Write([uint16]1)                           # Color planes
    $writer.Write([uint16]32)                          # Bits per pixel
    $writer.Write([uint32]$pngData.Length)             # Size of image data
    $writer.Write([uint32]$offset)                     # Offset to image data
    
    $imageDataList += $pngData
    $offset += $pngData.Length
    
    $pngStream.Dispose()
    $graphics.Dispose()
    $bitmap.Dispose()
}

# Write all image data
foreach ($imageData in $imageDataList) {
    $writer.Write($imageData)
}

$writer.Flush()
$icoBytes = $ms.ToArray()
[System.IO.File]::WriteAllBytes("$outputDir\favicon.ico", $icoBytes)

$writer.Dispose()
$ms.Dispose()
$sourceImage.Dispose()

Write-Host "✓ Created favicon.ico with sizes: 16x16, 32x32, 48x48, 256x256"
Write-Host ""
Write-Host "All favicons generated successfully!"
