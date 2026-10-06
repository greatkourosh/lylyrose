#!/usr/bin/env python3
"""Audit the Night palette by reading COMPUTED colours, not the CSS source.

Night is a visitor choice, so it is applied by rewriting :root in JS. Nothing in
the stylesheet is a "night" block to grep for. Reading the CSS would only show
the light tokens again; only getComputedStyle sees what the browser resolved
after the override, which is where the actual bugs live.

Usage: night-audit.py <slug> <url>
"""
import asyncio
import json
import subprocess
import sys
import urllib.request

import websockets

PORT = 9345
WIDTH = int(sys.argv[3]) if len(sys.argv) > 3 else 1440
HEIGHT = 1000
SLUG = sys.argv[1]
SRC = sys.argv[2]

# Walk visible elements, group by resolved background, and report any element
# whose background is near-white while the page is supposed to be dark. Those
# are the hardcoded #fff surfaces the palette never reaches.
MEASURE = """
(() => {
  const cs = getComputedStyle(document.body);
  const px = el => getComputedStyle(el);
  const lum = c => {
    const [r,g,b] = c.match(/[\\d.]+/g).map(Number).slice(0,3).map(v => v/255)
      .map(v => v <= .03928 ? v/12.92 : ((v+.055)/1.055) ** 2.4);
    return .2126*r + .7152*g + .0722*b;
  };
  const cr = (a, b) => { const [x,y] = [lum(a), lum(b)].sort((m,n) => n-m);
    return (x+.05)/(y+.05); };

  // Nearest ancestor with a real fill. Falling back to <body> made every child
  // of the light-blue footer look like near-black text on a dark page, when the
  // footer fill is what it actually sits on.
  const bgOf = el => {
    for (let n = el; n; n = n.parentElement) {
      const c = getComputedStyle(n).backgroundColor;
      if (c !== 'rgba(0, 0, 0, 0)' && c !== 'transparent') return c;
    }
    return getComputedStyle(document.body).backgroundColor;
  };

  const bg = cs.backgroundColor;
  const out = { bg, bodyColor: cs.color, page: document.documentElement.getAttribute('data-palette'), items: [] };
  const seen = new Map();
  for (const el of document.querySelectorAll('body *')) {
    const b = el.getBoundingClientRect();
    if (b.width < 12 || b.height < 12) continue;
    const s = px(el);
    const rect = [Math.round(b.x), Math.round(b.y), Math.round(b.width), Math.round(b.height)].join(',');
    const key = s.backgroundColor + '|' + s.color + '|' + rect;
    if (seen.has(key)) continue;
    seen.set(key, 1);
    const kids = el.children.length;
    // Only leaf-ish nodes: a wrapper's colour is inherited noise.
    if (kids > 2) continue;
    out.items.push({
      sel: el.tagName.toLowerCase() + (el.className && typeof el.className === 'string'
            ? '.' + el.className.trim().split(/\\s+/).slice(0,3).join('.') : ''),
      bg: (s.backgroundColor === 'rgba(0, 0, 0, 0)' || s.backgroundColor === 'transparent')
            ? bgOf(el) : s.backgroundColor,
      color: s.color, rect,
      cr: Math.round(cr(s.color,
            (s.backgroundColor === 'rgba(0, 0, 0, 0)' || s.backgroundColor === 'transparent')
              ? bgOf(el) : s.backgroundColor) * 100) / 100,
    });
  }
  return out;
})()
"""

def http(path):
    return json.loads(urllib.request.urlopen(f"http://127.0.0.1:{PORT}{path}", timeout=30).read())

async def main():
    proc = subprocess.Popen(
        ["google-chrome", "--headless=new", "--no-sandbox", "--disable-gpu",
         f"--remote-debugging-port={PORT}", "--remote-allow-origins=*", "about:blank"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )
    try:
        for _ in range(80):
            try:
                http("/json/version"); break
            except Exception:
                await asyncio.sleep(0.25)
        req = urllib.request.Request(f"http://127.0.0.1:{PORT}/json/new?about:blank", method="PUT")
        t = json.loads(urllib.request.urlopen(req, timeout=30).read())
        async with websockets.connect(t["webSocketDebuggerUrl"], max_size=None) as ws:
            i = [0]
            async def send(method, params=None):
                i[0] += 1
                await ws.send(json.dumps({"id": i[0], "method": method, "params": params or {}}))
                while True:
                    r = json.loads(await ws.recv())
                    if r.get("id") == i[0]:
                        return r

            await send("Emulation.setDeviceMetricsOverride",
                       {"width": WIDTH, "height": HEIGHT, "deviceScaleFactor": 1, "mobile": False})
            await send("Page.enable")
            await send("Network.enable")
            await send("Network.setCookie",
                       {"name": "lylyrose_palette", "value": SLUG, "url": SRC})
            # The palette is rendered server-side from the cookie, so it has to be
            # in place BEFORE the document is fetched. Setting it on an already
            # loaded page then reading computed style only measures Ivory.
            await send("Page.navigate", {"url": SRC})
            await asyncio.sleep(5)
            r = await send("Runtime.evaluate",
                           {"expression": MEASURE, "returnByValue": True})
            val = r["result"]["result"].get("value")
            if not val:
                print(json.dumps(r, indent=2)[:2000]); return
            print(f"page={val['page']}  bg={val['bg']}  bodyColor={val['bodyColor']}")
            bad = [x for x in val["items"] if x["cr"] < 4.5]
            print(f"\n-- {len(bad)} of {len(val['items'])} leaf nodes under 4.5:1 --")
            for x in bad:
                print(f"  {x['cr']:>5}:1  bg={x['bg']:<22} color={x['color']:<22} {x['sel']}")
    finally:
        proc.terminate()

asyncio.run(main())
