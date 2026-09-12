# Generate high-quality multi-resolution ICO from PNG
# Windows displays different sizes depending on view mode:
# - 256x256: Large/Extra Large icon view
# - 48x48: Medium/Normal icon view
# - 32x32: Small icon view, taskbar
# - 16x16: List/Details view

Add-Type -AssemblyName System.Drawing

$sourcePath = "I:\Nizam-School-System\packaging\assets\hadaba-256.png"
$outputPath = "I:\Nizam-School-System\packaging\assets\hadaba.ico"

# Load source image
$sourceImage = [System.Drawing.Image]::FromFile($sourcePath)

# Sizes to include in ICO (from largest to smallest for best quality)
$sizes = @(256, 128, 64, 48, 32, 16)

Write-Host "Creating high-quality ICO with sizes: $($sizes -join ', ')"

# Create memory stream for ICO data
$ms = New-Object System.IO.MemoryStream
$writer = New-Object System.IO.BinaryWriter($ms)

# ICO file header
$writer.Write([uint16]0)           # Reserved (must be 0)
$writer.Write([uint16]1)           # Type: 1 = ICO
$writer.Write([uint16]$sizes.Count) # Number of images

# Calculate offset to first image data
$imageDataOffset = 6 + ($sizes.Count * 16)

# Store image data for later writing
$imageDataList = @()

# Write directory entries and prepare image data
foreach ($size in $sizes) {
    Write-Host "  Processing ${size}x${size}..."
    
    # Create high-quality resized bitmap
    $bitmap = New-Object System.Drawing.Bitmap $size, $size
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    
    # Use highest quality settings
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
    $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
    
    # Draw resized image
    $graphics.DrawImage($sourceImage, 0, 0, $size, $size)
    
    # Save as PNG to memory stream
    $pngStream = New-Object System.IO.MemoryStream
    $bitmap.Save($pngStream, [System.Drawing.Imaging.ImageFormat]::Png)
    $pngData = $pngStream.ToArray()
    
    # Write ICONDIRENTRY (16 bytes)
    if ($size -eq 256) {
        $writer.Write([byte]0)  # Width: 0 means 256
        $writer.Write([byte]0)  # Height: 0 means 256
    } else {
        $writer.Write([byte]$size)
        $writer.Write([byte]$size)
    }
    $writer.Write([byte]0)           # Color palette (0 = no palette)
    $writer.Write([byte]0)           # Reserved
    $writer.Write([uint16]1)         # Color planes
    $writer.Write([uint16]32)        # Bits per pixel (32-bit RGBA)
    $writer.Write([uint32]$pngData.Length)  # Size of image data
    $writer.Write([uint32]$imageDataOffset) # Offset to image data
    
    # Store image data
    $imageDataList += $pngData
    $imageDataOffset += $pngData.Length
    
    # Cleanup
    $pngStream.Dispose()
    $graphics.Dispose()
    $bitmap.Dispose()
}

# Write all image data
foreach ($imageData in $imageDataList) {
    $writer.Write($imageData)
}

# Finalize and save
$writer.Flush()
$icoBytes = $ms.ToArray()
[System.IO.File]::WriteAllBytes($outputPath, $icoBytes)

# Cleanup
$writer.Dispose()
$ms.Dispose()
$sourceImage.Dispose()

$fileSize = (Get-Item $outputPath).Length
Write-Host ""
Write-Host "✓ Created high-quality ICO: $outputPath"
Write-Host "  File size: $([math]::Round($fileSize/1KB, 1)) KB"
Write-Host "  Contains: $($sizes.Count) sizes"
