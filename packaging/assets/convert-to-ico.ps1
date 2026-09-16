Add-Type -AssemblyName System.Drawing

$sourcePath = "I:\Nizam-School-System\packaging\assets\hadaba-256.png"
$outputPath = "I:\Nizam-School-System\packaging\assets\hadaba.ico"

Write-Host "Converting PNG to ICO..."

$sourceImage = [System.Drawing.Image]::FromFile($sourcePath)
$sizes = @(256, 128, 64, 48, 32, 16)

$ms = New-Object System.IO.MemoryStream
$writer = New-Object System.IO.BinaryWriter($ms)

# ICO header
$writer.Write([uint16]0)
$writer.Write([uint16]1)
$writer.Write([uint16]$sizes.Count)

$imageDataOffset = 6 + ($sizes.Count * 16)
$imageDataList = @()

foreach ($size in $sizes) {
    Write-Host "  Processing ${size}x${size}..."
    
    $bitmap = New-Object System.Drawing.Bitmap $size, $size
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
    $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
    
    $graphics.DrawImage($sourceImage, 0, 0, $size, $size)
    
    $pngStream = New-Object System.IO.MemoryStream
    $bitmap.Save($pngStream, [System.Drawing.Imaging.ImageFormat]::Png)
    $pngData = $pngStream.ToArray()
    
    if ($size -eq 256) {
        $writer.Write([byte]0)
        $writer.Write([byte]0)
    } else {
        $writer.Write([byte]$size)
        $writer.Write([byte]$size)
    }
    $writer.Write([byte]0)
    $writer.Write([byte]0)
    $writer.Write([uint16]1)
    $writer.Write([uint16]32)
    $writer.Write([uint32]$pngData.Length)
    $writer.Write([uint32]$imageDataOffset)
    
    $imageDataList += $pngData
    $imageDataOffset += $pngData.Length
    
    $pngStream.Dispose()
    $graphics.Dispose()
    $bitmap.Dispose()
}

foreach ($imageData in $imageDataList) {
    $writer.Write($imageData)
}

$writer.Flush()
$icoBytes = $ms.ToArray()
[System.IO.File]::WriteAllBytes($outputPath, $icoBytes)

$writer.Dispose()
$ms.Dispose()
$sourceImage.Dispose()

$fileSize = (Get-Item $outputPath).Length
Write-Host ""
Write-Host "Created ICO: $outputPath"
Write-Host "File size: $([math]::Round($fileSize/1KB, 1)) KB"
Write-Host "Contains $($sizes.Count) sizes"
