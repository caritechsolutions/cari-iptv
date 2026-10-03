#!/usr/bin/env python3
"""Render every coopcable brand PNG from the SVG generators.

Run from this folder:  python3 gen_assets.py
Needs: pillow, numpy, cairosvg.
Writes ../icon.png, ../icon_foreground.png, ../splash.png, ../logo.png,
../logo_dark.png, and the SVG files next to this script.
"""
import io, math, os
import cairosvg, numpy as np
from PIL import Image, ImageDraw
import gen_mark, gen_wordmark

HERE = os.path.dirname(os.path.abspath(__file__))
BRAND = os.path.dirname(HERE)
BLUE = gen_mark.BLUE                 # #01B1DD, sampled from the square icon
DARK = '#0F172A'                     # lettering on light backgrounds (= BACKGROUND_COLOR)
WHITE = '#FFFFFF'

# mark extents inside gen_mark's 192 space (measured on the rendered path)
MARK_X0, MARK_Y0, MARK_W, MARK_H = 46.0, 42.8, 103.0, 106.4


def svg(viewbox, w, h, body):
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s" width="%d" height="%d">\n%s\n</svg>\n'
            % (viewbox, w, h, body))


def png(svg_text, w, h):
    data = cairosvg.svg2png(bytestring=svg_text.encode(), output_width=w, output_height=h)
    return Image.open(io.BytesIO(data)).convert('RGBA')


def mark_at(fill, x, y, height):
    """The mark, translated so its bbox top-left is (x, y) and scaled to `height`."""
    s = height / MARK_H
    return '<g transform="translate(%.3f %.3f) scale(%.5f) translate(%.3f %.3f)">%s</g>' % (
        x, y, s, -MARK_X0, -MARK_Y0, gen_mark.mark_group(fill))


def lettering_at(fill, x, y, cap_height):
    """CABLE, translated so its cap-top-left is (x, y), scaled to `cap_height`."""
    s = cap_height / (gen_wordmark.BASE - gen_wordmark.TOP)
    return '<g transform="translate(%.3f %.3f) scale(%.5f) translate(%.3f %.3f)">%s</g>' % (
        x, y, s, -124.9, -gen_wordmark.TOP, gen_wordmark.lettering_group(fill))


def enclosing_radius(img):
    """Radius of the smallest circle about the canvas centre that holds all opaque pixels."""
    a = np.array(img.getchannel('A')) > 8
    ys, xs = np.where(a); cx, cy = (img.width - 1) / 2, (img.height - 1) / 2
    return math.sqrt(((xs - cx) ** 2 + (ys - cy) ** 2).max())


def main():
    out = {}
    # --- wordmark layout (measured from 320x180_white.png): mark bbox 94x96 at (11,43),
    #     lettering cap height 54.8 at x 124.9, top 76.3; gap mark->lettering 21 px ---
    LW, LH = 1800, 600     # logo canvas (6x the source wordmark's content box + air)
    S = 6.0
    body = lambda letter_fill: mark_at(BLUE, 3, 3, 96.0) + lettering_at(
        letter_fill, 3 + 93.0 + 21.0, 3 + (76.3 - 43.0), 54.8)
    logo_dark = svg('0 0 %d %d' % (LW / S, LH / S), LW, LH, body(WHITE))
    logo_light = svg('0 0 %d %d' % (LW / S, LH / S), LW, LH, body(DARK))
    for name, text in (('logo_dark', logo_dark), ('logo', logo_light)):
        with open(os.path.join(HERE, name + '.svg'), 'w') as f:
            f.write(text)
        im = png(text, LW, LH)
        bbox = im.getbbox(); pad = 12
        im = im.crop((max(0, bbox[0] - pad), max(0, bbox[1] - pad), min(LW, bbox[2] + pad), min(LH, bbox[3] + pad)))
        im.save(os.path.join(BRAND, name + '.png'), optimize=True); out[name] = im.size

    # --- icon.png: the square source at 1024 (mark 54% of the width, margins as supplied) ---
    icon = png(svg('0 0 192 192', 1024, 1024,
                   '<rect width="192" height="192" fill="%s"/>%s' % (BLUE, gen_mark.mark_group(WHITE))), 1024, 1024)
    icon.convert('RGB').save(os.path.join(BRAND, 'icon.png'), optimize=True); out['icon'] = icon.size

    # --- icon_foreground.png: white mark only, transparent, inside the adaptive safe circle
    #     (66/108 of the canvas = 626 px diameter at 1024) with 4% air ---
    safe_r = 1024 * 66 / 108 / 2 * 0.96
    h = 560.0
    for _ in range(6):   # scale so the mark's enclosing circle (about the canvas centre) fits
        fg = png(svg('0 0 1024 1024', 1024, 1024, mark_at(WHITE, (1024 - h * MARK_W / MARK_H) / 2, (1024 - h) / 2, h)), 1024, 1024)
        r = enclosing_radius(fg)
        if abs(r - safe_r) < 1.0:
            break
        h *= safe_r / r
    fg.save(os.path.join(BRAND, 'icon_foreground.png'), optimize=True)
    out['icon_foreground'] = (fg.size, 'mark height %d px, enclosing radius %d px, safe radius %d px' % (h, r, safe_r))

    # --- splash.png: white mark centred on the brand blue (1024 square; the splash
    #     colour is the same blue, so the tile is seamless full-screen) ---
    sh = 520.0
    sp = png(svg('0 0 1024 1024', 1024, 1024, '<rect width="1024" height="1024" fill="%s"/>%s' % (
        BLUE, mark_at(WHITE, (1024 - sh * MARK_W / MARK_H) / 2, (1024 - sh) / 2, sh))), 1024, 1024)
    sp.save(os.path.join(BRAND, 'splash.png'), optimize=True); out['splash'] = sp.size

    for k, v in out.items():
        print('%-16s %s' % (k, v))


if __name__ == '__main__':
    main()
