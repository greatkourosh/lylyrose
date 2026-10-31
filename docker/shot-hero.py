#!/usr/bin/env python3
"""Screenshot the home hero at a real viewport, with autoplay frozen.

The hero rotates every 5s, so a screenshot taken at an arbitrary moment may
catch a slide mid-transition. Setting --dk-hero-photo on slide 1 and freezing
the timers keeps what we capture stable.

Usage: python3 shot-hero.py [width] [url] [out.png]
"""
import asyncio
import base64
import json
import subprocess
import sys
import urllib.request

import websockets

PORT = 9339
WIDTH = int(sys.argv[1]) if len(sys.argv) > 1 else 1440
SRC = sys.argv[2] if len(sys.argv) > 2 else "https://lylyrose.local/"
OUT = sys.argv[3] if len(sys.argv) > 3 else "/tmp/hero.png"
HEIGHT = 1200


async def main():
    proc = subprocess.Popen(
        ["google-chrome", "--headless=new", "--no-sandbox", "--disable-gpu",
         "--ignore-certificate-errors",
         f"--remote-debugging-port={PORT}", "--remote-allow-origins=*", "about:blank"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )
    try:
        for _ in range(80):
            try:
                json.loads(urllib.request.urlopen(
                    f"http://127.0.0.1:{PORT}/json/version", timeout=5).read())
                break
            except Exception:
                await asyncio.sleep(0.25)

        tabs = json.loads(urllib.request.urlopen(
            f"http://127.0.0.1:{PORT}/json/list", timeout=5).read())
        tab = next(t for t in tabs if t["type"] == "page")
        async with websockets.connect(
            tab["webSocketDebuggerUrl"], max_size=64 * 1024 * 1024
        ) as ws:
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
                      {"width": WIDTH, "height": HEIGHT, "deviceScaleFactor": 1,
                       "mobile": False})
            # Kill animation timers before anything runs, so the hero cannot
            # advance between paint and capture.
            await cmd("Page.addScriptToEvaluateOnNewDocument", {"source": """
                (() => {
                  const noop = () => 0;
                  window.setInterval = noop; window.setTimeout = noop;
                })();
            """})
            await cmd("Page.navigate", {"url": SRC})
            await asyncio.sleep(6)

            info = await cmd("Runtime.evaluate", {"expression": """
                (() => {
                  const s = document.querySelector('.dk-hero-slide.is-active');
                  const h = document.querySelector('.dk-hero');
                  if (!h) return null;
                  const cs = s ? getComputedStyle(s, '::before') : null;
                  const b = h.getBoundingClientRect();
                  return {
                    classes: s ? s.className : null,
                    photoVar: s ? s.style.getPropertyValue('--dk-hero-photo') : null,
                    beforeImage: cs ? cs.backgroundImage.slice(0, 120) : null,
                    beforeSize: cs ? cs.backgroundSize : null,
                    slideH: s ? Math.round(s.getBoundingClientRect().height) : 0,
                    clip: {x: 0, y: Math.max(0, b.top + scrollY - 10),
                           width: innerWidth, height: Math.round(b.height) + 20, scale: 1},
                  };
                })()
            """, "returnByValue": True})
            v = info["result"]["result"]["value"]
            print(json.dumps(v, indent=2, ensure_ascii=False))
            if not v or not v.get("clip"):
                print("no .dk-hero found")
                return
            shot = await cmd("Page.captureScreenshot",
                             {"format": "png", "clip": v["clip"],
                              "captureBeyondViewport": True})
            open(OUT, "wb").write(base64.b64decode(shot["result"]["data"]))
            print("wrote", OUT)
    finally:
        proc.terminate()


asyncio.run(main())
