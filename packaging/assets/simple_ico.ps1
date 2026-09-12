# Build a multi-resolution ICO directly. GetHicon() serializes one bitmap only,
# which forces Windows to scale the icon at most desktop and shortcut sizes.
#
# Run exactly:
#   & "I:\Nizam-School-System\packaging\assets\simple_ico.ps1"
# The underscore is part of the filename; do not insert a backslash before it.
Add-Type -AssemblyName System.Drawing

$source = Join-Path $PSScriptRoot 'hadaba-256.png'
$output = Join-Path $PSScriptRoot 'hadaba.ico'
$sizes = @(16, 24, 32, 48, 64, 128, 256)

if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
    throw "Source image not found: $source"
}

function Get-PngBytes([System.Drawing.Image] $image, [int] $size) {
    $bitmap = [System.Drawing.Bitmap]::new($size, $size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    try {
        $graphics.Clear([System.Drawing.Color]::Transparent)
        $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
        $graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
        $graphics.DrawImage($image, [System.Drawing.Rectangle]::new(0, 0, $size, $size))
        $stream = [System.IO.MemoryStream]::new()
        try {
            $bitmap.Save($stream, [System.Drawing.Imaging.ImageFormat]::Png)
            return $stream.ToArray()
        } finally {
            $stream.Dispose()
        }
    } finally {
        $graphics.Dispose()
        $bitmap.Dispose()
    }
}

$sourceImage = [System.Drawing.Image]::FromFile($source)
try {
    $images = foreach ($size in $sizes) {
        [PSCustomObject]@{ Size = $size; Bytes = Get-PngBytes $sourceImage $size }
    }

    $stream = [System.IO.File]::Open($output, [System.IO.FileMode]::Create, [System.IO.FileAccess]::Write)
    $writer = [System.IO.BinaryWriter]::new($stream)
    try {
        # ICONDIR: reserved=0, type=1, image count.
        $writer.Write([UInt16]0)
        $writer.Write([UInt16]1)
        $writer.Write([UInt16]$images.Count)

        $offset = 6 + (16 * $images.Count)
        foreach ($image in $images) {
            # ICONDIRENTRY. A zero width/height byte represents 256px.
            $dimension = if ($image.Size -eq 256) { [byte]0 } else { [byte]$image.Size }
            $writer.Write($dimension)
            $writer.Write($dimension)
            $writer.Write([byte]0)
            $writer.Write([byte]0)
            $writer.Write([UInt16]1)
            $writer.Write([UInt16]32)
            $writer.Write([UInt32]$image.Bytes.Length)
            $writer.Write([UInt32]$offset)
            $offset += $image.Bytes.Length
        }
        foreach ($image in $images) {
            $writer.Write([byte[]]$image.Bytes)
        }
    } finally {
        $writer.Dispose()
        $stream.Dispose()
    }
} finally {
    $sourceImage.Dispose()
}

Write-Host "Created $output with $($sizes.Count) resolutions: $($sizes -join ', ') px"
