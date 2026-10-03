#!/usr/bin/env python3
"""Generate the Coop Cable "CABLE" lettering as SVG.

Coordinates are in the 320 x 180 space of the source wordmark
(320x180_white.png); every value was measured from that file's pixels
(sub-pixel edges from alpha coverage).  Run: python3 gen_wordmark.py <out-dir>
"""
import sys, os

TOP, BASE = 76.3, 131.1            # cap height 54.8
BAR = 7.7                          # horizontal bar thickness


def rrect(x0, y0, x1, y1, rl=0.0, rr=0.0):
    """Rounded rectangle; rl = radius of the two left corners, rr = right."""
    return ('M%.2f %.2f H%.2f A%.2f %.2f 0 0 1 %.2f %.2f V%.2f A%.2f %.2f 0 0 1 %.2f %.2f '
            'H%.2f A%.2f %.2f 0 0 1 %.2f %.2f V%.2f A%.2f %.2f 0 0 1 %.2f %.2f Z') % (
        x0 + rl, y0, x1 - rr, rr, rr, x1, y0 + rr, y1 - rr, rr, rr, x1 - rr, y1,
        x0 + rl, rl, rl, x0, y1 - rl, y0 + rl, rl, rl, x0 + rl, y0)


def poly(pts):
    return 'M' + ' L'.join('%.2f %.2f' % p for p in pts) + ' Z'


# ---- C: rounded outer, rounded counter, flat aperture on the right ----
C_OUTER = (124.9, 75.6, 156.4, 131.6, 12.5, 13.5)
C_INNER = (135.3, 82.9, 146.6, 124.3, 3.5, 3.5)
C_APERTURE = (135.3, 94.4, 157, 111.0)

# ---- A: flat top, straight legs, flat-cut counter apex ----
A_OUTER = [(171.8, TOP), (186.2, TOP), (197.5, BASE), (186.9, BASE), (185.2, 120.4),
           (172.2, 120.4), (170.3, BASE), (160.7, BASE)]
A_COUNTER = [(178.0, 86.6), (179.1, 86.6), (183.6, 113.0), (173.6, 113.0)]

# ---- B: stem + two bowls; counters with rounded right corners ----
B_X0, B_STEM = 203.2, 213.4
B_TOP_BOWL = (B_X0, TOP, 235.1, 101.6, 0, 10.5)     # bottom-right radius handled below
B_BOT_BOWL = (B_X0, 101.6, 236.3, BASE, 0, 11.5)
B_WAIST_X = 230.0
B_C1 = (B_STEM, 84.0, 224.7, 98.6, 0, 5.0)
B_C2 = (B_STEM, 106.3, 225.8, 123.6, 0, 5.0)

# ---- L ----
L = [(243.1, TOP), (253.4, TOP), (253.4, 123.6), (271.4, 123.6), (271.4, BASE), (243.1, BASE)]

# ---- E ----
E_STEM = (277.1, TOP, 287.4, BASE)
E_BARS = [(277.1, TOP, 306.4, TOP + BAR), (277.1, 98.6, 302.4, 106.3), (277.1, 123.6, 306.4, BASE)]


def b_path():
    """B outline: stem, top bowl, waist, bottom bowl, as one outer path."""
    x0, y0, xt, yw = B_X0, TOP, 235.1, 101.6
    xb, y1 = 236.3, BASE
    return ('M%.2f %.2f H%.2f A12 12 0 0 1 %.2f %.2f V93.0 '
            'C%.2f 97.5 %.2f 100.0 %.2f %.2f '
            'C233.0 102.6 %.2f 105.5 %.2f 112.0 V118.1 '
            'A13 13 0 0 1 %.2f %.2f H%.2f Z') % (
        x0, y0, xt - 12, xt, y0 + 12,
        xt, B_WAIST_X + 1.5, B_WAIST_X, yw,
        xb, xb,
        xb - 13, y1, x0)


def lettering_group(fill):
    p = ['<g fill="%s" fill-rule="evenodd">' % fill]
    # C = outer minus inner minus aperture (evenodd on nested paths; aperture via clip rects)
    p.append('<path d="%s %s"/>' % (rrect(*C_OUTER), rrect(*C_INNER)))
    p.append('</g><g fill="%s">' % fill)          # cover trick is not needed; carve aperture:
    p.pop(); p.pop()
    p = ['<g fill="%s">' % fill]
    # C: draw as outer-minus-inner with evenodd, then cut the aperture with a mask-free approach:
    # use two sub-paths (outer, inner) evenodd and a third rect sub-path for the aperture.
    # The aperture rect overlaps the inner counter, so build it as a polygon that lies
    # entirely in the ring: x from inner right edge to beyond the outer edge.
    ax0, ay0, ax1, ay1 = C_APERTURE
    ap = 'M%.2f %.2f H%.2f V%.2f H%.2f Z' % (C_INNER[2] - 0.01, ay0, ax1, ay1, C_INNER[2] - 0.01)
    # evenodd: outer(1) + inner(2) + aperture(2) -> ring minus aperture; aperture part outside
    # the outer shape counts 1 (odd) and would fill, so clip it with clipPath to the outer.
    p.append('<clipPath id="cclip"><path d="%s"/></clipPath>' % rrect(*C_OUTER))
    p.append('<path clip-path="url(#cclip)" fill-rule="evenodd" d="%s %s %s"/>' % (
        rrect(*C_OUTER), rrect(*C_INNER), ap))
    p.append('<path fill-rule="evenodd" d="%s %s"/>' % (poly(A_OUTER), poly(A_COUNTER)))
    p.append('<path fill-rule="evenodd" d="%s %s %s"/>' % (b_path(), rrect(*B_C1), rrect(*B_C2)))
    p.append('<path d="%s"/>' % poly(L))
    p.append('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f"/>' % (
        E_STEM[0], E_STEM[1], E_STEM[2] - E_STEM[0], E_STEM[3] - E_STEM[1]))
    for (x0, y0, x1, y1) in E_BARS:
        p.append('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f"/>' % (x0, y0, x1 - x0, y1 - y0))
    p.append('</g>')
    return '\n'.join(p)


LETTER_BOX = (124, 75, 184, 58)   # x, y, w, h in the 320 space, 1 px air


def svg(viewbox, w, h, body):
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s" width="%d" height="%d">\n%s\n</svg>\n'
            % (viewbox, w, h, body))


def main(out):
    os.makedirs(out, exist_ok=True)
    vb = '%d %d %d %d' % LETTER_BOX
    for name, fill in (('lettering-white.svg', '#FFFFFF'), ('lettering-blue.svg', '#01B1DD'),
                       ('lettering-black.svg', '#000000')):
        with open(os.path.join(out, name), 'w') as f:
            f.write(svg(vb, LETTER_BOX[2] * 4, LETTER_BOX[3] * 4, lettering_group(fill)))
    print('wrote', out)


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else '.')
