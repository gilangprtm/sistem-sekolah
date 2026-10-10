#!/usr/bin/env python3
"""Generate browser favicon assets from the same school logo used by the PWA.

Requires Pillow: python3 -m pip install Pillow
Run: python3 scripts/generate-favicons.py
Commit the generated files; this is not a runtime or npm build step.
"""
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "public/images/logo-sekolah.png"
PUBLIC = ROOT / "public"

with Image.open(SOURCE) as source:
    logo = source.convert("RGBA")
    if logo.width != logo.height:
        size = max(logo.size)
        square = Image.new("RGBA", (size, size), (0, 0, 0, 0))
        square.alpha_composite(logo, ((size - logo.width) // 2, (size - logo.height) // 2))
        logo = square

    for size in (16, 32):
        logo.resize((size, size), Image.Resampling.LANCZOS).save(
            PUBLIC / f"favicon-{size}x{size}.png", optimize=True
        )

    logo.save(
        PUBLIC / "favicon.ico",
        format="ICO",
        sizes=[(16, 16), (32, 32), (48, 48)],
    )

print("Generated favicon.ico and favicon-{16,32}x{16,32}.png from school logo.")
