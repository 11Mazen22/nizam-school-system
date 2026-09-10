"""Build-time only: generates packaging/assets/nizam.ico. Not part of the
shipped application -- run once (or whenever the icon design changes) to
produce the .ico the installer and launcher embed."""
from PIL import Image, ImageDraw, ImageFont
import os

SIZES = [16, 24, 32, 48, 64, 128, 256]
TEAL = (31, 111, 92, 255)      # matches the palette used elsewhere for Nizam materials
TEAL_DARK = (15, 65, 55, 255)
WHITE = (255, 255, 255, 255)

def rounded_square(size, radius_ratio=0.22):
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    r = max(2, int(size * radius_ratio))
    draw.rounded_rectangle([0, 0, size - 1, size - 1], radius=r, fill=TEAL)
    return img

def draw_mark(img, size):
    draw = ImageDraw.Draw(img)
    # Bold geometric "N" built from three strokes -- legible even at 16px,
    # where a script letterform or a detailed glyph would just smear.
    stroke = max(2, round(size * 0.13))
    pad = round(size * 0.26)
    top = pad
    bottom = size - pad
    left = pad
    right = size - pad
    # left vertical
    draw.line([(left, top), (left, bottom)], fill=WHITE, width=stroke)
    # right vertical
    draw.line([(right, top), (right, bottom)], fill=WHITE, width=stroke)
    # diagonal
    draw.line([(left, top), (right, bottom)], fill=WHITE, width=stroke)

def build(size):
    img = rounded_square(size)
    draw_mark(img, size)
    return img

out_dir = os.path.dirname(os.path.abspath(__file__))
images = [build(s) for s in SIZES]
ico_path = os.path.join(out_dir, "nizam.ico")
images[0].save(ico_path, format="ICO", sizes=[(s, s) for s in SIZES],
                append_images=images[1:])

png_path = os.path.join(out_dir, "nizam-256.png")
build(256).save(png_path, format="PNG")

print("wrote", ico_path, "and", png_path)
