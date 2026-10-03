#!/usr/bin/env python3
"""Generate the Coop Cable mark as SVG.

Coordinates are in the 192 x 192 space of the source icon
(logo_1769696262_0119378f.png); every point below was measured from that
file's pixels.  Run: python3 gen_mark.py <out-dir>
"""
import sys, os

BLUE = '#01B1DD'   # sampled: (1, 177, 221) covers 85% of the source icon


def catmull(points, closed=False):
    """Smooth cubic path through points (Catmull-Rom -> Bezier)."""
    n = len(points)
    if closed:
        pts = points
    else:
        pts = points
    def tangent(i):
        if closed:
            a = pts[(i - 1) % n]; b = pts[(i + 1) % n]
        else:
            a = pts[max(i - 1, 0)]; b = pts[min(i + 1, n - 1)]
        return ((b[0] - a[0]) / 2, (b[1] - a[1]) / 2)
    d = ['M%.2f %.2f' % pts[0]]
    last = n if closed else n - 1
    for i in range(last):
        p0 = pts[i]; p1 = pts[(i + 1) % n]
        t0 = tangent(i); t1 = tangent((i + 1) % n)
        d.append('C%.2f %.2f %.2f %.2f %.2f %.2f' % (
            p0[0] + t0[0] / 3, p0[1] + t0[1] / 3,
            p1[0] - t1[0] / 3, p1[1] - t1[1] / 3, p1[0], p1[1]))
    if closed:
        d.append('Z')
    return ' '.join(d)


# --- TV frame (brush stroke: 4 px at the top, tapering to a point bottom
# left, flaring to ~6 px at the cut end on the right) ---
frame_outer = [(46.3, 92), (46, 89), (46.8, 85.5), (48, 82.5), (49.6, 80.3),
               (52, 78.7), (55.5, 77.3), (60.5, 75.9), (66.5, 74.5), (73.5, 73.2),
               (80.5, 72), (87.5, 70.7), (94.5, 69.5), (102, 68.8), (111, 67.9),
               (122, 67.65), (132, 67.9), (137, 69.1), (140.3, 70.6),
               (142.5, 72.8), (144.6, 75.5), (146.2, 78.5), (147.4, 81.8),
               (148.3, 85), (148.9, 87)]
frame_inner = [(143.2, 88.2), (142.6, 86), (142, 84), (141.3, 82),
               (140.5, 80), (139.6, 78.3), (138.5, 76.9), (137, 75.6),
               (135, 74.5), (132, 73.6), (128, 73), (123, 72.6),
               (117, 72.5), (111, 72.6), (104, 73), (97, 73.5), (90, 74.3),
               (83, 75.2), (76, 76.3), (69, 77.5), (63, 78.7), (58, 79.8),
               (54, 81), (51.5, 82.5), (49.8, 84.4), (48.6, 86.6),
               (47.6, 89), (46.8, 91.2)]
frame_d = catmull(frame_outer) + ' L%.2f %.2f ' % frame_inner[0] + \
    catmull(frame_inner)[1:] + ' Z'

# --- bracket bottom right (square cut top, tapering to a point) ---
br_outer = [(142, 122), (148, 122), (148, 123.5), (147.3, 126), (147, 129),
            (147, 131.5), (146.3, 134), (145.8, 136), (144.6, 138), (143.7, 140),
            (142.8, 141.8), (141.7, 143), (140.5, 144.2), (138.6, 145.3),
            (136.3, 146.4), (133.3, 147.4), (129.3, 148.3), (124, 149),
            (118, 149.1)]
br_inner = [(118.5, 148.2), (122.5, 147.3), (126.5, 146.3), (129.5, 145.3),
            (131.8, 144.2), (133.8, 143), (135.3, 141.8), (137, 140.4),
            (138.1, 138.6), (138.9, 136.5), (139.6, 134), (140.3, 131.2),
            (141, 128), (141.5, 125), (142, 122.5)]
bracket_d = catmull(br_outer) + ' L%.2f %.2f ' % br_inner[0] + \
    catmull(br_inner)[1:] + ' Z'

# --- antennas ---
balls = [((75.1, 46.6), 3.8), ((102.7, 46.9), 3.25)]
sticks = [((75.1, 46.6), (89.0, 70.2), 3.05), ((102.7, 46.9), (96.6, 70.2), 2.6)]

# --- lettering: four bold rounded rings ---
RX_OUT, RY_OUT, RX_IN, RY_IN = 13.1, 13.05, 5.32, 6.31   # holes are upright ovals
rings = {'c': (83.2, 97.8), 'o': (111.25, 97.8), 'o2': (83.0, 124.8), 'p': (111.25, 125.0)}
SLOT = (97.66, 99.52)            # c: horizontal cut, y range
STEM = (98.34, 125.0, 7.6, 142.0)  # p: x, y-top, width, y-bottom


def stick_path(a, b, w):
    import math
    dx, dy = b[0] - a[0], b[1] - a[1]
    L = math.hypot(dx, dy); nx, ny = -dy / L * w / 2, dx / L * w / 2
    return 'M%.2f %.2f L%.2f %.2f L%.2f %.2f L%.2f %.2f Z' % (
        a[0] + nx, a[1] + ny, b[0] + nx, b[1] + ny, b[0] - nx, b[1] - ny, a[0] - nx, a[1] - ny)


def ring_path(c, rxo, ryo, rxi, ryi):
    cx, cy = c
    return ('M%.2f %.2f a%.2f %.2f 0 1 0 %.2f 0 a%.2f %.2f 0 1 0 %.2f 0 Z '
            'M%.2f %.2f a%.2f %.2f 0 1 1 %.2f 0 a%.2f %.2f 0 1 1 %.2f 0 Z') % (
        cx - rxo, cy, rxo, ryo, 2 * rxo, rxo, ryo, -2 * rxo,
        cx - rxi, cy, rxi, ryi, 2 * rxi, rxi, ryi, -2 * rxi)


def c_path(c, rxo, ryo, rxi, ryi, slot):
    """Ring with a horizontal slot on the right (the letter c)."""
    import math
    cx, cy = c
    y0, y1 = slot
    ox0 = cx + rxo * math.sqrt(1 - ((y0 - cy) / ryo) ** 2)
    ox1 = cx + rxo * math.sqrt(1 - ((y1 - cy) / ryo) ** 2)
    ix0 = cx + rxi * math.sqrt(1 - ((y0 - cy) / ryi) ** 2)
    ix1 = cx + rxi * math.sqrt(1 - ((y1 - cy) / ryi) ** 2)
    # outer: from upper terminal anticlockwise (via top, left, bottom) to lower terminal
    return ('M%.2f %.2f A%.2f %.2f 0 1 0 %.2f %.2f L%.2f %.2f A%.2f %.2f 0 1 1 %.2f %.2f Z' % (
        ox0, y0, rxo, ryo, ox1, y1, ix1, y1, rxi, ryi, ix0, y0))


def mark_group(fill):
    parts = ['<g fill="%s">' % fill]
    parts.append('<path d="%s"/>' % frame_d)
    parts.append('<path d="%s"/>' % bracket_d)
    for (c, r) in balls:
        parts.append('<circle cx="%.2f" cy="%.2f" r="%.2f"/>' % (c[0], c[1], r))
    for (a, b, w) in sticks:
        parts.append('<path d="%s"/>' % stick_path(a, b, w))
    # letters: rings with even-odd holes; the c gets a slot, the p a stem
    parts.append('<path fill-rule="evenodd" d="%s"/>' % ring_path(rings['o'], RX_OUT, RY_OUT, RX_IN, RY_IN))
    parts.append('<path fill-rule="evenodd" d="%s"/>' % ring_path(rings['o2'], RX_OUT, RY_OUT, RX_IN, RY_IN))
    parts.append('<path fill-rule="evenodd" d="%s"/>' % ring_path(rings['p'], RX_OUT, RY_OUT, RX_IN, RY_IN))
    parts.append('<path d="%s"/>' % c_path(rings['c'], RX_OUT, RY_OUT, RX_IN, RY_IN, SLOT))
    x, yt, w, yb = STEM
    parts.append('<path d="M%.2f %.2f h%.2f V%.2f a1.5 1.5 0 0 1 -1.5 1.5 h%.2f a1.5 1.5 0 0 1 -1.5 -1.5 Z"/>' % (
        x, yt, w, yb - 1.5, -(w - 3)))
    parts.append('</g>')
    return '\n'.join(parts)


# mark bounding box in the 192 space (measured): x 46..149, y 43..149
MARK_BOX = (45, 42, 105, 108)   # x, y, w, h with 1 px air


def svg(viewbox, w, h, body):
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s" width="%d" height="%d">\n%s\n</svg>\n'
            % (viewbox, w, h, body))


def main(out):
    os.makedirs(out, exist_ok=True)
    vb = '%d %d %d %d' % MARK_BOX
    with open(os.path.join(out, 'mark-white.svg'), 'w') as f:
        f.write(svg(vb, MARK_BOX[2] * 4, MARK_BOX[3] * 4, mark_group('#FFFFFF')))
    with open(os.path.join(out, 'mark-blue.svg'), 'w') as f:
        f.write(svg(vb, MARK_BOX[2] * 4, MARK_BOX[3] * 4, mark_group(BLUE)))
    with open(os.path.join(out, 'icon.svg'), 'w') as f:
        f.write(svg('0 0 192 192', 1024, 1024,
                    '<rect width="192" height="192" fill="%s"/>\n%s' % (BLUE, mark_group('#FFFFFF'))))
    print('wrote', out)


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else '.')
