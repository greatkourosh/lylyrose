#!/usr/bin/env python3
"""Assert the /perfume/ finder card's body stays inside its card at mobile width.

Regression guard for a real, shipped bug: the card is `flex-direction: row` with
`min-height: 100%` on the image, and at <=480px the row flips to column — but
`min-height: 100%` was never reset. As a column item that resolves against the
card's own height, so the 130px image grew to fill all but 2px of the card and
pushed the body out past `overflow: hidden`. The card looked blank.

Headless --window-size is ignored by Chrome (it lays out at 500px), so the
device metrics override goes over CDP *before* navigation. Exit 0 = fixed.

    python3 docker/measure-quick-finder.py [width] [url]
"""
import asyncio, json, os, subprocess, sys, urllib.request

import websockets

PORT = int(os.environ.get("CDP_PORT", "9333"))
WIDTH = int(sys.argv[1]) if len(sys.argv) > 1 else 390
HEIGHT = 844
SRC = sys.argv[2] if len(sys.argv) > 2 else "https://lylyrose.local/perfume/"

MEASURE = """
(async () => {
  const cards = [...document.querySelectorAll('.pf-quick-card')];
  if (!cards.length) return {error: 'no .pf-quick-card'};
  cards.forEach(c => c.scrollIntoView({block: 'center'}));
  await new Promise(r => setTimeout(r, 1200));
  const box = el => { if (!el) return null; const b = el.getBoundingClientRect();
    return {x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height)}; };
  const cs = el => el ? getComputedStyle(el) : null;
  return {
    viewport: {w: innerWidth, h: innerHeight},
    overflowX: document.documentElement.scrollWidth > innerWidth,
    cards: cards.map((c, i) => {
      const img = c.querySelector('.pf-quick-img');
      const imgEl = c.querySelector('.pf-quick-img img');
      const body = c.querySelector('.pf-quick-body');
      const b = c.querySelector('.pf-quick-body b');
      const small = c.querySelector('.pf-quick-body small');
      const cta = c.querySelector('.pf-quick-cta');
      const s = cs(c);
      return {
        i, cls: c.className, box: box(c),
        display: s.display, flexDir: s.flexDirection, minH: s.minHeight, bg: s.backgroundColor,
        imgSpan: box(img), imgSpanDisplay: cs(img) && cs(img).display,
        imgW: imgEl ? imgEl.naturalWidth : null,
        imgLoading: imgEl ? imgEl.loading : null,
        imgComplete: imgEl ? imgEl.complete : null,
        imgBox: box(imgEl),
        body: box(body), bodyOrder: cs(body) && cs(body).order,
        textB: b ? b.textContent.trim().slice(0, 18) : null,
        textBBox: box(b), textBColor: cs(b) && cs(b).color, textBSize: cs(b) && cs(b).fontSize,
        small: small ? small.textContent.trim().slice(0, 18) : null,
        smallBox: box(small),
        ctaText: cta ? cta.textContent.trim().slice(0, 22) : null,
        ctaBox: box(cta),
      };
    }),
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
                      {"width": WIDTH, "height": HEIGHT, "deviceScaleFactor": 1, "mobile": True})
            await cmd("Page.navigate", {"url": SRC})
            await asyncio.sleep(5)
            await cmd("Runtime.evaluate", {"expression": "window.scrollTo(0,0)"})
            res = await cmd("Runtime.evaluate",
                            {"expression": MEASURE, "returnByValue": True, "awaitPromise": True})
            val = res.get("result", {}).get("result", {}).get("value")
            if not QUIET:
                print(json.dumps(val, ensure_ascii=False, indent=2))
            return val
    finally:
        proc.terminate()


def check(val) -> int:
    """Exit 1 if the finder card's body escapes the card, or any card has no image text."""
    if not val or val.get("error"):
        print("FAIL: %s" % (val or {}).get("error", "no measurement"))
        return 1
    bad = []
    for c in val["cards"]:
        if c["body"] is None or c["box"] is None:
            bad.append("card %d: unmeasurable" % c["i"])
            continue
        card_bottom = c["box"]["y"] + c["box"]["h"]
        body_bottom = c["body"]["y"] + c["body"]["h"]
        inside = body_bottom <= card_bottom + 1
        print("%-28s dir=%-6s card=%4d img=%4d body=%4d bottom=%d/%d %s"
              % (c["cls"], c["flexDir"], c["box"]["h"], c["imgSpan"]["h"], c["body"]["h"],
                 body_bottom, card_bottom, "ok" if inside else "CLIPPED"))
        if not inside:
            bad.append("card %d (%s): body bottom %d past card bottom %d — clipped by overflow:hidden"
                       % (c["i"], c["cls"], body_bottom, card_bottom))
        if not (c["textB"] or "").strip():
            bad.append("card %d (%s): empty headline" % (c["i"], c["cls"]))
    if val.get("overflowX"):
        bad.append("horizontal overflow at %dpx" % val["viewport"]["w"])
    if bad:
        for b in bad:
            print("FAIL:", b)
        return 1
    print("PASS: all %d quick card(s) intact at %dpx" % (len(val["cards"]), val["viewport"]["w"]))
    return 0


QUIET = os.environ.get("QUIET") == "1"
sys.exit(check(asyncio.run(main())))
