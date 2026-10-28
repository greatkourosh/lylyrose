#!/usr/bin/env python3
"""Measure the care landing pages' tile row and grid at a real viewport.

Headless --window-size is ignored by Chrome (it lays out at 500px), so the
device metrics override is set over CDP *before* navigation.

Usage: python3 measure-care.py [width] [url]
"""
import asyncio
import json
import subprocess
import sys
import urllib.request

import websockets

PORT = 9337
WIDTH = int(sys.argv[1]) if len(sys.argv) > 1 else 1440
HEIGHT = 1000
SRC = sys.argv[2] if len(sys.argv) > 2 else "https://lylyrose.local/skincare/"

MEASURE = """
(() => {
  const r = el => { if (!el) return null; const b = el.getBoundingClientRect();
    return {x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height)}; };
  const tiles = [...document.querySelectorAll('.pf-family')].map(t => {
    const img = t.querySelector('.pf-family-img img');
    const box = t.querySelector('.pf-family-img').getBoundingClientRect();
    return {
      name: t.querySelector('.pf-family-name').textContent.trim().replace(/\\s*\\u2039\\s*$/, ''),
      src: img ? img.currentSrc.split('/').pop() : null,
      natural: img ? img.naturalWidth + 'x' + img.naturalHeight : null,
      loaded: img ? (img.complete && img.naturalWidth > 0) : false,
      w: Math.round(box.width), h: Math.round(box.height),
      imgPadding: img ? getComputedStyle(img.parentElement).padding : null,
      imgObjectFit: img ? getComputedStyle(img).objectFit : null,
    };
  });
  const cover = document.querySelector('.pf-family-img');
  return {
    viewport: {w: innerWidth, h: innerHeight},
    scrollW: document.documentElement.scrollWidth,
    overflowX: document.documentElement.scrollWidth > innerWidth,
    docH: document.documentElement.scrollHeight,
    familyRow: r(document.querySelector('.pf-family-row')),
    familyRowCols: document.querySelector('.pf-family-row') ? getComputedStyle(document.querySelector('.pf-family-row')).gridTemplateColumns : null,
    familyImgBg: cover ? getComputedStyle(cover).backgroundColor : null,
    tiles,
    tileCount: tiles.length,
    gridCols: document.querySelector('.pf-grid') ? getComputedStyle(document.querySelector('.pf-grid')).gridTemplateColumns : null,
    cardCount: document.querySelectorAll('.pf-card').length,
    railCards: document.querySelectorAll('.pf-best-card').length,
    styleVer: (document.querySelector('link[id*=lylyrose-style]') || {}).href,
  };
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
                http("/json/version")
                break
            except Exception:
                await asyncio.sleep(0.25)

        tab = next(t for t in http("/json/list") if t["type"] == "page")
        async with websockets.connect(tab["webSocketDebuggerUrl"], max_size=32 * 1024 * 1024) as ws:
            n = 0

            async def cmd(method, params=None):
                nonlocal n
                n += 1
                await ws.send(json.dumps({"id": n, "method": method, "params": params or {}}))
                while True:
                    msg = json.loads(await ws.recv())
                    if msg.get("id") == n:
                        return msg

            await cmd("Page.enable")
            await cmd("Runtime.enable")
            await cmd("Emulation.setDeviceMetricsOverride",
                      {"width": WIDTH, "height": HEIGHT, "deviceScaleFactor": 1, "mobile": False})
            await cmd("Page.navigate", {"url": SRC})
            await asyncio.sleep(7)

            res = await cmd("Runtime.evaluate", {"expression": MEASURE, "returnByValue": True})
            print(json.dumps(res.get("result", {}).get("result", {}).get("value"),
                             ensure_ascii=False, indent=2))
    finally:
        proc.terminate()


asyncio.run(main())
