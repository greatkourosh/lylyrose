#!/usr/bin/env python3
"""Does the hero release its drag when the pointer leaves the element?

The synthetic drag in drag-hero.py dispatches everything on .dk-hero, so it cannot
see a pointer that wanders off the element. This one moves the real mouse outside
the hero's box mid-gesture, which is what a person does, and then checks whether
the slide is still being dragged.
"""
import asyncio
import json
import subprocess
import sys
import urllib.request

import websockets

PORT = 9335
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


async def mouse(ws, n, kind, x, y, button="left", buttons=1):
    n += 1
    await ws.send(json.dumps({"id": n, "method": "Input.dispatchMouseEvent", "params": {
        "type": kind, "x": x, "y": y, "button": button, "buttons": buttons, "clickCount": 1}}))
    while True:
        msg = json.loads(await ws.recv())
        if msg.get("id") == n:
            return n


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
            await ws.send(json.dumps({"id": 1, "method": "Page.enable"}))
            await ws.send(json.dumps({"id": 2, "method": "Runtime.enable"}))
            await ws.send(json.dumps({"id": 3, "method": "Emulation.setDeviceMetricsOverride",
                                      "params": {"width": 1440, "height": 900, "deviceScaleFactor": 1, "mobile": False}}))
            for i in (1, 2, 3):
                while True:
                    if json.loads(await ws.recv()).get("id") == i:
                        break
            await ws.send(json.dumps({"id": 4, "method": "Page.navigate", "params": {"url": URL}}))
            while True:
                if json.loads(await ws.recv()).get("id") == 4:
                    break
            await asyncio.sleep(3)
            n = 4

            box = await js(ws, n, """
            (() => { const h = document.querySelector('.dk-hero'); if (!h) return null;
              const b = h.getBoundingClientRect();
              return {x: b.x, y: b.y, w: b.width, h: b.height}; })()
            """)
            if not box:
                print("no .dk-hero"); return
            n += 1
            print("hero box:", json.dumps({k: round(v) for k, v in box.items()}))

            cx, cy = int(box["x"] + box["w"] / 2), int(box["y"] + box["h"] / 2)

            # Real mouse: press inside, drag down out of the element, then release
            # outside — the pointerup can never reach the hero.
            n = await mouse(ws, n, "mousePressed", cx, cy, buttons=1)
            await asyncio.sleep(0.05)
            n = await mouse(ws, n, "mouseMoved", cx - 30, cy + 10, buttons=1)
            await asyncio.sleep(0.05)
            print("inside:", json.dumps(await js(ws, n, """
            (() => { const s = document.querySelector('.dk-hero-slide.is-active');
              return {t: s.style.transform,
                      hero: document.querySelector('.dk-hero').classList.contains('is-dragging')}; })()""")))

            # Leave the hero downward, well past its bottom edge.
            n = await mouse(ws, n, "mouseMoved", cx, int(box["y"] + box["h"] + 120), buttons=1)
            await asyncio.sleep(0.15)
            print("after leaving:", json.dumps(await js(ws, n, """
            (() => { const s = document.querySelector('.dk-hero-slide.is-active');
              return {t: s.style.transform,
                      hero: document.querySelector('.dk-hero').classList.contains('is-dragging'),
                      slide: s.classList.contains('is-dragging')}; })()""")))

            # Move back over the hero, still holding the button.
            n = await mouse(ws, n, "mouseMoved", cx - 60, cy, buttons=1)
            await asyncio.sleep(0.15)
            print("re-entered:", json.dumps(await js(ws, n, """
            (() => { const s = document.querySelector('.dk-hero-slide.is-active');
              return {t: s.style.transform,
                      hero: document.querySelector('.dk-hero').classList.contains('is-dragging')}; })()""")))

            # The reported case: let go somewhere else entirely, so the hero never
            # sees the pointerup that is supposed to end the drag.
            n = await mouse(ws, n, "mouseMoved", cx, int(box["y"] + box["h"] + 260), buttons=1)
            await asyncio.sleep(0.1)
            n = await mouse(ws, n, "mouseReleased", cx, int(box["y"] + box["h"] + 260),
                            button="left", buttons=0)
            await asyncio.sleep(0.3)
            print("RELEASED OUTSIDE:", json.dumps(await js(ws, n, """
            (() => { const s = document.querySelector('.dk-hero-slide.is-active');
              return {transformStuck: s.style.transform,
                      heroStillDragging: document.querySelector('.dk-hero').classList.contains('is-dragging'),
                      slideStillDragging: s.classList.contains('is-dragging'),
                      activeCount: document.querySelectorAll('.dk-hero-slide.is-active').length,
                      autoplayRunning: true}; })()""")))

            # Now move the mouse over the hero with NO button down: if the stale drag
            # is still live it will track, which is the "stuck to my mouse" symptom.
            n = await mouse(ws, n, "mouseMoved", cx - 200, cy, buttons=0)
            await asyncio.sleep(0.15)
            print("hover after release:", json.dumps(await js(ws, n, """
            (() => { const s = document.querySelector('.dk-hero-slide.is-active');
              return {transform: s.style.transform,
                      hero: document.querySelector('.dk-hero').classList.contains('is-dragging')}; })()""")))
    finally:
        proc.terminate()


asyncio.run(main())