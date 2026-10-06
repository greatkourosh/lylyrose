#!/usr/bin/env python3
"""Measure the finder result card's desktop split at a real viewport.

Headless --window-size is ignored by Chrome; it lays out at 500px, so the device
metrics override is set over CDP *before* the document loads.
"""
import asyncio
import json
import subprocess
import sys
import time
import urllib.request

import websockets

PORT = 9333
WIDTH = int(sys.argv[1]) if len(sys.argv) > 1 else 1440
HEIGHT = 1000
SRC = sys.argv[2] if len(sys.argv) > 2 else "https://lylyrose.local/perfume-finder/"

MEASURE = """
(() => {
  const card = document.querySelector('.asc-finder__result');
  if (!card) return {error: 'no result card'};
  const q = s => card.querySelector(s);
  const r = el => { if (!el) return null; const b = el.getBoundingClientRect();
    return {x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height)}; };
  const body = q('.asc-finder__body');
  return {
    viewport: {w: innerWidth, h: innerHeight},
    scrollW: document.documentElement.scrollWidth,
    overflowX: document.documentElement.scrollWidth > innerWidth,
    bodyDir: getComputedStyle(body).flexDirection,
    bodyAlign: getComputedStyle(body).alignItems,
    body: r(body), stack: r(q('.asc-finder__stack')), actions: r(q('.asc-finder__actions')),
    head: r(q('.asc-finder__head')), buy: r(q('.asc-finder__buy')), rating: r(q('.asc-finder__rating')),
    meta: r(q('.asc-finder__meta')), notes: r(q('.asc-finder__notes')),
    factorsCols: getComputedStyle(q('.asc-finder__factors')).gridTemplateColumns,
    factors: r(q('.asc-finder__factors')),
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
            # Before navigation, or the first layout is computed at the default size.
            await cmd("Emulation.setDeviceMetricsOverride",
                      {"width": WIDTH, "height": HEIGHT, "deviceScaleFactor": 1, "mobile": False})
            await cmd("Page.navigate", {"url": SRC})
            await asyncio.sleep(3)

            res = await cmd("Runtime.evaluate", {"expression": MEASURE, "returnByValue": True})
            print(json.dumps(res.get("result", {}).get("result", {}).get("value"),
                             ensure_ascii=False, indent=2))
    finally:
        proc.terminate()


asyncio.run(main())