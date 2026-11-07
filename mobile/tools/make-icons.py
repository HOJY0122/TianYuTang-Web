"""
Make the app icons and splash screens from one logo (transparent PNG).

    pip install pillow
    python mobile/tools/make-icons.py mobile/assets/logo.png mobile/android/app/src/main/res mobile/assets/icon-1024.png

Writes every mipmap-*/ic_launcher*.png size, every splash.png, and a 1024x1024 icon.
"""
import sys, os
from PIL import Image, ImageDraw
src, res = sys.argv[1], sys.argv[2]
logo = Image.open(src).convert('RGBA')
logo = logo.crop(logo.getchannel('A').point(lambda a: 255 if a > 8 else 0).getbbox())
print('logo content', logo.size)

def fit(canvas_w, canvas_h, frac, bg):
    c = Image.new('RGBA', (canvas_w, canvas_h), bg)
    box = int(min(canvas_w, canvas_h) * frac)
    s = box / max(logo.size)
    l = logo.resize((max(1, round(logo.width * s)), max(1, round(logo.height * s))), Image.LANCZOS)
    c.alpha_composite(l, ((canvas_w - l.width) // 2, (canvas_h - l.height) // 2))
    return c

def masked(size, frac, shape):
    img = fit(size * 4, size * 4, frac, (255, 255, 255, 255))      # draw 4x, then shrink: smooth edges
    m = Image.new('L', img.size, 0); d = ImageDraw.Draw(m)
    if shape == 'round': d.ellipse((0, 0, img.width - 1, img.height - 1), fill=255)
    else: d.rounded_rectangle((0, 0, img.width - 1, img.height - 1), radius=img.width // 6, fill=255)
    out = Image.new('RGBA', img.size, (0, 0, 0, 0)); out.paste(img, (0, 0), m)
    return out.resize((size, size), Image.LANCZOS)

dens = {'mdpi': 1, 'hdpi': 1.5, 'xhdpi': 2, 'xxhdpi': 3, 'xxxhdpi': 4}
for d, k in dens.items():
    leg, fg = round(48 * k), round(108 * k)
    # adaptive foreground: 108dp canvas, logo inside the ~66dp safe zone
    fit(fg, fg, 0.60, (0, 0, 0, 0)).save(f'{res}/mipmap-{d}/ic_launcher_foreground.png', optimize=True)
    masked(leg, 0.80, 'square').save(f'{res}/mipmap-{d}/ic_launcher.png', optimize=True)
    masked(leg, 0.70, 'round').save(f'{res}/mipmap-{d}/ic_launcher_round.png', optimize=True)

cream = (251, 243, 226, 255)
for root, _, files in os.walk(res):
    if 'splash.png' in files:
        p = os.path.join(root, 'splash.png'); w, h = Image.open(p).size
        fit(w, h, 0.45, cream).convert('RGB').save(p, optimize=True)
fit(1024, 1024, 0.80, (255, 255, 255, 255)).convert('RGB').save(sys.argv[3], optimize=True)
print('done')
