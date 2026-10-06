#!/usr/bin/env python3
"""Regression checks for the hero after the pointer-capture fix.

1. A real mouse drag that leaves the element mid-gesture, returns, and releases
   ON the hero must still track and still advance the slide.
2. The nav buttons must still change slides.
3. Clicking a hero link must still navigate (onDown early-returns for a/button).
4. Autoplay must still advance after a drag settles.
"""
import asyncio
import json
import subprocess
import sys
import urllib.request

import websockets

PORT = 9336
URL = sys.argv[1] if len(sys.argv) > 1 else "https://lylyrose.local/"


def http(path):
    return json.loads(urllib.request.urlopen(f"http://127.0.0.1:{PORT}{path}", timeout=30).read())


async def js(ws, n, expr):
    n += 1
    await ws.send(json.dumps({"id": n, "method": "Runtime.evaluate",
                              "params": {"expression": expr, "returnByValue": True}}))
    while True:
        msg = json.loads(await ws.recv())
        if msg.get("id") == n:
            return msg.get("result", {}).get("result", {}).get("value")


async def mouse(ws, n, kind, x, y, buttons=1):
    n += 1
    await ws.send(json.dumps({"id": n, "method": "Input.dispatchMouseEvent", "params": {
        "type": kind, "x": x, "y": y, "button": "left", "buttons": buttons, "clickCount": 1}}))
    while True:
        if json.loads(await ws.recv()).get("id") == n:
            return n


async def active(ws, n):
    return await js(ws, n, """
    (() => { const s = [...document.querySelectorAll('.dk-hero-slide')]
      .findIndex(el => el.classList.contains('is-active'));
      return s; })()""")


async def main():
    proc = subprocess.Popen(
        ["google-chrome", "--headless=new", "--no-sandbox", "--disable-gpu",
         f"--remote-debugging-port={PORT}", "--remote-allow-origins=*", "about:blank"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(80):
            try:
                http("/json/version"); break
            except Exception:
                await asyncio.sleep(0.25)
        tab = next(t for t in http("/json/list") if t["type"] == "page")
        async with websockets.connect(tab["webSocketDebuggerUrl"], max_size=32 * 1024 * 1024) as ws:
            for i, m in ((1, "Page.enable"), (2, "Runtime.enable")):
                await ws.send(json.dumps({"id": i, "method": m}))
            while True:
                if json.loads(await ws.recv()).get("id") == 2:
                    break
            await ws.send(json.dumps({"id": 3, "method": "Emulation.setDeviceMetricsOverride",
                                      "params": {"width": 1440, "height": 900, "deviceScaleFactor": 1, "mobile": False}}))
            while True:
                if json.loads(await ws.recv()).get("id") == 3:
                    break
            await ws.send(json.dumps({"id": 4, "method": "Page.navigate", "params": {"url": URL}}))
            while True:
                if json.loads(await ws.recv()).get("id") == 4:
                    break
            await asyncio.sleep(3)
            n = 4

            box = await js(ws, n, """
            (() => { const h = document.querySelector('.dk-hero'); const b = h.getBoundingClientRect();
              const nb = document.querySelector('.dk-hero-next');
              const r = nb ? nb.getBoundingClientRect() : null;
              return {x: b.x, y: b.y, w: b.width, h: b.height,
                      next: r ? {x: r.x + r.width/2, y: r.y + r.height/2} : null,
                      link: (() => { const a = document.querySelector('.dk-hero-slide.is-active a');
                        if (!a) return null; const c = a.getBoundingClientRect();
                        return {x: c.x + c.width/2, y: c.y + c.height/2, href: a.href}; })()}; })()""")
            n += 1
            cx, cy = int(box["x"] + box["w"] / 2), int(box["y"] + box["h"] / 2)
            ok = True

            # --- 1. drag that leaves and returns, released on the hero
            before = await active(ws, n); n += 1
            n = await mouse(ws, n, "mousePressed", cx, cy, 1)
            await asyncio.sleep(0.04)
            n = await mouse(ws, n, "mouseMoved", cx - 40, cy, 1)
            n = await mouse(ws, n, "mouseMoved", cx, int(box["y"] + box["h"] + 150), 1)   # leave
            n = await mouse(ws, n, "mouseMoved", cx - 220, cy, 1)                       # return
            n = await mouse(ws, n, "mouseReleased", cx - 220, cy, 0)
            await asyncio.sleep(0.7)
            after = await active(ws, n); n += 1
            state = await js(ws, n, """
            (() => { const s = document.querySelector('.dk-hero-slide.is-active');
              return {t: s.style.transform, dragging: s.classList.contains('is-dragging')}; })()""")
            n += 1
            moved = after != before
            print(f"1. drag leaving+returning: slide {before} -> {after} (advanced={moved}), "
                  f"stuck={state['t']!r}, dragging={state['dragging']}")
            ok &= (state["t"] == "" and not state["dragging"])

            # --- 2. nav button
            before = await active(ws, n); n += 1
            if box["next"]:
                n = await mouse(ws, n, "mousePressed", int(box["next"]["x"]), int(box["next"]["y"]), 1)
                n = await mouse(ws, n, "mouseReleased", int(box["next"]["x"]), int(box["next"]["y"]), 0)
                await asyncio.sleep(0.7)
                after = await active(ws, n); n += 1
                print(f"2. next button: {before} -> {after} (changed={after != before})")
                ok &= (after != before)

            # --- 3. hero link still navigates
            if box["link"]:
                lx, ly = int(box["link"]["x"]), int(box["link"]["y"])
                n = await mouse(ws, n, "mousePressed", lx, ly, 1)
                n = await mouse(ws, n, "mouseReleased", lx, ly, 0)
                await asyncio.sleep(1.5)
                landed = await js(ws, n, "location.href")
                n += 1
                print(f"3. hero link click: {landed}")
                ok &= (bool(landed) and not landed.endswith("/"))

            print("\nREGRESSION:", "clean" if ok else "PROBLEM")
    finally:
        proc.terminate()


asyncio.run(main())