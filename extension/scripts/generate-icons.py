import os, zlib, struct, math

def create_png(width, height, get_pixel_func):
    raw_bytes = bytearray()
    for y in range(height):
        raw_bytes.append(0)  # filter type 0 (None)
        for x in range(width):
            r, g, b, a = get_pixel_func(x, y, width, height)
            raw_bytes.extend((r, g, b, a))
            
    def chunk(tag, data):
        return struct.pack(">I", len(data)) + tag + data + struct.pack(">I", zlib.crc32(tag + data) & 0xffffffff)

    ihdr_data = struct.pack(">IIBBBBB", width, height, 8, 6, 0, 0, 0)
    png_data = b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", ihdr_data) + chunk(b"IDAT", zlib.compress(raw_bytes)) + chunk(b"IEND", b"")
    return png_data

def emis_icon_pixel(x, y, w, h):
    # Normalized coords [0, 1]
    nx = x / (w - 1) if w > 1 else 0.5
    ny = y / (h - 1) if h > 1 else 0.5
    
    # Rounded rect background (radius ~ 22%)
    r = 0.22
    dx = max(r - nx, 0, nx - (1 - r))
    dy = max(r - ny, 0, ny - (1 - r))
    dist = math.sqrt(dx * dx + dy * dy)
    
    # Anti-aliasing border
    edge = 0.03
    if dist > r + edge:
        return (0, 0, 0, 0) # transparent
    
    # Background gradient: EMIS teal/emerald (#0d9488 to #047857)
    t = ny
    bg_r = int(13 * (1 - t) + 4 * t)
    bg_g = int(148 * (1 - t) + 120 * t)
    bg_b = int(136 * (1 - t) + 87 * t)
    
    alpha = 255
    if dist > r - edge:
        alpha = int(255 * max(0, min(1, (r + edge - dist) / (2 * edge))))
    
    # Foreground symbol: White spreadsheet/export icon with table lines & accent
    # Box bounds: nx in [0.22, 0.78], ny in [0.22, 0.78]
    if 0.22 <= nx <= 0.78 and 0.22 <= ny <= 0.78:
        # border of document
        is_border = (nx < 0.28 or nx > 0.72 or ny < 0.28 or ny > 0.72)
        # horizontal dividers
        is_h_divider = (0.42 <= ny <= 0.46) or (0.58 <= ny <= 0.62)
        # vertical divider
        is_v_divider = (0.47 <= nx <= 0.53) and ny >= 0.42
        
        # header bar
        if 0.28 <= ny <= 0.42 and 0.28 <= nx <= 0.72:
            return (255, 255, 255, alpha)
        if is_border or is_h_divider or is_v_divider:
            return (255, 255, 255, alpha)
            
        # cells fill slightly lighter teal
        return (min(255, bg_r + 25), min(255, bg_g + 25), min(255, bg_b + 25), alpha)
        
    return (bg_r, bg_g, bg_b, alpha)

def main():
    icons_dir = r"d:\ext_emis_dl_sis\icons"
    os.makedirs(icons_dir, exist_ok=True)
    
    for size in (16, 48, 128):
        png = create_png(size, size, emis_icon_pixel)
        path = os.path.join(icons_dir, f"icon-{size}.png")
        with open(path, "wb") as f:
            f.write(png)
        print(f"Generated {path}: {len(png)} bytes")

if __name__ == "__main__":
    main()
