"""Convert hadaba-256.png to hadaba.ico with multiple resolutions"""
from PIL import Image
import os

# Icon sizes for Windows
SIZES = [16, 24, 32, 48, 64, 128, 256]

script_dir = os.path.dirname(os.path.abspath(__file__))
png_path = os.path.join(script_dir, "hadaba-256.png")
ico_path = os.path.join(script_dir, "hadaba.ico")

# Load the source PNG
source = Image.open(png_path)

# Generate resized versions for each size
images = []
for size in SIZES:
    # Use LANCZOS for high-quality downscaling
    resized = source.resize((size, size), Image.Resampling.LANCZOS)
    images.append(resized)

# Save as multi-resolution .ico
images[0].save(ico_path, format="ICO", sizes=[(s, s) for s in SIZES], append_images=images[1:])

print(f"✅ Created {ico_path} with resolutions: {SIZES}")
