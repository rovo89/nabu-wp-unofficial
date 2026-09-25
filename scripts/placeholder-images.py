#!/usr/bin/env python3
"""Generate the placeholder images for the demo content.

Deliberately abstract and visibly marked as placeholders: NABU's
instructions for group websites forbid reusing images from nabu.de or
nabu-netz.de, and real photos come from the NABU media library. These only
fill the slots so the theme can be tested with images of realistic size.
"""

import math
import random
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

OUT = Path(__file__).resolve().parent.parent / "themes" / "nabu-unofficial" / "assets" / "images"
OUT.mkdir(parents=True, exist_ok=True)


def font(size):
    for candidate in (
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
    ):
        if Path(candidate).exists():
            return ImageFont.truetype(candidate, size)
    return ImageFont.load_default()


def vertical_gradient(size, top, bottom):
    w, h = size
    img = Image.new("RGB", (1, h))
    px = img.load()
    for y in range(h):
        t = y / max(1, h - 1)
        px[0, y] = tuple(int(top[i] + (bottom[i] - top[i]) * t) for i in range(3))
    return img.resize((w, h), Image.BICUBIC)


def ridge(draw, size, base_y, amp, colour, seed, steps=14):
    """Soft hill silhouette."""
    rng = random.Random(seed)
    w, h = size
    offsets = [rng.uniform(-1, 1) for _ in range(steps + 1)]
    points = []
    for x in range(0, w + 1, 4):
        t = x / w * steps
        i = int(t)
        f = t - i
        a = offsets[min(i, steps)]
        b = offsets[min(i + 1, steps)]
        smooth = a + (b - a) * (3 * f * f - 2 * f * f * f)
        points.append((x, base_y + smooth * amp + math.sin(x / w * math.pi) * amp * 0.35))
    points += [(w, h), (0, h)]
    draw.polygon(points, fill=colour)


PALETTES = [
    ((198, 222, 236), (240, 246, 240), [(0.58, (146, 170, 140)), (0.70, (104, 134, 96)), (0.84, (66, 94, 58))]),
    ((214, 226, 232), (246, 242, 226), [(0.55, (163, 178, 132)), (0.72, (118, 138, 88)), (0.86, (78, 96, 56))]),
    ((186, 208, 226), (236, 240, 232), [(0.62, (128, 156, 156)), (0.76, (86, 116, 118)), (0.88, (54, 78, 82))]),
    ((222, 214, 196), (244, 238, 220), [(0.60, (176, 160, 112)), (0.74, (134, 122, 78)), (0.88, (92, 84, 52))]),
    ((196, 216, 232), (238, 244, 238), [(0.57, (150, 176, 128)), (0.71, (108, 140, 88)), (0.85, (70, 100, 56))]),
    ((206, 220, 228), (242, 240, 228), [(0.63, (140, 164, 144)), (0.78, (98, 124, 100)), (0.90, (62, 84, 64))]),
]


def landscape(width, height, seed, label):
    rng = random.Random(seed)
    sky_top, sky_bottom, layers = PALETTES[seed % len(PALETTES)]

    img = vertical_gradient((width, height), sky_top, sky_bottom)
    draw = ImageDraw.Draw(img, "RGBA")

    # Sun / haze
    sun_x = rng.uniform(0.15, 0.85) * width
    sun_y = rng.uniform(0.12, 0.30) * height
    r = height * 0.22
    for step in range(14, 0, -1):
        rad = r * step / 14
        draw.ellipse(
            (sun_x - rad, sun_y - rad, sun_x + rad, sun_y + rad),
            fill=(255, 252, 236, 10),
        )

    for frac, colour in layers:
        ridge(draw, (width, height), height * frac, height * 0.055, colour, seed * 17 + int(frac * 100))

    # Distant bird silhouettes
    for _ in range(rng.randint(2, 5)):
        bx = rng.uniform(0.1, 0.9) * width
        by = rng.uniform(0.1, 0.35) * height
        s = rng.uniform(0.008, 0.016) * width
        draw.arc((bx - s, by - s * 0.6, bx, by + s * 0.6), 200, 340, fill=(60, 70, 80, 170), width=max(1, int(s / 5)))
        draw.arc((bx, by - s * 0.6, bx + s, by + s * 0.6), 200, 340, fill=(60, 70, 80, 170), width=max(1, int(s / 5)))

    img = img.filter(ImageFilter.GaussianBlur(radius=max(0.6, height / 700)))

    # Visible placeholder mark
    draw = ImageDraw.Draw(img, "RGBA")
    f = font(max(11, height // 34))
    text = f"PLATZHALTER · {label}"
    bbox = draw.textbbox((0, 0), text, font=f)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    pad = max(6, height // 60)
    x, y = width - tw - pad * 2 - 8, height - th - pad * 2 - 8
    draw.rounded_rectangle((x, y, x + tw + pad * 2, y + th + pad * 2), radius=4, fill=(0, 0, 0, 110))
    draw.text((x + pad, y + pad - bbox[1]), text, font=f, fill=(255, 255, 255, 230))

    return img


def logo_placeholder(width=320, height=302):
    """Stand-in with the proportions of the NABU group logo and its blue bar.

    It contains nothing of the protected trademark and only gives the logo tab
    its shape until the group uploads its approved SVG.
    """
    img = Image.new("RGB", (width, height), (255, 255, 255))
    draw = ImageDraw.Draw(img)

    bar_h = int(height * 0.26)
    bar_y = height - bar_h
    draw.rectangle((0, bar_y, width, height), fill=(0, 104, 178))

    box = (int(width * 0.16), int(height * 0.10), int(width * 0.84), int(bar_y - height * 0.08))
    draw.rounded_rectangle(box, radius=6, outline=(190, 190, 185), width=2)
    draw.line((box[0], box[1], box[2], box[3]), fill=(210, 210, 205), width=2)
    draw.line((box[2], box[1], box[0], box[3]), fill=(210, 210, 205), width=2)

    f_small = font(max(10, int(height * 0.075)))
    hint = "LOGO"
    bbox = draw.textbbox((0, 0), hint, font=f_small)
    draw.text(
        ((width - (bbox[2] - bbox[0])) / 2, (box[1] + box[3]) / 2 - (bbox[3] - bbox[1]) / 2 - bbox[1]),
        hint,
        font=f_small,
        fill=(150, 150, 145),
    )

    f_bar = font(max(12, int(bar_h * 0.40)))
    name = "ORTSGRUPPE"
    bbox = draw.textbbox((0, 0), name, font=f_bar)
    draw.text(
        ((width - (bbox[2] - bbox[0])) / 2, bar_y + (bar_h - (bbox[3] - bbox[1])) / 2 - bbox[1]),
        name,
        font=f_bar,
        fill=(255, 255, 255),
    )
    return img


def main():
    landscape(2000, 640, 1, "Titelbild").save(OUT / "hero.jpg", quality=82, optimize=True, progressive=True)

    subjects = [
        "streuobstwiese", "amphibien", "nistkasten",
        "bachlauf", "hecke", "wiesenpflege",
        "vogelkartierung", "insekten",
    ]
    for i, name in enumerate(subjects, start=2):
        landscape(800, 600, i, "Foto folgt").save(OUT / f"{name}.jpg", quality=80, optimize=True, progressive=True)

    logo_placeholder().save(OUT / "logo-placeholder.png", optimize=True)
    print(f"{len(list(OUT.glob('*')))} files in {OUT}")


if __name__ == "__main__":
    main()
