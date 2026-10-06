#!/usr/bin/env python3
"""Reproduce the hero drag-stuck bug by dispatching a real pointer sequence over CDP.

Synthetic events are the only way to drive this headlessly, but they must be
complete: pointerdown/pointermove/pointerup on the same pointerId, with the
mouse actually moved between them, because the symptom under test is what the
element's transform does over a gesture.
"""
import asyncio
import json
import subprocess
import sys
import urllib.request

import websockets

PORT = 9334
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
            n = 0
            await ws.send(json.dumps({"id": 1, "method": "Page.enable"}))
            await ws.send(json.dumps({"id": 2, "method": "Runtime.enable"}))
            for i in (1, 2):
                while True:
                    if json.loads(await ws.recv()).get("id") == i:
                        break
            n = 2
            await ws.send(json.dumps({"id": 3, "method": "Emulation.setDeviceMetricsOverride",
                                      "params": {"width": 1440, "height": 1000, "deviceScaleFactor": 1, "mobile": False}}))
            for i in (3,):
                while True:
                    if json.loads(await ws.recv()).get("id") == i:
                        break
            await ws.send(json.dumps({"id": 4, "method": "Page.navigate", "params": {"url": URL}}))
            while True:
                if json.loads(await ws.recv()).get("id") == 4:
                    break
            await asyncio.sleep(3)

            box = await js(ws, n, """
            (() => { const h = document.querySelector('.dk-hero'); if (!h) return null;
              const b = h.getBoundingClientRect();
              return {x: Math.round(b.x + b.width/2), y: Math.round(b.y + b.height/2)}; })()
            """)
            if not box:
                print("no .dk-hero on the page"); return
            n += 1

            # A real drag: down at centre, move left in steps, then release.
            print("viewport", await js(ws, n, "[innerWidth, innerHeight]"))
            n += 1
            x, y = box["x"], box["y"]
            await js(ws, n, f"""
            (() => {{
              const h = document.querySelector('.dk-hero');
              const mk = (type, cx, cy) => new PointerEvent(type, {{
                bubbles: true, cancelable: true, composed: true,
                pointerId: 1, pointerType: 'mouse', isPrimary: true,
                button: 0, buttons: type === 'pointerup' ? 0 : 1,
                clientX: cx, clientY: cy }});
              const s = h.querySelector('.dk-hero-slide.is-active');
              window.__probe = {{steps: []}};
              h.dispatchEvent(mk('pointerdown', {x}, {y}));
              window.__probe.steps.push({{at: 'down', transform: s ? s.style.transform : null}});
              return true;
            }})()
            """)
            n += 1

            for step in range(1, 6):
                nx = x - step * 40
                r = await js(ws, n, f"""
                (() => {{
                  const h = document.querySelector('.dk-hero');
                  const s = h.querySelector('.dk-hero-slide.is-active');
                  const before = s ? s.style.transform : null;
                  h.dispatchEvent(new PointerEvent('pointermove', {{
                    bubbles: true, cancelable: true, composed: true,
                    pointerId: 1, pointerType: 'mouse', isPrimary: true,
                    button: 0, buttons: 1, clientX: {nx}, clientY: {y} }}));
                  return {{transform: s ? s.style.transform : null,
                          moved: before !== (s ? s.style.transform : null)}};
                }})()
                """)
                n += 1
                print(f"  move to x={nx}: transform={r['transform']!r} moved={r['moved']}")

            print("  -- releasing --")
            r = await js(ws, n, f"""
            (() => {{
              const h = document.querySelector('.dk-hero');
              const s = h.querySelector('.dk-hero-slide.is-active');
              h.dispatchEvent(new PointerEvent('pointerup', {{
                bubbles: true, cancelable: true, composed: true,
                pointerId: 1, pointerType: 'mouse', isPrimary: true,
                button: 0, buttons: 0, clientX: {x - 200}, clientY: {y} }}));
              const after = s ? s.style.transform : null;
              return {{transformAfterUp: after,
                      heroDragging: h.classList.contains('is-dragging'),
                      slideDragging: s ? s.classList.contains('is-dragging') : null,
                      activeCount: document.querySelectorAll('.dk-hero-slide.is-active').length}};
            }})()
            """)
            n += 1
            print("  after pointerup:", json.dumps(r))

            # Does the slide track the pointer during a *hover* move with no button?
            r2 = await js(ws, n, """
            (() => { const s = document.querySelector('.dk-hero-slide.is-active');
              return {transform: s ? s.style.transform : null,
                      heroDragging: document.querySelector('.dk-hero').classList.contains('is-dragging')}; })()
            """)
            n += 1
            print("  idle after release:", json.dumps(r2))
    finally:
        proc.terminate()


asyncio.run(main())